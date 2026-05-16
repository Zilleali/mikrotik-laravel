<?php

use ZillEAli\MikrotikLaravel\Events\RouterConnected;
use ZillEAli\MikrotikLaravel\Events\RouterUnreachable;
use ZillEAli\MikrotikLaravel\Events\SessionCreated;
use ZillEAli\MikrotikLaravel\Events\SessionDisconnected;

// ─── SessionCreated ───────────────────────────────────────────

it('creates SessionCreated event with correct properties', function () {
    $event = new SessionCreated(
        username: 'ali-home',
        ip: '10.0.0.45',
        router: 'main',
        service: 'pppoe',
        macAddress: null,
        raw: ['name' => 'ali-home', 'address' => '10.0.0.45'],
    );

    expect($event->username)->toBe('ali-home')
        ->and($event->ip)->toBe('10.0.0.45')
        ->and($event->router)->toBe('main')
        ->and($event->service)->toBe('pppoe')
        ->and($event->raw)->toHaveKey('name');
});

it('creates SessionCreated with default values', function () {
    $event = new SessionCreated(username: 'user1', ip: '10.0.0.1');

    expect($event->router)->toBe('default')
        ->and($event->service)->toBe('pppoe')
        ->and($event->macAddress)->toBeNull()
        ->and($event->raw)->toBeEmpty();
});

// ─── SessionDisconnected ──────────────────────────────────────

it('creates SessionDisconnected event with correct properties', function () {
    $event = new SessionDisconnected(
        username: 'ali-home',
        router: 'main',
        service: 'pppoe',
        ip: '10.0.0.45',
        uptime: '2h14m',
        reason: 'manual',
    );

    expect($event->username)->toBe('ali-home')
        ->and($event->uptime)->toBe('2h14m')
        ->and($event->reason)->toBe('manual')
        ->and($event->ip)->toBe('10.0.0.45');
});

it('creates SessionDisconnected with default values', function () {
    $event = new SessionDisconnected(username: 'user1');

    expect($event->router)->toBe('default')
        ->and($event->service)->toBe('pppoe')
        ->and($event->ip)->toBeNull()
        ->and($event->uptime)->toBeNull()
        ->and($event->reason)->toBe('manual');
});

// ─── RouterUnreachable ────────────────────────────────────────

it('creates RouterUnreachable event with correct properties', function () {
    $exception = new \RuntimeException('Connection refused');

    $event = new RouterUnreachable(
        host: '192.168.88.1',
        port: 8728,
        router: 'main',
        attempts: 3,
        error: 'Connection refused (111)',
        exception: $exception,
    );

    expect($event->host)->toBe('192.168.88.1')
        ->and($event->port)->toBe(8728)
        ->and($event->attempts)->toBe(3)
        ->and($event->error)->toBe('Connection refused (111)')
        ->and($event->exception)->toBeInstanceOf(\RuntimeException::class);
});

it('creates RouterUnreachable with default values', function () {
    $event = new RouterUnreachable(host: '192.168.88.1');

    expect($event->port)->toBe(8728)
        ->and($event->router)->toBe('default')
        ->and($event->attempts)->toBe(1)
        ->and($event->error)->toBe('')
        ->and($event->exception)->toBeNull();
});

// ─── RouterConnected ──────────────────────────────────────────

it('creates RouterConnected event with correct properties', function () {
    $event = new RouterConnected(
        host: '192.168.88.1',
        port: 8728,
        router: 'main',
    );

    expect($event->host)->toBe('192.168.88.1')
        ->and($event->port)->toBe(8728)
        ->and($event->router)->toBe('main');
});

it('creates RouterConnected with default values', function () {
    $event = new RouterConnected(host: '192.168.88.1');

    expect($event->port)->toBe(8728)
        ->and($event->router)->toBe('default');
});

// ─── Events are readonly ──────────────────────────────────────

it('SessionCreated properties are readonly', function () {
    $event = new SessionCreated(username: 'user1', ip: '10.0.0.1');

    $threw = false;
    try {
        // @phpstan-ignore-next-line
        $event->username = 'changed';
    } catch (\Error $e) {
        $threw = true;
    }

    expect($threw)->toBeTrue();
});

it('RouterUnreachable properties are readonly', function () {
    $event = new RouterUnreachable(host: '192.168.88.1');

    $threw = false;
    try {
        // @phpstan-ignore-next-line
        $event->host = 'changed';
    } catch (\Error $e) {
        $threw = true;
    }

    expect($threw)->toBeTrue();
});