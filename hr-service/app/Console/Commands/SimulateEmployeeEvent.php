<?php

namespace App\Console\Commands;

use App\Jobs\PublishEmployeeEvent;
use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SimulateEmployeeEvent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:employees:simulate-event
                    {--action=created : The event action (created, updated, deleted)}
                    {--country=USA : Country ISO code (USA or GERMANY)}
                    {--employee= : Existing employee ID to base the payload on}
                    {--payload= : Optional JSON payload override}
                    {--dry-run : Output payload without dispatching}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate employee events by publishing payloads through the event bus.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = strtolower((string) $this->option('action'));
        $country = strtoupper((string) $this->option('country'));
        $employeeId = $this->option('employee');
        $dryRun = (bool) $this->option('dry-run');

        if (! in_array($action, ['created', 'updated', 'deleted'], true)) {
            $this->components->error('Invalid --action provided. Use created, updated, or deleted.');

            return self::INVALID;
        }

        try {
            $payload = $this->resolvePayload($action, $country, $employeeId);

            $this->components->info('Prepared payload:');
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if ($dryRun) {
                $this->components->warn('Dry run mode active. No event dispatched.');

                return self::SUCCESS;
            }

            $routingKey = sprintf('employee.%s.%s', strtolower($country), $action);
            PublishEmployeeEvent::dispatch($routingKey, $payload);

            $this->components->success(sprintf('Event dispatched on [%s].', $routingKey));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Failed to simulate employee event', [
                'action' => $action,
                'country' => $country,
                'employee_id' => $employeeId,
                'exception' => $exception->getMessage(),
            ]);

            $this->components->error('Unable to simulate event. See logs for details.');

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolvePayload(string $action, string $country, ?string $employeeId): array
    {
        if ($payload = $this->option('payload')) {
            return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }

        $snapshot = $employeeId ? $this->snapshotFromEmployeeId((int) $employeeId) : $this->snapshotTemplate($country);

        $eventType = match ($action) {
            'created' => 'EmployeeCreated',
            'updated' => 'EmployeeUpdated',
            'deleted' => 'EmployeeDeleted',
            default => 'EmployeeUpdated',
        };

        $changedFields = $action === 'deleted'
            ? []
            : $this->changedFieldsFromSnapshot($snapshot);

        return [
            'event_type' => $eventType,
            'event_id' => (string) Str::uuid(),
            'timestamp' => now()->toIso8601String(),
            'country' => $snapshot['country'],
            'data' => [
                'employee_id' => $snapshot['id'],
                'changed_fields' => $changedFields,
                'employee' => $snapshot,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotFromEmployeeId(int $employeeId): array
    {
        $employee = Employee::query()->findOrFail($employeeId);

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'last_name' => $employee->last_name,
            'salary' => (float) $employee->salary,
            'country' => strtoupper($employee->country),
            'attributes' => $employee->attributes ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshotTemplate(string $country): array
    {
        $templates = [
            'USA' => [
                'id' => random_int(1000, 9999),
                'name' => 'Simulated',
                'last_name' => 'Employee',
                'salary' => 78000,
                'country' => 'USA',
                'attributes' => [
                    'ssn' => '555-66-7777',
                    'address' => '500 Demo Street, Austin, TX',
                ],
            ],
            'GERMANY' => [
                'id' => random_int(1000, 9999),
                'name' => 'Simulierte',
                'last_name' => 'Mitarbeiterin',
                'salary' => 69000,
                'country' => 'GERMANY',
                'attributes' => [
                    'goal' => 'Pilot remote onboarding',
                    'tax_id' => 'DE102938475',
                ],
            ],
        ];

        return $templates[$country] ?? $templates['USA'];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<int, string>
     */
    protected function changedFieldsFromSnapshot(array $snapshot): array
    {
        $base = ['name', 'last_name', 'salary', 'country'];
        $attributeFields = array_map(
            fn (string $key) => "attributes.$key",
            array_keys(Arr::get($snapshot, 'attributes', []))
        );

        return array_values(array_unique(array_merge($base, $attributeFields)));
    }
}
