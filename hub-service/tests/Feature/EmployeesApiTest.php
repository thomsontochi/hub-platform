<?php

use App\Domain\Employees\Contracts\EmployeeCache;
use App\Domain\Employees\DTOs\EmployeeSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function (): void {
    config()->set('cache.default', 'array');
    config()->set('cache.ui_store', 'array');
    config()->set('cache.events_store', 'array');

    config()->set('ui', [
        'cache' => [
            'steps' => 900,
            'schema' => 900,
            'employees' => 120,
        ],
        'countries' => [
            'usa' => [
                'steps' => [],
                'columns' => [
                    [
                        'field' => 'name',
                        'key' => 'name',
                        'label' => 'First Name',
                        'type' => 'text',
                    ],
                    [
                        'field' => 'last_name',
                        'key' => 'last_name',
                        'label' => 'Last Name',
                        'type' => 'text',
                    ],
                    [
                        'field' => 'salary',
                        'key' => 'salary',
                        'label' => 'Salary',
                        'type' => 'currency',
                    ],
                    [
                        'field' => 'ssn',
                        'key' => 'attributes.ssn',
                        'label' => 'SSN',
                        'type' => 'text',
                        'mask' => true,
                    ],
                ],
                'widgets' => [],
            ],
        ],
    ]);

    Cache::store('array')->flush();

    /** @var EmployeeCache $employeeCache */
    $employeeCache = app(EmployeeCache::class);
    $employeeCache->put(new EmployeeSnapshot(
        id: 1,
        name: 'John',
        lastName: 'Doe',
        salary: 75000,
        country: 'USA',
        attributes: ['ssn' => '123-45-6789'],
    ));
});

it('returns paginated employees with masked ssn and column metadata', function (): void {
    $response = $this->getJson('/api/employees?country=USA');

    $response->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('data.columns.0.label', 'First Name')
            ->where('data.employees.0.attributes.ssn', '***-**-6789')
            ->where('meta.total', 1)
            ->where('meta.per_page', 15)
            ->where('meta.current_page', 1)
            ->has('links.first')
            ->has('links.last')
        );
});

it('honors pagination parameters', function (): void {
    /** @var EmployeeCache $employeeCache */
    $employeeCache = app(EmployeeCache::class);

    foreach (range(2, 6) as $id) {
        $employeeCache->put(new EmployeeSnapshot(
            id: $id,
            name: 'User '.$id,
            lastName: 'Test',
            salary: 60000,
            country: 'USA',
            attributes: ['ssn' => sprintf('111-11-%04d', $id)],
        ));
    }

    $response = $this->getJson('/api/employees?country=usa&per_page=2&page=2');

    $response->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('meta.current_page', 2)
            ->where('meta.per_page', 2)
            ->where('meta.total', 6)
            ->has('data.employees', 2)
            ->has('links.prev')
            ->has('links.next')
        );
});
