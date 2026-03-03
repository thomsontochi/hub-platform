<?php

namespace App\Domain\Checklists\Services;

use App\Domain\Checklists\Contracts\ChecklistCache;
use App\Domain\Checklists\Contracts\ChecklistRuleRepository;
use App\Domain\Checklists\DTOs\ChecklistProjection;
use App\Domain\Checklists\DTOs\ChecklistSummary;
use App\Domain\Checklists\DTOs\EmployeeChecklist;
use App\Domain\Employees\Contracts\EmployeeCache;
use App\Domain\Employees\DTOs\EmployeeSnapshot;
use Illuminate\Support\Facades\Log;

class ChecklistProjectionService
{
    public function __construct(
        protected ChecklistRuleRepository $rules,
        protected ChecklistEvaluator $evaluator,
        protected ChecklistCache $cache,
        protected EmployeeCache $employeeCache,
    ) {
    }

    public function project(EmployeeSnapshot $snapshot): ?ChecklistProjection
    {
        $ruleSet = $this->rules->forCountry($snapshot->country);

        if (! $ruleSet) {
            Log::warning('Checklist rules missing for country', ['country' => $snapshot->country]);

            return null;
        }

        $checklist = $this->evaluator->evaluate($snapshot, $ruleSet);
        $this->cache->putEmployee($checklist);
        $summary = $this->recalculateSummary($snapshot->country);

        return new ChecklistProjection(
            summary: $summary,
            employees: $this->cache->allEmployees($snapshot->country),
            target: $checklist,
        );
    }

    public function remove(EmployeeSnapshot $snapshot): ChecklistProjection
    {
        $this->cache->forgetEmployee($snapshot->id);
        $summary = $this->recalculateSummary($snapshot->country);

        return new ChecklistProjection(
            summary: $summary,
            employees: $this->cache->allEmployees($snapshot->country),
        );
    }

    public function rebuild(string $country): ?ChecklistProjection
    {
        $ruleSet = $this->rules->forCountry($country);

        if (! $ruleSet) {
            Log::warning('Checklist rules missing for country during rebuild', ['country' => $country]);

            return null;
        }

        $employees = $this->employeeCache->allForCountry($country);
        $checklists = array_map(function (EmployeeSnapshot $snapshot) use ($ruleSet) {
            return $this->evaluator->evaluate($snapshot, $ruleSet);
        }, $employees);

        foreach ($checklists as $checklist) {
            $this->cache->putEmployee($checklist);
        }

        $summary = $this->buildSummary($country, $checklists);
        $this->cache->putSummary($summary);

        return new ChecklistProjection(
            summary: $summary,
            employees: $checklists,
        );
    }

    public function get(string $country): ?ChecklistProjection
    {
        $ruleSet = $this->rules->forCountry($country);

        if (! $ruleSet) {
            Log::warning('Checklist rules missing for country during fetch', ['country' => $country]);

            return null;
        }

        $summary = $this->cache->getSummary($country);

        if (! $summary) {
            return $this->rebuild($country);
        }

        $employees = $this->cache->allEmployees($country);

        if ($summary->totalEmployees > 0 && empty($employees)) {
            return $this->rebuild($country);
        }

        return new ChecklistProjection(
            summary: $summary,
            employees: $employees,
        );
    }

    protected function recalculateSummary(string $country): ChecklistSummary
    {
        $employees = $this->cache->allEmployees($country);

        if (! $employees) {
            $summary = new ChecklistSummary(
                country: $country,
                totalEmployees: 0,
                completeEmployees: 0,
                incompleteEmployees: 0,
                averageCompletionRate: 0.0,
            );

            $this->cache->putSummary($summary);

            return $summary;
        }

        $summary = $this->buildSummary($country, $employees);
        $this->cache->putSummary($summary);

        return $summary;
    }

    /**
     * @param  EmployeeChecklist[]  $checklists
     */
    protected function buildSummary(string $country, array $checklists): ChecklistSummary
    {
        $total = count($checklists);
        $complete = count(array_filter($checklists, fn (EmployeeChecklist $checklist) => $checklist->status === 'complete'));
        $incomplete = $total - $complete;
        $avgCompletion = $total > 0
            ? array_sum(array_map(fn (EmployeeChecklist $checklist) => $checklist->completionRate, $checklists)) / $total
            : 0.0;

        return new ChecklistSummary(
            country: $country,
            totalEmployees: $total,
            completeEmployees: $complete,
            incompleteEmployees: $incomplete,
            averageCompletionRate: $avgCompletion,
        );
    }
}
