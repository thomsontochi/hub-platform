<?php

namespace App\Domain\UI\DTOs;

class StepDefinition
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $icon,
        public readonly string $path,
        public readonly int $order
    ) {
    }
}
