<?php

namespace App\Domain\Checklists\Services;

use App\Domain\Checklists\DTOs\ChecklistRuleSet;
use App\Domain\Checklists\DTOs\EmployeeChecklist;
use App\Domain\Checklists\DTOs\FieldStatus;
use App\Domain\Employees\DTOs\EmployeeSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class ChecklistEvaluator
{
    public function evaluate(EmployeeSnapshot $snapshot, ChecklistRuleSet $ruleSet): EmployeeChecklist
    {
        $payload = $snapshot->toArray();

        $rules = [];
        $messages = [];

        foreach ($ruleSet->fields as $fieldRule) {
            $rules[$fieldRule->field] = $fieldRule->rules;

            foreach ($fieldRule->messages as $rule => $message) {
                $messages[$fieldRule->field . '.' . $rule] = $message;
            }
        }

        $validator = Validator::make($payload, $rules, $messages);
        $validator->passes(); // force evaluation
        $errors = $validator->errors();

        $fieldStatuses = array_map(
            function ($fieldRule) use ($errors) {
                $messages = $errors->get($fieldRule->field);

                return new FieldStatus(
                    field: $fieldRule->field,
                    label: $fieldRule->label,
                    complete: empty($messages),
                    message: $messages[0] ?? null,
                );
            },
            $ruleSet->fields
        );

        $totalFields = count($fieldStatuses);
        $completeFields = count(array_filter($fieldStatuses, fn (FieldStatus $status) => $status->complete));
        $completionRate = $totalFields > 0 ? $completeFields / $totalFields : 1.0;

        return new EmployeeChecklist(
            employee: $snapshot,
            fields: $fieldStatuses,
            completionRate: $completionRate,
            evaluatedAt: Carbon::now(),
            status: $completionRate >= 1 ? 'complete' : 'incomplete'
        );
    }
}
