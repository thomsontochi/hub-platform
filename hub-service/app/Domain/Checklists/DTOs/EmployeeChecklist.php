<?php

namespace App\Domain\Checklists\DTOs;

use App\Domain\Employees\DTOs\EmployeeSnapshot;
use Illuminate\Support\Carbon;

class EmployeeChecklist
{
    /**
     * @param  FieldStatus[]  $fields
     */
    public function __construct(
        public readonly EmployeeSnapshot $employee,
        public readonly array $fields,
        public readonly float $completionRate,
        public readonly ?Carbon $evaluatedAt = null,
        public readonly ?string $status = null,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            employee: EmployeeSnapshot::fromArray($payload['employee']),
            fields: array_map(static fn (array $field) => FieldStatus::fromArray($field), $payload['fields'] ?? []),
            completionRate: (float) ($payload['completion_rate'] ?? 0.0),
            evaluatedAt: isset($payload['evaluated_at']) ? Carbon::parse($payload['evaluated_at']) : null,
            status: $payload['status'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'employee' => $this->employee->toArray(),
            'fields' => array_map(static fn (FieldStatus $field) => $field->toArray(), $this->fields),
            'completion_rate' => $this->completionRate,
            'status' => $this->status,
            'evaluated_at' => $this->evaluatedAt?->toAtomString(),
        ];
    }
}
