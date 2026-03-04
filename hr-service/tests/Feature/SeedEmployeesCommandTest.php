<?php

use App\Jobs\PublishEmployeeEvent;
use App\Models\Employee;
use Illuminate\Support\Facades\Queue;

it('seeds sample employees and dispatches events', function (): void {
    Queue::fake();

    $this->artisan('hr:employees:seed', ['--refresh' => true])
        ->expectsOutputToContain('Seeded')
        ->assertExitCode(0);

    expect(Employee::count())->toBe(4);

    Queue::assertPushed(PublishEmployeeEvent::class, 4);
});
