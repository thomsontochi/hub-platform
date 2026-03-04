<?php

namespace App\Providers;

use App\Console\Commands\SeedEmployees;
use App\Console\Commands\SimulateEmployeeEvent;
use App\Domain\Employees\Contracts\EmployeeRepository;
use App\Infrastructure\Employees\EloquentEmployeeRepository;
use App\Messaging\Contracts\EventPublisher;
use App\Messaging\RabbitMq\RabbitMqPublisher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EmployeeRepository::class, EloquentEmployeeRepository::class);
        $this->app->singleton(EventPublisher::class, RabbitMqPublisher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedEmployees::class,
                SimulateEmployeeEvent::class,
            ]);
        }
    }
}
