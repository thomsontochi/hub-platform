<?php

namespace App\Domain\UI\DTOs;

class WidgetDefinition
{
    /**
     * @param  string[]  $channels
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $label,
        public readonly string $icon,
        public readonly string $dataSource,
        public readonly array $channels
    ) {
    }
}
