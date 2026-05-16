<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\PppoeManager;
use ZillEAli\MikrotikLaravel\Support\CachingProxy;

// ─── Trackable client class ───────────────────────────────────

class TrackableClient extends RouterosClient
{
    public int $queryCount = 0;
    private array $responses;

    public function __construct(array $responses = [])
    {
        parent::__construct(host: '127.0.0.1');
        $this->responses = $responses;
    }

    public function query(string $command, array $params = [], array $queries = []): array
    {
        $this->queryCount++;

        return $this->responses[$command] ?? [];
    }

    public function send(array $words): array
    {
        return [];
    }
    public function isConnected(): bool
    {
        return true;
    }
}

// ─── CachingProxy wraps manager ───────────────────────────────

it('returns same result as underlying manager', function () {
    $client = new TrackableClient([
        '/ppp/secret/print' => [['name' => 'ali-home', 'service' => 'pppoe']],
    ]);

    $manager = new CachingProxy(new PppoeManager($client), ttl: 30);
    $secrets = $manager->getSecrets();

    expect($secrets)->toHaveCount(1)
        ->and($secrets[0]['name'])->toBe('ali-home');
});

it('does not call router again on cache hit', function () {
    $client = new TrackableClient([
        '/ppp/secret/print' => [['name' => 'ali-home']],
    ]);

    $manager = new CachingProxy(new PppoeManager($client), ttl: 30);

    $manager->getSecrets();
    $manager->getSecrets();
    $manager->getSecrets();

    expect($client->queryCount)->toBe(1);
});

it('calls router again after ttl expires', function () {
    $client = new TrackableClient([
        '/ppp/secret/print' => [['name' => 'ali-home']],
    ]);

    $manager = new CachingProxy(new PppoeManager($client), ttl: 1);

    $manager->getSecrets();
    sleep(2);
    $manager->getSecrets();

    expect($client->queryCount)->toBe(2);
});

it('calls router again after flush', function () {
    $client = new TrackableClient([
        '/ppp/secret/print' => [['name' => 'ali-home']],
    ]);

    $manager = new CachingProxy(new PppoeManager($client), ttl: 60);

    $manager->getSecrets();
    $manager->flush();
    $manager->getSecrets();

    expect($client->queryCount)->toBe(2);
});

it('does not cache createSecret calls', function () {
    $client = new TrackableClient();
    $manager = new CachingProxy(new PppoeManager($client), ttl: 60);

    $manager->createSecret(['name' => 'user1', 'password' => 'pass']);
    $manager->createSecret(['name' => 'user2', 'password' => 'pass']);

    expect($client->queryCount)->toBe(2);
});

it('invalidates cache after write operation', function () {
    $client = new TrackableClient([
        '/ppp/secret/print' => [['name' => 'ali-home']],
    ]);

    $manager = new CachingProxy(new PppoeManager($client), ttl: 60);

    $manager->getSecrets();
    $manager->createSecret(['name' => 'new', 'password' => 'x']);
    $manager->getSecrets();

    expect($client->queryCount)->toBe(3);
});

it('caches getSecrets and getActiveSessions separately', function () {
    $client = new TrackableClient([
        '/ppp/secret/print' => [['name' => 'ali-home']],
        '/ppp/active/print' => [['name' => 'ali-home', 'address' => '10.0.0.1']],
    ]);

    $manager = new CachingProxy(new PppoeManager($client), ttl: 60);

    $manager->getSecrets();
    $manager->getActiveSessions();
    $manager->getSecrets();
    $manager->getActiveSessions();

    expect($client->queryCount)->toBe(2);
});
