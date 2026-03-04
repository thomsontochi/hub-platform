<?php

namespace App\Domain\Checklists\DTOs;

class ChecklistProjection
{
    /**
     * @param  EmployeeChecklist[]  $employees
     */
    public function __construct(
        public readonly ChecklistSummary $summary,
        public readonly array $employees,
        public readonly ?EmployeeChecklist $target = null,
    ) {}
}
