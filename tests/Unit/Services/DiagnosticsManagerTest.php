<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\DiagnosticsManager;

// ─── Helper ───────────────────────────────────────────────────

function mockDiagClient(array $responses = [], bool $connected = true, string $host = '192.168.88.1', int $port = 8728): RouterosClient
{
    return new class ($responses, $connected, $host, $port) extends RouterosClient {
        public function __construct(
            private array $responses,
            private bool $mockedConnected,
            string $host,
            int $port,
        ) {
            parent::__construct(host: $host, port: $port);
        }

        public function query(string $command, array $params = [], array $queries = []): array
        {
            return $this->responses[$command] ?? [];
        }

        public function sendRaw(string $command, array $params = []): array
        {
            return $this->responses[$command] ?? [];
        }

        public function isConnected(): bool
        {
            return $this->mockedConnected;
        }

        public function send(array $words): array
        {
            return [];
        }
    };
}

// ─── isAlive ──────────────────────────────────────────────────

it('returns true when router responds to identity query', function () {
    $client = mockDiagClient(['/system/identity/print' => [['name' => 'ISP-Core']]]);
    $manager = new DiagnosticsManager($client);

    expect($manager->isAlive())->toBeTrue();
});

it('returns false when identity query returns empty', function () {
    $client = mockDiagClient(['/system/identity/print' => []]);
    $manager = new DiagnosticsManager($client);

    expect($manager->isAlive())->toBeFalse();
});

it('returns false when query throws', function () {
    $client = new class extends RouterosClient {
        public function __construct()
        {
            parent::__construct(host: '127.0.0.1');
        }

        public function query(string $command, array $params = [], array $queries = []): array
        {
            throw new \RuntimeException('connection refused');
        }

        public function isConnected(): bool
        {
            return true;
        }

        public function send(array $words): array
        {
            return [];
        }
    };

    $manager = new DiagnosticsManager($client);
    expect($manager->isAlive())->toBeFalse();
});

// ─── ping ─────────────────────────────────────────────────────

it('ping returns connected and alive when router responds', function () {
    $client = mockDiagClient(['/system/identity/print' => [['name' => 'ISP-Core']]]);
    $manager = new DiagnosticsManager($client);

    $result = $manager->ping();

    expect($result['connected'])->toBeTrue()
        ->and($result['alive'])->toBeTrue()
        ->and($result['host'])->toBe('192.168.88.1')
        ->and($result['port'])->toBe(8728)
        ->and($result['latency_ms'])->toBeFloat()
        ->and($result['error'])->toBeNull();
});

it('ping returns not connected when client is not connected', function () {
    $client = mockDiagClient([], false);
    $manager = new DiagnosticsManager($client);

    $result = $manager->ping();

    expect($result['connected'])->toBeFalse()
        ->and($result['alive'])->toBeFalse()
        ->and($result['error'])->toBe('Not connected');
});

it('ping has all required keys', function () {
    $client = mockDiagClient(['/system/identity/print' => [['name' => 'R1']]]);
    $manager = new DiagnosticsManager($client);

    $result = $manager->ping();

    expect($result)->toHaveKeys(['host', 'port', 'connected', 'alive', 'latency_ms', 'error']);
});

// ─── latency ──────────────────────────────────────────────────

it('latency returns a float', function () {
    $client = mockDiagClient(['/system/identity/print' => [['name' => 'R1']]]);
    $manager = new DiagnosticsManager($client);

    expect($manager->latency(1))->toBeFloat()->toBeGreaterThanOrEqual(0.0);
});

it('latency clamps samples to 1-10', function () {
    $client = mockDiagClient(['/system/identity/print' => [['name' => 'R1']]]);
    $manager = new DiagnosticsManager($client);

    // Should not throw regardless of sample count
    expect($manager->latency(0))->toBeFloat();
    expect($manager->latency(100))->toBeFloat();
});

it('latency returns -1.0 when all samples fail', function () {
    $client = new class extends RouterosClient {
        public function __construct()
        {
            parent::__construct(host: '127.0.0.1');
        }

        public function query(string $command, array $params = [], array $queries = []): array
        {
            throw new \RuntimeException('timeout');
        }

        public function isConnected(): bool { return true; }
        public function send(array $words): array { return []; }
    };

    $manager = new DiagnosticsManager($client);
    expect($manager->latency(2))->toBe(-1.0);
});

// ─── getRaw ───────────────────────────────────────────────────

it('getRaw returns raw response words', function () {
    $rawWords = ['!re', '=.id=*1', '=address=192.168.1.1/24', '', '!done'];
    $client = mockDiagClient(['/ip/address/print' => $rawWords]);
    $manager = new DiagnosticsManager($client);

    $result = $manager->getRaw('/ip/address/print');

    expect($result)->toBe($rawWords);
});

// ─── connectionInfo ───────────────────────────────────────────

it('connectionInfo returns host, port, connected, protocol for plain API', function () {
    $client = mockDiagClient([], true, '10.0.0.1', 8728);
    $manager = new DiagnosticsManager($client);

    $info = $manager->connectionInfo();

    expect($info['host'])->toBe('10.0.0.1')
        ->and($info['port'])->toBe(8728)
        ->and($info['connected'])->toBeTrue()
        ->and($info['protocol'])->toBe('API');
});

it('connectionInfo reports API-SSL for port 8729', function () {
    $client = mockDiagClient([], true, '10.0.0.1', 8729);
    $manager = new DiagnosticsManager($client);

    expect($manager->connectionInfo()['protocol'])->toBe('API-SSL');
});
