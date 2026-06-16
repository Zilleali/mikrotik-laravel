<?php

namespace ZillEAli\MikrotikLaravel\Exceptions;

use RuntimeException;

/**
 * ResourceNotFoundException
 *
 * Thrown when a write operation (update, delete, enable, disable, kick)
 * targets a RouterOS resource that does not exist.
 *
 * Previously these operations returned silently. This exception lets callers
 * distinguish between "operation succeeded" and "resource was not found".
 *
 * @package ZillEAli\MikrotikLaravel\Exceptions
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class ResourceNotFoundException extends RuntimeException
{
    public function __construct(
        string      $message = '',
        private readonly string $resourceType = '',
        private readonly string $identifier = '',
        int         $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for a missing resource of any type.
     *
     * @param  string $resourceType Human-readable resource type e.g. 'pppoe-secret'
     * @param  string $identifier   The name / address / key used to look it up
     */
    public static function for(string $resourceType, string $identifier): self
    {
        return new self(
            "{$resourceType} '{$identifier}' not found on the router.",
            $resourceType,
            $identifier,
        );
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
