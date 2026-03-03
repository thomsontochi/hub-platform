<?php

declare(strict_types=1);

use App\Domain\Checklists\Services\ChecklistEvaluator;
use App\Domain\Checklists\Services\ChecklistProjectionService;
use App\Domain\Employees\Handlers\ProjectingEmployeeEventHandler;
use App\Events\ChecklistUpdated;
use App\Infrastructure\Checklists\CacheChecklistCache;
use App\Infrastructure\Checklists\ConfigChecklistRuleRepository;
use App\Infrastructure\Employees\CacheEmployeeCache;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Carbon::setTestNow('2026-03-03T18:37:33+00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function makeChecklistPipeline(): array
{
    $ruleRepository = new ConfigChecklistRuleRepository();
    $evaluator = new ChecklistEvaluator();
    $checklistCache = new CacheChecklistCache(new Repository(new ArrayStore()));
    $employeeCache = new CacheEmployeeCache(new Repository(new ArrayStore()));

    $service = new ChecklistProjectionService(
        $ruleRepository,
        $evaluator,
        $checklistCache,
        $employeeCache,
    );

    $handler = new ProjectingEmployeeEventHandler($employeeCache, $service);

    return compact('service', 'handler', 'employeeCache');
}

it('projects checklist data when an employee created event arrives', function (): void {
    config()->set('checklists', [
        'usa' => [
            'fields' => [
                'attributes.ssn' => [
                    'label' => 'Social Security Number',
                    'rules' => ['required'],
                ],
                'salary' => [
                    'label' => 'Salary',
                    'rules' => ['required', 'numeric', 'min:0'],
                ],
                'attributes.address' => [
                    'label' => 'Address',
                    'rules' => ['required'],
                ],
            ],
        ],
    ]);

    ['service' => $service, 'handler' => $handler] = makeChecklistPipeline();

    Event::fake([ChecklistUpdated::class]);

    $payload = [
        'event_type' => 'EmployeeCreated',
        'event_id' => (string) Str::uuid(),
        'timestamp' => now()->toIso8601String(),
        'country' => 'USA',
        'data' => [
            'employee_id' => 42,
            'changed_fields' => [
                'name', 'last_name', 'salary', 'country', 'attributes.ssn', 'attributes.address',
            ],
            'employee' => [
                'id' => 42,
                'name' => 'John',
                'last_name' => 'Snow',
                'salary' => 75000,
                'country' => 'USA',
                'attributes' => [
                    'ssn' => '133-45-6579',
                    'address' => '123 Main St',
                ],
            ],
        ],
    ];

    $handler->handle('employee.usa.created', $payload);

    /** @var \App\Domain\Checklists\DTOs\ChecklistProjection|null $projection */
    $projection = $service->get('usa');

    expect($projection)->not->toBeNull();
    expect($projection->summary->country)->toBe('USA');
    expect($projection->summary->totalEmployees)->toBe(1);
    expect($projection->summary->completeEmployees)->toBe(1);
    expect($projection->summary->averageCompletionRate)->toBe(1.0);
    expect($projection->employees)->toHaveCount(1);
    expect($projection->employees[0]->status)->toBe('complete');

    Event::assertDispatched(ChecklistUpdated::class, function (ChecklistUpdated $event) {
        return $event->projection->summary->country === 'USA'
            && $event->projection->summary->totalEmployees === 1;
    });
});

it('recalculates projection when an employee delete event arrives', function (): void {
    config()->set('checklists', [
        'usa' => [
            'fields' => [
                'attributes.ssn' => [
                    'label' => 'Social Security Number',
                    'rules' => ['required'],
                ],
            ],
        ],
    ]);

    ['service' => $service, 'handler' => $handler] = makeChecklistPipeline();

    Event::fake([ChecklistUpdated::class]);

    $createPayload = [
        'event_type' => 'EmployeeCreated',
        'event_id' => (string) Str::uuid(),
        'timestamp' => now()->toIso8601String(),
        'country' => 'USA',
        'data' => [
            'employee_id' => 91,
            'changed_fields' => ['name', 'attributes.ssn'],
            'employee' => [
                'id' => 91,
                'name' => 'Brienne',
                'last_name' => 'Tarth',
                'salary' => 62000,
                'country' => 'USA',
                'attributes' => [
                    'ssn' => '999-45-1111',
                ],
            ],
        ],
    ];

    $handler->handle('employee.usa.created', $createPayload);

    $deletePayload = [
        'event_type' => 'EmployeeDeleted',
        'event_id' => (string) Str::uuid(),
        'timestamp' => now()->toIso8601String(),
        'country' => 'USA',
        'data' => [
            'employee_id' => 91,
            'changed_fields' => [],
            'employee' => $createPayload['data']['employee'],
        ],
    ];

    $handler->handle('employee.usa.deleted', $deletePayload);

    /** @var \App\Domain\Checklists\DTOs\ChecklistProjection|null $projection */
    $projection = $service->get('usa');

    expect($projection)->not->toBeNull();
    expect($projection->summary->totalEmployees)->toBe(0);
    expect($projection->summary->completeEmployees)->toBe(0);
    expect($projection->employees)->toBeEmpty();

    Event::assertDispatched(ChecklistUpdated::class, function (ChecklistUpdated $event) {
        return $event->projection->summary->totalEmployees === 0
            && $event->projection->summary->country === 'USA';
    });
});
