<?php

namespace App\Domain\Checklists\DTOs;

class FieldStatus
{
    public function __construct(
        public readonly string $field,
        public readonly string $label,
        public readonly bool $complete,
        public readonly ?string $message = null,
    ) {}

    public static function fromArray(array $payload): self
    {
        return new self(
            field: $payload['field'],
            label: $payload['label'],
            complete: (bool) ($payload['complete'] ?? false),
            message: $payload['message'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'label' => $this->label,
            'complete' => $this->complete,
            'message' => $this->message,
        ];
    }
}
