<?php

namespace App\Infrastructure\Checklists;

use App\Domain\Checklists\Contracts\ChecklistCache;
use App\Domain\Checklists\DTOs\ChecklistSummary;
use App\Domain\Checklists\DTOs\EmployeeChecklist;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;

class CacheChecklistCache implements ChecklistCache
{
    public function __construct(
        protected CacheRepository $cache
    ) {
    }

    public function putEmployee(EmployeeChecklist $checklist, int $ttlSeconds = 300): void
    {
        $country = strtolower($checklist->employee->country);
        $employeeId = $checklist->employee->id;

        $this->cache->put(
            $this->employeeKey($country, $employeeId),
            $checklist->toArray(),
            Carbon::now()->addSeconds($ttlSeconds)
        );

        $indexKey = $this->indexKey($country);
        $ids = $this->cache->get($indexKey, []);

        if (! in_array($employeeId, $ids, true)) {
            $ids[] = $employeeId;
        }

        $this->cache->put($indexKey, $ids, Carbon::now()->addSeconds($ttlSeconds));
        $this->cache->put($this->employeeCountryKey($employeeId), $country, Carbon::now()->addSeconds($ttlSeconds));
    }

    public function getEmployee(string $country, int $employeeId): ?EmployeeChecklist
    {
        $country = strtolower($country);
        $payload = $this->cache->get($this->employeeKey($country, $employeeId));

        if (! $payload) {
            return null;
        }

        return EmployeeChecklist::fromArray($payload);
    }

    public function forgetEmployee(int $employeeId): ?EmployeeChecklist
    {
        $country = $this->cache->pull($this->employeeCountryKey($employeeId));

        if (! $country) {
            return null;
        }

        $country = strtolower($country);
        $key = $this->employeeKey($country, $employeeId);
        $payload = $this->cache->pull($key);

        if (! $payload) {
            $this->removeFromIndex($country, $employeeId);

            return null;
        }

        $this->removeFromIndex($country, $employeeId);

        return EmployeeChecklist::fromArray($payload);
    }

    public function putSummary(ChecklistSummary $summary, int $ttlSeconds = 300): void
    {
        $this->cache->put(
            $this->summaryKey($summary->country),
            $summary->toArray(),
            Carbon::now()->addSeconds($ttlSeconds)
        );
    }

    public function getSummary(string $country): ?ChecklistSummary
    {
        $payload = $this->cache->get($this->summaryKey($country));

        if (! $payload) {
            return null;
        }

        return ChecklistSummary::fromArray($payload);
    }

    public function forgetSummary(string $country): void
    {
        $this->cache->forget($this->summaryKey($country));
    }

    public function allEmployees(string $country): array
    {
        $country = strtolower($country);
        $ids = $this->cache->get($this->indexKey($country), []);

        return array_values(array_filter(array_map(function (int $id) use ($country) {
            return $this->getEmployee($country, $id);
        }, $ids)));
    }

    protected function removeFromIndex(string $country, int $employeeId): void
    {
        $indexKey = $this->indexKey($country);
        $ids = array_filter(
            $this->cache->get($indexKey, []),
            fn (int $id) => $id !== $employeeId
        );

        if ($ids) {
            $this->cache->put($indexKey, array_values($ids));
        } else {
            $this->cache->forget($indexKey);
        }
    }

    protected function employeeKey(string $country, int $employeeId): string
    {
        return sprintf('checklists:%s:employees:%d', strtolower($country), $employeeId);
    }

    protected function summaryKey(string $country): string
    {
        return sprintf('checklists:%s:summary', strtolower($country));
    }

    protected function indexKey(string $country): string
    {
        return sprintf('checklists:%s:index', strtolower($country));
    }

    protected function employeeCountryKey(int $employeeId): string
    {
        return sprintf('checklists:employees:%d:country', $employeeId);
    }
}
