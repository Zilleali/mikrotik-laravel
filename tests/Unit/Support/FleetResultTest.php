<?php

use ZillEAli\MikrotikLaravel\Support\FleetResult;

function makeFleetResult(): FleetResult
{
    return new FleetResult(
        ['north' => 10, 'south' => 20],
        ['tower' => new RuntimeException('timeout')],
    );
}

it('separates successful results from failures', function () {
    $result = makeFleetResult();

    expect($result->successful())->toBe(['north' => 10, 'south' => 20])
        ->and($result->failedRouters())->toBe(['tower'])
        ->and($result->succeededRouters())->toBe(['north', 'south']);
});

it('get() returns the value or a default', function () {
    $result = makeFleetResult();

    expect($result->get('north'))->toBe(10)
        ->and($result->get('tower'))->toBeNull()
        ->and($result->get('tower', 0))->toBe(0);
});

it('get() returns a stored null instead of the default', function () {
    expect((new FleetResult(['north' => null]))->get('north', 'x'))->toBeNull();
});

it('error() returns the exception for a failed router', function () {
    expect(makeFleetResult()->error('tower')?->getMessage())->toBe('timeout')
        ->and(makeFleetResult()->error('north'))->toBeNull();
});

it('reports failure state', function () {
    expect(makeFleetResult()->hasFailures())->toBeTrue()
        ->and(makeFleetResult()->allSucceeded())->toBeFalse()
        ->and((new FleetResult(['a' => 1]))->allSucceeded())->toBeTrue()
        ->and(makeFleetResult()->succeeded('north'))->toBeTrue()
        ->and(makeFleetResult()->succeeded('tower'))->toBeFalse();
});

it('count() includes successes and failures', function () {
    expect(makeFleetResult())->toHaveCount(3);
});

it('map() transforms results and keeps errors', function () {
    $mapped = makeFleetResult()->map(fn (int $v, string $router) => "{$router}={$v}");

    expect($mapped->successful())->toBe(['north' => 'north=10', 'south' => 'south=20'])
        ->and($mapped->failedRouters())->toBe(['tower']);
});

it('toArray() serialises errors as messages', function () {
    expect(makeFleetResult()->toArray())->toBe([
        'results' => ['north' => 10, 'south' => 20],
        'errors' => ['tower' => 'timeout'],
    ]);
});

it('is iterable over successful results', function () {
    expect(iterator_to_array(makeFleetResult()))->toBe(['north' => 10, 'south' => 20]);
});
