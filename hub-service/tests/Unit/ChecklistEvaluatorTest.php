<?php

declare(strict_types=1);

use App\Domain\Checklists\DTOs\ChecklistRuleSet;
use App\Domain\Checklists\DTOs\FieldRule;
use App\Domain\Checklists\Services\ChecklistEvaluator;
use App\Domain\Employees\DTOs\EmployeeSnapshot;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-03-03T18:00:00+00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('marks all fields complete when the employee satisfies the rules', function (): void {
    $snapshot = new EmployeeSnapshot(
        id: 1,
        name: 'Arya',
        lastName: 'Stark',
        salary: 85000,
        country: 'USA',
        attributes: [
            'ssn' => '123-45-6789',
            'address' => '1 Winterfell Way',
        ],
    );

    $ruleSet = new ChecklistRuleSet('usa', [
        new FieldRule('attributes.ssn', 'Social Security Number', ['required']),
        new FieldRule('salary', 'Salary', ['required', 'numeric', 'min:0']),
        new FieldRule('attributes.address', 'Address', ['required']),
    ]);

    $evaluator = new ChecklistEvaluator();

    $result = $evaluator->evaluate($snapshot, $ruleSet);

    expect($result->status)->toBe('complete');
    expect($result->completionRate)->toBe(1.0);
    expect(collect($result->fields)->every(fn ($field) => $field->complete))->toBeTrue();
    expect($result->evaluatedAt?->toIso8601String())->toBe('2026-03-03T18:00:00+00:00');
});

it('captures validation failures and messages when fields are incomplete', function (): void {
    $snapshot = new EmployeeSnapshot(
        id: 2,
        name: 'Sansa',
        lastName: 'Stark',
        salary: 48000,
        country: 'USA',
        attributes: [
            'ssn' => null,
            'address' => '',
        ],
    );

    $ruleSet = new ChecklistRuleSet('usa', [
        new FieldRule('attributes.ssn', 'Social Security Number', ['required'], ['required' => 'SSN required.']),
        new FieldRule('salary', 'Salary', ['required', 'numeric', 'min:50000'], ['min' => 'Salary too low.']),
        new FieldRule('attributes.address', 'Address', ['required'], ['required' => 'Address required.']),
    ]);

    $evaluator = new ChecklistEvaluator();

    $result = $evaluator->evaluate($snapshot, $ruleSet);

    $fields = collect($result->fields)->keyBy('field');

    expect($result->status)->toBe('incomplete');
    expect($result->completionRate)->toBeLessThan(1.0);

    expect($fields['attributes.ssn']->complete)->toBeFalse();
    expect($fields['attributes.ssn']->message)->toBe('SSN required.');

    expect($fields['salary']->complete)->toBeFalse();
    expect($fields['salary']->message)->toBe('Salary too low.');

    expect($fields['attributes.address']->complete)->toBeFalse();
    expect($fields['attributes.address']->message)->toBe('Address required.');
});

it('treats empty rule sets as fully complete', function (): void {
    $snapshot = new EmployeeSnapshot(
        id: 3,
        name: 'Bran',
        lastName: 'Stark',
        salary: 0,
        country: 'USA',
        attributes: [],
    );

    $ruleSet = new ChecklistRuleSet('usa', []);

    $evaluator = new ChecklistEvaluator();

    $result = $evaluator->evaluate($snapshot, $ruleSet);

    expect($result->status)->toBe('complete');
    expect($result->completionRate)->toBe(1.0);
    expect($result->fields)->toBeEmpty();
});
