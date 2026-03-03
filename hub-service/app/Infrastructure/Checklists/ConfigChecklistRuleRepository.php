<?php

namespace App\Infrastructure\Checklists;

use App\Domain\Checklists\Contracts\ChecklistRuleRepository;
use App\Domain\Checklists\DTOs\ChecklistRuleSet;
use App\Domain\Checklists\DTOs\FieldRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ConfigChecklistRuleRepository implements ChecklistRuleRepository
{
    public function forCountry(string $country): ?ChecklistRuleSet
    {
        $countryKey = Str::lower($country);
        $config = config('checklists.' . $countryKey);

        if (! $config || ! isset($config['fields'])) {
            return null;
        }

        $fields = array_map(
            function (array $fieldConfig): FieldRule {
                return new FieldRule(
                    field: $fieldConfig['field'],
                    label: $fieldConfig['label'] ?? Str::title(str_replace(['_', '.'], ' ', $fieldConfig['field'])),
                    rules: $fieldConfig['rules'] ?? [],
                    messages: $fieldConfig['messages'] ?? [],
                );
            },
            array_map(
                fn ($key, $value) => array_merge(['field' => $key], $value),
                array_keys($config['fields']),
                $config['fields']
            )
        );

        return new ChecklistRuleSet(
            country: $countryKey,
            fields: $fields,
        );
    }
}
