<?php

use ZillEAli\MikrotikLaravel\Exceptions\ValidationException;
use ZillEAli\MikrotikLaravel\Support\HasValidation;

// ─── Test double ──────────────────────────────────────────────

function makeValidator(): object
{
    return new class {
        use HasValidation;

        public function notEmpty(string $value, string $field): void
        {
            $this->validateNotEmpty($value, $field);
        }

        public function ip(string $ip, string $field = 'address'): void
        {
            $this->validateIp($ip, $field);
        }

        public function cidr(string $cidr, string $field = 'address'): void
        {
            $this->validateCidr($cidr, $field);
        }

        public function mac(string $mac, string $field = 'mac-address'): void
        {
            $this->validateMac($mac, $field);
        }

        public function port(int $port, string $field = 'port'): void
        {
            $this->validatePort($port, $field);
        }

        public function required(array $data, array $keys, string $context): void
        {
            $this->validateRequiredKeys($data, $keys, $context);
        }
    };
}

// ─── validateNotEmpty ─────────────────────────────────────────

it('accepts a non-empty string', function () {
    $v = makeValidator();
    expect(fn () => $v->notEmpty('hello', 'field'))->not->toThrow(ValidationException::class);
});

it('throws on empty string', function () {
    $v = makeValidator();
    expect(fn () => $v->notEmpty('', 'name'))->toThrow(ValidationException::class);
});

it('throws on whitespace-only string', function () {
    $v = makeValidator();
    expect(fn () => $v->notEmpty('   ', 'name'))->toThrow(ValidationException::class);
});

it('empty field error message contains field name', function () {
    $v = makeValidator();
    try {
        $v->notEmpty('', 'username');
    } catch (ValidationException $e) {
        expect($e->getMessage())->toContain('username');
    }
});

// ─── validateIp ───────────────────────────────────────────────

it('accepts a valid ipv4 address', function () {
    $v = makeValidator();
    expect(fn () => $v->ip('192.168.1.1'))->not->toThrow(ValidationException::class);
});

it('throws on invalid ip address', function () {
    $v = makeValidator();
    expect(fn () => $v->ip('999.999.999.999'))->toThrow(ValidationException::class);
});

it('throws on non-ip string', function () {
    $v = makeValidator();
    expect(fn () => $v->ip('not-an-ip'))->toThrow(ValidationException::class);
});

it('throws on ipv6 address', function () {
    $v = makeValidator();
    expect(fn () => $v->ip('::1'))->toThrow(ValidationException::class);
});

it('throws on empty string for ip', function () {
    $v = makeValidator();
    expect(fn () => $v->ip(''))->toThrow(ValidationException::class);
});

// ─── validateCidr ─────────────────────────────────────────────

it('accepts a valid cidr', function () {
    $v = makeValidator();
    expect(fn () => $v->cidr('192.168.1.0/24'))->not->toThrow(ValidationException::class);
});

it('accepts an ip without prefix', function () {
    $v = makeValidator();
    expect(fn () => $v->cidr('10.0.0.1'))->not->toThrow(ValidationException::class);
});

it('throws on cidr with invalid ip', function () {
    $v = makeValidator();
    expect(fn () => $v->cidr('999.999.0.0/24'))->toThrow(ValidationException::class);
});

it('throws on cidr with prefix over 32', function () {
    $v = makeValidator();
    expect(fn () => $v->cidr('192.168.1.0/33'))->toThrow(ValidationException::class);
});

it('throws on cidr with non-numeric prefix', function () {
    $v = makeValidator();
    expect(fn () => $v->cidr('192.168.1.0/abc'))->toThrow(ValidationException::class);
});

// ─── validateMac ──────────────────────────────────────────────

it('accepts a valid mac address', function () {
    $v = makeValidator();
    expect(fn () => $v->mac('AA:BB:CC:DD:EE:FF'))->not->toThrow(ValidationException::class);
});

it('accepts lowercase mac address', function () {
    $v = makeValidator();
    expect(fn () => $v->mac('aa:bb:cc:dd:ee:ff'))->not->toThrow(ValidationException::class);
});

it('throws on mac with dashes instead of colons', function () {
    $v = makeValidator();
    expect(fn () => $v->mac('AA-BB-CC-DD-EE-FF'))->toThrow(ValidationException::class);
});

it('throws on mac with wrong length', function () {
    $v = makeValidator();
    expect(fn () => $v->mac('AA:BB:CC:DD:EE'))->toThrow(ValidationException::class);
});

it('throws on empty mac', function () {
    $v = makeValidator();
    expect(fn () => $v->mac(''))->toThrow(ValidationException::class);
});

// ─── validatePort ─────────────────────────────────────────────

it('accepts port 514', function () {
    $v = makeValidator();
    expect(fn () => $v->port(514))->not->toThrow(ValidationException::class);
});

it('accepts minimum port 1', function () {
    $v = makeValidator();
    expect(fn () => $v->port(1))->not->toThrow(ValidationException::class);
});

it('accepts maximum port 65535', function () {
    $v = makeValidator();
    expect(fn () => $v->port(65535))->not->toThrow(ValidationException::class);
});

it('throws on port 0', function () {
    $v = makeValidator();
    expect(fn () => $v->port(0))->toThrow(ValidationException::class);
});

it('throws on port over 65535', function () {
    $v = makeValidator();
    expect(fn () => $v->port(65536))->toThrow(ValidationException::class);
});

// ─── validateRequiredKeys ─────────────────────────────────────

it('accepts data with all required keys', function () {
    $v = makeValidator();
    expect(fn () => $v->required(['name' => 'foo', 'password' => 'bar'], ['name', 'password'], 'ctx'))
        ->not->toThrow(ValidationException::class);
});

it('throws when a required key is missing', function () {
    $v = makeValidator();
    expect(fn () => $v->required(['name' => 'foo'], ['name', 'password'], 'ctx'))
        ->toThrow(ValidationException::class);
});

it('throws when a required key is empty string', function () {
    $v = makeValidator();
    expect(fn () => $v->required(['name' => '', 'password' => 'bar'], ['name', 'password'], 'ctx'))
        ->toThrow(ValidationException::class);
});

it('throws when a required key is whitespace only', function () {
    $v = makeValidator();
    expect(fn () => $v->required(['name' => '   ', 'password' => 'bar'], ['name', 'password'], 'ctx'))
        ->toThrow(ValidationException::class);
});

it('missing required field error contains field name', function () {
    $v = makeValidator();
    try {
        $v->required(['name' => 'foo'], ['name', 'secret'], 'radius-server');
    } catch (ValidationException $e) {
        expect($e->getMessage())->toContain('secret');
    }
});
