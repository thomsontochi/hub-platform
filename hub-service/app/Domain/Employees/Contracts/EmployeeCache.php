<?php

namespace App\Domain\Employees\Contracts;

use App\Domain\Employees\DTOs\EmployeeSnapshot;

interface EmployeeCache
{
    public function remember(int $employeeId, callable $resolver): EmployeeSnapshot;

    public function put(EmployeeSnapshot $snapshot, int $ttlSeconds = 300): void;

    public function forget(int $employeeId): void;

    /**
     * @return array<int, EmployeeSnapshot>
     */
    public function allForCountry(string $country): array;
}
