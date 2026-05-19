<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\SessionMonitor;

function makeSessionClient(array $responses = []): RouterosClient
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

// ─── getAllActiveSessions ─────────────────────────────────────

it('returns combined pppoe and hotspot sessions', function () {
    $client = makeSessionClient([
        '/ppp/active/print' => [
            ['name' => 'ali-home',   'address' => '10.0.0.1', 'uptime' => '2h', 'service' => 'pppoe'],
            ['name' => 'zain-fiber', 'address' => '10.0.0.2', 'uptime' => '5h', 'service' => 'pppoe'],
        ],
        '/ip/hotspot/active/print' => [
            ['user' => 'guest001', 'address' => '192.168.1.10', 'uptime' => '30m'],
        ],
    ]);

    $monitor = new SessionMonitor($client);
    $sessions = $monitor->getAllActiveSessions();

    expect($sessions)->toHaveCount(3)
        ->and($sessions[0]['type'])->toBe('pppoe')
        ->and($sessions[2]['type'])->toBe('hotspot');
});

// ─── getTotalSessionCount ─────────────────────────────────────

it('returns total session count across all services', function () {
    $client = makeSessionClient([
        '/ppp/active/print' => [
            ['name' => 'user1'],
            ['name' => 'user2'],
        ],
        '/ip/hotspot/active/print' => [
            ['user' => 'guest1'],
        ],
    ]);

    $monitor = new SessionMonitor($client);

    expect($monitor->getTotalSessionCount())->toBe(3);
});

// ─── getPppoeSessionCount ─────────────────────────────────────

it('returns pppoe session count', function () {
    $client = makeSessionClient([
        '/ppp/active/print' => [
            ['name' => 'user1'],
            ['name' => 'user2'],
            ['name' => 'user3'],
        ],
    ]);

    $monitor = new SessionMonitor($client);

    expect($monitor->getPppoeSessionCount())->toBe(3);
});

// ─── getHotspotSessionCount ───────────────────────────────────

it('returns hotspot session count', function () {
    $client = makeSessionClient([
        '/ip/hotspot/active/print' => [
            ['user' => 'guest1'],
            ['user' => 'guest2'],
        ],
    ]);

    $monitor = new SessionMonitor($client);

    expect($monitor->getHotspotSessionCount())->toBe(2);
});

// ─── isUserOnline ─────────────────────────────────────────────

it('returns true when pppoe user is online', function () {
    $client = makeSessionClient([
        '/ppp/active/print' => [
            ['name' => 'ali-home', 'address' => '10.0.0.1'],
        ],
        '/ip/hotspot/active/print' => [],
    ]);

    $monitor = new SessionMonitor($client);

    expect($monitor->isUserOnline('ali-home'))->toBeTrue();
});

it('returns true when hotspot user is online', function () {
    $client = makeSessionClient([
        '/ppp/active/print' => [],
        '/ip/hotspot/active/print' => [
            ['user' => 'guest001', 'address' => '192.168.1.10'],
        ],
    ]);

    $monitor = new SessionMonitor($client);

    expect($monitor->isUserOnline('guest001'))->toBeTrue();
});

it('returns false when user is not online', function () {
    $client = makeSessionClient([
        '/ppp/active/print' => [],
        '/ip/hotspot/active/print' => [],
    ]);

    $monitor = new SessionMonitor($client);

    expect($monitor->isUserOnline('offline-user'))->toBeFalse();
});

// ─── getUserSession ───────────────────────────────────────────

it('returns session data for online pppoe user', function () {
    $client = makeSessionClient([
        '/ppp/active/print' => [
            ['name' => 'ali-home', 'address' => '10.0.0.1', 'uptime' => '2h14m'],
        ],
        '/ip/hotspot/active/print' => [],
    ]);

    $monitor = new SessionMonitor($client);
    $session = $monitor->getUserSession('ali-home');

    expect($session)->not->toBeNull()
        ->and($session['uptime'])->toBe('2h14m')
        ->and($session['type'])->toBe('pppoe');
});

it('returns null for offline user', function () {
    $client = makeSessionClient([
        '/ppp/active/print' => [],
        '/ip/hotspot/active/print' => [],
    ]);

    $monitor = new SessionMonitor($client);

    expect($monitor->getUserSession('offline'))->toBeNull();
});

// ─── getSessionsByUptime ──────────────────────────────────────

it('returns sessions longer than given minutes', function () {
    $client = makeSessionClient([
        '/ppp/active/print' => [
            ['name' => 'user1', 'uptime' => '3h30m'],
            ['name' => 'user2', 'uptime' => '45m'],
            ['name' => 'user3', 'uptime' => '2h'],
        ],
        '/ip/hotspot/active/print' => [],
    ]);

    $monitor = new SessionMonitor($client);
    $sessions = $monitor->getSessionsLongerThan(60);

    expect($sessions)->toHaveCount(2);
});

// ─── getSummary ───────────────────────────────────────────────

it('returns session summary', function () {
    $client = makeSessionClient([
        '/ppp/active/print' => [
            ['name' => 'user1'],
            ['name' => 'user2'],
        ],
        '/ip/hotspot/active/print' => [
            ['user' => 'guest1'],
        ],
    ]);

    $monitor = new SessionMonitor($client);
    $summary = $monitor->getSummary();

    expect($summary)->toHaveKey('pppoe')
        ->and($summary)->toHaveKey('hotspot')
        ->and($summary)->toHaveKey('total')
        ->and($summary['pppoe'])->toBe(2)
        ->and($summary['hotspot'])->toBe(1)
        ->and($summary['total'])->toBe(3);
});
