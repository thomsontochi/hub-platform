<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function (): void {
    config()->set('cache.default', 'array');
    config()->set('cache.ui_store', 'array');
    Cache::store('array')->flush();
});

it('returns widget schema for USA dashboard', function (): void {
    $response = $this->getJson('/api/schema/dashboard?country=usa');

    $response->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('data', 3)
            ->where('data.0.id', 'employee_count')
            ->where('data.1.data_source', 'employees.average_salary')
        );
});

it('returns widget schema for Germany documentation step', function (): void {
    $response = $this->getJson('/api/schema/documentation?country=germany');

    $response->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('data', 1)
            ->where('data.0.id', 'country_docs')
        );
});

it('responds with 404 for unknown step', function (): void {
    $response = $this->getJson('/api/schema/unknown?country=usa');

    $response->assertStatus(404);
});
