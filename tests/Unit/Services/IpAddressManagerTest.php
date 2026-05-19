<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\IpAddressManager;

function makeIpAddressClient(array $responses = []): RouterosClient
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

// ─── getAddresses ─────────────────────────────────────────────

it('returns all ip addresses', function () {
    $client = makeIpAddressClient([
        '/ip/address/print' => [
            ['address' => '192.168.1.1/24', 'interface' => 'ether1', 'disabled' => 'false'],
            ['address' => '10.0.0.1/8',     'interface' => 'ether2', 'disabled' => 'false'],
        ],
    ]);

    $manager   = new IpAddressManager($client);
    $addresses = $manager->getAddresses();

    expect($addresses)->toHaveCount(2)
        ->and($addresses[0]['address'])->toBe('192.168.1.1/24')
        ->and($addresses[1]['interface'])->toBe('ether2');
});

it('returns empty array when no addresses', function () {
    $client  = makeIpAddressClient(['/ip/address/print' => []]);
    $manager = new IpAddressManager($client);

    expect($manager->getAddresses())->toBeEmpty();
});

// ─── getAddressByInterface ────────────────────────────────────

it('returns addresses for specific interface', function () {
    $client = makeIpAddressClient([
        '/ip/address/print' => [
            ['address' => '192.168.1.1/24', 'interface' => 'ether1'],
            ['address' => '10.0.0.1/8',     'interface' => 'ether2'],
            ['address' => '172.16.0.1/16',  'interface' => 'ether1'],
        ],
    ]);

    $manager   = new IpAddressManager($client);
    $addresses = $manager->getAddressesByInterface('ether1');

    expect($addresses)->toHaveCount(2)
        ->and($addresses[0]['interface'])->toBe('ether1')
        ->and($addresses[1]['interface'])->toBe('ether1');
});

// ─── addAddress ───────────────────────────────────────────────

it('adds ip address without throwing', function () {
    $client  = makeIpAddressClient();
    $manager = new IpAddressManager($client);

    expect(fn () => $manager->addAddress([
        'address'   => '192.168.2.1/24',
        'interface' => 'ether3',
        'comment'   => 'LAN address',
    ]))->not->toThrow(\Exception::class);
});

// ─── removeAddress ────────────────────────────────────────────

it('removes address without throwing', function () {
    $client = makeIpAddressClient([
        '/ip/address/print' => [
            ['.id' => '*1', 'address' => '192.168.1.1/24', 'interface' => 'ether1'],
        ],
    ]);

    $manager = new IpAddressManager($client);

    expect(fn () => $manager->removeAddress('192.168.1.1/24'))
        ->not->toThrow(\Exception::class);
});

it('does not throw when removing non-existent address', function () {
    $client  = makeIpAddressClient(['/ip/address/print' => []]);
    $manager = new IpAddressManager($client);

    expect(fn () => $manager->removeAddress('99.99.99.99/24'))
        ->not->toThrow(\Exception::class);
});

// ─── enableAddress / disableAddress ───────────────────────────

it('enables address without throwing', function () {
    $client = makeIpAddressClient([
        '/ip/address/print' => [
            ['.id' => '*1', 'address' => '192.168.1.1/24', 'disabled' => 'true'],
        ],
    ]);

    $manager = new IpAddressManager($client);

    expect(fn () => $manager->enableAddress('192.168.1.1/24'))
        ->not->toThrow(\Exception::class);
});

it('disables address without throwing', function () {
    $client = makeIpAddressClient([
        '/ip/address/print' => [
            ['.id' => '*1', 'address' => '192.168.1.1/24', 'disabled' => 'false'],
        ],
    ]);

    $manager = new IpAddressManager($client);

    expect(fn () => $manager->disableAddress('192.168.1.1/24'))
        ->not->toThrow(\Exception::class);
});

// ─── getAddressCount ──────────────────────────────────────────

it('returns correct address count', function () {
    $client = makeIpAddressClient([
        '/ip/address/print' => [
            ['address' => '192.168.1.1/24', 'interface' => 'ether1'],
            ['address' => '10.0.0.1/8',     'interface' => 'ether2'],
            ['address' => '172.16.0.1/16',  'interface' => 'ether3'],
        ],
    ]);

    $manager = new IpAddressManager($client);

    expect($manager->getAddressCount())->toBe(3);
});

// ─── isAddressAssigned ────────────────────────────────────────

it('returns true when address is assigned', function () {
    $client = makeIpAddressClient([
        '/ip/address/print' => [
            ['address' => '192.168.1.1/24', 'interface' => 'ether1'],
        ],
    ]);

    $manager = new IpAddressManager($client);

    expect($manager->isAddressAssigned('192.168.1.1/24'))->toBeTrue();
});

it('returns false when address is not assigned', function () {
    $client  = makeIpAddressClient(['/ip/address/print' => []]);
    $manager = new IpAddressManager($client);

    expect($manager->isAddressAssigned('99.99.99.99/24'))->toBeFalse();
});