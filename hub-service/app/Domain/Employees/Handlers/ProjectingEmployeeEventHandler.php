<?php

namespace App\Domain\Employees\Handlers;

use App\Domain\Checklists\Services\ChecklistProjectionService;
use App\Domain\Employees\Contracts\EmployeeCache;
use App\Domain\Employees\Contracts\EmployeeEventHandler;
use App\Domain\Employees\DTOs\EmployeeSnapshot;
use App\Events\ChecklistUpdated;
use App\Infrastructure\UI\Cache\UiCacheRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ProjectingEmployeeEventHandler implements EmployeeEventHandler
{
    public function __construct(
        protected EmployeeCache $cache,
        protected ChecklistProjectionService $projectionService,
        protected UiCacheRepository $uiCache
    ) {
    }

    public function handle(string $routingKey, array $payload): void
    {
        $action = $this->resolveAction($routingKey);
        $data = $payload['data'] ?? null;

        if (! $data) {
            Log::warning('Employee event payload missing data', [
                'routing_key' => $routingKey,
                'payload' => $payload,
            ]);

            return;
        }

        $employeeId = Arr::get($data, 'employee_id');

        if ($action === 'deleted') {
            if ($employeeId) {
                $snapshot = $this->cache->forget($employeeId);

                if ($snapshot) {
                    $projection = $this->projectionService->remove($snapshot);
                    ChecklistUpdated::dispatch($projection);
                    $this->uiCache->forgetEmployees($snapshot->country);
                }

                Log::info('Employee removed from cache after delete event', [
                    'employee_id' => $employeeId,
                    'routing_key' => $routingKey,
                ]);
            }

            return;
        }

        $employeePayload = Arr::get($data, 'employee');

        if (! $employeePayload || ! $employeeId) {
            Log::warning('Employee event payload missing employee details', [
                'routing_key' => $routingKey,
                'payload' => $payload,
            ]);

            return;
        }

        $snapshot = EmployeeSnapshot::fromArray(array_merge($employeePayload, [
            'meta' => [
                'event_type' => $payload['event_type'] ?? null,
                'event_id' => $payload['event_id'] ?? null,
                'timestamp' => $payload['timestamp'] ?? null,
                'changed_fields' => Arr::get($data, 'changed_fields', []),
            ],
        ]));

        $this->cache->put($snapshot);
        $projection = $this->projectionService->project($snapshot);

        $this->uiCache->forgetEmployees($snapshot->country);

        if ($projection) {
            ChecklistUpdated::dispatch($projection);
        }

        Log::info('Employee snapshot cached from event', [
            'employee_id' => $snapshot->id,
            'routing_key' => $routingKey,
            'changed_fields' => Arr::get($data, 'changed_fields', []),
        ]);
    }

    protected function resolveAction(string $routingKey): string
    {
        return (string) last(explode('.', $routingKey));
    }
}
