<?php

use ZillEAli\MikrotikLaravel\Connections\ConnectionPool;
use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;

// ─── Class exists ─────────────────────────────────────────────

it('ConnectionPool class exists', function () {
    expect(class_exists(ConnectionPool::class))->toBeTrue();
});

// ─── Basic instantiation ──────────────────────────────────────

it('creates ConnectionPool with empty connections', function () {
    $pool = new ConnectionPool();

    expect($pool->count())->toBe(0);
});

// ─── add / get ────────────────────────────────────────────────

it('stores and retrieves a client by name', function () {
    $pool   = new ConnectionPool();
    $client = new class extends RouterosClient {
        public function __construct() { parent::__construct(host: '127.0.0.1'); }
        public function isConnected(): bool { return true; }
    };

    $pool->add('main', $client);

    expect($pool->get('main'))->toBe($client);
});

it('returns null for unknown router name', function () {
    $pool = new ConnectionPool();

    expect($pool->get('nonexistent'))->toBeNull();
});

// ─── has ──────────────────────────────────────────────────────

it('returns true when connection exists', function () {
    $pool   = new ConnectionPool();
    $client = new class extends RouterosClient {
        public function __construct() { parent::__construct(host: '127.0.0.1'); }
        public function isConnected(): bool { return true; }
    };

    $pool->add('main', $client);

    expect($pool->has('main'))->toBeTrue();
});

it('returns false when connection does not exist', function () {
    $pool = new ConnectionPool();

    expect($pool->has('nonexistent'))->toBeFalse();
});

// ─── isAlive ──────────────────────────────────────────────────

it('returns true when connection is alive', function () {
    $pool   = new ConnectionPool();
    $client = new class extends RouterosClient {
        public function __construct() { parent::__construct(host: '127.0.0.1'); }
        public function isConnected(): bool { return true; }
    };

    $pool->add('main', $client);

    expect($pool->isAlive('main'))->toBeTrue();
});

it('returns false when connection is dead', function () {
    $pool   = new ConnectionPool();
    $client = new class extends RouterosClient {
        public function __construct() { parent::__construct(host: '127.0.0.1'); }
        public function isConnected(): bool { return false; }
    };

    $pool->add('main', $client);

    expect($pool->isAlive('main'))->toBeFalse();
});

it('returns false for non-existent connection', function () {
    $pool = new ConnectionPool();

    expect($pool->isAlive('ghost'))->toBeFalse();
});

// ─── remove ───────────────────────────────────────────────────

it('removes a connection by name', function () {
    $pool   = new ConnectionPool();
    $client = new class extends RouterosClient {
        public function __construct() { parent::__construct(host: '127.0.0.1'); }
        public function isConnected(): bool { return true; }
    };

    $pool->add('main', $client);
    $pool->remove('main');

    expect($pool->has('main'))->toBeFalse()
        ->and($pool->count())->toBe(0);
});

it('does not throw when removing non-existent connection', function () {
    $pool = new ConnectionPool();

    expect(fn () => $pool->remove('ghost'))
        ->not->toThrow(\Exception::class);
});

// ─── flush ────────────────────────────────────────────────────

it('flushes all connections', function () {
    $pool = new ConnectionPool();

    $client1 = new class extends RouterosClient {
        public function __construct() { parent::__construct(host: '127.0.0.1'); }
        public function isConnected(): bool { return true; }
    };

    $client2 = new class extends RouterosClient {
        public function __construct() { parent::__construct(host: '127.0.0.2'); }
        public function isConnected(): bool { return true; }
    };

    $pool->add('main', $client1);
    $pool->add('branch', $client2);
    $pool->flush();

    expect($pool->count())->toBe(0);
});

// ─── count ────────────────────────────────────────────────────

it('returns correct connection count', function () {
    $pool = new ConnectionPool();

    $makeClient = fn (string $host) => new class($host) extends RouterosClient {
        public function __construct(string $h) { parent::__construct(host: $h); }
        public function isConnected(): bool { return true; }
    };

    $pool->add('main',   $makeClient('127.0.0.1'));
    $pool->add('branch', $makeClient('127.0.0.2'));
    $pool->add('edge',   $makeClient('127.0.0.3'));

    expect($pool->count())->toBe(3);
});

// ─── getNames ─────────────────────────────────────────────────

it('returns all connection names', function () {
    $pool = new ConnectionPool();

    $makeClient = fn (string $host) => new class($host) extends RouterosClient {
        public function __construct(string $h) { parent::__construct(host: $h); }
        public function isConnected(): bool { return true; }
    };

    $pool->add('main',   $makeClient('127.0.0.1'));
    $pool->add('branch', $makeClient('127.0.0.2'));

    $names = $pool->getNames();

    expect($names)->toContain('main')
        ->and($names)->toContain('branch')
        ->and($names)->toHaveCount(2);
});

// ─── getAliveConnections ──────────────────────────────────────

it('returns only alive connections', function () {
    $pool = new ConnectionPool();

    $alive = new class extends RouterosClient {
        public function __construct() { parent::__construct(host: '127.0.0.1'); }
        public function isConnected(): bool { return true; }
    };

    $dead = new class extends RouterosClient {
        public function __construct() { parent::__construct(host: '127.0.0.2'); }
        public function isConnected(): bool { return false; }
    };

    $pool->add('main',   $alive);
    $pool->add('branch', $dead);

    $aliveConnections = $pool->getAliveConnections();

    expect($aliveConnections)->toHaveCount(1)
        ->and(array_key_exists('main', $aliveConnections))->toBeTrue()
        ->and(array_key_exists('branch', $aliveConnections))->toBeFalse();
});

// ─── pruneDeadConnections ─────────────────────────────────────

it('removes dead connections and keeps alive ones', function () {
    $pool = new ConnectionPool();

    $alive = new class extends RouterosClient {
        public function __construct() { parent::__construct(host: '127.0.0.1'); }
        public function isConnected(): bool { return true; }
    };

    $dead = new class extends RouterosClient {
        public function __construct() { parent::__construct(host: '127.0.0.2'); }
        public function isConnected(): bool { return false; }
    };

    $pool->add('main',   $alive);
    $pool->add('branch', $dead);
    $pool->pruneDeadConnections();

    expect($pool->count())->toBe(1)
        ->and($pool->has('main'))->toBeTrue()
        ->and($pool->has('branch'))->toBeFalse();
});