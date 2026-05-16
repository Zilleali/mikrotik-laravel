<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\HotspotManager;

// ─── Helper ───────────────────────────────────────────────────

function makeHotspotClient(array $responses = []): RouterosClient
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

// ─── getUsers ─────────────────────────────────────────────────

it('returns all hotspot users', function () {
    $client = makeHotspotClient([
        '/ip/hotspot/user/print' => [
            ['name' => 'user1', 'profile' => 'default', 'disabled' => 'false'],
            ['name' => 'user2', 'profile' => 'premium',  'disabled' => 'false'],
        ],
    ]);

    $manager = new HotspotManager($client);
    $users   = $manager->getUsers();

    expect($users)->toHaveCount(2)
        ->and($users[0]['name'])->toBe('user1')
        ->and($users[1]['profile'])->toBe('premium');
});

it('returns empty array when no hotspot users exist', function () {
    $client  = makeHotspotClient(['/ip/hotspot/user/print' => []]);
    $manager = new HotspotManager($client);

    expect($manager->getUsers())->toBeEmpty();
});

// ─── getUser ──────────────────────────────────────────────────

it('returns single hotspot user by name', function () {
    $client = makeHotspotClient([
        '/ip/hotspot/user/print' => [
            ['name' => 'user1', 'profile' => 'default'],
        ],
    ]);

    $manager = new HotspotManager($client);
    $user    = $manager->getUser('user1');

    expect($user)->not->toBeNull()
        ->and($user['name'])->toBe('user1');
});

it('returns null when hotspot user not found', function () {
    $client  = makeHotspotClient(['/ip/hotspot/user/print' => []]);
    $manager = new HotspotManager($client);

    expect($manager->getUser('nonexistent'))->toBeNull();
});

// ─── getActiveHosts ───────────────────────────────────────────

it('returns active hotspot hosts', function () {
    $client = makeHotspotClient([
        '/ip/hotspot/active/print' => [
            ['user' => 'user1', 'address' => '192.168.1.10', 'uptime' => '1h'],
            ['user' => 'user2', 'address' => '192.168.1.11', 'uptime' => '2h'],
        ],
    ]);

    $manager = new HotspotManager($client);
    $hosts   = $manager->getActiveHosts();

    expect($hosts)->toHaveCount(2)
        ->and($hosts[0]['user'])->toBe('user1')
        ->and($hosts[1]['address'])->toBe('192.168.1.11');
});

it('returns empty array when no active hosts', function () {
    $client  = makeHotspotClient(['/ip/hotspot/active/print' => []]);
    $manager = new HotspotManager($client);

    expect($manager->getActiveHosts())->toBeEmpty();
});

// ─── getProfiles ──────────────────────────────────────────────

it('returns hotspot profiles', function () {
    $client = makeHotspotClient([
        '/ip/hotspot/user/profile/print' => [
            ['name' => 'default', 'rate-limit' => '2M/2M'],
            ['name' => 'premium', 'rate-limit' => '10M/10M'],
        ],
    ]);

    $manager  = new HotspotManager($client);
    $profiles = $manager->getProfiles();

    expect($profiles)->toHaveCount(2)
        ->and($profiles[0]['name'])->toBe('default')
        ->and($profiles[1]['rate-limit'])->toBe('10M/10M');
});

// ─── createUser ───────────────────────────────────────────────

it('creates hotspot user without throwing', function () {
    $client  = makeHotspotClient();
    $manager = new HotspotManager($client);

    expect(fn () => $manager->createUser([
        'name'     => 'newuser',
        'password' => 'pass123',
        'profile'  => 'default',
    ]))->not->toThrow(\Exception::class);
});

// ─── deleteUser ───────────────────────────────────────────────

it('deletes hotspot user without throwing', function () {
    $client  = makeHotspotClient();
    $manager = new HotspotManager($client);

    expect(fn () => $manager->deleteUser('user1'))
        ->not->toThrow(\Exception::class);
});

// ─── kickHost ─────────────────────────────────────────────────

it('kicks active hotspot host without throwing', function () {
    $client  = makeHotspotClient();
    $manager = new HotspotManager($client);

    expect(fn () => $manager->kickHost('user1'))
        ->not->toThrow(\Exception::class);
});

// ─── generateVouchers ─────────────────────────────────────────

it('generates correct number of vouchers', function () {
    $client   = makeHotspotClient();
    $manager  = new HotspotManager($client);
    $vouchers = $manager->generateVouchers(5);

    expect($vouchers)->toHaveCount(5);
});

it('generates vouchers with unique names', function () {
    $client   = makeHotspotClient();
    $manager  = new HotspotManager($client);
    $vouchers = $manager->generateVouchers(10);

    $names = array_column($vouchers, 'name');
    expect(array_unique($names))->toHaveCount(10);
});

it('generates vouchers with correct prefix', function () {
    $client   = makeHotspotClient();
    $manager  = new HotspotManager($client);
    $vouchers = $manager->generateVouchers(3, prefix: 'VIP');

    foreach ($vouchers as $voucher) {
        expect($voucher['name'])->toStartWith('VIP');
    }
});

// ─── enableUser / disableUser ─────────────────────────────────

it('enables hotspot user without throwing', function () {
    $client  = makeHotspotClient();
    $manager = new HotspotManager($client);

    expect(fn () => $manager->enableUser('user1'))
        ->not->toThrow(\Exception::class);
});

it('disables hotspot user without throwing', function () {
    $client  = makeHotspotClient();
    $manager = new HotspotManager($client);

    expect(fn () => $manager->disableUser('user1'))
        ->not->toThrow(\Exception::class);
});