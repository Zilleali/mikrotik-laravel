<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\FirewallManager;

// ─── Helper ───────────────────────────────────────────────────

function makeFirewallClient(array $responses = []): RouterosClient
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

// ─── getFilterRules ───────────────────────────────────────────

it('returns all firewall filter rules', function () {
    $client = makeFirewallClient([
        '/ip/firewall/filter/print' => [
            ['chain' => 'input',   'action' => 'accept', 'protocol' => 'tcp'],
            ['chain' => 'forward', 'action' => 'drop',   'protocol' => 'udp'],
        ],
    ]);

    $manager = new FirewallManager($client);
    $rules = $manager->getFilterRules();

    expect($rules)->toHaveCount(2)
        ->and($rules[0]['chain'])->toBe('input')
        ->and($rules[1]['action'])->toBe('drop');
});

it('returns empty array when no filter rules exist', function () {
    $client = makeFirewallClient(['/ip/firewall/filter/print' => []]);
    $manager = new FirewallManager($client);

    expect($manager->getFilterRules())->toBeEmpty();
});

// ─── getNatRules ──────────────────────────────────────────────

it('returns all NAT rules', function () {
    $client = makeFirewallClient([
        '/ip/firewall/nat/print' => [
            ['chain' => 'srcnat', 'action' => 'masquerade', 'out-interface' => 'ether1'],
        ],
    ]);

    $manager = new FirewallManager($client);
    $rules = $manager->getNatRules();

    expect($rules)->toHaveCount(1)
        ->and($rules[0]['action'])->toBe('masquerade');
});

// ─── getMangleRules ───────────────────────────────────────────

it('returns all mangle rules', function () {
    $client = makeFirewallClient([
        '/ip/firewall/mangle/print' => [
            ['chain' => 'prerouting', 'action' => 'mark-connection', 'new-connection-mark' => 'isp1'],
        ],
    ]);

    $manager = new FirewallManager($client);
    $rules = $manager->getMangleRules();

    expect($rules)->toHaveCount(1)
        ->and($rules[0]['chain'])->toBe('prerouting');
});

// ─── getAddressLists ──────────────────────────────────────────

it('returns all address lists', function () {
    $client = makeFirewallClient([
        '/ip/firewall/address-list/print' => [
            ['list' => 'blocked',   'address' => '1.2.3.4'],
            ['list' => 'whitelist', 'address' => '8.8.8.8'],
        ],
    ]);

    $manager = new FirewallManager($client);
    $lists = $manager->getAddressLists();

    expect($lists)->toHaveCount(2)
        ->and($lists[0]['list'])->toBe('blocked')
        ->and($lists[1]['address'])->toBe('8.8.8.8');
});

// ─── addToAddressList ─────────────────────────────────────────

it('adds ip to address list without throwing', function () {
    $client = makeFirewallClient();
    $manager = new FirewallManager($client);

    expect(fn () => $manager->addToAddressList('1.2.3.4', 'blocked'))
        ->not->toThrow(\Exception::class);
});

it('adds ip with comment to address list', function () {
    $client = makeFirewallClient();
    $manager = new FirewallManager($client);

    expect(fn () => $manager->addToAddressList('1.2.3.4', 'blocked', 'spam IP'))
        ->not->toThrow(\Exception::class);
});

// ─── removeFromAddressList ────────────────────────────────────

it('removes ip from address list without throwing', function () {
    $client = makeFirewallClient([
        '/ip/firewall/address-list/print' => [
            ['.id' => '*1', 'list' => 'blocked', 'address' => '1.2.3.4'],
        ],
    ]);

    $manager = new FirewallManager($client);

    expect(fn () => $manager->removeFromAddressList('1.2.3.4', 'blocked'))
        ->not->toThrow(\Exception::class);
});

// ─── addFilterRule ────────────────────────────────────────────

it('adds firewall filter rule without throwing', function () {
    $client = makeFirewallClient();
    $manager = new FirewallManager($client);

    expect(fn () => $manager->addFilterRule([
        'chain' => 'input',
        'action' => 'drop',
        'src-address' => '1.2.3.4',
    ]))->not->toThrow(\Exception::class);
});

// ─── addNatRule ───────────────────────────────────────────────

it('adds NAT rule without throwing', function () {
    $client = makeFirewallClient();
    $manager = new FirewallManager($client);

    expect(fn () => $manager->addNatRule([
        'chain' => 'srcnat',
        'action' => 'masquerade',
        'out-interface' => 'ether1',
    ]))->not->toThrow(\Exception::class);
});

// ─── addMangleRule ────────────────────────────────────────────

it('adds mangle rule without throwing', function () {
    $client = makeFirewallClient();
    $manager = new FirewallManager($client);

    expect(fn () => $manager->addMangleRule([
        'chain' => 'prerouting',
        'action' => 'mark-packet',
        'new-packet-mark' => 'isp1',
    ]))->not->toThrow(\Exception::class);
});

// ─── isIpBlocked ──────────────────────────────────────────────

it('returns true when ip is in blocked list', function () {
    $client = makeFirewallClient([
        '/ip/firewall/address-list/print' => [
            ['.id' => '*1', 'list' => 'blocked', 'address' => '1.2.3.4'],
        ],
    ]);

    $manager = new FirewallManager($client);

    expect($manager->isIpInList('1.2.3.4', 'blocked'))->toBeTrue();
});

it('returns false when ip is not in list', function () {
    $client = makeFirewallClient(['/ip/firewall/address-list/print' => []]);
    $manager = new FirewallManager($client);

    expect($manager->isIpInList('9.9.9.9', 'blocked'))->toBeFalse();
});
