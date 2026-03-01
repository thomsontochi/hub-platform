<?php

namespace App\Domain\Employees\Handlers;

use App\Domain\Employees\Contracts\EmployeeEventHandler;
use Illuminate\Support\Facades\Log;

class LoggingEmployeeEventHandler implements EmployeeEventHandler
{
    public function handle(string $routingKey, array $payload): void
    {
        Log::info('Received employee event', [
            'routing_key' => $routingKey,
            'payload' => $payload,
        ]);
    }
}
