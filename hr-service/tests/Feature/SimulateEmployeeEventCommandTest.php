<?php

use App\Jobs\PublishEmployeeEvent;
use App\Models\Employee;
use Illuminate\Support\Facades\Queue;

it('dispatches a simulated employee event using existing snapshot', function (): void {
    Queue::fake();

    $employee = Employee::factory()->create([
        'name' => 'Alex',
        'last_name' => 'Morgan',
        'salary' => 88000,
        'country' => 'USA',
        'attributes' => [
            'ssn' => '111-22-3333',
            'address' => '100 River Road, Boston, MA',
        ],
    ]);

    $this->artisan('hr:employees:simulate-event', [
        '--action' => 'updated',
        '--country' => 'USA',
        '--employee' => (string) $employee->id,
    ])->expectsOutputToContain('Event dispatched')
        ->assertExitCode(0);

    Queue::assertPushed(PublishEmployeeEvent::class, function (PublishEmployeeEvent $job) use ($employee) {
        $payload = $job->payload();

        return $job->routingKey() === 'employee.usa.updated'
            && $payload['event_type'] === 'EmployeeUpdated'
            && $payload['data']['employee_id'] === $employee->id
            && in_array('attributes.ssn', $payload['data']['changed_fields'], true);
    });
});

it('supports dry run mode without dispatching', function (): void {
    Queue::fake();

    $this->artisan('hr:employees:simulate-event', [
        '--action' => 'created',
        '--country' => 'USA',
        '--dry-run' => true,
    ])->expectsOutputToContain('Dry run mode active')
        ->assertExitCode(0);

    Queue::assertNothingPushed();
});
