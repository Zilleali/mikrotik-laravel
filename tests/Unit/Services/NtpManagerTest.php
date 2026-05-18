<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\NtpManager;

function makeNtpClient(array $responses = []): RouterosClient
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

// ─── getClientSettings ────────────────────────────────────────

it('returns ntp client settings', function () {
    $client = makeNtpClient([
        '/system/ntp/client/print' => [[
            'enabled' => 'yes',
            'primary-ntp' => '216.239.35.0',
            'secondary-ntp' => '216.239.35.4',
            'server-dns-names' => 'time.google.com',
        ]],
    ]);

    $manager = new NtpManager($client);
    $settings = $manager->getClientSettings();

    expect($settings)->not->toBeEmpty()
        ->and($settings['enabled'])->toBe('yes')
        ->and($settings['primary-ntp'])->toBe('216.239.35.0');
});

it('returns empty array when ntp not configured', function () {
    $client = makeNtpClient(['/system/ntp/client/print' => []]);
    $manager = new NtpManager($client);

    expect($manager->getClientSettings())->toBeEmpty();
});

// ─── isEnabled ────────────────────────────────────────────────

it('returns true when ntp client is enabled', function () {
    $client = makeNtpClient([
        '/system/ntp/client/print' => [['enabled' => 'yes']],
    ]);

    $manager = new NtpManager($client);

    expect($manager->isEnabled())->toBeTrue();
});

it('returns false when ntp client is disabled', function () {
    $client = makeNtpClient([
        '/system/ntp/client/print' => [['enabled' => 'no']],
    ]);

    $manager = new NtpManager($client);

    expect($manager->isEnabled())->toBeFalse();
});

// ─── enable / disable ─────────────────────────────────────────

it('enables ntp client without throwing', function () {
    $client = makeNtpClient();
    $manager = new NtpManager($client);

    expect(fn () => $manager->enable())
        ->not->toThrow(\Exception::class);
});

it('disables ntp client without throwing', function () {
    $client = makeNtpClient();
    $manager = new NtpManager($client);

    expect(fn () => $manager->disable())
        ->not->toThrow(\Exception::class);
});

// ─── setServers ───────────────────────────────────────────────

it('sets ntp servers without throwing', function () {
    $client = makeNtpClient();
    $manager = new NtpManager($client);

    expect(fn () => $manager->setServers(
        primary:   '216.239.35.0',
        secondary: '216.239.35.4'
    ))->not->toThrow(\Exception::class);
});

it('sets ntp server by dns name without throwing', function () {
    $client = makeNtpClient();
    $manager = new NtpManager($client);

    expect(fn () => $manager->setServersByDns(
        'time.google.com',
        'time.cloudflare.com'
    ))->not->toThrow(\Exception::class);
});

// ─── getSystemClock ───────────────────────────────────────────

it('returns system clock info', function () {
    $client = makeNtpClient([
        '/system/clock/print' => [[
            'time' => '14:30:00',
            'date' => 'may/18/2026',
            'time-zone-name' => 'Asia/Karachi',
        ]],
    ]);

    $manager = new NtpManager($client);
    $clock = $manager->getSystemClock();

    expect($clock)->not->toBeEmpty()
        ->and($clock['time'])->toBe('14:30:00')
        ->and($clock['time-zone-name'])->toBe('Asia/Karachi');
});

// ─── setTimezone ──────────────────────────────────────────────

it('sets timezone without throwing', function () {
    $client = makeNtpClient();
    $manager = new NtpManager($client);

    expect(fn () => $manager->setTimezone('Asia/Karachi'))
        ->not->toThrow(\Exception::class);
});

// ─── getSyncStatus ────────────────────────────────────────────

it('returns sync status', function () {
    $client = makeNtpClient([
        '/system/ntp/client/print' => [[
            'enabled' => 'yes',
            'primary-ntp' => '216.239.35.0',
            'synced' => 'yes',
            'last-update-from' => '216.239.35.0',
        ]],
    ]);

    $manager = new NtpManager($client);
    $status = $manager->getSyncStatus();

    expect($status['synced'])->toBe('yes')
        ->and($status['last-update-from'])->toBe('216.239.35.0');
});

it('returns false for isSynced when not synced', function () {
    $client = makeNtpClient([
        '/system/ntp/client/print' => [['synced' => 'no']],
    ]);

    $manager = new NtpManager($client);

    expect($manager->isSynced())->toBeFalse();
});

it('returns true for isSynced when synced', function () {
    $client = makeNtpClient([
        '/system/ntp/client/print' => [['synced' => 'yes']],
    ]);

    $manager = new NtpManager($client);

    expect($manager->isSynced())->toBeTrue();
});
