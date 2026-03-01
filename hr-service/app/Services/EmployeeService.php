<?php

namespace App\Services;

use App\Domain\Employees\Contracts\EmployeeRepository;
use App\Domain\Employees\DTOs\EmployeeData;
use App\Http\Resources\EmployeeResource;
use App\Jobs\PublishEmployeeEvent;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function __construct(
        protected EmployeeRepository $repository
    ) {
    }

    public function list(string $country, int $perPage = 15): LengthAwarePaginator
    {
        Log::debug('Listing employees by country', [
            'country' => $country,
            'per_page' => $perPage,
        ]);

        return $this->repository->paginateByCountry($country, $perPage);
    }

    public function find(int $employeeId): ?Employee
    {
        $employee = $this->repository->find($employeeId);

        if (! $employee) {
            Log::warning('Employee not found', [
                'employee_id' => $employeeId,
            ]);
        }

        return $employee;
    }

    public function create(EmployeeData $data, array $changedFields): Employee
    {
        Log::info('Creating employee', [
            'country' => $data->country,
            'name' => $data->name,
        ]);

        $this->assertUniqueIdentifier($data);

        $employee = $this->repository->create($data);

        $this->dispatchEvent(
            eventType: 'EmployeeCreated',
            action: 'created',
            employee: $employee,
            changedFields: $changedFields ?: $this->defaultChangedFields($employee)
        );

        return $employee;
    }

    public function update(Employee $employee, EmployeeData $data, array $changedFields): Employee
    {
        Log::info('Updating employee', [
            'employee_id' => $employee->id,
            'country' => $employee->country,
        ]);

        $this->assertUniqueIdentifier($data, $employee->id);

        $updated = $this->repository->update($employee, $data);

        $this->dispatchEvent(
            eventType: 'EmployeeUpdated',
            action: 'updated',
            employee: $updated,
            changedFields: $changedFields ?: $this->defaultChangedFields($updated)
        );

        return $updated;
    }

    public function delete(Employee $employee): void
    {
        Log::info('Deleting employee', [
            'employee_id' => $employee->id,
            'country' => $employee->country,
        ]);

        $snapshot = EmployeeResource::make($employee)->resolve();
        $this->repository->delete($employee);

        $this->dispatchEvent(
            eventType: 'EmployeeDeleted',
            action: 'deleted',
            employee: $employee,
            changedFields: [],
            snapshot: $snapshot
        );
    }

    protected function dispatchEvent(string $eventType, string $action, Employee $employee, array $changedFields = [], ?array $snapshot = null): void
    {
        $country = strtoupper($employee->country);
        $routingKey = sprintf('employee.%s.%s', strtolower($country), $action);

        $payload = [
            'event_type' => $eventType,
            'event_id' => (string) Str::uuid(),
            'timestamp' => now()->toIso8601String(),
            'country' => $country,
            'data' => [
                'employee_id' => $employee->id,
                'changed_fields' => array_values($changedFields),
                'employee' => $snapshot ?? EmployeeResource::make($employee)->resolve(),
            ],
        ];

        PublishEmployeeEvent::dispatch($routingKey, $payload);
    }

    protected function defaultChangedFields(Employee $employee): array
    {
        $base = ['name', 'last_name', 'salary', 'country'];
        $attributeKeys = array_keys($employee->getAttribute('attributes') ?? []);

        $attributes = array_map(
            fn (string $key) => "attributes.$key",
            $attributeKeys
        );

        return array_merge($base, $attributes);
    }

    protected function assertUniqueIdentifier(EmployeeData $data, ?int $ignoreId = null): void
    {
        $country = strtoupper($data->country);
        $identifierKey = data_get(config('countries'), "$country.unique_identifier");

        if (! $identifierKey) {
            return;
        }

        $identifierValue = $data->attributes[$identifierKey] ?? null;

        if (! $identifierValue) {
            return;
        }

        if ($this->repository->existsWithCountryIdentifier($country, $identifierKey, $identifierValue, $ignoreId)) {
            throw ValidationException::withMessages([
                "attributes.$identifierKey" => __('An employee with this :identifier already exists for :country.', [
                    'identifier' => $identifierKey,
                    'country' => $country,
                ]),
            ]);
        }
    }
}
