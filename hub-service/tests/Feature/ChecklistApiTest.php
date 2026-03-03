<?php

use App\Domain\Checklists\DTOs\ChecklistProjection;
use App\Domain\Checklists\DTOs\ChecklistSummary;
use App\Domain\Checklists\DTOs\EmployeeChecklist;
use App\Domain\Employees\DTOs\EmployeeSnapshot;
use App\Domain\Checklists\Services\ChecklistProjectionService;
use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Mockery;

afterEach(function (): void {
    Mockery::close();
});

it('returns the cached checklist projection for a country', function (): void {
    config()->set('checklists', [
        'usa' => ['fields' => []],
    ]);

    $projection = new ChecklistProjection(
        summary: new ChecklistSummary(
            country: 'usa',
            totalEmployees: 2,
            completeEmployees: 1,
            incompleteEmployees: 1,
            averageCompletionRate: 0.75,
        ),
        employees: [
            new EmployeeChecklist(
                employee: new EmployeeSnapshot(
                    id: 1,
                    name: 'John',
                    lastName: 'Doe',
                    salary: 75000,
                    country: 'USA',
                    attributes: ['ssn' => '123-45-6789'],
                ),
                fields: [],
                completionRate: 1.0,
                evaluatedAt: Carbon::parse('2024-01-01T00:00:00Z'),
                status: 'complete',
            ),
            new EmployeeChecklist(
                employee: new EmployeeSnapshot(
                    id: 2,
                    name: 'Jane',
                    lastName: 'Smith',
                    salary: 65000,
                    country: 'USA',
                    attributes: ['ssn' => '987-65-4321'],
                ),
                fields: [],
                completionRate: 0.5,
                evaluatedAt: Carbon::parse('2024-01-02T00:00:00Z'),
                status: 'incomplete',
            ),
        ],
    );

    /** @var \Tests\TestCase $this */

    $service = Mockery::mock(ChecklistProjectionService::class);
    $service->shouldReceive('get')
        ->once()
        ->with('usa')
        ->andReturn($projection);

    $this->swap(ChecklistProjectionService::class, $service);

    $this->getJson('/api/checklists?country=usa')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('data.summary.country', 'usa')
            ->where('data.summary.total_employees', 2)
            ->where('data.summary.average_completion_rate', 0.75)
            ->has('data.employees', 2)
            ->etc()
        );
});

it('returns a 404 JSON envelope when rules are missing for a country', function (): void {
    config()->set('checklists', [
        'france' => ['fields' => []],
    ]);

    /** @var \Tests\TestCase $this */

    $service = Mockery::mock(ChecklistProjectionService::class);
    $service->shouldReceive('get')
        ->once()
        ->with('france')
        ->andReturnNull();

    $this->swap(ChecklistProjectionService::class, $service);

    $this->getJson('/api/checklists?country=france')
        ->assertNotFound()
        ->assertJson([
            'status' => 'error',
            'message' => 'Checklist rules not configured for country [FRANCE].',
        ]);
});

it('returns validation envelope for unsupported country', function (): void {
    config()->set('checklists', [
        'usa' => ['fields' => []],
    ]);

    /** @var \Tests\TestCase $this */

    $this->getJson('/api/checklists?country=unknown')
        ->assertUnprocessable()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('status', 'error')
            ->where('message', 'Validation failed.')
            ->has('errors.country')
        );
});
