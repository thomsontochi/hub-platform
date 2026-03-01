<?php

namespace App\Domain\Employees\Contracts;

use App\Domain\Employees\DTOs\EmployeeData;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EmployeeRepository
{
    public function create(EmployeeData $data): Employee;

    public function update(Employee $employee, EmployeeData $data): Employee;

    public function delete(Employee $employee): void;

    public function find(int $employeeId): ?Employee;

    public function paginateByCountry(string $country, int $perPage = 15): LengthAwarePaginator;

    public function existsWithCountryIdentifier(string $country, string $identifierKey, string $identifierValue, ?int $ignoreId = null): bool;
}
