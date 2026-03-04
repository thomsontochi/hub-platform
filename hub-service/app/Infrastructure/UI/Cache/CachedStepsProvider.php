<?php

namespace App\Infrastructure\UI\Cache;

use App\Domain\UI\Services\UiConfigurationService;

class CachedStepsProvider
{
    public function __construct(
        protected UiConfigurationService $service,
        protected UiCacheRepository $cache
    ) {}

    public function get(string $country): array
    {
        return $this->cache->rememberSteps($country, fn () => $this->service->steps($country));
    }
}
