<?php

namespace App\Domain\UI\Repositories;

use App\Domain\UI\DTOs\ColumnDefinition;
use App\Domain\UI\DTOs\StepDefinition;
use App\Domain\UI\DTOs\WidgetDefinition;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class UiConfigurationRepository
{
    /**
     * @return StepDefinition[]
     */
    public function stepsForCountry(string $country): array
    {
        $config = $this->countryConfig($country);

        $steps = collect($config['steps'] ?? [])
            ->map(fn (array $step) => new StepDefinition(
                id: $step['id'],
                label: $step['label'],
                icon: $step['icon'],
                path: $step['path'],
                order: (int) ($step['order'] ?? 0),
            ))
            ->sortBy('order')
            ->values();

        return $steps->all();
    }

    /**
     * @return ColumnDefinition[]
     */
    public function columnsForCountry(string $country): array
    {
        $config = $this->countryConfig($country);

        return collect($config['columns'] ?? [])
            ->map(fn (array $column) => new ColumnDefinition(
                field: $column['field'],
                key: $column['key'],
                label: $column['label'],
                type: $column['type'] ?? 'text',
                mask: (bool) ($column['mask'] ?? false),
            ))
            ->all();
    }

    /**
     * @return WidgetDefinition[]
     */
    public function widgetsForStep(string $country, string $step): array
    {
        $config = $this->countryConfig($country);
        $widgets = Arr::get($config, 'widgets.' . Str::lower($step), []);

        return collect($widgets)
            ->map(fn (array $widget) => new WidgetDefinition(
                id: $widget['id'],
                type: $widget['type'],
                label: $widget['label'],
                icon: $widget['icon'],
                dataSource: $widget['data_source'],
                channels: $widget['channels'] ?? [],
            ))
            ->all();
    }

    protected function countryConfig(string $country): array
    {
        $key = Str::lower($country);

        return config("ui.countries.$key", []);
    }
}
