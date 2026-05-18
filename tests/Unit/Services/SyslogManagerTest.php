<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\SyslogManager;

function makeSyslogClient(array $responses = []): RouterosClient
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

// ─── getTargets ───────────────────────────────────────────────

it('returns all logging targets', function () {
    $client = makeSyslogClient([
        '/system/logging/action/print' => [
            ['name' => 'memory', 'target' => 'memory', 'memory-lines' => '1000'],
            ['name' => 'disk',   'target' => 'disk',   'file-name' => 'log'],
            ['name' => 'remote', 'target' => 'remote',  'remote' => '192.168.1.100', 'remote-port' => '514'],
        ],
    ]);

    $manager = new SyslogManager($client);
    $targets = $manager->getTargets();

    expect($targets)->toHaveCount(3)
        ->and($targets[0]['name'])->toBe('memory')
        ->and($targets[2]['target'])->toBe('remote');
});

it('returns empty array when no targets', function () {
    $client  = makeSyslogClient(['/system/logging/action/print' => []]);
    $manager = new SyslogManager($client);

    expect($manager->getTargets())->toBeEmpty();
});

// ─── getTarget ────────────────────────────────────────────────

it('returns single target by name', function () {
    $client = makeSyslogClient([
        '/system/logging/action/print' => [
            ['name' => 'remote', 'target' => 'remote', 'remote' => '192.168.1.100'],
        ],
    ]);

    $manager = new SyslogManager($client);
    $target  = $manager->getTarget('remote');

    expect($target)->not->toBeNull()
        ->and($target['remote'])->toBe('192.168.1.100');
});

it('returns null when target not found', function () {
    $client  = makeSyslogClient(['/system/logging/action/print' => []]);
    $manager = new SyslogManager($client);

    expect($manager->getTarget('nonexistent'))->toBeNull();
});

// ─── getRules ─────────────────────────────────────────────────

it('returns all logging rules', function () {
    $client = makeSyslogClient([
        '/system/logging/print' => [
            ['topics' => 'info',    'action' => 'memory', 'disabled' => 'false'],
            ['topics' => 'error',   'action' => 'remote', 'disabled' => 'false'],
            ['topics' => 'warning', 'action' => 'disk',   'disabled' => 'false'],
        ],
    ]);

    $manager = new SyslogManager($client);
    $rules   = $manager->getRules();

    expect($rules)->toHaveCount(3)
        ->and($rules[0]['topics'])->toBe('info')
        ->and($rules[1]['action'])->toBe('remote');
});

// ─── addRemoteTarget ──────────────────────────────────────────

it('adds remote syslog target without throwing', function () {
    $client  = makeSyslogClient();
    $manager = new SyslogManager($client);

    expect(fn () => $manager->addRemoteTarget(
        name:       'nexalink-syslog',
        remoteIp:   '192.168.1.100',
        remotePort: 514,
        comment:    'NexaLink syslog server'
    ))->not->toThrow(\Exception::class);
});

// ─── addRule ──────────────────────────────────────────────────

it('adds logging rule without throwing', function () {
    $client  = makeSyslogClient();
    $manager = new SyslogManager($client);

    expect(fn () => $manager->addRule(
        topics: 'pppoe',
        action: 'remote'
    ))->not->toThrow(\Exception::class);
});

// ─── removeTarget ─────────────────────────────────────────────

it('removes target without throwing', function () {
    $client = makeSyslogClient([
        '/system/logging/action/print' => [
            ['.id' => '*3', 'name' => 'remote', 'target' => 'remote'],
        ],
    ]);

    $manager = new SyslogManager($client);

    expect(fn () => $manager->removeTarget('remote'))
        ->not->toThrow(\Exception::class);
});

// ─── removeRule ───────────────────────────────────────────────

it('removes rule without throwing', function () {
    $client = makeSyslogClient([
        '/system/logging/print' => [
            ['.id' => '*1', 'topics' => 'pppoe', 'action' => 'remote'],
        ],
    ]);

    $manager = new SyslogManager($client);

    expect(fn () => $manager->removeRule('pppoe', 'remote'))
        ->not->toThrow(\Exception::class);
});

// ─── getRemoteTargets ─────────────────────────────────────────

it('returns only remote targets', function () {
    $client = makeSyslogClient([
        '/system/logging/action/print' => [
            ['name' => 'memory', 'target' => 'memory'],
            ['name' => 'remote', 'target' => 'remote', 'remote' => '192.168.1.100'],
            ['name' => 'disk',   'target' => 'disk'],
        ],
    ]);

    $manager = new SyslogManager($client);
    $remote  = $manager->getRemoteTargets();

    expect($remote)->toHaveCount(1)
        ->and($remote[0]['target'])->toBe('remote');
});

// ─── hasRemoteLogging ─────────────────────────────────────────

it('returns true when remote logging configured', function () {
    $client = makeSyslogClient([
        '/system/logging/action/print' => [
            ['name' => 'remote', 'target' => 'remote', 'remote' => '192.168.1.100'],
        ],
    ]);

    $manager = new SyslogManager($client);

    expect($manager->hasRemoteLogging())->toBeTrue();
});

it('returns false when no remote logging', function () {
    $client = makeSyslogClient([
        '/system/logging/action/print' => [
            ['name' => 'memory', 'target' => 'memory'],
        ],
    ]);

    $manager = new SyslogManager($client);

    expect($manager->hasRemoteLogging())->toBeFalse();
});