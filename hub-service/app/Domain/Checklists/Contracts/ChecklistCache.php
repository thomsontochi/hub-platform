<?php

namespace App\Domain\Checklists\Contracts;

use App\Domain\Checklists\DTOs\ChecklistSummary;
use App\Domain\Checklists\DTOs\EmployeeChecklist;

interface ChecklistCache
{
    public function putEmployee(EmployeeChecklist $checklist, int $ttlSeconds = 300): void;

    public function getEmployee(string $country, int $employeeId): ?EmployeeChecklist;

    public function forgetEmployee(int $employeeId): ?EmployeeChecklist;

    public function putSummary(ChecklistSummary $summary, int $ttlSeconds = 300): void;

    public function getSummary(string $country): ?ChecklistSummary;

    public function forgetSummary(string $country): void;

    /**
     * @return array<int, EmployeeChecklist>
     */
    public function allEmployees(string $country): array;
}
