<?php

namespace App\Domain\Checklists\Contracts;

use App\Domain\Checklists\DTOs\ChecklistRuleSet;

interface ChecklistRuleRepository
{
    public function forCountry(string $country): ?ChecklistRuleSet;
}
