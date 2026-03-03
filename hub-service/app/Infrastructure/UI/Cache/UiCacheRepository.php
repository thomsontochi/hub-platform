<?php

namespace App\Infrastructure\UI\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;

class UiCacheRepository
{
    public function __construct(
        protected CacheRepository $store
    ) {
    }

    public function rememberSteps(string $country, Closure $callback): mixed
    {
        $ttl = (int) config('ui.cache.steps', 900);
        $key = sprintf('ui:steps:%s', strtolower($country));

        return $this->store->remember($key, $ttl, $callback);
    }

    public function rememberSchema(string $country, string $step, Closure $callback): mixed
    {
        $ttl = (int) config('ui.cache.schema', 900);
        $key = sprintf('ui:schema:%s:%s', strtolower($country), strtolower($step));

        return $this->store->remember($key, $ttl, $callback);
    }

    public function rememberEmployees(string $country, int $page, int $perPage, Closure $callback): mixed
    {
        $ttl = (int) config('ui.cache.employees', 120);
        $key = $this->employeesKey($country, $page, $perPage);
        $indexKey = $this->employeesIndexKey($country);

        $value = $this->store->remember($key, $ttl, $callback);

        $keys = $this->store->get($indexKey, []);
        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            $this->store->put($indexKey, $keys, $ttl);
        }

        return $value;
    }

    public function forgetEmployees(string $country): void
    {
        $indexKey = $this->employeesIndexKey($country);
        $keys = $this->store->get($indexKey, []);

        foreach ($keys as $key) {
            $this->store->forget($key);
        }

        $this->store->forget($indexKey);
    }

    protected function employeesKey(string $country, int $page, int $perPage): string
    {
        return sprintf('ui:employees:%s:%d:%d', strtolower($country), $page, $perPage);
    }

    protected function employeesIndexKey(string $country): string
    {
        return sprintf('ui:employees:index:%s', strtolower($country));
    }
}
