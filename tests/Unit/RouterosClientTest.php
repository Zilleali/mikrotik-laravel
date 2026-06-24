<?php

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;

// ─── Connection Tests ─────────────────────────────────────────

it('is not connected before connect() is called', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    expect($client->isConnected())->toBeFalse();
});

it('throws ConnectionException on unreachable host', function () {
    $client = new RouterosClient(
        host:    '192.0.2.1', // RFC 5737 — guaranteed unreachable
        port:    8728,
        timeout: 1,
    );

    expect(fn () => $client->connect())
        ->toThrow(ConnectionException::class);
});

it('disconnects cleanly without error', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    // Should not throw even if never connected
    $client->disconnect();

    expect($client->isConnected())->toBeFalse();
});

// ─── Length Encoding Tests ────────────────────────────────────

it('encodes length < 0x80 as 1 byte', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    $method = new ReflectionMethod($client, 'encodeLength');
    $method->setAccessible(true);

    expect(strlen($method->invoke($client, 0)))->toBe(1)
        ->and(strlen($method->invoke($client, 50)))->toBe(1)
        ->and(strlen($method->invoke($client, 127)))->toBe(1);
});

it('encodes length 0x80–0x3FFF as 2 bytes', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    $method = new ReflectionMethod($client, 'encodeLength');
    $method->setAccessible(true);

    expect(strlen($method->invoke($client, 128)))->toBe(2)
        ->and(strlen($method->invoke($client, 200)))->toBe(2)
        ->and(strlen($method->invoke($client, 0x3FFF)))->toBe(2);
});

it('encodes length 0x4000–0x1FFFFF as 3 bytes', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    $method = new ReflectionMethod($client, 'encodeLength');
    $method->setAccessible(true);

    expect(strlen($method->invoke($client, 0x4000)))->toBe(3)
        ->and(strlen($method->invoke($client, 0x1FFFFF)))->toBe(3);
});

// ─── Query Builder Tests ──────────────────────────────────────

it('throws ConnectionException if query called without connect', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    expect(fn () => $client->query('/ip/address/print'))
        ->toThrow(ConnectionException::class, 'Not connected');
});

it('throws ConnectionException if send called without connect', function () {
    $client = new RouterosClient(host: '192.168.88.1');

    expect(fn () => $client->send(['/system/identity/print']))
        ->toThrow(ConnectionException::class);
});

// ─── query() Row Parsing Tests ────────────────────────────────
function makeQueryClient(array $sendResponse): RouterosClient
{
    return new class ($sendResponse) extends RouterosClient {
        public function __construct(private array $sendResponse)
        {
            parent::__construct(host: '127.0.0.1');
        }

        public function send(array $words): array
        {
            return $this->sendResponse;
        }

        public function isConnected(): bool
        {
            return true;
        }
    };
}

it('query() returns multiple rows from a multi-result response', function () {
    // Wire format after readResponse() strips !re but keeps empty-word terminators:
    // row 1: =.id=*0, =name=default, ""
    // row 2: =.id=*1, =name=1-Hour,  ""
    // row 3: =.id=*2, =name=12-Hours,""
    $client = makeQueryClient([
        '=.id=*0', '=name=default',  '=rate-limit=2M/2M',  '',
        '=.id=*1', '=name=1-Hour',   '=rate-limit=5M/5M',  '',
        '=.id=*2', '=name=12-Hours', '=rate-limit=10M/10M', '',
    ]);

    $result = $client->query('/ip/hotspot/user/profile/print');

    expect($result)->toHaveCount(3)
        ->and($result[0]['name'])->toBe('default')
        ->and($result[1]['name'])->toBe('1-Hour')
        ->and($result[2]['name'])->toBe('12-Hours');
});

it('query() returns correct field values for each row', function () {
    $client = makeQueryClient([
        '=.id=*0', '=name=default',  '=rate-limit=2M/2M',  '',
        '=.id=*1', '=name=premium',  '=rate-limit=20M/20M', '',
    ]);

    $result = $client->query('/ip/hotspot/user/profile/print');

    expect($result[0])->toMatchArray(['.id' => '*0', 'name' => 'default',  'rate-limit' => '2M/2M'])
        ->and($result[1])->toMatchArray(['.id' => '*1', 'name' => 'premium', 'rate-limit' => '20M/20M']);
});

it('query() returns single row when response contains one entry', function () {
    $client = makeQueryClient([
        '=.id=*1', '=name=7-days', '=shared-users=1', '',
    ]);

    $result = $client->query('/ip/hotspot/user/profile/print');

    expect($result)->toHaveCount(1)
        ->and($result[0]['name'])->toBe('7-days');
});

it('query() returns empty array when response is empty', function () {
    $client = makeQueryClient([]);

    expect($client->query('/ip/hotspot/user/profile/print'))->toBeEmpty();
});

it('query() does not duplicate the last row', function () {
    $client = makeQueryClient([
        '=.id=*0', '=name=default', '',
        '=.id=*1', '=name=premium', '',
    ]);

    expect($client->query('/ip/hotspot/user/profile/print'))->toHaveCount(2);
});

// ─── readResponse() Tests ─────────────────────────────────────

it('readResponse() strips !re markers but preserves empty-word row terminators', function () {
    $client = new RouterosClient(host: '192.168.88.1');
    $method = new ReflectionMethod($client, 'readResponse');
    $method->setAccessible(true);

    $encode = fn (string $word): string => chr(strlen($word)) . $word;

    $wireData = $encode('!re')
        . $encode('=.id=*0')
        . $encode('=name=test')
        . $encode('')
        . $encode('!done');

    $socket = fopen('php://memory', 'r+');
    fwrite($socket, $wireData);
    rewind($socket);

    $socketProp = new ReflectionProperty($client, 'socket');
    $socketProp->setAccessible(true);
    $socketProp->setValue($client, $socket);

    $connectedProp = new ReflectionProperty($client, 'connected');
    $connectedProp->setAccessible(true);
    $connectedProp->setValue($client, true);

    $response = $method->invoke($client);

    expect($response)->toBe(['=.id=*0', '=name=test', ''])
        ->and($response)->not->toContain('!re');
});
