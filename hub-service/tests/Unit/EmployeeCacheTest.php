<?php

use App\Domain\Employees\DTOs\EmployeeSnapshot;
use App\Infrastructure\Employees\CacheEmployeeCache;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

beforeEach(function () {
    $store = new ArrayStore;
    test()->cache = new CacheEmployeeCache(new Repository($store));
});

it('stores a snapshot when put is called', function () {
    $snapshot = EmployeeSnapshot::fromArray([
        'id' => 1,
        'name' => 'Alice',
        'last_name' => 'Smith',
        'salary' => 75000,
        'country' => 'USA',
        'attributes' => ['ssn' => '123-45-6789'],
    ]);

    test()->cache->put($snapshot, ttlSeconds: 60);

    $result = test()->cache->remember(1, fn () => null);

    expect($result->id)->toBe(1)
        ->and($result->country)->toBe('USA');
});

it('removes snapshot when forget is called', function () {
    $snapshot = EmployeeSnapshot::fromArray([
        'id' => 2,
        'name' => 'Bob',
        'last_name' => 'Jones',
        'salary' => 65000,
        'country' => 'USA',
        'attributes' => ['ssn' => '555-44-3333'],
    ]);

    test()->cache->put($snapshot, ttlSeconds: 60);
    test()->cache->forget(2);

    expect(test()->cache->allForCountry('USA'))->toBeEmpty();
});

it('returns all snapshots by country', function () {
    $usaSnapshot = EmployeeSnapshot::fromArray([
        'id' => 3,
        'name' => 'Carol',
        'last_name' => 'White',
        'salary' => 82000,
        'country' => 'USA',
        'attributes' => ['ssn' => '777-88-9999'],
    ]);

    $deSnapshot = EmployeeSnapshot::fromArray([
        'id' => 4,
        'name' => 'Dieter',
        'last_name' => 'Schmidt',
        'salary' => 70000,
        'country' => 'DEU',
        'attributes' => ['tax_id' => 'DE-12345'],
    ]);

    test()->cache->put($usaSnapshot, ttlSeconds: 60);
    test()->cache->put($deSnapshot, ttlSeconds: 60);

    $usaEmployees = test()->cache->allForCountry('USA');

    expect($usaEmployees)
        ->toHaveCount(1)
        ->and($usaEmployees[0]->name)->toBe('Carol');
});

it('hydrates snapshot via remember when cache miss occurs', function () {
    $snapshot = EmployeeSnapshot::fromArray([
        'id' => 5,
        'name' => 'Ethan',
        'last_name' => 'Wright',
        'salary' => 90000,
        'country' => 'USA',
        'attributes' => ['ssn' => '901-23-4567'],
    ]);

    $resolved = test()->cache->remember(5, fn () => $snapshot);

    expect($resolved->id)->toBe(5);
});
