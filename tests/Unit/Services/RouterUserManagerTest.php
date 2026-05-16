<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Services\RouterUserManager;

function makeRouterUserClient(array $responses = []): RouterosClient
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

// ─── getUsers ─────────────────────────────────────────────────

it('returns all router users', function () {
    $client = makeRouterUserClient([
        '/user/print' => [
            ['name' => 'admin',   'group' => 'full',     'disabled' => 'false'],
            ['name' => 'noc',     'group' => 'read',     'disabled' => 'false'],
            ['name' => 'monitor', 'group' => 'api-only', 'disabled' => 'false'],
        ],
    ]);

    $manager = new RouterUserManager($client);
    $users = $manager->getUsers();

    expect($users)->toHaveCount(3)
        ->and($users[0]['name'])->toBe('admin')
        ->and($users[1]['group'])->toBe('read');
});

it('returns empty array when no users', function () {
    $client = makeRouterUserClient(['/user/print' => []]);
    $manager = new RouterUserManager($client);

    expect($manager->getUsers())->toBeEmpty();
});

// ─── getUser ──────────────────────────────────────────────────

it('returns single user by name', function () {
    $client = makeRouterUserClient([
        '/user/print' => [
            ['name' => 'noc', 'group' => 'read', 'comment' => 'NOC operator'],
        ],
    ]);

    $manager = new RouterUserManager($client);
    $user = $manager->getUser('noc');

    expect($user)->not->toBeNull()
        ->and($user['name'])->toBe('noc')
        ->and($user['comment'])->toBe('NOC operator');
});

it('returns null when user not found', function () {
    $client = makeRouterUserClient(['/user/print' => []]);
    $manager = new RouterUserManager($client);

    expect($manager->getUser('ghost'))->toBeNull();
});

// ─── getGroups ────────────────────────────────────────────────

it('returns all user groups', function () {
    $client = makeRouterUserClient([
        '/user/group/print' => [
            ['name' => 'full',     'policy' => 'local,telnet,ssh,ftp,reboot,read,write,policy,test,winbox,password,web,sniff,sensitive,api,romon,rest-api'],
            ['name' => 'read',     'policy' => 'local,telnet,ssh,ftp,read,winbox,web,api'],
            ['name' => 'write',    'policy' => 'local,telnet,ssh,ftp,read,write,winbox,web,api'],
        ],
    ]);

    $manager = new RouterUserManager($client);
    $groups = $manager->getGroups();

    expect($groups)->toHaveCount(3)
        ->and($groups[0]['name'])->toBe('full')
        ->and($groups[1]['name'])->toBe('read');
});

// ─── addUser ──────────────────────────────────────────────────

it('adds router user without throwing', function () {
    $client = makeRouterUserClient();
    $manager = new RouterUserManager($client);

    expect(fn () => $manager->addUser([
        'name' => 'noc-user',
        'password' => 'SecurePass123',
        'group' => 'read',
        'comment' => 'NOC read-only user',
    ]))->not->toThrow(\Exception::class);
});

// ─── deleteUser ───────────────────────────────────────────────

it('deletes router user without throwing', function () {
    $client = makeRouterUserClient([
        '/user/print' => [
            ['.id' => '*1', 'name' => 'noc-user', 'group' => 'read'],
        ],
    ]);

    $manager = new RouterUserManager($client);

    expect(fn () => $manager->deleteUser('noc-user'))
        ->not->toThrow(\Exception::class);
});

it('does not throw when deleting non-existent user', function () {
    $client = makeRouterUserClient(['/user/print' => []]);
    $manager = new RouterUserManager($client);

    expect(fn () => $manager->deleteUser('ghost'))
        ->not->toThrow(\Exception::class);
});

// ─── changePassword ───────────────────────────────────────────

it('changes password without throwing', function () {
    $client = makeRouterUserClient([
        '/user/print' => [
            ['.id' => '*1', 'name' => 'admin', 'group' => 'full'],
        ],
    ]);

    $manager = new RouterUserManager($client);

    expect(fn () => $manager->changePassword('admin', 'NewSecurePass!'))
        ->not->toThrow(\Exception::class);
});

// ─── enableUser / disableUser ─────────────────────────────────

it('enables router user without throwing', function () {
    $client = makeRouterUserClient([
        '/user/print' => [
            ['.id' => '*1', 'name' => 'noc', 'disabled' => 'true'],
        ],
    ]);

    $manager = new RouterUserManager($client);

    expect(fn () => $manager->enableUser('noc'))
        ->not->toThrow(\Exception::class);
});

it('disables router user without throwing', function () {
    $client = makeRouterUserClient([
        '/user/print' => [
            ['.id' => '*1', 'name' => 'noc', 'disabled' => 'false'],
        ],
    ]);

    $manager = new RouterUserManager($client);

    expect(fn () => $manager->disableUser('noc'))
        ->not->toThrow(\Exception::class);
});

// ─── getActiveSessions ────────────────────────────────────────

it('returns active user sessions (winbox/ssh/api)', function () {
    $client = makeRouterUserClient([
        '/user/active/print' => [
            ['name' => 'admin', 'address' => '192.168.1.5',  'via' => 'winbox', 'when' => '2h30m'],
            ['name' => 'noc',   'address' => '192.168.1.10', 'via' => 'api',    'when' => '45m'],
        ],
    ]);

    $manager = new RouterUserManager($client);
    $sessions = $manager->getActiveSessions();

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]['via'])->toBe('winbox')
        ->and($sessions[1]['name'])->toBe('noc');
});

// ─── isUserActive ─────────────────────────────────────────────

it('returns true when user has active session', function () {
    $client = makeRouterUserClient([
        '/user/active/print' => [
            ['name' => 'admin', 'address' => '192.168.1.5', 'via' => 'winbox'],
        ],
    ]);

    $manager = new RouterUserManager($client);

    expect($manager->isUserActive('admin'))->toBeTrue();
});

it('returns false when user has no active session', function () {
    $client = makeRouterUserClient(['/user/active/print' => []]);
    $manager = new RouterUserManager($client);

    expect($manager->isUserActive('admin'))->toBeFalse();
});
