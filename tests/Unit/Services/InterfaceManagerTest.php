<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\InterfaceManager;

function makeInterfaceClient(array $responses = []): RouterosClient
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

// ─── getInterfaces ────────────────────────────────────────────

it('returns all interfaces', function () {
    $client = makeInterfaceClient([
        '/interface/print' => [
            ['name' => 'ether1', 'type' => 'ether', 'running' => 'true',  'disabled' => 'false'],
            ['name' => 'ether2', 'type' => 'ether', 'running' => 'false', 'disabled' => 'false'],
            ['name' => 'wlan1',  'type' => 'wlan',  'running' => 'true',  'disabled' => 'false'],
        ],
    ]);

    $manager = new InterfaceManager($client);
    $interfaces = $manager->getInterfaces();

    expect($interfaces)->toHaveCount(3)
        ->and($interfaces[0]['name'])->toBe('ether1')
        ->and($interfaces[2]['type'])->toBe('wlan');
});

it('returns empty array when no interfaces', function () {
    $client = makeInterfaceClient(['/interface/print' => []]);
    $manager = new InterfaceManager($client);

    expect($manager->getInterfaces())->toBeEmpty();
});

// ─── getInterface ─────────────────────────────────────────────

it('returns single interface by name', function () {
    $client = makeInterfaceClient([
        '/interface/print' => [
            ['name' => 'ether1', 'type' => 'ether', 'running' => 'true'],
        ],
    ]);

    $manager = new InterfaceManager($client);
    $interface = $manager->getInterface('ether1');

    expect($interface)->not->toBeNull()
        ->and($interface['name'])->toBe('ether1')
        ->and($interface['running'])->toBe('true');
});

it('returns null when interface not found', function () {
    $client = makeInterfaceClient(['/interface/print' => []]);
    $manager = new InterfaceManager($client);

    expect($manager->getInterface('ether99'))->toBeNull();
});

// ─── getRunning / getDisabled ─────────────────────────────────

it('returns only running interfaces', function () {
    $client = makeInterfaceClient([
        '/interface/print' => [
            ['name' => 'ether1', 'running' => 'true',  'disabled' => 'false'],
            ['name' => 'ether2', 'running' => 'false', 'disabled' => 'false'],
            ['name' => 'ether3', 'running' => 'true',  'disabled' => 'false'],
        ],
    ]);

    $manager = new InterfaceManager($client);
    $running = $manager->getRunningInterfaces();

    expect($running)->toHaveCount(2)
        ->and($running[0]['name'])->toBe('ether1')
        ->and($running[1]['name'])->toBe('ether3');
});

it('returns only disabled interfaces', function () {
    $client = makeInterfaceClient([
        '/interface/print' => [
            ['name' => 'ether1', 'running' => 'true',  'disabled' => 'false'],
            ['name' => 'ether4', 'running' => 'false', 'disabled' => 'true'],
        ],
    ]);

    $manager = new InterfaceManager($client);
    $disabled = $manager->getDisabledInterfaces();

    expect($disabled)->toHaveCount(1)
        ->and($disabled[0]['name'])->toBe('ether4');
});

// ─── enable / disable ─────────────────────────────────────────

it('enables interface without throwing', function () {
    $client = makeInterfaceClient();
    $manager = new InterfaceManager($client);

    expect(fn () => $manager->enableInterface('ether1'))
        ->not->toThrow(\Exception::class);
});

it('disables interface without throwing', function () {
    $client = makeInterfaceClient();
    $manager = new InterfaceManager($client);

    expect(fn () => $manager->disableInterface('ether1'))
        ->not->toThrow(\Exception::class);
});

// ─── getTraffic ───────────────────────────────────────────────

it('returns traffic stats for interface', function () {
    $client = makeInterfaceClient([
        '/interface/monitor-traffic' => [
            ['name' => 'ether1', 'rx-bits-per-second' => '82000000', 'tx-bits-per-second' => '48000000'],
        ],
    ]);

    $manager = new InterfaceManager($client);
    $traffic = $manager->getTraffic('ether1');

    expect($traffic)->not->toBeEmpty()
        ->and($traffic['rx-bits-per-second'])->toBe('82000000')
        ->and($traffic['tx-bits-per-second'])->toBe('48000000');
});

it('returns empty array when traffic unavailable', function () {
    $client = makeInterfaceClient(['/interface/monitor-traffic' => []]);
    $manager = new InterfaceManager($client);

    expect($manager->getTraffic('ether1'))->toBeEmpty();
});

// ─── getVlans ─────────────────────────────────────────────────

it('returns vlan interfaces', function () {
    $client = makeInterfaceClient([
        '/interface/vlan/print' => [
            ['name' => 'vlan10', 'vlan-id' => '10', 'interface' => 'ether2'],
            ['name' => 'vlan20', 'vlan-id' => '20', 'interface' => 'ether2'],
        ],
    ]);

    $manager = new InterfaceManager($client);
    $vlans = $manager->getVlans();

    expect($vlans)->toHaveCount(2)
        ->and($vlans[0]['vlan-id'])->toBe('10');
});

// ─── getEthernetInterfaces ────────────────────────────────────

it('filters ethernet interfaces only', function () {
    $client = makeInterfaceClient([
        '/interface/print' => [
            ['name' => 'ether1', 'type' => 'ether'],
            ['name' => 'wlan1',  'type' => 'wlan'],
            ['name' => 'ether2', 'type' => 'ether'],
        ],
    ]);

    $manager = new InterfaceManager($client);
    $ethernet = $manager->getEthernetInterfaces();

    expect($ethernet)->toHaveCount(2)
        ->and($ethernet[0]['type'])->toBe('ether');
});
