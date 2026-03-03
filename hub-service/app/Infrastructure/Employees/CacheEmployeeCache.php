<?php

namespace App\Infrastructure\Employees;

use App\Domain\Employees\Contracts\EmployeeCache;
use App\Domain\Employees\DTOs\EmployeeSnapshot;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;

class CacheEmployeeCache implements EmployeeCache
{
    public function __construct(
        protected CacheRepository $cache
    ) {
    }

    public function remember(int $employeeId, callable $resolver): EmployeeSnapshot
    {
        $cached = $this->getSnapshot($employeeId);

        if ($cached) {
            return $cached;
        }

        $snapshot = $resolver();
        $this->put($snapshot);

        return $snapshot;
    }

    public function put(EmployeeSnapshot $snapshot, int $ttlSeconds = 300): void
    {
        $this->cache->put(
            $this->snapshotKey($snapshot->id),
            $snapshot->toArray(),
            Carbon::now()->addSeconds($ttlSeconds)
        );

        $indexKey = $this->countryIndexKey($snapshot->country);
        $ids = $this->cache->get($indexKey, []);

        if (! in_array($snapshot->id, $ids, true)) {
            $ids[] = $snapshot->id;
        }

        $this->cache->put($indexKey, $ids, Carbon::now()->addSeconds($ttlSeconds));
    }

    public function forget(int $employeeId): ?EmployeeSnapshot
    {
        $snapshot = $this->getSnapshot($employeeId);

        $this->cache->forget($this->snapshotKey($employeeId));

        if (! $snapshot) {
            return null;
        }

        $indexKey = $this->countryIndexKey($snapshot->country);
        $ids = array_filter(
            $this->cache->get($indexKey, []),
            fn (int $id) => $id !== $employeeId
        );

        if ($ids) {
            $this->cache->put($indexKey, array_values($ids));
        } else {
            $this->cache->forget($indexKey);
        }

        return $snapshot;
    }

    public function allForCountry(string $country): array
    {
        $index = $this->cache->get($this->countryIndexKey($country), []);

        return array_values(array_filter(array_map(
            fn (int $id) => $this->getSnapshot($id),
            $index
        )));
    }

    protected function getSnapshot(int $employeeId): ?EmployeeSnapshot
    {
        $data = $this->cache->get($this->snapshotKey($employeeId));

        if (! $data) {
            return null;
        }

        return EmployeeSnapshot::fromArray($data);
    }

    protected function snapshotKey(int $employeeId): string
    {
        return sprintf('employees:snapshots:%d', $employeeId);
    }

    protected function countryIndexKey(string $country): string
    {
        return sprintf('employees:snapshots:index:%s', strtolower($country));
    }
}
