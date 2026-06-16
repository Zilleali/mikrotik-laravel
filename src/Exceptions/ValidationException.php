<?php

namespace ZillEAli\MikrotikLaravel\Exceptions;

use InvalidArgumentException;

/**
 * ValidationException
 *
 * Thrown when a caller passes an argument that would be rejected by the
 * RouterOS API or that would produce undefined router behaviour — e.g. an
 * empty queue name, a malformed IP address, or an invalid MAC address.
 *
 * Extends InvalidArgumentException so callers can catch either this class
 * or the standard PHP base class.
 *
 * @package ZillEAli\MikrotikLaravel\Exceptions
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class ValidationException extends InvalidArgumentException
{
    public static function emptyField(string $field): self
    {
        return new self("Field '{$field}' must not be empty.");
    }

    public static function invalidIp(string $field, string $value): self
    {
        return new self(
            "Field '{$field}' must be a valid IPv4 address, got '{$value}'."
        );
    }

    public static function invalidCidr(string $field, string $value): self
    {
        return new self(
            "Field '{$field}' must be a valid IPv4/prefix (e.g. 192.168.1.0/24), got '{$value}'."
        );
    }

    public static function invalidMac(string $field, string $value): self
    {
        return new self(
            "Field '{$field}' must be a valid MAC address (XX:XX:XX:XX:XX:XX), got '{$value}'."
        );
    }

    public static function invalidPort(string $field, int $value): self
    {
        return new self(
            "Field '{$field}' must be between 1 and 65535, got {$value}."
        );
    }

    public static function missingRequiredField(string $field, string $context): self
    {
        return new self(
            "Required field '{$field}' is missing or empty in {$context}."
        );
    }
}
