<?php

namespace App\Providers;

use App\Console\Commands\ConsumeEmployeeEvents;
use App\Domain\Checklists\Contracts\ChecklistCache;
use App\Domain\Checklists\Contracts\ChecklistRuleRepository;
use App\Domain\Employees\Contracts\EmployeeCache;
use App\Domain\Employees\Contracts\EmployeeEventHandler;
use App\Domain\Employees\Handlers\ProjectingEmployeeEventHandler;
use App\Domain\UI\Repositories\UiConfigurationRepository;
use App\Domain\UI\Services\UiConfigurationService;
use App\Infrastructure\Checklists\CacheChecklistCache;
use App\Infrastructure\Checklists\ConfigChecklistRuleRepository;
use App\Infrastructure\Employees\CacheEmployeeCache;
use App\Infrastructure\UI\Cache\UiCacheRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EmployeeEventHandler::class, ProjectingEmployeeEventHandler::class);
        $this->app->singleton(EmployeeCache::class, function ($app) {
            $cacheFactory = $app->make(CacheFactory::class);
            $storeName = config('cache.events_store', config('cache.default'));

            return new CacheEmployeeCache($cacheFactory->store($storeName));
        });

        $this->app->singleton(ChecklistRuleRepository::class, ConfigChecklistRuleRepository::class);
        $this->app->singleton(ChecklistCache::class, function ($app) {
            $cacheFactory = $app->make(CacheFactory::class);
            $storeName = config('cache.checklists_store', config('cache.default'));

            return new CacheChecklistCache($cacheFactory->store($storeName));
        });

        $this->app->singleton(UiConfigurationRepository::class);
        $this->app->singleton(UiConfigurationService::class, function ($app) {
            return new UiConfigurationService(
                $app->make(UiConfigurationRepository::class)
            );
        });

        $this->app->singleton(UiCacheRepository::class, function ($app) {
            $cacheFactory = $app->make(CacheFactory::class);
            $storeName = config('cache.ui_store', config('cache.default'));

            return new UiCacheRepository($cacheFactory->store($storeName));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ConsumeEmployeeEvents::class,
            ]);
        }
    }
}
