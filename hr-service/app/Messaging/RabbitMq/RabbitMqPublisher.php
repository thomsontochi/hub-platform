<?php

namespace App\Messaging\RabbitMq;

use App\Messaging\Contracts\EventPublisher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class RabbitMqPublisher implements EventPublisher
{
    public function __construct(
        protected ?array $config = null
    ) {
        $this->config = $config ?? config('rabbitmq');
    }

    public function publish(string $routingKey, array $payload): void
    {
        $config = $this->config;

        try {
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
                (float) Arr::get($config, 'read_write_timeout', 3.0)
            );

            $channel = $connection->channel();
            $exchange = Arr::get($config, 'exchange', 'employee.events');

            $channel->exchange_declare($exchange, 'topic', false, true, false);

            $message = new AMQPMessage(
                json_encode($payload, JSON_THROW_ON_ERROR),
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                ]
            );

            $channel->basic_publish($message, $exchange, $routingKey);

            $channel->close();
            $connection->close();

            Log::debug('Published RabbitMQ event', [
                'routing_key' => $routingKey,
                'exchange' => $exchange,
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to publish RabbitMQ event', [
                'routing_key' => $routingKey,
                'payload' => $payload,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
