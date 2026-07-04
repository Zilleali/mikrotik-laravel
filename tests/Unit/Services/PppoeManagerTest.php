<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ResourceNotFoundException;
use ZillEAli\MikrotikLaravel\Services\PppoeManager;

// ─── Helper — mock client ─────────────────────────────────────

function mockClient(array $responses = [], array $streams = []): RouterosClient
{
    return new class ($responses, $streams) extends RouterosClient {
        public function __construct(private array $responses, private array $streams)
        {
            parent::__construct(host: '127.0.0.1');
        }

        public function query(string $command, array $params = [], array $queries = []): array
        {
            return $this->responses[$command] ?? [];
        }

        public function queryStream(string $command, array $params = [], array $queries = []): \Generator
        {
            foreach ($this->streams[$command] ?? [] as $row) {
                yield $row;
            }
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

// ─── getSecrets ───────────────────────────────────────────────

it('returns all pppoe secrets', function () {
    $client = mockClient([
        '/ppp/secret/print' => [
            ['name' => 'ali-home',   'service' => 'pppoe', 'disabled' => 'false'],
            ['name' => 'zain-fiber', 'service' => 'pppoe', 'disabled' => 'false'],
        ],
    ]);

    $manager = new PppoeManager($client);
    $secrets = $manager->getSecrets();

    expect($secrets)->toHaveCount(2)
        ->and($secrets[0]['name'])->toBe('ali-home')
        ->and($secrets[1]['name'])->toBe('zain-fiber');
});

it('returns empty array when no secrets exist', function () {
    $client = mockClient(['/ppp/secret/print' => []]);
    $manager = new PppoeManager($client);

    expect($manager->getSecrets())->toBeEmpty();
});

// ─── getSecret ────────────────────────────────────────────────

it('returns single secret by name', function () {
    $client = mockClient([
        '/ppp/secret/print' => [
            ['name' => 'ali-home', 'service' => 'pppoe', 'profile' => '10mbps'],
        ],
    ]);

    $manager = new PppoeManager($client);
    $secret = $manager->getSecret('ali-home');

    expect($secret)->not->toBeNull()
        ->and($secret['name'])->toBe('ali-home')
        ->and($secret['profile'])->toBe('10mbps');
});

it('returns null when secret not found', function () {
    $client = mockClient(['/ppp/secret/print' => []]);
    $manager = new PppoeManager($client);

    expect($manager->getSecret('nonexistent'))->toBeNull();
});

// ─── getActiveSessions ────────────────────────────────────────

it('returns active pppoe sessions', function () {
    $client = mockClient([
        '/ppp/active/print' => [
            ['name' => 'ali-home', 'address' => '10.0.0.45', 'uptime' => '2h14m'],
            ['name' => 'zain-fiber', 'address' => '10.0.0.82', 'uptime' => '6h3m'],
        ],
    ]);

    $manager = new PppoeManager($client);
    $sessions = $manager->getActiveSessions();

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]['address'])->toBe('10.0.0.45');
});

// ─── getSecretByIp ────────────────────────────────────────────

it('finds active session by ip address', function () {
    $client = mockClient([
        '/ppp/active/print' => [
            ['name' => 'ali-home',   'address' => '10.0.0.45'],
            ['name' => 'zain-fiber', 'address' => '10.0.0.82'],
        ],
    ]);

    $manager = new PppoeManager($client);
    $session = $manager->getSecretByIp('10.0.0.45');

    expect($session)->not->toBeNull()
        ->and($session['name'])->toBe('ali-home');
});

it('returns null when ip not found', function () {
    $client = mockClient(['/ppp/active/print' => []]);
    $manager = new PppoeManager($client);

    expect($manager->getSecretByIp('10.0.0.99'))->toBeNull();
});

// ─── getProfiles ──────────────────────────────────────────────

it('returns pppoe profiles', function () {
    $client = mockClient([
        '/ppp/profile/print' => [
            ['name' => '10mbps', 'rate-limit' => '10M/10M'],
            ['name' => '20mbps', 'rate-limit' => '20M/20M'],
        ],
    ]);

    $manager = new PppoeManager($client);
    $profiles = $manager->getProfiles();

    expect($profiles)->toHaveCount(2)
        ->and($profiles[0]['name'])->toBe('10mbps');
});

// ─── enableSecret / disableSecret ─────────────────────────────

it('throws when enabling non-existent pppoe secret', function () {
    $client = mockClient();
    $manager = new PppoeManager($client);

    expect(fn () => $manager->enableSecret('ali-home'))->toThrow(ResourceNotFoundException::class);
});

it('throws when disabling non-existent pppoe secret', function () {
    $client = mockClient();
    $manager = new PppoeManager($client);

    expect(fn () => $manager->disableSecret('ali-home'))->toThrow(ResourceNotFoundException::class);
});

// ─── bulkEnable / bulkDisable ─────────────────────────────────

it('throws when bulk enabling non-existent secrets', function () {
    $client = mockClient();
    $manager = new PppoeManager($client);

    expect(fn () => $manager->bulkEnable(['ali-home', 'zain-fiber']))
        ->toThrow(ResourceNotFoundException::class);
});

it('throws when bulk disabling non-existent secrets', function () {
    $client = mockClient();
    $manager = new PppoeManager($client);

    expect(fn () => $manager->bulkDisable(['ali-home', 'zain-fiber']))
        ->toThrow(ResourceNotFoundException::class);
});

// ─── deleteSecret ─────────────────────────────────────────────

it('throws when deleting non-existent pppoe secret', function () {
    $client = mockClient();
    $manager = new PppoeManager($client);

    expect(fn () => $manager->deleteSecret('ali-home'))
        ->toThrow(ResourceNotFoundException::class);
});

// ─── kickSession ──────────────────────────────────────────────

it('throws when kicking non-existent pppoe session', function () {
    $client = mockClient();
    $manager = new PppoeManager($client);

    expect(fn () => $manager->kickSession('ali-home'))
        ->toThrow(ResourceNotFoundException::class);
});

// ─── bulkKick ─────────────────────────────────────────────────

it('throws when bulk kicking non-existent sessions', function () {
    $client = mockClient();
    $manager = new PppoeManager($client);

    expect(fn () => $manager->bulkKick(['ali-home', 'zain-fiber']))
        ->toThrow(ResourceNotFoundException::class);
});

// ─── Validation ───────────────────────────────────────────────

it('createSecret throws on missing required key', function () {
    $client = mockClient();
    $manager = new PppoeManager($client);

    expect(fn () => $manager->createSecret(['name' => 'ali']))
        ->toThrow(\ZillEAli\MikrotikLaravel\Exceptions\ValidationException::class);
});

it('deleteSecret throws on empty name', function () {
    $client = mockClient();
    $manager = new PppoeManager($client);

    expect(fn () => $manager->deleteSecret(''))
        ->toThrow(\ZillEAli\MikrotikLaravel\Exceptions\ValidationException::class);
});

// ─── Streaming ────────────────────────────────────────────────

it('streamSecrets returns a generator', function () {
    $client = mockClient(streams: [
        '/ppp/secret/print' => [
            ['name' => 'user1', 'password' => 'pass1'],
            ['name' => 'user2', 'password' => 'pass2'],
        ],
    ]);
    $manager = new PppoeManager($client);
    $gen = $manager->streamSecrets();

    expect($gen)->toBeInstanceOf(\Generator::class);

    $rows = iterator_to_array($gen);
    expect($rows)->toHaveCount(2)
        ->and($rows[0]['name'])->toBe('user1')
        ->and($rows[1]['name'])->toBe('user2');
});

it('streamSessions returns a generator', function () {
    $client = mockClient(streams: [
        '/ppp/active/print' => [
            ['name' => 'ali-home', 'address' => '10.0.0.1'],
        ],
    ]);
    $manager = new PppoeManager($client);
    $rows = iterator_to_array($manager->streamSessions());

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('ali-home');
});

it('streamSecrets yields nothing when no secrets exist', function () {
    $client = mockClient(streams: ['/ppp/secret/print' => []]);
    $manager = new PppoeManager($client);

    expect(iterator_to_array($manager->streamSecrets()))->toBeEmpty();
});
