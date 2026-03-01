<?php

namespace App\Domain\Employees\Contracts;

interface EmployeeEventHandler
{
    public function handle(string $routingKey, array $payload): void;
}
