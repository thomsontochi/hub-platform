<?php

use App\Jobs\PublishEmployeeEvent;
use App\Models\Employee;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function (): void {
    Queue::fake();
});

it('creates an employee and dispatches event', function (): void {
    $payload = [
        'name' => 'John',
        'last_name' => 'Doe',
        'salary' => 75000,
        'country' => 'usa',
        'attributes' => [
            'ssn' => '123-45-6789',
            'address' => '123 Main St',
        ],
    ];

    $this->postJson('/api/v1/employees', $payload)
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('data.name', 'John')
            ->where('data.country', 'USA')
            ->where('data.attributes.ssn', '123-45-6789')
            ->etc()
        );

    Queue::assertPushed(PublishEmployeeEvent::class, function (PublishEmployeeEvent $job) {
        return $job->routingKey() === 'employee.usa.created'
            && $job->payload()['event_type'] === 'EmployeeCreated';
    });
});

it('updates an employee and dispatches event', function (): void {
    $employee = Employee::factory()->create([
        'name' => 'Jane',
        'last_name' => 'Doe',
        'salary' => 65000,
        'country' => 'USA',
        'attributes' => [
            'ssn' => '555-55-5555',
            'address' => '123 somewhere',
        ],
    ]);

    $this->patchJson("/api/v1/employees/{$employee->id}", [
        'salary' => 68000,
    ])->assertOk();

    Queue::assertPushed(PublishEmployeeEvent::class, function (PublishEmployeeEvent $job) use ($employee) {
        return $job->routingKey() === 'employee.usa.updated'
            && $job->payload()['data']['employee_id'] === $employee->id
            && $job->payload()['event_type'] === 'EmployeeUpdated';
    });
});

it('lists employees filtered by country', function (): void {
    Employee::factory()->create([
        'country' => 'USA',
    ]);

    Employee::factory()->create([
        'country' => 'Germany',
    ]);

    $this->getJson('/api/v1/employees?country=USA')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('data', 1)
            ->has('links')
            ->has('meta')
            ->where('data.0.country', 'USA')
            ->etc()
        );
});

it('deletes an employee and publish event', function (): void {
    $employee = Employee::factory()->create([
        'country' => 'USA',
    ]);

    $this->deleteJson("/api/v1/employees/{$employee->id}")
        ->assertNoContent();

    Queue::assertPushed(PublishEmployeeEvent::class, function (PublishEmployeeEvent $job) use ($employee) {
        return $job->routingKey() === 'employee.usa.deleted'
            && $job->payload()['event_type'] === 'EmployeeDeleted'
            && $job->payload()['data']['employee_id'] === $employee->id;
    });
});
