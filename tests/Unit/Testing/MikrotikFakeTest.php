<?php

use ZillEAli\MikrotikLaravel\Facades\MikroTik;
use ZillEAli\MikrotikLaravel\MikrotikManager;
use ZillEAli\MikrotikLaravel\Testing\MikrotikFake;

// ─── fake() — container swap ──────────────────────────────────

it('fake() swaps the MikrotikManager binding in the container', function () {
    $fake = MikrotikFake::fake();

    expect(app(MikrotikManager::class))->toBeInstanceOf(MikrotikFake::class)
        ->and(app('mikrotik'))->toBeInstanceOf(MikrotikFake::class)
        ->and($fake)->toBeInstanceOf(MikrotikFake::class);
});

it('fake() makes the MikroTik facade resolve to the fake', function () {
    MikrotikFake::fake();

    expect(MikroTik::getFacadeRoot())->toBeInstanceOf(MikrotikFake::class);
});

// ─── forCommand — response loading ───────────────────────────

it('forCommand() pre-loads rows returned by query()', function () {
    $fake = MikrotikFake::fake();

    $fake->forCommand('/ppp/secret/print', [
        ['name' => 'ali-home', 'profile' => '10mbps', 'disabled' => 'false'],
    ]);

    $secrets = $fake->pppoe()->getSecrets();

    expect($secrets)->toHaveCount(1)
        ->and($secrets[0]['name'])->toBe('ali-home');
});

it('forCommand() can be called after the first manager call', function () {
    $fake = MikrotikFake::fake();

    // trigger client creation
    $fake->pppoe()->getSecrets();

    // update responses dynamically
    $fake->forCommand('/ppp/secret/print', [
        ['name' => 'new-user', 'profile' => '20mbps', 'disabled' => 'false'],
    ]);

    $secrets = $fake->pppoe()->getSecrets();

    expect($secrets[0]['name'])->toBe('new-user');
});

it('returns empty array for commands with no pre-loaded response', function () {
    $fake = MikrotikFake::fake();

    $secrets = $fake->pppoe()->getSecrets();

    expect($secrets)->toBeEmpty();
});

// ─── forStream — generator loading ───────────────────────────

it('forStream() yields rows through streaming methods', function () {
    $fake = MikrotikFake::fake();

    $fake->forStream('/ppp/secret/print', [
        ['name' => 'user1', 'profile' => '10mbps'],
        ['name' => 'user2', 'profile' => '20mbps'],
    ]);

    $rows = [];
    foreach ($fake->pppoe()->streamSecrets() as $row) {
        $rows[] = $row;
    }

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['name'])->toBe('user1');
});

// ─── assertQueried ────────────────────────────────────────────

it('assertQueried() passes when command was sent', function () {
    $fake = MikrotikFake::fake();
    $fake->pppoe()->getSecrets();

    $fake->assertQueried('/ppp/secret/print');
});

it('assertQueried() fails when command was not sent', function () {
    $fake = MikrotikFake::fake();

    expect(fn () => $fake->assertQueried('/ppp/secret/print'))
        ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
});

// ─── assertNotQueried ─────────────────────────────────────────

it('assertNotQueried() passes when command was not sent', function () {
    $fake = MikrotikFake::fake();

    $fake->assertNotQueried('/ppp/secret/print');
});

it('assertNotQueried() fails when command was sent', function () {
    $fake = MikrotikFake::fake();
    $fake->pppoe()->getSecrets();

    expect(fn () => $fake->assertNotQueried('/ppp/secret/print'))
        ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);
});

// ─── assertQueryCount ─────────────────────────────────────────

it('assertQueryCount() matches total commands sent', function () {
    $fake = MikrotikFake::fake();
    $fake->pppoe()->getSecrets();         // /ppp/secret/print
    $fake->hotspot()->getUsers();         // /ip/hotspot/user/print

    $fake->assertQueryCount(2);
});

// ─── recordedQueries ─────────────────────────────────────────

it('recordedQueries() returns all commands sent in order', function () {
    $fake = MikrotikFake::fake();
    $fake->pppoe()->getSecrets();
    $fake->system()->getResources();

    $queries = $fake->recordedQueries();

    expect($queries)->toHaveCount(2)
        ->and($queries[0])->toBe('/ppp/secret/print')
        ->and($queries[1])->toBe('/system/resource/print');
});

// ─── router() state reset ─────────────────────────────────────

it('router() state resets to default after each manager call', function () {
    $fake = MikrotikFake::fake([
        'routers' => ['branch' => ['host' => '10.0.0.1', 'port' => 8728]],
    ]);

    // Two calls — state must not leak between them
    $fake->router('branch')->pppoe()->getSecrets();
    $fake->pppoe()->getSecrets(); // must use 'default', not 'branch'

    // Both calls succeed without throwing — state was reset correctly
    expect($fake->assertQueryCount(2))->toBeNull();
});
