<?php

namespace App\Console\Commands;

use App\Domain\Employees\DTOs\EmployeeData;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SeedEmployees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:employees:seed {--refresh : Truncate the employees table before seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed sample USA and Germany employees using the domain service layer.';

    /**
     * Execute the console command.
     */
    public function handle(EmployeeService $service): int
    {
        $refresh = (bool) $this->option('refresh');

        try {
            if ($refresh) {
                Employee::truncate();
                $this->components->info('Employees table truncated.');
            }

            $seedPayloads = $this->seedPayloads();
            $created = 0;

            foreach ($seedPayloads as $payload) {
                $dto = EmployeeData::fromArray($payload);
                $changed = $this->changedFieldsFromPayload($payload);

                $employee = $service->create($dto, $changed);
                $created++;

                $this->components->twoColumnDetail(
                    sprintf('%s %s (%s)', $employee->name, $employee->last_name, $employee->country),
                    sprintf('#%d', $employee->id)
                );
            }

            $this->components->success("Seeded {$created} employees across countries.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Failed to seed employees', [
                'refresh' => $refresh,
                'exception' => $exception->getMessage(),
            ]);

            $this->components->error('Unable to seed employees. See logs for details.');

            return self::FAILURE;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function seedPayloads(): array
    {
        return [
            [
                'id' => null,
                'name' => 'Maya',
                'last_name' => 'Lopez',
                'salary' => 82000,
                'country' => 'USA',
                'attributes' => [
                    'ssn' => '105-44-2987',
                    'address' => '1200 Ocean Ave, Miami, FL',
                ],
            ],
            [
                'id' => null,
                'name' => 'Jordan',
                'last_name' => 'Kim',
                'salary' => 91000,
                'country' => 'USA',
                'attributes' => [
                    'ssn' => '223-65-4412',
                    'address' => '45 Market Street, San Francisco, CA',
                ],
            ],
            [
                'id' => null,
                'name' => 'Anika',
                'last_name' => 'Schneider',
                'salary' => 71000,
                'country' => 'GERMANY',
                'attributes' => [
                    'goal' => 'Launch Q3 onboarding program',
                    'tax_id' => 'DE192837465',
                ],
            ],
            [
                'id' => null,
                'name' => 'Felix',
                'last_name' => 'Bauer',
                'salary' => 68000,
                'country' => 'GERMANY',
                'attributes' => [
                    'goal' => 'Improve retention by 10%',
                    'tax_id' => 'DE485739102',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function changedFieldsFromPayload(array $payload): array
    {
        $base = ['name', 'last_name', 'salary', 'country'];
        $attributeFields = array_map(
            fn (string $key) => "attributes.$key",
            array_keys($payload['attributes'] ?? [])
        );

        return array_values(array_unique(array_merge($base, $attributeFields)));
    }
}
