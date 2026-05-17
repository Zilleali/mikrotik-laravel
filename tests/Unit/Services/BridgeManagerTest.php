<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\BridgeManager;

function makeBridgeClient(array $responses = []): RouterosClient
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

// ─── getBridges ───────────────────────────────────────────────

it('returns all bridges', function () {
    $client = makeBridgeClient([
        '/interface/bridge/print' => [
            ['name' => 'bridge1', 'mtu' => '1500', 'disabled' => 'false', 'running' => 'true'],
            ['name' => 'bridge2', 'mtu' => '1500', 'disabled' => 'false', 'running' => 'true'],
        ],
    ]);

    $manager = new BridgeManager($client);
    $bridges = $manager->getBridges();

    expect($bridges)->toHaveCount(2)
        ->and($bridges[0]['name'])->toBe('bridge1')
        ->and($bridges[1]['name'])->toBe('bridge2');
});

it('returns empty array when no bridges exist', function () {
    $client = makeBridgeClient(['/interface/bridge/print' => []]);
    $manager = new BridgeManager($client);

    expect($manager->getBridges())->toBeEmpty();
});

// ─── getBridge ────────────────────────────────────────────────

it('returns single bridge by name', function () {
    $client = makeBridgeClient([
        '/interface/bridge/print' => [
            ['name' => 'bridge1', 'mtu' => '1500', 'running' => 'true'],
        ],
    ]);

    $manager = new BridgeManager($client);
    $bridge = $manager->getBridge('bridge1');

    expect($bridge)->not->toBeNull()
        ->and($bridge['name'])->toBe('bridge1');
});

it('returns null when bridge not found', function () {
    $client = makeBridgeClient(['/interface/bridge/print' => []]);
    $manager = new BridgeManager($client);

    expect($manager->getBridge('nonexistent'))->toBeNull();
});

// ─── getBridgePorts ───────────────────────────────────────────

it('returns all bridge ports', function () {
    $client = makeBridgeClient([
        '/interface/bridge/port/print' => [
            ['interface' => 'ether2', 'bridge' => 'bridge1', 'disabled' => 'false'],
            ['interface' => 'ether3', 'bridge' => 'bridge1', 'disabled' => 'false'],
            ['interface' => 'ether4', 'bridge' => 'bridge2', 'disabled' => 'false'],
        ],
    ]);

    $manager = new BridgeManager($client);
    $ports = $manager->getBridgePorts();

    expect($ports)->toHaveCount(3)
        ->and($ports[0]['interface'])->toBe('ether2');
});

// ─── getBridgePortsByBridge ───────────────────────────────────

it('returns ports for specific bridge', function () {
    $client = makeBridgeClient([
        '/interface/bridge/port/print' => [
            ['interface' => 'ether2', 'bridge' => 'bridge1'],
            ['interface' => 'ether3', 'bridge' => 'bridge1'],
            ['interface' => 'ether4', 'bridge' => 'bridge2'],
        ],
    ]);

    $manager = new BridgeManager($client);
    $ports = $manager->getBridgePortsByBridge('bridge1');

    expect($ports)->toHaveCount(2)
        ->and($ports[0]['bridge'])->toBe('bridge1')
        ->and($ports[1]['bridge'])->toBe('bridge1');
});

// ─── addBridge ────────────────────────────────────────────────

it('creates bridge without throwing', function () {
    $client = makeBridgeClient();
    $manager = new BridgeManager($client);

    expect(fn () => $manager->addBridge([
        'name' => 'bridge3',
        'comment' => 'test bridge',
    ]))->not->toThrow(\Exception::class);
});

// ─── removeBridge ─────────────────────────────────────────────

it('removes bridge without throwing', function () {
    $client = makeBridgeClient([
        '/interface/bridge/print' => [
            ['.id' => '*1', 'name' => 'bridge1'],
        ],
    ]);

    $manager = new BridgeManager($client);

    expect(fn () => $manager->removeBridge('bridge1'))
        ->not->toThrow(\Exception::class);
});

it('does not throw when removing non-existent bridge', function () {
    $client = makeBridgeClient(['/interface/bridge/print' => []]);
    $manager = new BridgeManager($client);

    expect(fn () => $manager->removeBridge('ghost'))
        ->not->toThrow(\Exception::class);
});

// ─── addBridgePort ────────────────────────────────────────────

it('adds bridge port without throwing', function () {
    $client = makeBridgeClient();
    $manager = new BridgeManager($client);

    expect(fn () => $manager->addBridgePort([
        'bridge' => 'bridge1',
        'interface' => 'ether5',
    ]))->not->toThrow(\Exception::class);
});

// ─── removeBridgePort ─────────────────────────────────────────

it('removes bridge port without throwing', function () {
    $client = makeBridgeClient([
        '/interface/bridge/port/print' => [
            ['.id' => '*1', 'interface' => 'ether2', 'bridge' => 'bridge1'],
        ],
    ]);

    $manager = new BridgeManager($client);

    expect(fn () => $manager->removeBridgePort('ether2'))
        ->not->toThrow(\Exception::class);
});

// ─── getBridgeHosts ───────────────────────────────────────────

it('returns bridge host table', function () {
    $client = makeBridgeClient([
        '/interface/bridge/host/print' => [
            ['mac-address' => 'AA:BB:CC:DD:EE:01', 'bridge' => 'bridge1', 'on-interface' => 'ether2'],
            ['mac-address' => 'AA:BB:CC:DD:EE:02', 'bridge' => 'bridge1', 'on-interface' => 'ether3'],
        ],
    ]);

    $manager = new BridgeManager($client);
    $hosts = $manager->getBridgeHosts();

    expect($hosts)->toHaveCount(2)
        ->and($hosts[0]['mac-address'])->toBe('AA:BB:CC:DD:EE:01');
});

// ─── getBridgeFilters ─────────────────────────────────────────

it('returns bridge filter rules', function () {
    $client = makeBridgeClient([
        '/interface/bridge/filter/print' => [
            ['chain' => 'forward', 'action' => 'drop', 'mac-protocol' => 'ip'],
        ],
    ]);

    $manager = new BridgeManager($client);
    $filters = $manager->getBridgeFilters();

    expect($filters)->toHaveCount(1)
        ->and($filters[0]['chain'])->toBe('forward')
        ->and($filters[0]['action'])->toBe('drop');
});

// ─── addBridgeFilter ──────────────────────────────────────────

it('adds bridge filter without throwing', function () {
    $client = makeBridgeClient();
    $manager = new BridgeManager($client);

    expect(fn () => $manager->addBridgeFilter([
        'chain' => 'forward',
        'action' => 'drop',
        'mac-protocol' => 'ip',
    ]))->not->toThrow(\Exception::class);
});

// ─── getPortCount ─────────────────────────────────────────────

it('returns correct port count for bridge', function () {
    $client = makeBridgeClient([
        '/interface/bridge/port/print' => [
            ['interface' => 'ether2', 'bridge' => 'bridge1'],
            ['interface' => 'ether3', 'bridge' => 'bridge1'],
            ['interface' => 'ether4', 'bridge' => 'bridge2'],
        ],
    ]);

    $manager = new BridgeManager($client);

    expect($manager->getPortCount('bridge1'))->toBe(2);
});
