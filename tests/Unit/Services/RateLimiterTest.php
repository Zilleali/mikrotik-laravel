<?php

use ZillEAli\MikrotikLaravel\Support\RateLimiter;

// ─── Basic instantiation ──────────────────────────────────────

it('RateLimiter class exists', function () {
    expect(class_exists(RateLimiter::class))->toBeTrue();
});

it('creates RateLimiter with default limits', function () {
    $limiter = new RateLimiter();

    expect($limiter->getMaxCallsPerSecond())->toBe(10)
        ->and($limiter->getMaxCallsPerMinute())->toBe(100);
});

it('accepts custom limits', function () {
    $limiter = new RateLimiter(
        maxCallsPerSecond: 5,
        maxCallsPerMinute: 50
    );

    expect($limiter->getMaxCallsPerSecond())->toBe(5)
        ->and($limiter->getMaxCallsPerMinute())->toBe(50);
});

// ─── isAllowed ────────────────────────────────────────────────

it('allows calls within limit', function () {
    $limiter = new RateLimiter(
        maxCallsPerSecond: 10,
        maxCallsPerMinute: 100
    );

    expect($limiter->isAllowed())->toBeTrue();
});

it('tracks call count correctly', function () {
    $limiter = new RateLimiter(
        maxCallsPerSecond: 10,
        maxCallsPerMinute: 100
    );

    $limiter->recordCall();
    $limiter->recordCall();
    $limiter->recordCall();

    expect($limiter->getCallCount())->toBe(3);
});

// ─── throttle ─────────────────────────────────────────────────

it('blocks when per-second limit exceeded', function () {
    $limiter = new RateLimiter(
        maxCallsPerSecond: 3,
        maxCallsPerMinute: 100
    );

    $limiter->recordCall();
    $limiter->recordCall();
    $limiter->recordCall();

    expect($limiter->isAllowedPerSecond())->toBeFalse();
});

it('allows when under per-second limit', function () {
    $limiter = new RateLimiter(
        maxCallsPerSecond: 10,
        maxCallsPerMinute: 100
    );

    $limiter->recordCall();
    $limiter->recordCall();

    expect($limiter->isAllowedPerSecond())->toBeTrue();
});

it('blocks when per-minute limit exceeded', function () {
    $limiter = new RateLimiter(
        maxCallsPerSecond: 1000,
        maxCallsPerMinute: 3
    );

    $limiter->recordCall();
    $limiter->recordCall();
    $limiter->recordCall();

    expect($limiter->isAllowedPerMinute())->toBeFalse();
});

it('allows when under per-minute limit', function () {
    $limiter = new RateLimiter(
        maxCallsPerSecond: 100,
        maxCallsPerMinute: 100
    );

    $limiter->recordCall();

    expect($limiter->isAllowedPerMinute())->toBeTrue();
});

// ─── reset ────────────────────────────────────────────────────

it('resets call count', function () {
    $limiter = new RateLimiter();

    $limiter->recordCall();
    $limiter->recordCall();
    $limiter->reset();

    expect($limiter->getCallCount())->toBe(0);
});

// ─── getStats ─────────────────────────────────────────────────

it('returns stats array', function () {
    $limiter = new RateLimiter(
        maxCallsPerSecond: 10,
        maxCallsPerMinute: 100
    );

    $limiter->recordCall();
    $limiter->recordCall();

    $stats = $limiter->getStats();

    expect($stats)->toHaveKey('total_calls')
        ->and($stats)->toHaveKey('calls_this_second')
        ->and($stats)->toHaveKey('calls_this_minute')
        ->and($stats)->toHaveKey('max_per_second')
        ->and($stats)->toHaveKey('max_per_minute')
        ->and($stats['total_calls'])->toBe(2)
        ->and($stats['max_per_second'])->toBe(10)
        ->and($stats['max_per_minute'])->toBe(100);
});

// ─── getRemainingCalls ────────────────────────────────────────

it('returns remaining calls per second', function () {
    $limiter = new RateLimiter(
        maxCallsPerSecond: 10,
        maxCallsPerMinute: 100
    );

    $limiter->recordCall();
    $limiter->recordCall();
    $limiter->recordCall();

    expect($limiter->getRemainingPerSecond())->toBe(7);
});

it('returns remaining calls per minute', function () {
    $limiter = new RateLimiter(
        maxCallsPerSecond: 100,
        maxCallsPerMinute: 50
    );

    $limiter->recordCall();
    $limiter->recordCall();

    expect($limiter->getRemainingPerMinute())->toBe(48);
});

// ─── isThrottled ──────────────────────────────────────────────

it('returns false when not throttled', function () {
    $limiter = new RateLimiter(
        maxCallsPerSecond: 10,
        maxCallsPerMinute: 100
    );

    expect($limiter->isThrottled())->toBeFalse();
});

it('returns true when per-second throttled', function () {
    $limiter = new RateLimiter(
        maxCallsPerSecond: 2,
        maxCallsPerMinute: 100
    );

    $limiter->recordCall();
    $limiter->recordCall();

    expect($limiter->isThrottled())->toBeTrue();
});
