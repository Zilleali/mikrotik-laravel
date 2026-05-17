<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Connections\RouterosClientSSL;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;

// ─── Class exists ─────────────────────────────────────────────

it('RouterosClientSSL class exists', function () {
    expect(class_exists(RouterosClientSSL::class))->toBeTrue();
});

it('RouterosClientSSL extends RouterosClient', function () {
    expect(RouterosClientSSL::class)
        ->toExtend(RouterosClient::class);
});

// ─── Default port ─────────────────────────────────────────────

it('uses port 8729 by default', function () {
    $client = new RouterosClientSSL(host: '192.168.88.1');

    $reflection = new ReflectionProperty($client, 'port');
    $reflection->setAccessible(true);

    expect($reflection->getValue($client))->toBe(8729);
});

it('accepts custom port', function () {
    $client = new RouterosClientSSL(
        host: '192.168.88.1',
        port: 8730,
    );

    $reflection = new ReflectionProperty($client, 'port');
    $reflection->setAccessible(true);

    expect($reflection->getValue($client))->toBe(8730);
});

// ─── SSL context options ───────────────────────────────────────

it('has ssl context options set', function () {
    $client = new RouterosClientSSL(host: '192.168.88.1');

    $reflection = new ReflectionProperty($client, 'sslContext');
    $reflection->setAccessible(true);

    $context = $reflection->getValue($client);

    expect($context)->toBeArray()
        ->and($context)->toHaveKey('ssl');
});

it('allows self-signed certificates by default', function () {
    $client = new RouterosClientSSL(host: '192.168.88.1');

    $reflection = new ReflectionProperty($client, 'sslContext');
    $reflection->setAccessible(true);

    $context = $reflection->getValue($client);

    expect($context['ssl']['verify_peer'])->toBeFalse()
        ->and($context['ssl']['verify_peer_name'])->toBeFalse();
});

it('can enable certificate verification', function () {
    $client = new RouterosClientSSL(
        host:         '192.168.88.1',
        verifyPeer:   true,
        caCertPath:   '/etc/ssl/certs/ca.pem',
    );

    $reflection = new ReflectionProperty($client, 'sslContext');
    $reflection->setAccessible(true);

    $context = $reflection->getValue($client);

    expect($context['ssl']['verify_peer'])->toBeTrue()
        ->and($context['ssl']['cafile'])->toBe('/etc/ssl/certs/ca.pem');
});

// ─── Not connected by default ─────────────────────────────────

it('is not connected before connect() is called', function () {
    $client = new RouterosClientSSL(host: '192.168.88.1');

    expect($client->isConnected())->toBeFalse();
});

// ─── Connection failure ───────────────────────────────────────

it('throws ConnectionException on unreachable SSL host', function () {
    $client = new RouterosClientSSL(
        host:    '192.0.2.1',
        port:    8729,
        timeout: 1,
    );

    expect(fn () => $client->connect())
        ->toThrow(ConnectionException::class);
});

// ─── getConnectionInfo ────────────────────────────────────────

it('returns correct connection info', function () {
    $client = new RouterosClientSSL(
        host:     '192.168.88.1',
        port:     8729,
        username: 'admin',
    );

    $info = $client->getConnectionInfo();

    expect($info)->toHaveKey('host')
        ->and($info)->toHaveKey('port')
        ->and($info)->toHaveKey('ssl')
        ->and($info['ssl'])->toBeTrue()
        ->and($info['host'])->toBe('192.168.88.1')
        ->and($info['port'])->toBe(8729);
});

// ─── Config integration ───────────────────────────────────────

it('can be instantiated with all options', function () {
    $client = new RouterosClientSSL(
        host:         '192.168.88.1',
        port:         8729,
        username:     'admin',
        password:     'secret',
        timeout:      10,
        verifyPeer:   false,
        caCertPath:   null,
    );

    expect($client)->toBeInstanceOf(RouterosClientSSL::class)
        ->and($client->isConnected())->toBeFalse();
});
