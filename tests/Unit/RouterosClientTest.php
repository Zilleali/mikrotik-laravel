<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;

// ─── Connection Tests ─────────────────────────────────────────

it('is not connected before connect() is called', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    expect($client->isConnected())->toBeFalse();
});

it('throws ConnectionException on unreachable host', function () {
    $client = new RouterosClient(
        host:    '192.0.2.1', // RFC 5737 — guaranteed unreachable
        port:    8728,
        timeout: 1,
    );

    expect(fn () => $client->connect())
        ->toThrow(ConnectionException::class);
});

it('disconnects cleanly without error', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    // Should not throw even if never connected
    $client->disconnect();

    expect($client->isConnected())->toBeFalse();
});

// ─── Length Encoding Tests ────────────────────────────────────

it('encodes length < 0x80 as 1 byte', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    $method = new ReflectionMethod($client, 'encodeLength');
    $method->setAccessible(true);

    expect(strlen($method->invoke($client, 0)))->toBe(1)
        ->and(strlen($method->invoke($client, 50)))->toBe(1)
        ->and(strlen($method->invoke($client, 127)))->toBe(1);
});

it('encodes length 0x80–0x3FFF as 2 bytes', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    $method = new ReflectionMethod($client, 'encodeLength');
    $method->setAccessible(true);

    expect(strlen($method->invoke($client, 128)))->toBe(2)
        ->and(strlen($method->invoke($client, 200)))->toBe(2)
        ->and(strlen($method->invoke($client, 0x3FFF)))->toBe(2);
});

it('encodes length 0x4000–0x1FFFFF as 3 bytes', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    $method = new ReflectionMethod($client, 'encodeLength');
    $method->setAccessible(true);

    expect(strlen($method->invoke($client, 0x4000)))->toBe(3)
        ->and(strlen($method->invoke($client, 0x1FFFFF)))->toBe(3);
});

// ─── Query Builder Tests ──────────────────────────────────────

it('throws ConnectionException if query called without connect', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    expect(fn () => $client->query('/ip/address/print'))
        ->toThrow(ConnectionException::class, 'Not connected');
});

it('throws ConnectionException if send called without connect', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    expect(fn () => $client->send(['/system/identity/print']))
        ->toThrow(ConnectionException::class);
});