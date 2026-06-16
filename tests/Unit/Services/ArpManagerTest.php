<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ResourceNotFoundException;
use ZillEAli\MikrotikLaravel\Services\ArpManager;

function makeArpClient(array $responses = []): RouterosClient
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

// ─── getArpTable ──────────────────────────────────────────────

it('returns full arp table', function () {
    $client = makeArpClient([
        '/ip/arp/print' => [
            ['address' => '192.168.1.10', 'mac-address' => 'AA:BB:CC:DD:EE:01', 'interface' => 'ether1', 'status' => 'reachable'],
            ['address' => '192.168.1.11', 'mac-address' => 'AA:BB:CC:DD:EE:02', 'interface' => 'ether1', 'status' => 'reachable'],
            ['address' => '10.0.0.1',     'mac-address' => 'AA:BB:CC:DD:EE:03', 'interface' => 'ether2', 'status' => 'stale'],
        ],
    ]);

    $manager = new ArpManager($client);
    $table = $manager->getArpTable();

    expect($table)->toHaveCount(3)
        ->and($table[0]['address'])->toBe('192.168.1.10')
        ->and($table[2]['status'])->toBe('stale');
});

it('returns empty array when arp table is empty', function () {
    $client = makeArpClient(['/ip/arp/print' => []]);
    $manager = new ArpManager($client);

    expect($manager->getArpTable())->toBeEmpty();
});

// ─── getArpByIp ───────────────────────────────────────────────

it('returns arp entry by ip address', function () {
    $client = makeArpClient([
        '/ip/arp/print' => [
            ['address' => '192.168.1.10', 'mac-address' => 'AA:BB:CC:DD:EE:01', 'interface' => 'ether1'],
        ],
    ]);

    $manager = new ArpManager($client);
    $entry = $manager->getArpByIp('192.168.1.10');

    expect($entry)->not->toBeNull()
        ->and($entry['mac-address'])->toBe('AA:BB:CC:DD:EE:01');
});

it('returns null when ip not found in arp table', function () {
    $client = makeArpClient(['/ip/arp/print' => []]);
    $manager = new ArpManager($client);

    expect($manager->getArpByIp('99.99.99.99'))->toBeNull();
});

// ─── getArpByMac ──────────────────────────────────────────────

it('returns arp entry by mac address', function () {
    $client = makeArpClient([
        '/ip/arp/print' => [
            ['address' => '192.168.1.10', 'mac-address' => 'AA:BB:CC:DD:EE:01', 'interface' => 'ether1'],
            ['address' => '192.168.1.11', 'mac-address' => 'AA:BB:CC:DD:EE:02', 'interface' => 'ether1'],
        ],
    ]);

    $manager = new ArpManager($client);
    $entry = $manager->getArpByMac('AA:BB:CC:DD:EE:01');

    expect($entry)->not->toBeNull()
        ->and($entry['address'])->toBe('192.168.1.10');
});

it('returns null when mac not found', function () {
    $client = makeArpClient(['/ip/arp/print' => []]);
    $manager = new ArpManager($client);

    expect($manager->getArpByMac('FF:FF:FF:FF:FF:FF'))->toBeNull();
});

// ─── getArpByInterface ────────────────────────────────────────

it('returns arp entries for specific interface', function () {
    $client = makeArpClient([
        '/ip/arp/print' => [
            ['address' => '192.168.1.10', 'mac-address' => 'AA:BB:CC:DD:EE:01', 'interface' => 'ether1'],
            ['address' => '192.168.1.11', 'mac-address' => 'AA:BB:CC:DD:EE:02', 'interface' => 'ether1'],
            ['address' => '10.0.0.1',     'mac-address' => 'AA:BB:CC:DD:EE:03', 'interface' => 'ether2'],
        ],
    ]);

    $manager = new ArpManager($client);
    $entries = $manager->getArpByInterface('ether1');

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['interface'])->toBe('ether1')
        ->and($entries[1]['interface'])->toBe('ether1');
});

// ─── addStaticArp ─────────────────────────────────────────────

it('adds static arp entry without throwing', function () {
    $client = makeArpClient();
    $manager = new ArpManager($client);

    expect(fn () => $manager->addStaticArp(
        '192.168.1.100',
        'AA:BB:CC:DD:EE:FF',
        'ether1'
    ))->not->toThrow(\Exception::class);
});

// ─── removeArp ────────────────────────────────────────────────

it('removes arp entry without throwing', function () {
    $client = makeArpClient([
        '/ip/arp/print' => [
            ['.id' => '*1', 'address' => '192.168.1.10', 'mac-address' => 'AA:BB:CC:DD:EE:01'],
        ],
    ]);

    $manager = new ArpManager($client);

    expect(fn () => $manager->removeArp('192.168.1.10'))
        ->not->toThrow(\Exception::class);
});

it('throws when removing non-existent arp entry', function () {
    $client = makeArpClient(['/ip/arp/print' => []]);
    $manager = new ArpManager($client);

    expect(fn () => $manager->removeArp('99.99.99.99'))
        ->toThrow(ResourceNotFoundException::class);
});

// ─── flushArpTable ────────────────────────────────────────────

it('flushes arp cache without throwing', function () {
    $client = makeArpClient();
    $manager = new ArpManager($client);

    expect(fn () => $manager->flushArpCache())
        ->not->toThrow(\Exception::class);
});

// ─── getStaticArpEntries ──────────────────────────────────────

it('returns only static arp entries', function () {
    $client = makeArpClient([
        '/ip/arp/print' => [
            ['address' => '192.168.1.10', 'mac-address' => 'AA:BB:CC:DD:EE:01', 'STATIC' => 'true'],
            ['address' => '192.168.1.11', 'mac-address' => 'AA:BB:CC:DD:EE:02', 'STATIC' => 'false'],
            ['address' => '192.168.1.12', 'mac-address' => 'AA:BB:CC:DD:EE:03', 'STATIC' => 'true'],
        ],
    ]);

    $manager = new ArpManager($client);
    $static = $manager->getStaticArpEntries();

    expect($static)->toHaveCount(2);
});

// ─── getMacByIp ───────────────────────────────────────────────

it('returns mac address for given ip', function () {
    $client = makeArpClient([
        '/ip/arp/print' => [
            ['address' => '192.168.1.10', 'mac-address' => 'AA:BB:CC:DD:EE:01'],
        ],
    ]);

    $manager = new ArpManager($client);

    expect($manager->getMacByIp('192.168.1.10'))->toBe('AA:BB:CC:DD:EE:01');
});

it('returns null when ip has no mac in arp table', function () {
    $client = makeArpClient(['/ip/arp/print' => []]);
    $manager = new ArpManager($client);

    expect($manager->getMacByIp('99.99.99.99'))->toBeNull();
});
