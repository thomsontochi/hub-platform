<?php

namespace App\Domain\Checklists\DTOs;

/**
 * @phpstan-type FieldRuleConfig array{
 *     field: string,
 *     label: string,
 *     rules: string[],
 *     messages?: array<string, string>
 * }
 */
class ChecklistRuleSet
{
    /**
     * @param  FieldRule[]  $fields
     */
    public function __construct(
        public readonly string $country,
        public readonly array $fields,
    ) {
    }
}
