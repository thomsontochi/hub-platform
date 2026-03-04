<?php

declare(strict_types=1);

use App\Domain\Employees\Contracts\EmployeeEventHandler;
use App\Messaging\RabbitMq\RabbitMqConsumer;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

beforeEach(function (): void {
    // placeholder for per-test setup if needed
});

afterEach(function (): void {
    Mockery::close();
});

expect()->extend('toHaveBeenAcked', function (MockInterface $channel, string $tag): void {
    $channel->shouldHaveReceived('basic_ack')->with($tag)->once();
});

class TestableRabbitMqConsumer extends RabbitMqConsumer
{
    public function exposeProcessMessage(
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
    }
}

it('acks messages when handler succeeds', function (): void {
    $handler = Mockery::mock(EmployeeEventHandler::class);
    $handler->shouldReceive('handle')
        ->once()
        ->with('employee.usa.created', ['foo' => 'bar']);

    $consumer = new TestableRabbitMqConsumer($handler, baseRabbitConfig());

    $channel = Mockery::mock(AMQPChannel::class);
    $channel->shouldReceive('basic_ack')->once()->with('tag-1');

    $message = Mockery::mock(AMQPMessage::class);
    $message->shouldReceive('getRoutingKey')->andReturn('employee.usa.created');
    $message->shouldReceive('getBody')->andReturn(json_encode(['foo' => 'bar']));
    $message->shouldReceive('getDeliveryTag')->andReturn('tag-1');
    $message->shouldReceive('has')->with('application_headers')->andReturn(false);

    $consumer->exposeProcessMessage(
        $channel,
        $message,
        'hub.employee.events',
        'employee.events.retry',
        'hub.employee.events.retry',
        'hub.employee.events.retry',
        'employee.events.dlq',
        'hub.employee.events.dlq',
        'hub.employee.events.dlq',
        3
    );
});

function baseRabbitConfig(): array
{
    return [
        'exchange' => 'employee.events',
        'queue' => 'hub.employee.events',
        'retry' => [
            'exchange' => 'employee.events.retry',
            'queue' => 'hub.employee.events.retry',
            'routing_key' => 'hub.employee.events.retry',
            'ttl' => 1000,
            'max_attempts' => 3,
        ],
        'dead_letter' => [
            'exchange' => 'employee.events.dlq',
            'queue' => 'hub.employee.events.dlq',
            'routing_key' => 'hub.employee.events.dlq',
        ],
    ];
}

it('requeues message when handler fails below retry threshold', function (): void {
    $handler = Mockery::mock(EmployeeEventHandler::class);
    $handler->shouldReceive('handle')->andThrow(new RuntimeException('failure'));

    $consumer = new TestableRabbitMqConsumer($handler, baseRabbitConfig());

    $channel = Mockery::mock(AMQPChannel::class);
    $channel->shouldReceive('basic_reject')->once()->with('tag-2', false);

    $message = Mockery::mock(AMQPMessage::class);
    $message->shouldReceive('getRoutingKey')->andReturn('employee.usa.created');
    $message->shouldReceive('getBody')->andReturn(json_encode(['foo' => 'bar']));
    $message->shouldReceive('getDeliveryTag')->andReturn('tag-2');
    $message->shouldReceive('has')->with('application_headers')->andReturn(false);

    $consumer->exposeProcessMessage(
        $channel,
        $message,
        'hub.employee.events',
        'employee.events.retry',
        'hub.employee.events.retry',
        'hub.employee.events.retry',
        'employee.events.dlq',
        'hub.employee.events.dlq',
        'hub.employee.events.dlq',
        3
    );
});

it('routes to dead-letter queue after max attempts', function (): void {
    $handler = Mockery::mock(EmployeeEventHandler::class);
    $handler->shouldReceive('handle')->andThrow(new RuntimeException('failure'));

    $consumer = new TestableRabbitMqConsumer($handler, baseRabbitConfig());

    $channel = Mockery::mock(AMQPChannel::class);
    $channel->shouldNotReceive('basic_reject');
    $channel->shouldReceive('basic_publish')->once()->with(
        Mockery::type(AMQPMessage::class),
        'employee.events.dlq',
        'hub.employee.events.dlq'
    );
    $channel->shouldReceive('basic_ack')->once()->with('tag-3');

    $message = Mockery::mock(AMQPMessage::class);
    $message->shouldReceive('getRoutingKey')->andReturn('employee.usa.created');
    $message->shouldReceive('getBody')->andReturn(json_encode(['foo' => 'bar']));
    $message->shouldReceive('getDeliveryTag')->andReturn('tag-3');
    $message->shouldReceive('has')->with('application_headers')->andReturn(true);
    $message->shouldReceive('has')->with('content_type')->andReturn(true);
    $message->shouldReceive('get')->with('content_type')->andReturn('application/json');
    $message->shouldReceive('get')->with('application_headers')->andReturn(new AMQPTable([
        'x-death' => [
            ['queue' => 'hub.employee.events', 'count' => 2],
        ],
        'x-original-routing-key' => 'employee.usa.created',
    ]));

    $consumer->exposeProcessMessage(
        $channel,
        $message,
        'hub.employee.events',
        'employee.events.retry',
        'hub.employee.events.retry',
        'hub.employee.events.retry',
        'employee.events.dlq',
        'hub.employee.events.dlq',
        'hub.employee.events.dlq',
        3
    );
});
