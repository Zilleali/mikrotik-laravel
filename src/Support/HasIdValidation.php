<?php

namespace ZillEAli\MikrotikLaravel\Support;

use ZillEAli\MikrotikLaravel\Exceptions\InvalidRouterResponseException;

trait HasIdValidation
{
    /**
     * Extract and validate the RouterOS .id field from a resource array.
     *
     * RouterOS includes .id in all print responses for writable resources.
     * If .id is absent or empty the response is malformed — passing an empty
     * .id would silently corrupt the wrong resource on the router.
     *
     * @param  array<string, mixed> $resource Resource array from RouterOS API
     * @param  string               $context  Human-readable resource type for the error message
     * @return string                         The .id value (never empty)
     *
     * @throws InvalidRouterResponseException If .id is missing or empty
     */
    protected function extractId(array $resource, string $context = 'resource'): string
    {
        $id = $resource['.id'] ?? null;

        if ($id === null || $id === '') {
            throw InvalidRouterResponseException::missingId($context);
        }

        return (string) $id;
    }
}
