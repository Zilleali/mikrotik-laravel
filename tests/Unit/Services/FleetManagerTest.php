<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;
use ZillEAli\MikrotikLaravel\Exceptions\ValidationException;
use ZillEAli\MikrotikLaravel\MikrotikManager;
use ZillEAli\MikrotikLaravel\Services\FleetManager;
use ZillEAli\MikrotikLaravel\Support\FleetResult;
use ZillEAli\MikrotikLaravel\Testing\FakeRouterosClient;

// ─── Helper — manager with one fake client per router ─────────

/**
 * @param array<string, array<string, list<array<string, string>>>> $responses Router name => command => rows
 * @param list<string>                                             $down      Routers that fail to connect
 * @param array<string, mixed>                                     $extra     Extra config
 */
function makeFleetManager(array $responses = [], array $down = [], array $extra = []): MikrotikManager
{
    $config = array_merge([
        'host' => '10.0.0.1',
        'routers' => [
            'north' => ['host' => '10.0.1.1', 'groups' => ['north', 'fiber']],
            'south' => ['host' => '10.0.2.1', 'groups' => ['south']],
            'tower' => ['host' => '10.0.3.1', 'groups' => ['north']],
        ],
    ], $extra);

    $manager = new class ($config) extends MikrotikManager {
        /** @var array<string, FakeRouterosClient> */
        public array $clients = [];

        /** @var list<string> */
        public array $down = [];

        protected function getClient(): RouterosClient
        {
            $name = $this->resolveAndResetRouter();

            if (in_array($name, $this->down, true)) {
                throw new ConnectionException("Router {$name} unreachable");
            }

            return $this->clients[$name] ??= new FakeRouterosClient([], []);
        }
    };

    foreach ($responses as $router => $rows) {
        $manager->clients[$router] = new FakeRouterosClient($rows, []);
    }

    $manager->down = $down;

    return $manager;
}

function resourceRows(string $version, int $cpu): array
{
    return [[
        'version' => $version,
        'cpu-load' => (string) $cpu,
        'free-memory' => '256',
        'total-memory' => '1024',
        'uptime' => '1d2h',
        'board-name' => 'CCR2004',
    ]];
}

// ─── Router selection ─────────────────────────────────────────

it('fleet() returns a FleetManager', function () {
    expect(makeFleetManager()->fleet())->toBeInstanceOf(FleetManager::class);
});

it('routers() includes default and every configured router', function () {
    expect(makeFleetManager()->fleet()->routers())
        ->toBe(['default', 'north', 'south', 'tower']);
});

it('routers() skips default when fleet.include_default is false', function () {
    $fleet = makeFleetManager(extra: ['fleet' => ['include_default' => false]])->fleet();

    expect($fleet->routers())->toBe(['north', 'south', 'tower']);
});

it('only() limits the fleet to the given routers', function () {
    expect(makeFleetManager()->fleet()->only('north', 'south')->routers())
        ->toBe(['north', 'south']);
});

it('only() throws for an unknown router', function () {
    makeFleetManager()->fleet()->only('mars');
})->throws(ConnectionException::class);

it('except() removes routers from the selection', function () {
    expect(makeFleetManager()->fleet()->except('default', 'south')->routers())
        ->toBe(['north', 'tower']);
});

it('group() selects routers tagged with that group', function () {
    expect(makeFleetManager()->fleet()->group('north')->routers())
        ->toBe(['north', 'tower']);
});

it('group() returns an empty selection for an unknown group', function () {
    expect(makeFleetManager()->fleet()->group('west')->routers())->toBe([]);
});

it('group() rejects an empty group name', function () {
    makeFleetManager()->fleet()->group('');
})->throws(ValidationException::class);

it('selection methods do not mutate the original fleet', function () {
    $fleet = makeFleetManager()->fleet();
    $fleet->only('north');

    expect($fleet->routers())->toHaveCount(4);
});

// ─── each() ───────────────────────────────────────────────────

it('each() runs the callback once per router with a pinned manager', function () {
    $result = makeFleetManager()->fleet()->each(
        fn (MikrotikManager $router, string $name) => $router->currentRouterName() . ':' . $name
    );

    expect($result)->toBeInstanceOf(FleetResult::class)
        ->and($result->successful())->toBe([
            'default' => 'default:default',
            'north' => 'north:north',
            'south' => 'south:south',
            'tower' => 'tower:tower',
        ]);
});

it('each() sends queries to the right router', function () {
    $manager = makeFleetManager([
        'north' => ['/system/identity/print' => [['name' => 'NORTH-CCR']]],
        'south' => ['/system/identity/print' => [['name' => 'SOUTH-CCR']]],
    ]);

    $result = $manager->fleet()->only('north', 'south')
        ->each(fn (MikrotikManager $r) => $r->system()->getIdentity());

    expect($result->successful())->toBe(['north' => 'NORTH-CCR', 'south' => 'SOUTH-CCR']);
});

it('each() keeps the router pinned across several manager calls', function () {
    $manager = makeFleetManager([
        'south' => [
            '/system/identity/print' => [['name' => 'SOUTH-CCR']],
            '/ppp/active/print' => [['name' => 'ali-home']],
        ],
    ]);

    $result = $manager->fleet()->only('south')->each(fn (MikrotikManager $r) => [
        $r->system()->getIdentity(),
        count($r->pppoe()->getActiveSessions()),
    ]);

    expect($result->get('south'))->toBe(['SOUTH-CCR', 1])
        ->and($manager->clients)->not->toHaveKey('default');
});

it('each() isolates failures to the router that failed', function () {
    $result = makeFleetManager(down: ['south'])->fleet()
        ->each(fn (MikrotikManager $r) => $r->system()->getIdentity());

    expect($result->succeededRouters())->toBe(['default', 'north', 'tower'])
        ->and($result->failedRouters())->toBe(['south'])
        ->and($result->error('south'))->toBeInstanceOf(ConnectionException::class)
        ->and($result->hasFailures())->toBeTrue();
});

// ─── Monitoring ───────────────────────────────────────────────

it('health() returns a snapshot per router', function () {
    $manager = makeFleetManager([
        'north' => [
            '/system/resource/print' => resourceRows('7.16', 12),
            '/system/identity/print' => [['name' => 'NORTH-CCR']],
        ],
    ]);

    $health = $manager->fleet()->only('north')->health()->get('north');

    expect($health['identity'])->toBe('NORTH-CCR')
        ->and($health['version'])->toBe('7.16')
        ->and($health['cpu_load'])->toBe(12)
        ->and($health['board'])->toBe('CCR2004')
        ->and($health['memory_used_percent'])->toBe(75.0)
        ->and($health)->toHaveKey('latency_ms');
});

it('health() reports memory_used_percent 0 when memory is unknown', function () {
    $health = makeFleetManager()->fleet()->only('north')->health()->get('north');

    expect($health['memory_used_percent'])->toBe(0.0)
        ->and($health['version'])->toBe('Unknown');
});

it('reachable() and unreachable() split the fleet', function () {
    $manager = makeFleetManager([
        'default' => ['/system/identity/print' => [['name' => 'core']]],
        'north' => ['/system/identity/print' => [['name' => 'n']]],
        'tower' => ['/system/identity/print' => [['name' => 't']]],
    ], down: ['south']);

    expect($manager->fleet()->reachable())->toBe(['default', 'north', 'tower'])
        ->and($manager->fleet()->unreachable())->toBe(['south']);
});

it('activeSessionCounts() and totalActiveSessions() aggregate sessions', function () {
    $manager = makeFleetManager([
        'north' => [
            '/ppp/active/print' => [['name' => 'a'], ['name' => 'b']],
            '/ip/hotspot/active/print' => [['user' => 'v1']],
        ],
        'south' => [
            '/ppp/active/print' => [['name' => 'c']],
        ],
    ], down: ['tower']);

    $fleet = $manager->fleet()->except('default');

    expect($fleet->activeSessionCounts()->successful())->toBe([
        'north' => ['pppoe' => 2, 'hotspot' => 1],
        'south' => ['pppoe' => 1, 'hotspot' => 0],
    ])->and($fleet->totalActiveSessions())->toBe(['pppoe' => 3, 'hotspot' => 1, 'total' => 4]);
});

// ─── Subscriber lookup ────────────────────────────────────────

it('findPppoeSession() locates the router a user is online on', function () {
    $manager = makeFleetManager([
        'north' => ['/ppp/active/print' => [['name' => 'bilal', 'address' => '10.10.0.5']]],
        'south' => ['/ppp/active/print' => [['name' => 'ali-home', 'address' => '10.20.0.9']]],
    ]);

    expect($manager->fleet()->findPppoeSession('ali-home'))
        ->toBe(['south' => ['name' => 'ali-home', 'address' => '10.20.0.9']]);
});

it('findPppoeSession() returns every router with a duplicate session', function () {
    $manager = makeFleetManager([
        'north' => ['/ppp/active/print' => [['name' => 'ali-home']]],
        'tower' => ['/ppp/active/print' => [['name' => 'ali-home']]],
    ]);

    expect(array_keys($manager->fleet()->findPppoeSession('ali-home')))->toBe(['north', 'tower']);
});

it('findPppoeSession() returns empty array when the user is offline', function () {
    expect(makeFleetManager()->fleet()->findPppoeSession('ghost'))->toBe([]);
});

it('findPppoeSession() skips unreachable routers', function () {
    $manager = makeFleetManager([
        'north' => ['/ppp/active/print' => [['name' => 'ali-home']]],
    ], down: ['south']);

    expect(array_keys($manager->fleet()->findPppoeSession('ali-home')))->toBe(['north']);
});

it('findPppoeSession() rejects an empty username', function () {
    makeFleetManager()->fleet()->findPppoeSession('');
})->throws(ValidationException::class);

it('findSessionByIp() locates a session by address', function () {
    $manager = makeFleetManager([
        'tower' => ['/ppp/active/print' => [['name' => 'x', 'address' => '100.64.1.20']]],
    ]);

    expect($manager->fleet()->findSessionByIp('100.64.1.20'))->toHaveKey('tower');
});

it('findSessionByIp() rejects an invalid IP', function () {
    makeFleetManager()->fleet()->findSessionByIp('999.1.1.1');
})->throws(ValidationException::class);

it('findHotspotSession() locates a hotspot user', function () {
    $manager = makeFleetManager([
        'south' => ['/ip/hotspot/active/print' => [['user' => 'voucher-77', 'address' => '192.168.50.3']]],
    ]);

    expect($manager->fleet()->findHotspotSession('voucher-77'))->toHaveKey('south');
});

it('kickPppoeEverywhere() kicks the user on every router they are online on', function () {
    $session = [['.id' => '*1A', 'name' => 'ali-home']];
    $manager = makeFleetManager([
        'north' => ['/ppp/active/print' => $session],
        'tower' => ['/ppp/active/print' => $session],
        'south' => ['/ppp/active/print' => [['.id' => '*2', 'name' => 'other']]],
    ]);

    expect($manager->fleet()->kickPppoeEverywhere('ali-home'))->toBe(['north', 'tower'])
        ->and($manager->clients['north']->recordedQueries())->toContain('/ppp/active/remove')
        ->and($manager->clients['tower']->recordedQueries())->toContain('/ppp/active/remove')
        ->and($manager->clients['south']->recordedQueries())->not->toContain('/ppp/active/remove');
});

it('kickPppoeEverywhere() returns empty array when the user is offline', function () {
    expect(makeFleetManager()->fleet()->kickPppoeEverywhere('ghost'))->toBe([]);
});

// ─── MikrotikManager::on() / getRouterNames() ─────────────────

it('on() pins a manager to a router across multiple calls', function () {
    $branch = makeFleetManager()->on('south');

    $branch->system();
    $branch->pppoe();

    expect($branch->currentRouterName())->toBe('south');
});

it('on() does not change the router of the original manager', function () {
    $manager = makeFleetManager();
    $manager->on('south');

    expect($manager->currentRouterName())->toBe('default');
});

it('on() shares the connection pool with the original manager', function () {
    $manager = makeFleetManager();

    expect($manager->on('north')->getPool())->toBe($manager->getPool());
});

it('on() throws for an unknown router', function () {
    makeFleetManager()->on('mars');
})->throws(ConnectionException::class);

it('router() still resets to default after one call', function () {
    $manager = makeFleetManager();
    $manager->router('south')->system();

    expect($manager->currentRouterName())->toBe('default');
});

it('getRouterNames() lists default plus configured routers', function () {
    expect(makeFleetManager()->getRouterNames())->toBe(['default', 'north', 'south', 'tower']);
});

it('getRouterNames() filters by group', function () {
    expect(makeFleetManager()->getRouterNames('fiber'))->toBe(['north']);
});

it('default router config carries ssl, verify_peer and ca_cert_path', function () {
    $manager = new class (['host' => '10.0.0.1', 'ssl' => true, 'verify_peer' => true, 'ca_cert_path' => '/etc/ca.pem']) extends MikrotikManager {
        public function exposeConfig(string $name): array
        {
            return $this->getRouterConfig($name);
        }
    };

    expect($manager->exposeConfig('default'))
        ->toMatchArray(['ssl' => true, 'verify_peer' => true, 'ca_cert_path' => '/etc/ca.pem']);
});

// ─── Commands ─────────────────────────────────────────────────

it('mikrotik:sync --router caches every dataset from that router', function () {
    $manager = makeFleetManager([
        'default' => ['/system/resource/print' => resourceRows('6.49', 90)],
        'south' => ['/system/resource/print' => resourceRows('7.16', 5)],
    ]);
    app()->instance(MikrotikManager::class, $manager);

    $this->artisan('mikrotik:sync', ['--router' => 'south'])->assertSuccessful();

    expect(cache()->get('mikrotik.south.system.resources')['version'])->toBe('7.16')
        ->and($manager->clients['default']->recordedQueries())->toBe([]);
});

it('mikrotik:fleet prints a table and succeeds when all routers are up', function () {
    app()->instance(MikrotikManager::class, makeFleetManager([
        'north' => ['/system/identity/print' => [['name' => 'NORTH-CCR']]],
    ]));

    $this->artisan('mikrotik:fleet', ['--group' => 'north'])
        ->expectsOutputToContain('NORTH-CCR')
        ->expectsOutputToContain('2/2 routers reachable.')
        ->assertSuccessful();
});

it('mikrotik:fleet fails when a router is down', function () {
    app()->instance(MikrotikManager::class, makeFleetManager(down: ['south']));

    $this->artisan('mikrotik:fleet', ['--router' => ['north', 'south']])
        ->expectsOutputToContain('Router south unreachable')
        ->assertFailed();
});

it('mikrotik:fleet --json outputs machine-readable results', function () {
    app()->instance(MikrotikManager::class, makeFleetManager());

    $this->artisan('mikrotik:fleet', ['--router' => ['north'], '--json' => true])
        ->expectsOutputToContain('"results"')
        ->assertSuccessful();
});

it('mikrotik:fleet fails when no router matches the group', function () {
    app()->instance(MikrotikManager::class, makeFleetManager());

    $this->artisan('mikrotik:fleet', ['--group' => 'west'])->assertFailed();
});
