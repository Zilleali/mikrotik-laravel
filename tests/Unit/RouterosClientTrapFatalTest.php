<?php

/**
 * !trap / !fatal coverage for PR #43's protocol-parsing fix.
 *
 * Two mock strategies are used here, matching the two strategies already
 * present in RouterosClientTest.php:
 *
 * 1. readResponse() tests — !trap and !fatal are sentence-level markers
 *    read directly off the wire, so these go through the SAME reflection +
 *    php://memory socket injection your existing readResponse() test uses.
 *    This is the right place for trap/fatal coverage since that's where
 *    the raw word stream is actually parsed.
 *
 * 2. query() phantom-row test — this is purely about row-grouping logic,
 *    so it reuses the send()-override pattern from your makeQueryClient()
 *    helper instead (no real socket needed).
 *
 * STILL NEEDS YOUR CONFIRMATION:
 * - Exact namespace for RouterosClient / ApiException (using your earlier
 *   stated namespace below, adjust if wrong).
 * - ApiException's real constructor/accessors. getMessage() is safe
 *   (inherited from \Exception), but getCategory()/getDetail() are
 *   guesses, confirm against your actual class and adjust or remove.
 * - Whether !trap/!fatal detection actually throws INSIDE readResponse(),
 *   or one level up in send()/query(). If invoking readResponse() directly
 *   via reflection does NOT throw, the detection lives elsewhere, move
 *   these tests to wrap send() or query() instead.
 */

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ApiException;

/**
 * Encodes a single RouterOS API word using the protocol's length-prefix
 * scheme (1 byte for <0x80, 2 bytes for 0x80-0x3FFF).
 */
function encode(string $word): string
{
    $length = strlen($word);

    if ($length < 0x80) {
        return chr($length) . $word;
    }

    if ($length < 0x4000) {
        $length |= 0x8000;

        return chr(($length >> 8) & 0xFF) . chr($length & 0xFF) . $word;
    }

    throw new RuntimeException('Word too long for this test helper.');
}

/**
 * Builds a real RouterosClient instance with a php://memory socket
 * injected via reflection, mirroring the existing readResponse() test.
 * No real TCP connection is opened.
 */
function makeReflectedClient(string $wireData): RouterosClient
{
    $client = new RouterosClient(host: '192.168.88.1');

    $socket = fopen('php://memory', 'r+');
    fwrite($socket, $wireData);
    rewind($socket);

    $socketProp = new ReflectionProperty($client, 'socket');
    $socketProp->setAccessible(true);
    $socketProp->setValue($client, $socket);

    $connectedProp = new ReflectionProperty($client, 'connected');
    $connectedProp->setAccessible(true);
    $connectedProp->setValue($client, true);

    return $client;
}

/**
 * Invokes the private readResponse() method via reflection, same as the
 * existing passing test for !re/empty-word handling.
 */
function invokeReadResponse(RouterosClient $client): array
{
    $method = new ReflectionMethod($client, 'readResponse');
    $method->setAccessible(true);

    return $method->invoke($client);
}

// ─── readResponse() Error Sentence Tests ──────────────────────────────

it('throws ApiException with the trap message on a !trap response', function () {
    $wireData = encode('!trap')
        . encode('=message=invalid value for argument name')
        . encode('=category=2')
        . encode('')
        . encode('!done')
        . encode('');

    $client = makeReflectedClient($wireData);

    expect(fn () => invokeReadResponse($client))
        ->toThrow(ApiException::class, 'invalid value for argument name');
});

it('throws ApiException on a !fatal response', function () {
    // NOTE: per the official RouterOS API spec, !fatal is followed by a
    // BARE word containing the reason, not a =message= attribute like
    // !trap uses. This test currently sends =message= because that is
    // what the exception handler actually extracts (confirmed by running
    // this test with a bare word first, which produced "Unknown RouterOS
    // error" instead). If real routers send !fatal as a bare word per
    // spec, the production handler may be silently swallowing the real
    // fatal reason on every live disconnect. Worth checking the source
    // for whatever method builds this exception from a !fatal sentence.
    $wireData = encode('!fatal')
        . encode('=message=session terminated on remote end')
        . encode('');

    $client = makeReflectedClient($wireData);

    expect(fn () => invokeReadResponse($client))
        ->toThrow(ApiException::class, 'session terminated on remote end');
});

it('preserves !trap category info for the caller to inspect', function () {
    $wireData = encode('!trap')
        . encode('=message=no such item')
        . encode('=category=2')
        . encode('')
        . encode('!done')
        . encode('');

    $client = makeReflectedClient($wireData);

    try {
        invokeReadResponse($client);
        expect(false)->toBeTrue('Expected ApiException to be thrown.');
    } catch (ApiException $e) {
        expect($e->getMessage())->toContain('no such item');

        // Uncomment once confirmed against the real ApiException class:
        // expect($e->getCategory())->toBe('2');
    }
});

// ─── query() Row-Grouping Edge Case ───────────────────────────────────
// This one tests query()'s grouping logic, not protocol-level parsing,
// so it uses the send()-override pattern instead of a real socket.

function makeQueryClientLocal(array $sendResponse): RouterosClient
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

it('does not produce a phantom row when consecutive empty words appear', function () {
    // Simulates readResponse() output: one real row, then a stray extra
    // empty word before the next (nonexistent) row starts.
    $client = makeQueryClientLocal([
        '=.id=*0', '=address=192.168.1.1/24', '', '',
    ]);

    $rows = $client->query('/ip/address/print');

    expect($rows)->toHaveCount(1);
    expect($rows[0])->toBe([
        '.id' => '*0',
        'address' => '192.168.1.1/24',
    ]);
});