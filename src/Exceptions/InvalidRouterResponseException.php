<?php

namespace ZillEAli\MikrotikLaravel\Exceptions;

use RuntimeException;

/**
 * InvalidRouterResponseException
 *
 * Thrown when the RouterOS API returns a resource that is missing
 * an expected field — most commonly the '.id' field that all writable
 * resources must have.
 *
 * If this is thrown it indicates either a RouterOS firmware bug, an
 * API permission issue that stripped the field, or a malformed mock
 * in tests.
 *
 * @package ZillEAli\MikrotikLaravel\Exceptions
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class InvalidRouterResponseException extends RuntimeException
{
    /**
     * Create exception for a resource that is missing the '.id' field.
     *
     * @param  string $context Human-readable context e.g. 'pppoe-secret'
     */
    public static function missingId(string $context): self
    {
        return new self(
            "RouterOS returned a '{$context}' resource without the required '.id' field. "
            . 'Check that the API user has sufficient read permissions.'
        );
    }
}
