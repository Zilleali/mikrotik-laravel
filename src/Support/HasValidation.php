<?php

namespace ZillEAli\MikrotikLaravel\Support;

use ZillEAli\MikrotikLaravel\Exceptions\ValidationException;

/**
 * HasValidation
 *
 * Provides lightweight input validation helpers for RouterOS parameter values.
 * All methods throw ValidationException on failure so callers get a clear
 * error before any API call is made.
 *
 * @package ZillEAli\MikrotikLaravel\Support
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
trait HasValidation
{
    /**
     * Assert that a string value is not empty (after trimming).
     */
    protected function validateNotEmpty(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw ValidationException::emptyField($field);
        }
    }

    /**
     * Assert that a string is a valid IPv4 address (no prefix).
     */
    protected function validateIp(string $ip, string $field = 'address'): void
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw ValidationException::invalidIp($field, $ip);
        }
    }

    /**
     * Assert that a string is a valid IPv4 address with optional /prefix.
     *
     * Accepts bare IPs ("192.168.1.1") and CIDR notation ("192.168.1.0/24").
     */
    protected function validateCidr(string $cidr, string $field = 'address'): void
    {
        $parts = explode('/', $cidr, 2);

        if (filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw ValidationException::invalidCidr($field, $cidr);
        }

        if (isset($parts[1])) {
            $prefix = $parts[1];
            if (! ctype_digit($prefix) || (int) $prefix > 32) {
                throw ValidationException::invalidCidr($field, $cidr);
            }
        }
    }

    /**
     * Assert that a string is a valid MAC address in XX:XX:XX:XX:XX:XX format.
     */
    protected function validateMac(string $mac, string $field = 'mac-address'): void
    {
        if (! preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $mac)) {
            throw ValidationException::invalidMac($field, $mac);
        }
    }

    /**
     * Assert that a port number is in the valid TCP/UDP range (1–65535).
     */
    protected function validatePort(int $port, string $field = 'port'): void
    {
        if ($port < 1 || $port > 65535) {
            throw ValidationException::invalidPort($field, $port);
        }
    }

    /**
     * Assert that all required keys exist and are non-empty in a data array.
     *
     * @param  array<string, mixed> $data
     * @param  string[]             $required
     */
    protected function validateRequiredKeys(array $data, array $required, string $context): void
    {
        foreach ($required as $key) {
            if (! isset($data[$key]) || (is_string($data[$key]) && trim($data[$key]) === '')) {
                throw ValidationException::missingRequiredField($key, $context);
            }
        }
    }
}
