<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\RadiusManager;

function makeRadiusClient(array $responses = []): RouterosClient
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

// ─── getServers ───────────────────────────────────────────────

it('returns all radius servers', function () {
    $client = makeRadiusClient([
        '/radius/print' => [
            ['address' => '172.16.24.17', 'service' => 'ppp,hotspot', 'secret' => 'testing123', 'disabled' => 'false'],
            ['address' => '10.0.0.5',     'service' => 'ppp',         'secret' => 'secret',      'disabled' => 'false'],
        ],
    ]);

    $manager = new RadiusManager($client);
    $servers = $manager->getServers();

    expect($servers)->toHaveCount(2)
        ->and($servers[0]['address'])->toBe('172.16.24.17')
        ->and($servers[1]['service'])->toBe('ppp');
});

it('returns empty array when no radius servers configured', function () {
    $client  = makeRadiusClient(['/radius/print' => []]);
    $manager = new RadiusManager($client);

    expect($manager->getServers())->toBeEmpty();
});

// ─── getServer ────────────────────────────────────────────────

it('returns single radius server by address', function () {
    $client = makeRadiusClient([
        '/radius/print' => [
            ['address' => '172.16.24.17', 'service' => 'ppp,hotspot'],
        ],
    ]);

    $manager = new RadiusManager($client);
    $server  = $manager->getServer('172.16.24.17');

    expect($server)->not->toBeNull()
        ->and($server['address'])->toBe('172.16.24.17');
});

it('returns null when radius server not found', function () {
    $client  = makeRadiusClient(['/radius/print' => []]);
    $manager = new RadiusManager($client);

    expect($manager->getServer('99.99.99.99'))->toBeNull();
});

// ─── addServer ────────────────────────────────────────────────

it('adds radius server without throwing', function () {
    $client  = makeRadiusClient();
    $manager = new RadiusManager($client);

    expect(fn () => $manager->addServer([
        'address' => '172.16.24.17',
        'secret'  => 'testing123',
        'service' => 'ppp,hotspot',
    ]))->not->toThrow(\Exception::class);
});

// ─── removeServer ─────────────────────────────────────────────

it('removes radius server without throwing', function () {
    $client = makeRadiusClient([
        '/radius/print' => [
            ['.id' => '*1', 'address' => '172.16.24.17'],
        ],
    ]);

    $manager = new RadiusManager($client);

    expect(fn () => $manager->removeServer('172.16.24.17'))
        ->not->toThrow(\Exception::class);
});

// ─── enableServer / disableServer ─────────────────────────────

it('enables radius server without throwing', function () {
    $client = makeRadiusClient([
        '/radius/print' => [
            ['.id' => '*1', 'address' => '172.16.24.17', 'disabled' => 'true'],
        ],
    ]);

    $manager = new RadiusManager($client);

    expect(fn () => $manager->enableServer('172.16.24.17'))
        ->not->toThrow(\Exception::class);
});

it('disables radius server without throwing', function () {
    $client = makeRadiusClient([
        '/radius/print' => [
            ['.id' => '*1', 'address' => '172.16.24.17', 'disabled' => 'false'],
        ],
    ]);

    $manager = new RadiusManager($client);

    expect(fn () => $manager->disableServer('172.16.24.17'))
        ->not->toThrow(\Exception::class);
});

// ─── getIncomingConfig ────────────────────────────────────────

it('returns radius incoming config', function () {
    $client = makeRadiusClient([
        '/radius/incoming/print' => [
            ['accept' => 'true', 'port' => '3799'],
        ],
    ]);

    $manager = new RadiusManager($client);
    $config  = $manager->getIncomingConfig();

    expect($config)->not->toBeEmpty()
        ->and($config['accept'])->toBe('true')
        ->and($config['port'])->toBe('3799');
});

// ─── isServerActive ───────────────────────────────────────────

it('returns true when radius server exists and is enabled', function () {
    $client = makeRadiusClient([
        '/radius/print' => [
            ['address' => '172.16.24.17', 'disabled' => 'false'],
        ],
    ]);

    $manager = new RadiusManager($client);

    expect($manager->isServerActive('172.16.24.17'))->toBeTrue();
});

it('returns false when radius server is disabled', function () {
    $client = makeRadiusClient([
        '/radius/print' => [
            ['address' => '172.16.24.17', 'disabled' => 'true'],
        ],
    ]);

    $manager = new RadiusManager($client);

    expect($manager->isServerActive('172.16.24.17'))->toBeFalse();
});

it('returns false when radius server does not exist', function () {
    $client  = makeRadiusClient(['/radius/print' => []]);
    $manager = new RadiusManager($client);

    expect($manager->isServerActive('99.99.99.99'))->toBeFalse();
});