<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\VpnManager;

function makeVpnClient(array $responses = []): RouterosClient
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

// ─── WireGuard Interfaces ─────────────────────────────────────

it('returns wireguard interfaces', function () {
    $client = makeVpnClient([
        '/interface/wireguard/print' => [
            ['name' => 'wg0', 'listen-port' => '13231', 'disabled' => 'false'],
            ['name' => 'wg1', 'listen-port' => '13232', 'disabled' => 'false'],
        ],
    ]);

    $manager    = new VpnManager($client);
    $interfaces = $manager->getWireGuardInterfaces();

    expect($interfaces)->toHaveCount(2)
        ->and($interfaces[0]['name'])->toBe('wg0')
        ->and($interfaces[1]['listen-port'])->toBe('13232');
});

// ─── WireGuard Peers ──────────────────────────────────────────

it('returns wireguard peers', function () {
    $client = makeVpnClient([
        '/interface/wireguard/peers/print' => [
            [
                'interface'  => 'wg0',
                'public-key' => 'abc123==',
                'endpoint-address' => '1.2.3.4',
                'endpoint-port'    => '13231',
                'allowed-address'  => '10.8.0.2/32',
            ],
        ],
    ]);

    $manager = new VpnManager($client);
    $peers   = $manager->getWireGuardPeers();

    expect($peers)->toHaveCount(1)
        ->and($peers[0]['public-key'])->toBe('abc123==')
        ->and($peers[0]['allowed-address'])->toBe('10.8.0.2/32');
});

it('returns empty array when no wireguard peers', function () {
    $client  = makeVpnClient(['/interface/wireguard/peers/print' => []]);
    $manager = new VpnManager($client);

    expect($manager->getWireGuardPeers())->toBeEmpty();
});

// ─── getWireGuardPeersByInterface ─────────────────────────────

it('returns peers for specific wireguard interface', function () {
    $client = makeVpnClient([
        '/interface/wireguard/peers/print' => [
            ['interface' => 'wg0', 'public-key' => 'key1==', 'allowed-address' => '10.8.0.2/32'],
            ['interface' => 'wg1', 'public-key' => 'key2==', 'allowed-address' => '10.9.0.2/32'],
        ],
    ]);

    $manager = new VpnManager($client);
    $peers   = $manager->getWireGuardPeersByInterface('wg0');

    expect($peers)->toHaveCount(1)
        ->and($peers[0]['interface'])->toBe('wg0');
});

// ─── addWireGuardPeer ─────────────────────────────────────────

it('adds wireguard peer without throwing', function () {
    $client  = makeVpnClient();
    $manager = new VpnManager($client);

    expect(fn () => $manager->addWireGuardPeer([
        'interface'       => 'wg0',
        'public-key'      => 'newpeerkey==',
        'allowed-address' => '10.8.0.3/32',
        'comment'         => 'branch office',
    ]))->not->toThrow(\Exception::class);
});

// ─── removeWireGuardPeer ──────────────────────────────────────

it('removes wireguard peer without throwing', function () {
    $client = makeVpnClient([
        '/interface/wireguard/peers/print' => [
            ['.id' => '*1', 'public-key' => 'abc123==', 'interface' => 'wg0'],
        ],
    ]);

    $manager = new VpnManager($client);

    expect(fn () => $manager->removeWireGuardPeer('abc123=='))
        ->not->toThrow(\Exception::class);
});

// ─── L2TP Sessions ────────────────────────────────────────────

it('returns active l2tp sessions', function () {
    $client = makeVpnClient([
        '/interface/l2tp-server/session/print' => [
            ['name' => 'l2tp-branch1', 'user' => 'branch1', 'address' => '10.0.0.2', 'uptime' => '5h'],
            ['name' => 'l2tp-branch2', 'user' => 'branch2', 'address' => '10.0.0.3', 'uptime' => '2h'],
        ],
    ]);

    $manager  = new VpnManager($client);
    $sessions = $manager->getL2tpSessions();

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]['user'])->toBe('branch1')
        ->and($sessions[1]['address'])->toBe('10.0.0.3');
});

it('returns empty array when no l2tp sessions', function () {
    $client  = makeVpnClient(['/interface/l2tp-server/session/print' => []]);
    $manager = new VpnManager($client);

    expect($manager->getL2tpSessions())->toBeEmpty();
});

// ─── PPTP Sessions ────────────────────────────────────────────

it('returns active pptp sessions', function () {
    $client = makeVpnClient([
        '/interface/pptp-server/session/print' => [
            ['name' => 'pptp-user1', 'user' => 'remote1', 'address' => '10.0.1.2', 'uptime' => '1h30m'],
        ],
    ]);

    $manager  = new VpnManager($client);
    $sessions = $manager->getPptpSessions();

    expect($sessions)->toHaveCount(1)
        ->and($sessions[0]['user'])->toBe('remote1');
});

// ─── getL2tpSecrets ───────────────────────────────────────────

it('returns l2tp server secrets', function () {
    $client = makeVpnClient([
        '/ppp/secret/print' => [
            ['name' => 'branch1', 'service' => 'l2tp', 'profile' => 'default'],
            ['name' => 'branch2', 'service' => 'l2tp', 'profile' => 'default'],
            ['name' => 'pppoe-user', 'service' => 'pppoe', 'profile' => '10mbps'],
        ],
    ]);

    $manager = new VpnManager($client);
    $secrets = $manager->getL2tpSecrets();

    expect($secrets)->toHaveCount(2)
        ->and($secrets[0]['service'])->toBe('l2tp')
        ->and($secrets[1]['name'])->toBe('branch2');
});

// ─── getActiveVpnCount ────────────────────────────────────────

it('returns total active vpn session count', function () {
    $client = makeVpnClient([
        '/interface/wireguard/peers/print'      => [
            ['interface' => 'wg0', 'current-endpoint-address' => '1.2.3.4'],
            ['interface' => 'wg0', 'current-endpoint-address' => '5.6.7.8'],
        ],
        '/interface/l2tp-server/session/print'  => [
            ['name' => 'l2tp-1', 'user' => 'branch1'],
        ],
        '/interface/pptp-server/session/print'  => [],
    ]);

    $manager = new VpnManager($client);

    expect($manager->getActiveVpnCount())->toBe(3);
});