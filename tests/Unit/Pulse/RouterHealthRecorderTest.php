<?php

use Carbon\CarbonImmutable;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Facades\Pulse;
use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;
use ZillEAli\MikrotikLaravel\MikrotikManager;
use ZillEAli\MikrotikLaravel\Pulse\Recorders\RouterHealthRecorder;
use ZillEAli\MikrotikLaravel\Services\DiagnosticsManager;

// ─── Helpers ──────────────────────────────────────────────────

function makeRouterManager(array $resources = [], float $latency = 5.0): MikrotikManager
{
    $client = new class ($resources) extends RouterosClient {
        public function __construct(private array $fakeResources)
        {
            parent::__construct(host: '127.0.0.1');
        }

        public function query(string $command, array $params = [], array $queries = []): array
        {
            return $this->fakeResources[$command] ?? [];
        }

        public function isConnected(): bool { return true; }
        public function send(array $words): array { return []; }
    };

    $fakeClient = $client;
    $fakeLat    = $latency;

    return new class ($fakeClient, $fakeLat) extends MikrotikManager {
        public function __construct(private RouterosClient $c, private float $lat)
        {
            parent::__construct(['host' => '127.0.0.1']);
        }

        protected function getClient(): RouterosClient
        {
            $this->resolveAndResetRouter();
            return $this->c;
        }

        public function diagnostics(): DiagnosticsManager
        {
            $lat = $this->lat;
            return new class ($this->c, $lat) extends DiagnosticsManager {
                public function __construct(RouterosClient $client, private float $fakeLat)
                {
                    parent::__construct($client);
                }

                public function latency(int $samples = 3): float { return $this->fakeLat; }
            };
        }
    };
}

function beat(int $second = 0): SharedBeat
{
    return new SharedBeat(CarbonImmutable::now()->setSecond($second), 'test');
}

function fakeEntry(): Entry
{
    return new Entry(time(), 'test', 'test');
}

// ─── Throttling ───────────────────────────────────────────────

it('skips all recording when beat second is not zero', function () {
    Pulse::shouldReceive('record')->never();

    $recorder = new RouterHealthRecorder(makeRouterManager());

    $recorder->record(beat(second: 15));
    $recorder->record(beat(second: 30));
    $recorder->record(beat(second: 45));
});

// ─── Happy path ───────────────────────────────────────────────

it('calls Pulse::record four times on second=0 beat', function () {
    Pulse::shouldReceive('record')->times(4)->andReturn(fakeEntry());

    $manager  = makeRouterManager([
        '/system/resource/print' => [['cpu-load' => '42', 'free-memory' => '256000', 'total-memory' => '1024000']],
    ]);

    (new RouterHealthRecorder($manager))->record(beat(second: 0));
});

it('records mikrotik_cpu with correct CPU value', function () {
    $entry = fakeEntry();

    Pulse::shouldReceive('record')
        ->with('mikrotik_cpu', 'default', 42)
        ->once()
        ->andReturn($entry);

    Pulse::shouldReceive('record')
        ->with(Mockery::not('mikrotik_cpu'), Mockery::any(), Mockery::any())
        ->times(3)
        ->andReturn($entry);

    $manager = makeRouterManager([
        '/system/resource/print' => [['cpu-load' => '42', 'free-memory' => '512000', 'total-memory' => '1024000']],
    ]);

    (new RouterHealthRecorder($manager))->record(beat(second: 0));
});

it('calculates memory percentage from free and total memory', function () {
    $entry = fakeEntry();

    // 75% used: (1 - 256000/1024000) * 100 = 75
    Pulse::shouldReceive('record')
        ->with('mikrotik_memory', 'default', 75)
        ->once()
        ->andReturn($entry);

    Pulse::shouldReceive('record')
        ->with(Mockery::not('mikrotik_memory'), Mockery::any(), Mockery::any())
        ->times(3)
        ->andReturn($entry);

    $manager = makeRouterManager([
        '/system/resource/print' => [['cpu-load' => '0', 'free-memory' => '256000', 'total-memory' => '1024000']],
    ]);

    (new RouterHealthRecorder($manager))->record(beat(second: 0));
});

it('records API latency rounded to integer milliseconds', function () {
    $entry = fakeEntry();

    Pulse::shouldReceive('record')
        ->with('mikrotik_latency', 'default', 8)
        ->once()
        ->andReturn($entry);

    Pulse::shouldReceive('record')
        ->with(Mockery::not('mikrotik_latency'), Mockery::any(), Mockery::any())
        ->times(3)
        ->andReturn($entry);

    $manager = makeRouterManager([
        '/system/resource/print' => [['cpu-load' => '0', 'free-memory' => '512000', 'total-memory' => '1024000']],
    ], latency: 8.0);

    (new RouterHealthRecorder($manager))->record(beat(second: 0));
});

it('records uptime beat=1 when router is alive', function () {
    $entry = fakeEntry();

    Pulse::shouldReceive('record')
        ->with('mikrotik_uptime_beat', 'default', 1)
        ->once()
        ->andReturn($entry);

    Pulse::shouldReceive('record')
        ->with(Mockery::not('mikrotik_uptime_beat'), Mockery::any(), Mockery::any())
        ->times(3)
        ->andReturn($entry);

    $manager = makeRouterManager([
        '/system/resource/print' => [['cpu-load' => '10', 'free-memory' => '512000', 'total-memory' => '1024000']],
    ]);

    (new RouterHealthRecorder($manager))->record(beat(second: 0));
});

// ─── Router down ──────────────────────────────────────────────

it('records only uptime beat=0 when router throws', function () {
    Pulse::shouldReceive('record')
        ->with('mikrotik_uptime_beat', Mockery::any(), 0)
        ->once()
        ->andReturn(fakeEntry());

    Pulse::shouldReceive('record')
        ->with(Mockery::not('mikrotik_uptime_beat'), Mockery::any(), Mockery::any())
        ->never();

    $manager = new class extends MikrotikManager {
        public function __construct()
        {
            parent::__construct(['host' => '127.0.0.1']);
        }

        protected function getClient(): RouterosClient
        {
            $this->resolveAndResetRouter();
            throw new ConnectionException('unreachable');
        }
    };

    (new RouterHealthRecorder($manager))->record(beat(second: 0));
});

// ─── Router key ───────────────────────────────────────────────

it('uses configured router_name as Pulse key', function () {
    $entry = fakeEntry();
    config(['pulse.recorders.' . RouterHealthRecorder::class . '.router_name' => 'branch-router']);

    Pulse::shouldReceive('record')
        ->with(Mockery::any(), 'branch-router', Mockery::any())
        ->times(4)
        ->andReturn($entry);

    $manager = makeRouterManager([
        '/system/resource/print' => [['cpu-load' => '10', 'free-memory' => '512000', 'total-memory' => '1024000']],
    ]);

    (new RouterHealthRecorder($manager))->record(beat(second: 0));
});
