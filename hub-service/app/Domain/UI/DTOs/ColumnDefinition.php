<?php

namespace App\Domain\UI\DTOs;

class ColumnDefinition
{
    public function __construct(
        public readonly string $field,
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly bool $mask = false
    ) {
    }
}
