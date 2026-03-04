<?php

namespace App\Messaging\RabbitMq;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class RabbitMqDeadLetterReplayer
{
    /**
     * @var callable
     */
    protected $connectionFactory;

    public function __construct(
        protected ?array $config = null,
        ?callable $connectionFactory = null,
    ) {
        $this->config = $config ?? config('rabbitmq');
        $this->connectionFactory = $connectionFactory ?? function (array $config): AMQPStreamConnection {
            return new AMQPStreamConnection(
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
        };
    }

    public function replay(int $limit = 10): int
    {
        if ($limit <= 0) {
            return 0;
        }

        $connection = $this->createConnection();
        $channel = $connection->channel();

        [$exchange, $deadLetterExchange, $deadLetterQueue] = $this->declareTopology($channel);

        $processed = 0;

        try {
            while ($processed < $limit) {
                $message = $channel->basic_get($deadLetterQueue);

                if (! $message) {
                    break;
                }

                $routingKey = $this->extractOriginalRoutingKey($message);
                $headers = $this->extractHeaders($message);

                try {
                    $channel->basic_publish(
                        new AMQPMessage(
                            $message->getBody(),
                            [
                                'content_type' => $message->has('content_type') ? $message->get('content_type') : 'application/json',
                                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                                'application_headers' => new AMQPTable($headers),
                            ]
                        ),
                        $exchange,
                        $routingKey
                    );

                    $channel->basic_ack($message->getDeliveryTag());

                    $processed++;

                    Log::info('Replayed message from dead-letter queue', [
                        'routing_key' => $routingKey,
                        'dead_letter_queue' => $deadLetterQueue,
                    ]);
                } catch (Throwable $exception) {
                    $channel->basic_nack($message->getDeliveryTag(), false, true);

                    Log::error('Failed to replay message from dead-letter queue', [
                        'dead_letter_queue' => $deadLetterQueue,
                        'routing_key' => $routingKey,
                        'exception' => $exception->getMessage(),
                    ]);

                    break;
                }
            }
        } finally {
            $channel->close();
            $connection->close();
        }

        return $processed;
    }

    protected function createConnection(): AMQPStreamConnection
    {
        $factory = $this->connectionFactory;

        return $factory($this->config);
    }

    protected function declareTopology(AMQPChannel $channel): array
    {
        $exchange = Arr::get($this->config, 'exchange', 'employee.events');

        $deadLetter = Arr::get($this->config, 'dead_letter', []);
        $deadLetterExchange = Arr::get($deadLetter, 'exchange', 'employee.events.dlq');
        $deadLetterQueue = Arr::get($deadLetter, 'queue', 'hub.employee.events.dlq');
        $deadLetterRoutingKey = Arr::get($deadLetter, 'routing_key', 'hub.employee.events.dlq');

        $channel->exchange_declare($exchange, 'topic', false, true, false);
        $channel->exchange_declare($deadLetterExchange, 'direct', false, true, false);

        $channel->queue_declare(
            $deadLetterQueue,
            false,
            true,
            false,
            false
        );
        $channel->queue_bind($deadLetterQueue, $deadLetterExchange, $deadLetterRoutingKey);

        return [$exchange, $deadLetterExchange, $deadLetterQueue];
    }

    protected function extractOriginalRoutingKey(AMQPMessage $message): string
    {
        $headers = $this->extractHeaders($message);

        return Arr::get($headers, 'x-original-routing-key', $message->getRoutingKey());
    }

    protected function extractHeaders(AMQPMessage $message): array
    {
        if (! $message->has('application_headers')) {
            return [];
        }

        $headers = $message->get('application_headers')->getNativeData();

        unset($headers['x-death'], $headers['x-first-death-exchange'], $headers['x-first-death-queue'], $headers['x-first-death-reason']);

        return $headers;
    }
}
