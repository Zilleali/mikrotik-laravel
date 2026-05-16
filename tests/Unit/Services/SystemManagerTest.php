<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\SystemManager;

// ─── Helper ───────────────────────────────────────────────────

function makeSystemClient(array $responses = []): RouterosClient
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

// ─── getResources ─────────────────────────────────────────────

it('returns system resources', function () {
    $client = makeSystemClient([
        '/system/resource/print' => [[
            'cpu-load' => '23',
            'free-memory' => '128000000',
            'total-memory' => '256000000',
            'uptime' => '14d6h',
            'version' => '7.14.3',
            'board-name' => 'hAP ac2',
            'architecture-name' => 'arm',
        ]],
    ]);

    $manager = new SystemManager($client);
    $resources = $manager->getResources();

    expect($resources)->not->toBeEmpty()
        ->and($resources['cpu-load'])->toBe('23')
        ->and($resources['uptime'])->toBe('14d6h')
        ->and($resources['board-name'])->toBe('hAP ac2');
});

it('returns empty array when resources unavailable', function () {
    $client = makeSystemClient(['/system/resource/print' => []]);
    $manager = new SystemManager($client);

    expect($manager->getResources())->toBeEmpty();
});

// ─── getHealth ────────────────────────────────────────────────

it('returns system health data', function () {
    $client = makeSystemClient([
        '/system/health/print' => [[
            'temperature' => '48',
            'voltage' => '12.1',
            'fan-speed' => '1200',
        ]],
    ]);

    $manager = new SystemManager($client);
    $health = $manager->getHealth();

    expect($health)->not->toBeEmpty()
        ->and($health['temperature'])->toBe('48')
        ->and($health['voltage'])->toBe('12.1');
});

// ─── getIdentity ──────────────────────────────────────────────

it('returns router identity name', function () {
    $client = makeSystemClient([
        '/system/identity/print' => [['name' => 'Main-Router']],
    ]);

    $manager = new SystemManager($client);

    expect($manager->getIdentity())->toBe('Main-Router');
});

it('returns unknown when identity is empty', function () {
    $client = makeSystemClient(['/system/identity/print' => []]);
    $manager = new SystemManager($client);

    expect($manager->getIdentity())->toBe('Unknown');
});

// ─── getLogs ──────────────────────────────────────────────────

it('returns system logs', function () {
    $client = makeSystemClient([
        '/log/print' => [
            ['time' => 'jan/01 10:00:00', 'topics' => 'system,info', 'message' => 'router started'],
            ['time' => 'jan/01 10:01:00', 'topics' => 'pppoe,info',  'message' => 'ali-home logged in'],
        ],
    ]);

    $manager = new SystemManager($client);
    $logs = $manager->getLogs();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]['message'])->toBe('router started');
});

it('returns limited logs when count specified', function () {
    $allLogs = array_fill(0, 20, ['time' => '10:00', 'topics' => 'info', 'message' => 'test']);

    $client = makeSystemClient(['/log/print' => $allLogs]);
    $manager = new SystemManager($client);
    $logs = $manager->getLogs(5);

    expect($logs)->toHaveCount(5);
});

// ─── getCpuLoad ───────────────────────────────────────────────

it('returns cpu load as integer', function () {
    $client = makeSystemClient([
        '/system/resource/print' => [['cpu-load' => '23']],
    ]);

    $manager = new SystemManager($client);

    expect($manager->getCpuLoad())->toBe(23)
        ->and($manager->getCpuLoad())->toBeInt();
});

// ─── getUptime ────────────────────────────────────────────────

it('returns uptime string', function () {
    $client = makeSystemClient([
        '/system/resource/print' => [['uptime' => '14d6h30m']],
    ]);

    $manager = new SystemManager($client);

    expect($manager->getUptime())->toBe('14d6h30m');
});

// ─── reboot ───────────────────────────────────────────────────

it('sends reboot command without throwing', function () {
    $client = makeSystemClient();
    $manager = new SystemManager($client);

    expect(fn () => $manager->reboot())->not->toThrow(\Exception::class);
});

// ─── ping ─────────────────────────────────────────────────────

it('returns ping results', function () {
    $client = makeSystemClient([
        '/ping' => [
            ['time' => '2ms', 'size' => '56', 'host' => '8.8.8.8'],
            ['time' => '3ms', 'size' => '56', 'host' => '8.8.8.8'],
        ],
    ]);

    $manager = new SystemManager($client);
    $result = $manager->ping('8.8.8.8', count: 2);

    expect($result)->toHaveCount(2)
        ->and($result[0]['host'])->toBe('8.8.8.8');
});
