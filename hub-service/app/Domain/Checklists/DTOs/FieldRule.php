<?php

namespace App\Domain\Checklists\DTOs;

class FieldRule
{
    public function __construct(
        public readonly string $field,
        public readonly string $label,
        public readonly array $rules,
        public readonly array $messages = [],
    ) {}
}
