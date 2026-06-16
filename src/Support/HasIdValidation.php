<?php

namespace ZillEAli\MikrotikLaravel\Support;

trait HasIdValidation
{
    /**
     * Extract and validate the RouterOS .id field from a resource array.
     *
     * RouterOS API includes .id in all print responses for writable resources.
     * If .id is absent or empty the response is malformed and the write
     * operation must not proceed — passing an empty .id silently corrupts
     * the wrong resource on the router.
     *
     * @param  array<string, mixed> $resource Resource array from RouterOS API
     * @return string|null                    The .id value, or null if missing/empty
     */
    protected function extractId(array $resource): ?string
    {
        $id = $resource['.id'] ?? null;

        if ($id === null || $id === '') {
            return null;
        }

        return (string) $id;
    }
}
