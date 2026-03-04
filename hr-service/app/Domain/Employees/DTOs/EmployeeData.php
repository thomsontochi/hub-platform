<?php

namespace App\Domain\Employees\DTOs;

class EmployeeData
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $lastName,
        public readonly float $salary,
        public readonly string $country,
        public readonly array $attributes = [],
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            id: $payload['id'] ?? null,
            name: $payload['name'],
            lastName: $payload['last_name'],
            salary: (float) $payload['salary'],
            country: $payload['country'],
            attributes: $payload['attributes'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_name' => $this->lastName,
            'salary' => $this->salary,
            'country' => $this->country,
            'attributes' => $this->attributes,
        ];
    }

    public function toPersistenceArray(): array
    {
        return [
            'name' => $this->name,
            'last_name' => $this->lastName,
            'salary' => $this->salary,
            'country' => $this->country,
            'attributes' => $this->attributes,
        ];
    }
}
