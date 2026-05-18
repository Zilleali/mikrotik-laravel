<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\RouteManager;

function makeRouteClient(array $responses = []): RouterosClient
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

// ─── getRoutes ────────────────────────────────────────────────

it('returns all routes', function () {
    $client = makeRouteClient([
        '/ip/route/print' => [
            ['dst-address' => '0.0.0.0/0',       'gateway' => '192.168.1.1', 'distance' => '1', 'active' => 'true'],
            ['dst-address' => '192.168.2.0/24',   'gateway' => '10.0.0.1',   'distance' => '1', 'active' => 'true'],
            ['dst-address' => '10.10.0.0/16',     'gateway' => '10.0.0.2',   'distance' => '5', 'active' => 'false'],
        ],
    ]);

    $manager = new RouteManager($client);
    $routes = $manager->getRoutes();

    expect($routes)->toHaveCount(3)
        ->and($routes[0]['dst-address'])->toBe('0.0.0.0/0')
        ->and($routes[1]['gateway'])->toBe('10.0.0.1');
});

it('returns empty array when no routes', function () {
    $client = makeRouteClient(['/ip/route/print' => []]);
    $manager = new RouteManager($client);

    expect($manager->getRoutes())->toBeEmpty();
});

// ─── getActiveRoutes ──────────────────────────────────────────

it('returns only active routes', function () {
    $client = makeRouteClient([
        '/ip/route/print' => [
            ['dst-address' => '0.0.0.0/0',     'gateway' => '192.168.1.1', 'active' => 'true'],
            ['dst-address' => '192.168.2.0/24', 'gateway' => '10.0.0.1',   'active' => 'true'],
            ['dst-address' => '10.10.0.0/16',   'gateway' => '10.0.0.2',   'active' => 'false'],
        ],
    ]);

    $manager = new RouteManager($client);
    $routes = $manager->getActiveRoutes();

    expect($routes)->toHaveCount(2)
        ->and($routes[0]['active'])->toBe('true')
        ->and($routes[1]['active'])->toBe('true');
});

// ─── getDefaultRoute ──────────────────────────────────────────

it('returns default route', function () {
    $client = makeRouteClient([
        '/ip/route/print' => [
            ['dst-address' => '0.0.0.0/0',     'gateway' => '192.168.1.1', 'active' => 'true'],
            ['dst-address' => '192.168.2.0/24', 'gateway' => '10.0.0.1',   'active' => 'true'],
        ],
    ]);

    $manager = new RouteManager($client);
    $default = $manager->getDefaultRoute();

    expect($default)->not->toBeNull()
        ->and($default['dst-address'])->toBe('0.0.0.0/0')
        ->and($default['gateway'])->toBe('192.168.1.1');
});

it('returns null when no default route', function () {
    $client = makeRouteClient([
        '/ip/route/print' => [
            ['dst-address' => '192.168.2.0/24', 'gateway' => '10.0.0.1'],
        ],
    ]);

    $manager = new RouteManager($client);

    expect($manager->getDefaultRoute())->toBeNull();
});

// ─── getRouteByDestination ────────────────────────────────────

it('returns route by destination', function () {
    $client = makeRouteClient([
        '/ip/route/print' => [
            ['dst-address' => '192.168.2.0/24', 'gateway' => '10.0.0.1'],
        ],
    ]);

    $manager = new RouteManager($client);
    $route = $manager->getRouteByDestination('192.168.2.0/24');

    expect($route)->not->toBeNull()
        ->and($route['gateway'])->toBe('10.0.0.1');
});

it('returns null when destination not found', function () {
    $client = makeRouteClient(['/ip/route/print' => []]);
    $manager = new RouteManager($client);

    expect($manager->getRouteByDestination('10.0.0.0/8'))->toBeNull();
});

// ─── addRoute ─────────────────────────────────────────────────

it('adds route without throwing', function () {
    $client = makeRouteClient();
    $manager = new RouteManager($client);

    expect(fn () => $manager->addRoute(
        '10.20.0.0/16',
        '192.168.1.1'
    ))->not->toThrow(\Exception::class);
});

it('adds route with distance and comment', function () {
    $client = makeRouteClient();
    $manager = new RouteManager($client);

    expect(fn () => $manager->addRoute(
        '10.20.0.0/16',
        '192.168.1.1',
        distance: 10,
        comment: 'branch office route'
    ))->not->toThrow(\Exception::class);
});

// ─── removeRoute ──────────────────────────────────────────────

it('removes route without throwing', function () {
    $client = makeRouteClient([
        '/ip/route/print' => [
            ['.id' => '*1', 'dst-address' => '10.20.0.0/16', 'gateway' => '192.168.1.1'],
        ],
    ]);

    $manager = new RouteManager($client);

    expect(fn () => $manager->removeRoute('10.20.0.0/16'))
        ->not->toThrow(\Exception::class);
});

it('does not throw when removing non-existent route', function () {
    $client = makeRouteClient(['/ip/route/print' => []]);
    $manager = new RouteManager($client);

    expect(fn () => $manager->removeRoute('99.0.0.0/8'))
        ->not->toThrow(\Exception::class);
});

// ─── enableRoute / disableRoute ───────────────────────────────

it('enables route without throwing', function () {
    $client = makeRouteClient([
        '/ip/route/print' => [
            ['.id' => '*1', 'dst-address' => '10.20.0.0/16', 'disabled' => 'true'],
        ],
    ]);

    $manager = new RouteManager($client);

    expect(fn () => $manager->enableRoute('10.20.0.0/16'))
        ->not->toThrow(\Exception::class);
});

it('disables route without throwing', function () {
    $client = makeRouteClient([
        '/ip/route/print' => [
            ['.id' => '*1', 'dst-address' => '10.20.0.0/16', 'disabled' => 'false'],
        ],
    ]);

    $manager = new RouteManager($client);

    expect(fn () => $manager->disableRoute('10.20.0.0/16'))
        ->not->toThrow(\Exception::class);
});

// ─── getRoutesByGateway ───────────────────────────────────────

it('returns routes by gateway', function () {
    $client = makeRouteClient([
        '/ip/route/print' => [
            ['dst-address' => '0.0.0.0/0',     'gateway' => '192.168.1.1'],
            ['dst-address' => '10.0.0.0/8',    'gateway' => '192.168.1.1'],
            ['dst-address' => '172.16.0.0/12', 'gateway' => '10.0.0.1'],
        ],
    ]);

    $manager = new RouteManager($client);
    $routes = $manager->getRoutesByGateway('192.168.1.1');

    expect($routes)->toHaveCount(2)
        ->and($routes[0]['gateway'])->toBe('192.168.1.1');
});
