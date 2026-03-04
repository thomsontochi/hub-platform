<?php

namespace App\Domain\Checklists\DTOs;

class ChecklistSummary
{
    public function __construct(
        public readonly string $country,
        public readonly int $totalEmployees,
        public readonly int $completeEmployees,
        public readonly int $incompleteEmployees,
        public readonly float $averageCompletionRate,
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            country: $payload['country'],
            totalEmployees: (int) ($payload['total_employees'] ?? 0),
            completeEmployees: (int) ($payload['complete_employees'] ?? 0),
            incompleteEmployees: (int) ($payload['incomplete_employees'] ?? 0),
            averageCompletionRate: (float) ($payload['average_completion_rate'] ?? 0.0),
        );
    }

    public function toArray(): array
    {
        return [
            'country' => $this->country,
            'total_employees' => $this->totalEmployees,
            'complete_employees' => $this->completeEmployees,
            'incomplete_employees' => $this->incompleteEmployees,
            'average_completion_rate' => $this->averageCompletionRate,
        ];
    }
}
