<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\WirelessManager;

function makeWirelessClient(array $responses = []): RouterosClient
{
    return new class($responses) extends RouterosClient {
        public function __construct(private array $responses) {
            parent::__construct(host: '127.0.0.1');
        }
        public function query(string $command, array $params = [], array $queries = []): array
        {
            return $this->responses[$command] ?? [];
        }
        public function send(array $words): array { return []; }
        public function isConnected(): bool { return true; }
    };
}

// ─── getInterfaces ────────────────────────────────────────────

it('returns wireless interfaces', function () {
    $client = makeWirelessClient([
        '/interface/wireless/print' => [
            ['name' => 'wlan1', 'ssid' => 'ISP-2G', 'band' => '2ghz-b/g/n', 'disabled' => 'false'],
            ['name' => 'wlan2', 'ssid' => 'ISP-5G', 'band' => '5ghz-a/n/ac', 'disabled' => 'false'],
        ],
    ]);

    $manager    = new WirelessManager($client);
    $interfaces = $manager->getInterfaces();

    expect($interfaces)->toHaveCount(2)
        ->and($interfaces[0]['ssid'])->toBe('ISP-2G')
        ->and($interfaces[1]['band'])->toBe('5ghz-a/n/ac');
});

// ─── getRegistrationTable ─────────────────────────────────────

it('returns registration table (connected clients)', function () {
    $client = makeWirelessClient([
        '/interface/wireless/registration-table/print' => [
            ['mac-address' => 'AA:BB:CC:DD:EE:01', 'interface' => 'wlan1', 'signal-strength' => '-65', 'tx-rate' => '54Mbps'],
            ['mac-address' => 'AA:BB:CC:DD:EE:02', 'interface' => 'wlan1', 'signal-strength' => '-72', 'tx-rate' => '24Mbps'],
        ],
    ]);

    $manager = new WirelessManager($client);
    $clients = $manager->getRegistrationTable();

    expect($clients)->toHaveCount(2)
        ->and($clients[0]['mac-address'])->toBe('AA:BB:CC:DD:EE:01')
        ->and($clients[1]['signal-strength'])->toBe('-72');
});

it('returns empty registration table when no clients connected', function () {
    $client  = makeWirelessClient(['/interface/wireless/registration-table/print' => []]);
    $manager = new WirelessManager($client);

    expect($manager->getRegistrationTable())->toBeEmpty();
});

// ─── getRegistrationByInterface ───────────────────────────────

it('returns clients for specific interface', function () {
    $client = makeWirelessClient([
        '/interface/wireless/registration-table/print' => [
            ['mac-address' => 'AA:BB:CC:DD:EE:01', 'interface' => 'wlan1'],
            ['mac-address' => 'AA:BB:CC:DD:EE:02', 'interface' => 'wlan2'],
        ],
    ]);

    $manager = new WirelessManager($client);
    $clients = $manager->getRegistrationByInterface('wlan1');

    expect($clients)->toHaveCount(1)
        ->and($clients[0]['interface'])->toBe('wlan1');
});

// ─── getAccessList ────────────────────────────────────────────

it('returns access list entries', function () {
    $client = makeWirelessClient([
        '/interface/wireless/access-list/print' => [
            ['mac-address' => 'AA:BB:CC:DD:EE:01', 'interface' => 'wlan1', 'authentication' => 'true'],
            ['mac-address' => 'AA:BB:CC:DD:EE:02', 'interface' => 'any',   'authentication' => 'false'],
        ],
    ]);

    $manager = new WirelessManager($client);
    $list    = $manager->getAccessList();

    expect($list)->toHaveCount(2)
        ->and($list[0]['mac-address'])->toBe('AA:BB:CC:DD:EE:01');
});

// ─── addToAccessList ──────────────────────────────────────────

it('adds mac to access list without throwing', function () {
    $client  = makeWirelessClient();
    $manager = new WirelessManager($client);

    expect(fn () => $manager->addToAccessList('AA:BB:CC:DD:EE:FF', [
        'interface'      => 'wlan1',
        'authentication' => 'true',
        'comment'        => 'trusted device',
    ]))->not->toThrow(\Exception::class);
});

// ─── removeFromAccessList ─────────────────────────────────────

it('removes mac from access list without throwing', function () {
    $client = makeWirelessClient([
        '/interface/wireless/access-list/print' => [
            ['.id' => '*1', 'mac-address' => 'AA:BB:CC:DD:EE:FF'],
        ],
    ]);

    $manager = new WirelessManager($client);

    expect(fn () => $manager->removeFromAccessList('AA:BB:CC:DD:EE:FF'))
        ->not->toThrow(\Exception::class);
});

// ─── getConnectedClientsCount ─────────────────────────────────

it('returns correct count of connected wireless clients', function () {
    $client = makeWirelessClient([
        '/interface/wireless/registration-table/print' => [
            ['mac-address' => 'AA:BB:CC:DD:EE:01'],
            ['mac-address' => 'AA:BB:CC:DD:EE:02'],
            ['mac-address' => 'AA:BB:CC:DD:EE:03'],
        ],
    ]);

    $manager = new WirelessManager($client);

    expect($manager->getConnectedClientsCount())->toBe(3);
});