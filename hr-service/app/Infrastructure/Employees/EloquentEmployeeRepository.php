<?php

namespace App\Infrastructure\Employees;

use App\Domain\Employees\Contracts\EmployeeRepository;
use App\Domain\Employees\DTOs\EmployeeData;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentEmployeeRepository implements EmployeeRepository
{
    public function create(EmployeeData $data): Employee
    {
        return Employee::create($data->toPersistenceArray());
    }

    public function update(Employee $employee, EmployeeData $data): Employee
    {
        $employee->fill($data->toPersistenceArray());
        $employee->save();

        return $employee->refresh();
    }

    public function delete(Employee $employee): void
    {
        $employee->delete();
    }

    public function find(int $employeeId): ?Employee
    {
        return Employee::find($employeeId);
    }

    public function paginateByCountry(string $country, int $perPage = 15): LengthAwarePaginator
    {
        return Employee::query()
            ->where('country', strtoupper($country))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function existsWithCountryIdentifier(string $country, string $identifierKey, string $identifierValue, ?int $ignoreId = null): bool
    {
        $jsonPath = sprintf("attributes->>'%s'", $identifierKey);

        return Employee::query()
            ->where('country', strtoupper($country))
            ->whereRaw("{$jsonPath} = ?", [$identifierValue])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
