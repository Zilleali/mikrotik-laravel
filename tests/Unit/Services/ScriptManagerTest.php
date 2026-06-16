<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ResourceNotFoundException;
use ZillEAli\MikrotikLaravel\Services\ScriptManager;

function makeScriptClient(array $responses = []): RouterosClient
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

// ─── getScripts ───────────────────────────────────────────────

it('returns all scripts', function () {
    $client = makeScriptClient([
        '/system/script/print' => [
            ['name' => 'backup-config',  'owner' => 'admin', 'policy' => 'read,write', 'disabled' => 'false'],
            ['name' => 'flush-dns',      'owner' => 'admin', 'policy' => 'read',        'disabled' => 'false'],
            ['name' => 'reboot-nightly', 'owner' => 'admin', 'policy' => 'reboot',      'disabled' => 'true'],
        ],
    ]);

    $manager = new ScriptManager($client);
    $scripts = $manager->getScripts();

    expect($scripts)->toHaveCount(3)
        ->and($scripts[0]['name'])->toBe('backup-config')
        ->and($scripts[2]['disabled'])->toBe('true');
});

it('returns empty array when no scripts', function () {
    $client = makeScriptClient(['/system/script/print' => []]);
    $manager = new ScriptManager($client);

    expect($manager->getScripts())->toBeEmpty();
});

// ─── getScript ────────────────────────────────────────────────

it('returns single script by name', function () {
    $client = makeScriptClient([
        '/system/script/print' => [
            ['name' => 'backup-config', 'owner' => 'admin', 'policy' => 'read,write'],
        ],
    ]);

    $manager = new ScriptManager($client);
    $script = $manager->getScript('backup-config');

    expect($script)->not->toBeNull()
        ->and($script['name'])->toBe('backup-config');
});

it('returns null when script not found', function () {
    $client = makeScriptClient(['/system/script/print' => []]);
    $manager = new ScriptManager($client);

    expect($manager->getScript('nonexistent'))->toBeNull();
});

// ─── addScript ────────────────────────────────────────────────

it('adds script without throwing', function () {
    $client = makeScriptClient();
    $manager = new ScriptManager($client);

    expect(fn () => $manager->addScript(
        'flush-dns',
        '/ip dns flush',
        comment: 'Flush DNS cache'
    ))->not->toThrow(\Exception::class);
});

// ─── updateScript ─────────────────────────────────────────────

it('updates script without throwing', function () {
    $client = makeScriptClient([
        '/system/script/print' => [
            ['.id' => '*1', 'name' => 'flush-dns', 'source' => '/ip dns flush'],
        ],
    ]);

    $manager = new ScriptManager($client);

    expect(fn () => $manager->updateScript('flush-dns', [
        'source' => '/ip dns flush\r\n:log info "DNS flushed"',
    ]))->not->toThrow(\Exception::class);
});

// ─── removeScript ─────────────────────────────────────────────

it('removes script without throwing', function () {
    $client = makeScriptClient([
        '/system/script/print' => [
            ['.id' => '*1', 'name' => 'flush-dns'],
        ],
    ]);

    $manager = new ScriptManager($client);

    expect(fn () => $manager->removeScript('flush-dns'))
        ->not->toThrow(\Exception::class);
});

it('throws when removing non-existent script', function () {
    $client = makeScriptClient(['/system/script/print' => []]);
    $manager = new ScriptManager($client);

    expect(fn () => $manager->removeScript('ghost'))
        ->toThrow(ResourceNotFoundException::class);
});

// ─── runScript ────────────────────────────────────────────────

it('runs script without throwing', function () {
    $client = makeScriptClient([
        '/system/script/print' => [
            ['.id' => '*1', 'name' => 'flush-dns'],
        ],
    ]);

    $manager = new ScriptManager($client);

    expect(fn () => $manager->runScript('flush-dns'))
        ->not->toThrow(\Exception::class);
});

it('throws when running non-existent script', function () {
    $client = makeScriptClient(['/system/script/print' => []]);
    $manager = new ScriptManager($client);

    expect(fn () => $manager->runScript('ghost'))
        ->toThrow(ResourceNotFoundException::class);
});

// ─── getSchedulers ────────────────────────────────────────────

it('returns all schedulers', function () {
    $client = makeScriptClient([
        '/system/scheduler/print' => [
            ['name' => 'daily-backup',  'interval' => '1d',  'on-event' => 'backup-config', 'disabled' => 'false'],
            ['name' => 'hourly-check',  'interval' => '1h',  'on-event' => 'check-status',  'disabled' => 'false'],
        ],
    ]);

    $manager = new ScriptManager($client);
    $schedulers = $manager->getSchedulers();

    expect($schedulers)->toHaveCount(2)
        ->and($schedulers[0]['name'])->toBe('daily-backup')
        ->and($schedulers[1]['interval'])->toBe('1h');
});

// ─── addScheduler ─────────────────────────────────────────────

it('adds scheduler without throwing', function () {
    $client = makeScriptClient();
    $manager = new ScriptManager($client);

    expect(fn () => $manager->addScheduler(
        name:     'daily-backup',
        onEvent:  'backup-config',
        interval: '1d',
        comment:  'Daily backup job'
    ))->not->toThrow(\Exception::class);
});

// ─── removeScheduler ──────────────────────────────────────────

it('removes scheduler without throwing', function () {
    $client = makeScriptClient([
        '/system/scheduler/print' => [
            ['.id' => '*1', 'name' => 'daily-backup'],
        ],
    ]);

    $manager = new ScriptManager($client);

    expect(fn () => $manager->removeScheduler('daily-backup'))
        ->not->toThrow(\Exception::class);
});

// ─── getScriptCount ───────────────────────────────────────────

it('returns correct script count', function () {
    $client = makeScriptClient([
        '/system/script/print' => [
            ['name' => 'script1'],
            ['name' => 'script2'],
            ['name' => 'script3'],
        ],
    ]);

    $manager = new ScriptManager($client);

    expect($manager->getScriptCount())->toBe(3);
});

// ─── Validation ───────────────────────────────────────────────

it('addScript throws on empty name', function () {
    $client = makeScriptClient();
    $manager = new ScriptManager($client);

    expect(fn () => $manager->addScript('', '/ip dns flush'))
        ->toThrow(\ZillEAli\MikrotikLaravel\Exceptions\ValidationException::class);
});

it('addScript throws on empty source', function () {
    $client = makeScriptClient();
    $manager = new ScriptManager($client);

    expect(fn () => $manager->addScript('flush-dns', ''))
        ->toThrow(\ZillEAli\MikrotikLaravel\Exceptions\ValidationException::class);
});
