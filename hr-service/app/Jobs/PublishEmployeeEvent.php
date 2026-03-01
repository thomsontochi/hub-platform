<?php

namespace App\Jobs;

use App\Messaging\Contracts\EventPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishEmployeeEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $backoff = 5;

    public function __construct(
        protected string $routingKey,
        protected array $payload
    ) {
        $this->onQueue('events');
    }

    public function handle(EventPublisher $publisher): void
    {
        $publisher->publish($this->routingKey, $this->payload);
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('PublishEmployeeEvent failed after retries', [
            'routing_key' => $this->routingKey,
            'payload' => $this->payload,
            'exception' => $exception->getMessage(),
        ]);
    }

    public function routingKey(): string
    {
        return $this->routingKey;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}
