<?php

namespace App\Messaging\RabbitMq;

use App\Domain\Employees\Contracts\EmployeeEventHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class RabbitMqConsumer
{
    public function __construct(
        protected EmployeeEventHandler $handler,
        protected ?array $config = null,
    ) {
        $this->config = $config ?? config('rabbitmq');
    }

    public function consume(): void
    {
        $config = $this->config;

        $connection = new AMQPStreamConnection(
            Arr::get($config, 'host', 'rabbitmq'),
            (int) Arr::get($config, 'port', 5672),
            Arr::get($config, 'user', 'guest'),
            Arr::get($config, 'password', 'guest'),
            Arr::get($config, 'vhost', '/'),
            false,
            'AMQPLAIN',
            null,
            'en_US',
            (float) Arr::get($config, 'connection_timeout', 3.0),
            (float) Arr::get($config, 'read_write_timeout', 3.0),
        );

        $channel = $connection->channel();

        $exchange = Arr::get($config, 'exchange', 'employee.events');
        $queue = Arr::get($config, 'queue', 'hub.employee.events');
        $routingKeys = Arr::get($config, 'routing_keys', ['employee.*.*']);
        $prefetchCount = (int) Arr::get($config, 'prefetch_count', 10);

        $retryConfig = Arr::get($config, 'retry', []);
        $retryExchange = Arr::get($retryConfig, 'exchange', 'employee.events.retry');
        $retryRoutingKey = Arr::get($retryConfig, 'routing_key', 'hub.employee.events.retry');
        $retryQueue = Arr::get($retryConfig, 'queue', 'hub.employee.events.retry');
        $retryTtl = (int) Arr::get($retryConfig, 'ttl', 5000);
        $maxAttempts = (int) Arr::get($retryConfig, 'max_attempts', 3);

        $deadLetterConfig = Arr::get($config, 'dead_letter', []);
        $deadLetterExchange = Arr::get($deadLetterConfig, 'exchange', 'employee.events.dlq');
        $deadLetterRoutingKey = Arr::get($deadLetterConfig, 'routing_key', 'hub.employee.events.dlq');
        $deadLetterQueue = Arr::get($deadLetterConfig, 'queue', $deadLetterRoutingKey);

        $channel->exchange_declare($exchange, 'topic', false, true, false);
        $channel->exchange_declare($retryExchange, 'direct', false, true, false);
        $channel->exchange_declare($deadLetterExchange, 'direct', false, true, false);

        $channel->queue_declare(
            $queue,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable([
                'x-dead-letter-exchange' => $retryExchange,
                'x-dead-letter-routing-key' => $retryRoutingKey,
            ])
        );

        foreach ($routingKeys as $routingKey) {
            $channel->queue_bind($queue, $exchange, $routingKey);
        }

        $channel->queue_bind($queue, $exchange, $retryRoutingKey);

        $channel->queue_declare(
            $retryQueue,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable([
                'x-message-ttl' => $retryTtl,
                'x-dead-letter-exchange' => $exchange,
            ])
        );
        $channel->queue_bind($retryQueue, $retryExchange, $retryRoutingKey);

        $channel->queue_declare(
            $deadLetterQueue,
            false,
            true,
            false,
            false
        );
        $channel->queue_bind($deadLetterQueue, $deadLetterExchange, $deadLetterRoutingKey);

        $channel->basic_qos(0, $prefetchCount, null);

        $callback = function (AMQPMessage $message) use (
            $channel,
            $queue,
            $retryExchange,
            $retryRoutingKey,
            $retryQueue,
            $deadLetterExchange,
            $deadLetterRoutingKey,
            $deadLetterQueue,
            $maxAttempts
        ): void {
            $this->processMessage(
                $channel,
                $message,
                $queue,
                $retryExchange,
                $retryRoutingKey,
                $retryQueue,
                $deadLetterExchange,
                $deadLetterRoutingKey,
                $deadLetterQueue,
                $maxAttempts
            );
        };

        $channel->basic_consume($queue, '', false, false, false, false, $callback);

        Log::info('RabbitMQ consumer started', [
            'exchange' => $exchange,
            'queue' => $queue,
            'routing_keys' => $routingKeys,
        ]);

        while ($channel->is_open()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    protected function processMessage(
        AMQPChannel $channel,
        AMQPMessage $message,
        string $queue,
        string $retryExchange,
        string $retryRoutingKey,
        string $retryQueue,
        string $deadLetterExchange,
        string $deadLetterRoutingKey,
        string $deadLetterQueue,
        int $maxAttempts
    ): void {
        $routingKey = $message->getRoutingKey();

        try {
            $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);

            $this->handler->handle($routingKey, $payload);

            $channel->basic_ack($message->getDeliveryTag());
        } catch (Throwable $exception) {
            $attempts = $this->calculateAttempts($message, $queue);

            Log::error('Failed to handle RabbitMQ message', [
                'routing_key' => $routingKey,
                'payload' => $message->getBody(),
                'exception' => $exception->getMessage(),
                'attempts' => $attempts + 1,
                'max_attempts' => $maxAttempts,
            ]);

            if (($attempts + 1) >= $maxAttempts) {
                $this->sendToDeadLetter(
                    $channel,
                    $message,
                    $deadLetterExchange,
                    $deadLetterRoutingKey,
                    $deadLetterQueue,
                    $exception
                );

                $channel->basic_ack($message->getDeliveryTag());

                return;
            }

            Log::info('Scheduling RabbitMQ message retry', [
                'routing_key' => $routingKey,
                'attempts' => $attempts + 1,
                'max_attempts' => $maxAttempts,
                'retry_exchange' => $retryExchange,
                'retry_routing_key' => $retryRoutingKey,
                'retry_queue' => $retryQueue,
            ]);

            $channel->basic_reject($message->getDeliveryTag(), false);
        }
    }

    protected function calculateAttempts(AMQPMessage $message, string $queue): int
    {
        $headers = $message->has('application_headers')
            ? $message->get('application_headers')->getNativeData()
            : [];

        if (! isset($headers['x-death']) || ! is_array($headers['x-death'])) {
            return 0;
        }

        foreach ($headers['x-death'] as $death) {
            if (($death['queue'] ?? null) === $queue) {
                return (int) ($death['count'] ?? 0);
            }
        }

        return 0;
    }

    protected function sendToDeadLetter(
        AMQPChannel $channel,
        AMQPMessage $originalMessage,
        string $deadLetterExchange,
        string $deadLetterRoutingKey,
        string $deadLetterQueue,
        Throwable $exception
    ): void {
        $headers = $originalMessage->has('application_headers')
            ? $originalMessage->get('application_headers')->getNativeData()
            : [];

        $headers['x-original-routing-key'] = $originalMessage->getRoutingKey();
        $headers['x-failure-message'] = $exception->getMessage();

        $message = new AMQPMessage(
            $originalMessage->getBody(),
            [
                'content_type' => $originalMessage->get('content_type') ?? 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'application_headers' => new AMQPTable($headers),
            ]
        );

        $channel->basic_publish($message, $deadLetterExchange, $deadLetterRoutingKey);

        Log::warning('Message routed to dead-letter queue after max retries', [
            'routing_key' => $originalMessage->getRoutingKey(),
            'dead_letter_exchange' => $deadLetterExchange,
            'dead_letter_routing_key' => $deadLetterRoutingKey,
            'dead_letter_queue' => $deadLetterQueue,
        ]);
    }
}
