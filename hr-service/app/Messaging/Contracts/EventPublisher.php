<?php

namespace App\Messaging\Contracts;

interface EventPublisher
{
    public function publish(string $routingKey, array $payload): void;
}
