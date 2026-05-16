<?php

namespace ZillEAli\MikrotikLaravel\Support;

/**
 * CachingProxy
 *
 * Transparent caching layer for any RouterOS manager.
 *
 * Wraps any manager (PppoeManager, HotspotManager, etc.) and
 * caches read method results in memory for the configured TTL.
 * Write methods (create, update, delete, kick, enable, disable)
 * bypass cache and automatically invalidate cached read results.
 *
 * Usage:
 *  $manager = new CachingProxy(
 *      new PppoeManager($client),
 *      ttl: 30  // seconds
 *  );
 *  $manager->getSecrets();        // hits router, caches result
 *  $manager->getSecrets();        // returns from cache
 *  $manager->createSecret([...]); // hits router, clears cache
 *  $manager->getSecrets();        // hits router again (cache was cleared)
 *
 * Via Facade (after MikrotikManager support added):
 *  MikroTik::pppoe()->cache(30)->getSecrets();
 *
 * @package ZillEAli\MikrotikLaravel\Support
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class CachingProxy
{
    /**
     * In-memory cache store.
     * Key: method signature (method + args hash)
     * Value: ['data' => mixed, 'expires_at' => int]
     *
     * @var array<string, array{data: mixed, expires_at: int}>
     */
    protected array $cache = [];

    /**
     * Method name prefixes that are considered write operations.
     * These bypass cache and trigger cache invalidation.
     *
     * @var string[]
     */
    protected array $writePrefixes = [
        'create', 'update', 'delete', 'add', 'remove',
        'enable', 'disable', 'kick', 'bulk', 'set',
        'reboot', 'flush', 'make', 'change',
    ];

    /**
     * @param object $manager Underlying manager instance (PppoeManager, etc.)
     * @param int    $ttl     Cache TTL in seconds
     */
    public function __construct(
        protected object $manager,
        protected int    $ttl = 30
    ) {
    }

    // =========================================================
    // Magic Proxy
    // =========================================================

    /**
     * Intercept all method calls on the underlying manager.
     *
     * - Read methods: check cache first, call manager on miss, store result
     * - Write methods: call manager directly, invalidate all cache entries
     *
     * @param  string $method    Method name being called
     * @param  array  $arguments Method arguments
     * @return mixed             Manager return value
     */
    public function __call(string $method, array $arguments): mixed
    {
        if ($this->isWriteMethod($method)) {
            $result = $this->manager->$method(...$arguments);
            $this->flush(); // invalidate all cached reads after write

            return $result;
        }

        $cacheKey = $this->makeCacheKey($method, $arguments);

        // Return cached result if still valid
        if ($this->hasValidCache($cacheKey)) {
            return $this->cache[$cacheKey]['data'];
        }

        // Call underlying manager and cache the result
        $result = $this->manager->$method(...$arguments);

        $this->cache[$cacheKey] = [
            'data' => $result,
            'expires_at' => time() + $this->ttl,
        ];

        return $result;
    }

    // =========================================================
    // Cache Management
    // =========================================================

    /**
     * Flush (clear) all cached entries.
     *
     * Called automatically after any write operation.
     * Can also be called manually to force fresh data.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * Get the number of entries currently in cache.
     *
     * @return int
     */
    public function cacheSize(): int
    {
        return count($this->cache);
    }

    /**
     * Get remaining TTL for a cached method in seconds.
     *
     * @param  string $method    Method name
     * @param  array  $arguments Method arguments
     * @return int               Remaining seconds, or 0 if not cached / expired
     */
    public function remainingTtl(string $method, array $arguments = []): int
    {
        $key = $this->makeCacheKey($method, $arguments);

        if (! $this->hasValidCache($key)) {
            return 0;
        }

        return max(0, $this->cache[$key]['expires_at'] - time());
    }

    // =========================================================
    // Internals
    // =========================================================

    /**
     * Check if a method is a write operation based on its prefix.
     *
     * @param  string $method Method name
     * @return bool
     */
    protected function isWriteMethod(string $method): bool
    {
        foreach ($this->writePrefixes as $prefix) {
            if (str_starts_with(strtolower($method), $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a unique cache key for a method + arguments combo.
     *
     * @param  string $method
     * @param  array  $arguments
     * @return string
     */
    protected function makeCacheKey(string $method, array $arguments): string
    {
        return $method . ':' . md5(serialize($arguments));
    }

    /**
     * Check if a cache entry exists and has not expired.
     *
     * @param  string $key Cache key
     * @return bool
     */
    protected function hasValidCache(string $key): bool
    {
        if (! isset($this->cache[$key])) {
            return false;
        }

        if (time() > $this->cache[$key]['expires_at']) {
            unset($this->cache[$key]);

            return false;
        }

        return true;
    }
}
