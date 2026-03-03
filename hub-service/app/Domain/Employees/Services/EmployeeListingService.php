<?php

namespace App\Domain\Employees\Services;

use App\Domain\Employees\Contracts\EmployeeCache;
use App\Domain\Employees\DTOs\EmployeeSnapshot;
use App\Domain\UI\DTOs\ColumnDefinition;
use App\Domain\UI\Repositories\UiConfigurationRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class EmployeeListingService
{
    public function __construct(
        protected EmployeeCache $employeeCache,
        protected UiConfigurationRepository $uiConfig,
    ) {
    }

    /**
     * @return array{columns: ColumnDefinition[], employees: LengthAwarePaginator}
     */
    public function list(string $country, int $perPage = 15, int $page = 1): array
    {
        $snapshots = collect($this->employeeCache->allForCountry($country));

        $columns = $this->uiConfig->columnsForCountry($country);

        $masked = $snapshots->map(fn (EmployeeSnapshot $snapshot) => $this->maskSensitiveData($snapshot, $columns));

        $paginator = $this->paginate($masked, $perPage, $page);

        return [
            'columns' => $columns,
            'employees' => $paginator,
        ];
    }

    protected function paginate(Collection $items, int $perPage, int $page): LengthAwarePaginator
    {
        $currentPage = $page ?: Paginator::resolveCurrentPage('page');
        $results = $items->forPage($currentPage, $perPage)->values();

        return new LengthAwarePaginator(
            items: $results,
            total: $items->count(),
            perPage: $perPage,
            currentPage: $currentPage,
            options: [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * @param ColumnDefinition[] $columns
     */
    protected function maskSensitiveData(EmployeeSnapshot $snapshot, array $columns): EmployeeSnapshot
    {
        $attributes = $snapshot->attributes;

        foreach ($columns as $column) {
            if ($column->mask && $column->key === 'attributes.ssn') {
                $attributes['ssn'] = $this->maskSsn($attributes['ssn'] ?? null);
            }
        }

        return new EmployeeSnapshot(
            id: $snapshot->id,
            name: $snapshot->name,
            lastName: $snapshot->lastName,
            salary: $snapshot->salary,
            country: $snapshot->country,
            attributes: $attributes,
            meta: $snapshot->meta,
        );
    }

    protected function maskSsn(?string $value): ?string
    {
        if (! $value || strlen($value) < 4) {
            return $value;
        }

        return sprintf('***-**-%s', substr($value, -4));
    }
}
