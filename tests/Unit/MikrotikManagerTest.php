<?php

use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;
use ZillEAli\MikrotikLaravel\MikrotikManager;
use ZillEAli\MikrotikLaravel\Services\FirewallManager;
use ZillEAli\MikrotikLaravel\Services\HotspotManager;
use ZillEAli\MikrotikLaravel\Services\PppoeManager;
use ZillEAli\MikrotikLaravel\Services\QueueManager;
use ZillEAli\MikrotikLaravel\Services\SystemManager;

// ─── Helper — MikrotikManager with mocked client ──────────────

function makeManager(array $configOverride = []): MikrotikManager
{
    $config = array_merge([
        'host' => '127.0.0.1',
        'port' => 8728,
        'username' => 'admin',
        'password' => '',
        'timeout' => 1,
        'retry_attempts' => 1,
        'retry_delay' => 0,
        'routers' => [
            'branch' => [
                'host' => '127.0.0.2',
                'port' => 8728,
                'username' => 'admin',
                'password' => '',
                'timeout' => 1,
            ],
        ],
    ], $configOverride);

    return new class ($config) extends MikrotikManager {
        protected function getClient(): \ZillEAli\MikrotikLaravel\Connections\RouterosClient
        {
            $this->resolveAndResetRouter(); // ← reset call karo

            return new class () extends \ZillEAli\MikrotikLaravel\Connections\RouterosClient {
                public function __construct()
                {
                    parent::__construct(host: '127.0.0.1');
                }
                public function query(string $command, array $params = [], array $queries = []): array
                {
                    return [];
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
    };
}

// ─── Manager returns correct service instances ─────────────────

it('returns PppoeManager instance', function () {
    expect(makeManager()->pppoe())->toBeInstanceOf(PppoeManager::class);
});

it('returns HotspotManager instance', function () {
    expect(makeManager()->hotspot())->toBeInstanceOf(HotspotManager::class);
});

it('returns QueueManager instance', function () {
    expect(makeManager()->queue())->toBeInstanceOf(QueueManager::class);
});

it('returns FirewallManager instance', function () {
    expect(makeManager()->firewall())->toBeInstanceOf(FirewallManager::class);
});

it('returns SystemManager instance', function () {
    expect(makeManager()->system())->toBeInstanceOf(SystemManager::class);
});

// ─── Router selection ─────────────────────────────────────────

it('returns manager instance when selecting named router', function () {
    $manager = makeManager();

    expect($manager->router('branch'))->toBeInstanceOf(MikrotikManager::class);
});

it('resets to default router after selection', function () {
    $manager = makeManager();
    $manager->router('branch');

    // currentRouter should reset after getClient call — pppoe() triggers it
    $manager->pppoe();

    $reflection = new ReflectionProperty($manager, 'currentRouter');
    $reflection->setAccessible(true);

    expect($reflection->getValue($manager))->toBe('default');
});

// ─── Config resolution ────────────────────────────────────────

it('throws ConnectionException for unknown router name', function () {
    $manager = new MikrotikManager([
        'host' => '127.0.0.1',
        'retry_attempts' => 1,
        'retry_delay' => 0,
        'routers' => [],
    ]);

    expect(fn () => $manager->router('nonexistent')->pppoe())
        ->toThrow(ConnectionException::class, "Router 'nonexistent' not found");
});

// ─── Disconnect ───────────────────────────────────────────────

it('disconnects without error when no connection exists', function () {
    $manager = makeManager();

    expect(fn () => $manager->disconnect())->not->toThrow(\Exception::class);
});

it('disconnects all without error', function () {
    $manager = makeManager();

    expect(fn () => $manager->disconnectAll())->not->toThrow(\Exception::class);
});
