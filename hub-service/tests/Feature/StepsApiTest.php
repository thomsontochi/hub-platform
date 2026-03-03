<?php

use App\Domain\UI\Repositories\UiConfigurationRepository;
use App\Domain\UI\Services\UiConfigurationService;
use App\Http\Controllers\API\StepsController;
use App\Http\Requests\StepsIndexRequest;
use App\Infrastructure\UI\Cache\UiCacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function (): void {
    config()->set('ui', require base_path('config/ui.php'));
});

it('returns steps for USA', function (): void {
    Route::get('/api/steps', StepsController::class);

    $response = $this->getJson('/api/steps?country=usa');

    $response->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('data', 2)
            ->where('data.0.id', 'dashboard')
            ->where('data.1.id', 'employees')
        );
});

it('returns steps for Germany', function (): void {
    Route::get('/api/steps', StepsController::class);

    $response = $this->getJson('/api/steps?country=germany');

    $response->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('data', 3)
            ->where('data.2.id', 'documentation')
        );
});

it('validates country input', function (): void {
    Route::get('/api/steps', StepsController::class);

    $response = $this->getJson('/api/steps?country=unknown');

    $response->assertUnprocessable()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('message', 'The selected country is invalid.')
            ->has('errors.country')
        );
});
