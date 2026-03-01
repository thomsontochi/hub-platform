<?php

namespace App\Messaging\RabbitMq;

use App\Domain\Employees\Contracts\EmployeeEventHandler;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
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

        $channel->exchange_declare($exchange, 'topic', false, true, false);
        $channel->queue_declare($queue, false, true, false, false);

        foreach ($routingKeys as $routingKey) {
            $channel->queue_bind($queue, $exchange, $routingKey);
        }

        $channel->basic_qos(null, $prefetchCount, null);

        $callback = function (AMQPMessage $message) use ($channel): void {
            $routingKey = $message->getRoutingKey();

            try {
                $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);

                $this->handler->handle($routingKey, $payload);

                $channel->basic_ack($message->getDeliveryTag());
            } catch (Throwable $exception) {
                Log::error('Failed to handle RabbitMQ message', [
                    'routing_key' => $routingKey,
                    'payload' => $message->getBody(),
                    'exception' => $exception->getMessage(),
                ]);

                $channel->basic_nack($message->getDeliveryTag(), false, false);
            }
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
}
