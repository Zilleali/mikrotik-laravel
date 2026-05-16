<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\DhcpManager;

function makeDhcpClient(array $responses = []): RouterosClient
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

// ─── getLeases ────────────────────────────────────────────────

it('returns all dhcp leases', function () {
    $client = makeDhcpClient([
        '/ip/dhcp-server/lease/print' => [
            ['address' => '192.168.1.10', 'mac-address' => 'AA:BB:CC:DD:EE:01', 'host-name' => 'pc1', 'status' => 'bound'],
            ['address' => '192.168.1.11', 'mac-address' => 'AA:BB:CC:DD:EE:02', 'host-name' => 'pc2', 'status' => 'bound'],
        ],
    ]);

    $manager = new DhcpManager($client);
    $leases = $manager->getLeases();

    expect($leases)->toHaveCount(2)
        ->and($leases[0]['address'])->toBe('192.168.1.10')
        ->and($leases[1]['host-name'])->toBe('pc2');
});

it('returns empty array when no leases', function () {
    $client = makeDhcpClient(['/ip/dhcp-server/lease/print' => []]);
    $manager = new DhcpManager($client);

    expect($manager->getLeases())->toBeEmpty();
});

// ─── getLeaseByMac ────────────────────────────────────────────

it('finds lease by mac address', function () {
    $client = makeDhcpClient([
        '/ip/dhcp-server/lease/print' => [
            ['address' => '192.168.1.10', 'mac-address' => 'AA:BB:CC:DD:EE:01'],
            ['address' => '192.168.1.11', 'mac-address' => 'AA:BB:CC:DD:EE:02'],
        ],
    ]);

    $manager = new DhcpManager($client);
    $lease = $manager->getLeaseByMac('AA:BB:CC:DD:EE:01');

    expect($lease)->not->toBeNull()
        ->and($lease['address'])->toBe('192.168.1.10');
});

it('returns null when mac not found', function () {
    $client = makeDhcpClient(['/ip/dhcp-server/lease/print' => []]);
    $manager = new DhcpManager($client);

    expect($manager->getLeaseByMac('FF:FF:FF:FF:FF:FF'))->toBeNull();
});

// ─── getLeaseByIp ─────────────────────────────────────────────

it('finds lease by ip address', function () {
    $client = makeDhcpClient([
        '/ip/dhcp-server/lease/print' => [
            ['address' => '192.168.1.10', 'mac-address' => 'AA:BB:CC:DD:EE:01', 'host-name' => 'pc1'],
        ],
    ]);

    $manager = new DhcpManager($client);
    $lease = $manager->getLeaseByIp('192.168.1.10');

    expect($lease)->not->toBeNull()
        ->and($lease['host-name'])->toBe('pc1');
});

// ─── getServers ───────────────────────────────────────────────

it('returns dhcp servers', function () {
    $client = makeDhcpClient([
        '/ip/dhcp-server/print' => [
            ['name' => 'dhcp1', 'interface' => 'ether2', 'address-pool' => 'pool1', 'disabled' => 'false'],
        ],
    ]);

    $manager = new DhcpManager($client);
    $servers = $manager->getServers();

    expect($servers)->toHaveCount(1)
        ->and($servers[0]['name'])->toBe('dhcp1')
        ->and($servers[0]['interface'])->toBe('ether2');
});

// ─── getActiveLeasesCount ─────────────────────────────────────

it('returns correct count of active leases', function () {
    $client = makeDhcpClient([
        '/ip/dhcp-server/lease/print' => [
            ['address' => '192.168.1.10', 'status' => 'bound'],
            ['address' => '192.168.1.11', 'status' => 'bound'],
            ['address' => '192.168.1.12', 'status' => 'waiting'],
        ],
    ]);

    $manager = new DhcpManager($client);

    expect($manager->getActiveLeasesCount())->toBe(2);
});

// ─── makeLeaseStatic ──────────────────────────────────────────

it('makes lease static without throwing', function () {
    $client = makeDhcpClient([
        '/ip/dhcp-server/lease/print' => [
            ['.id' => '*1', 'address' => '192.168.1.10', 'mac-address' => 'AA:BB:CC:DD:EE:01'],
        ],
    ]);

    $manager = new DhcpManager($client);

    expect(fn () => $manager->makeLeaseStatic('AA:BB:CC:DD:EE:01'))
        ->not->toThrow(\Exception::class);
});

// ─── deleteLease ──────────────────────────────────────────────

it('deletes lease without throwing', function () {
    $client = makeDhcpClient([
        '/ip/dhcp-server/lease/print' => [
            ['.id' => '*1', 'address' => '192.168.1.10', 'mac-address' => 'AA:BB:CC:DD:EE:01'],
        ],
    ]);

    $manager = new DhcpManager($client);

    expect(fn () => $manager->deleteLease('AA:BB:CC:DD:EE:01'))
        ->not->toThrow(\Exception::class);
});
