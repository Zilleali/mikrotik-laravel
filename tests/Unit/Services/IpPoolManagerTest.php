<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\IpPoolManager;

function makeIpPoolClient(array $responses = []): RouterosClient
{
    return new class ($responses) extends RouterosClient {
        public function __construct(private array $responses)
        {
            parent::__construct(host: '127.0.0.1');
        }
        public function query(string $command, array $params = [], array $queries = []): array
        {
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
    };
}

// ─── getPools ─────────────────────────────────────────────────

it('returns all ip pools', function () {
    $client = makeIpPoolClient([
        '/ip/pool/print' => [
            ['name' => 'pppoe-pool', 'ranges' => '10.0.0.1-10.0.0.254'],
            ['name' => 'dhcp-pool',  'ranges' => '192.168.1.10-192.168.1.254'],
        ],
    ]);

    $manager = new IpPoolManager($client);
    $pools = $manager->getPools();

    expect($pools)->toHaveCount(2)
        ->and($pools[0]['name'])->toBe('pppoe-pool')
        ->and($pools[1]['ranges'])->toBe('192.168.1.10-192.168.1.254');
});

it('returns empty array when no pools exist', function () {
    $client = makeIpPoolClient(['/ip/pool/print' => []]);
    $manager = new IpPoolManager($client);

    expect($manager->getPools())->toBeEmpty();
});

// ─── getPool ──────────────────────────────────────────────────

it('returns single pool by name', function () {
    $client = makeIpPoolClient([
        '/ip/pool/print' => [
            ['name' => 'pppoe-pool', 'ranges' => '10.0.0.1-10.0.0.254'],
        ],
    ]);

    $manager = new IpPoolManager($client);
    $pool = $manager->getPool('pppoe-pool');

    expect($pool)->not->toBeNull()
        ->and($pool['name'])->toBe('pppoe-pool');
});

it('returns null when pool not found', function () {
    $client = makeIpPoolClient(['/ip/pool/print' => []]);
    $manager = new IpPoolManager($client);

    expect($manager->getPool('nonexistent'))->toBeNull();
});

// ─── getUsedAddresses ─────────────────────────────────────────

it('returns used ip addresses from pool', function () {
    $client = makeIpPoolClient([
        '/ip/pool/used/print' => [
            ['pool' => 'pppoe-pool', 'address' => '10.0.0.1', 'info' => 'ali-home'],
            ['pool' => 'pppoe-pool', 'address' => '10.0.0.2', 'info' => 'zain-fiber'],
        ],
    ]);

    $manager = new IpPoolManager($client);
    $used = $manager->getUsedAddresses('pppoe-pool');

    expect($used)->toHaveCount(2)
        ->and($used[0]['address'])->toBe('10.0.0.1')
        ->and($used[1]['info'])->toBe('zain-fiber');
});

it('returns empty array when pool has no used addresses', function () {
    $client = makeIpPoolClient(['/ip/pool/used/print' => []]);
    $manager = new IpPoolManager($client);

    expect($manager->getUsedAddresses('pppoe-pool'))->toBeEmpty();
});

// ─── createPool ───────────────────────────────────────────────

it('creates ip pool without throwing', function () {
    $client = makeIpPoolClient();
    $manager = new IpPoolManager($client);

    expect(fn () => $manager->createPool([
        'name' => 'new-pool',
        'ranges' => '10.1.0.1-10.1.0.254',
    ]))->not->toThrow(\Exception::class);
});

// ─── deletePool ───────────────────────────────────────────────

it('deletes pool without throwing', function () {
    $client = makeIpPoolClient([
        '/ip/pool/print' => [
            ['.id' => '*1', 'name' => 'old-pool', 'ranges' => '10.2.0.1-10.2.0.254'],
        ],
    ]);

    $manager = new IpPoolManager($client);

    expect(fn () => $manager->deletePool('old-pool'))
        ->not->toThrow(\Exception::class);
});

// ─── getUsedCount ─────────────────────────────────────────────

it('returns correct used address count', function () {
    $client = makeIpPoolClient([
        '/ip/pool/used/print' => [
            ['pool' => 'pppoe-pool', 'address' => '10.0.0.1'],
            ['pool' => 'pppoe-pool', 'address' => '10.0.0.2'],
            ['pool' => 'pppoe-pool', 'address' => '10.0.0.3'],
        ],
    ]);

    $manager = new IpPoolManager($client);

    expect($manager->getUsedCount('pppoe-pool'))->toBe(3);
});
