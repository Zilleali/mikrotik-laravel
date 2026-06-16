<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ResourceNotFoundException;
use ZillEAli\MikrotikLaravel\Exceptions\ValidationException;
use ZillEAli\MikrotikLaravel\Services\DnsManager;

function makeDnsClient(array $responses = []): RouterosClient
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
        public function send(array $words): array { return []; }
        public function isConnected(): bool { return true; }
    };
}

// ─── getSettings ──────────────────────────────────────────────

it('returns dns settings', function () {
    $client = makeDnsClient([
        '/ip/dns/print' => [['servers' => '8.8.8.8,1.1.1.1', 'allow-remote-requests' => 'yes']],
    ]);
    $manager = new DnsManager($client);

    expect($manager->getSettings())->toHaveKey('servers');
});

it('returns empty array when no dns settings', function () {
    $client = makeDnsClient(['/ip/dns/print' => []]);
    $manager = new DnsManager($client);

    expect($manager->getSettings())->toBeArray()->toBeEmpty();
});

// ─── isRemoteRequestsAllowed ──────────────────────────────────

it('returns true when remote requests are allowed', function () {
    $client = makeDnsClient([
        '/ip/dns/print' => [['allow-remote-requests' => 'yes']],
    ]);
    $manager = new DnsManager($client);

    expect($manager->isRemoteRequestsAllowed())->toBeTrue();
});

it('returns false when remote requests are not allowed', function () {
    $client = makeDnsClient([
        '/ip/dns/print' => [['allow-remote-requests' => 'no']],
    ]);
    $manager = new DnsManager($client);

    expect($manager->isRemoteRequestsAllowed())->toBeFalse();
});

// ─── getStaticEntries ─────────────────────────────────────────

it('returns all static dns entries', function () {
    $client = makeDnsClient([
        '/ip/dns/static/print' => [
            ['.id' => '*1', 'name' => 'nexalink.local', 'address' => '192.168.1.100'],
            ['.id' => '*2', 'name' => 'ads.example.com', 'address' => '0.0.0.0'],
        ],
    ]);
    $manager = new DnsManager($client);

    expect($manager->getStaticEntries())->toHaveCount(2);
});

// ─── getStaticEntry ───────────────────────────────────────────

it('returns a static dns entry by hostname', function () {
    $client = makeDnsClient([
        '/ip/dns/static/print' => [
            ['.id' => '*1', 'name' => 'nexalink.local', 'address' => '192.168.1.100'],
        ],
    ]);
    $manager = new DnsManager($client);
    $entry = $manager->getStaticEntry('nexalink.local');

    expect($entry)->not->toBeNull()
        ->and($entry['address'])->toBe('192.168.1.100');
});

it('returns null when static dns entry not found', function () {
    $client = makeDnsClient(['/ip/dns/static/print' => []]);
    $manager = new DnsManager($client);

    expect($manager->getStaticEntry('missing.local'))->toBeNull();
});

// ─── addStaticEntry ───────────────────────────────────────────

it('adds a static dns entry without throwing', function () {
    $client = makeDnsClient();
    $manager = new DnsManager($client);

    expect(fn () => $manager->addStaticEntry('nexalink.local', '192.168.1.100'))
        ->not->toThrow(\Exception::class);
});

// ─── updateStaticEntry ────────────────────────────────────────

it('updates a static dns entry without throwing', function () {
    $client = makeDnsClient([
        '/ip/dns/static/print' => [
            ['.id' => '*1', 'name' => 'nexalink.local', 'address' => '192.168.1.100'],
        ],
    ]);
    $manager = new DnsManager($client);

    expect(fn () => $manager->updateStaticEntry('nexalink.local', ['address' => '192.168.1.200']))
        ->not->toThrow(\Exception::class);
});

it('throws when updating non-existent dns entry', function () {
    $client = makeDnsClient(['/ip/dns/static/print' => []]);
    $manager = new DnsManager($client);

    expect(fn () => $manager->updateStaticEntry('missing.local', []))
        ->toThrow(ResourceNotFoundException::class);
});

// ─── removeStaticEntry ────────────────────────────────────────

it('removes a static dns entry without throwing', function () {
    $client = makeDnsClient([
        '/ip/dns/static/print' => [
            ['.id' => '*1', 'name' => 'nexalink.local', 'address' => '192.168.1.100'],
        ],
    ]);
    $manager = new DnsManager($client);

    expect(fn () => $manager->removeStaticEntry('nexalink.local'))
        ->not->toThrow(\Exception::class);
});

it('throws when removing non-existent dns entry', function () {
    $client = makeDnsClient(['/ip/dns/static/print' => []]);
    $manager = new DnsManager($client);

    expect(fn () => $manager->removeStaticEntry('missing.local'))
        ->toThrow(ResourceNotFoundException::class);
});

// ─── blockDomain ─────────────────────────────────────────────

it('blocks a domain without throwing', function () {
    $client = makeDnsClient();
    $manager = new DnsManager($client);

    expect(fn () => $manager->blockDomain('ads.example.com'))
        ->not->toThrow(\Exception::class);
});

// ─── Validation ───────────────────────────────────────────────

it('addStaticEntry throws on empty name', function () {
    $client = makeDnsClient();
    $manager = new DnsManager($client);

    expect(fn () => $manager->addStaticEntry('', '192.168.1.1'))
        ->toThrow(ValidationException::class);
});

it('addStaticEntry throws on invalid ip address', function () {
    $client = makeDnsClient();
    $manager = new DnsManager($client);

    expect(fn () => $manager->addStaticEntry('nexalink.local', 'not-an-ip'))
        ->toThrow(ValidationException::class);
});

it('updateStaticEntry throws on empty name', function () {
    $client = makeDnsClient();
    $manager = new DnsManager($client);

    expect(fn () => $manager->updateStaticEntry('', []))
        ->toThrow(ValidationException::class);
});

it('removeStaticEntry throws on empty name', function () {
    $client = makeDnsClient();
    $manager = new DnsManager($client);

    expect(fn () => $manager->removeStaticEntry(''))
        ->toThrow(ValidationException::class);
});
