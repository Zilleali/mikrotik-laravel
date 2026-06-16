<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ResourceNotFoundException;
use ZillEAli\MikrotikLaravel\Support\HasIdValidation;

/**
 * DnsManager
 *
 * Manages MikroTik DNS settings, static entries, and cache.
 *
 * Critical for ISPs:
 *  - Set upstream DNS servers for the router
 *  - Add static DNS entries for internal services
 *  - Enable DNS server for clients (allow-remote-requests)
 *  - Flush DNS cache when entries become stale
 *  - Block domains via static entries pointing to 0.0.0.0
 *
 * Usage:
 *  $manager = new DnsManager($client);
 *  $manager->getSettings();
 *  $manager->addStaticEntry('nexalink.local', '192.168.1.100');
 *  $manager->setServers(['8.8.8.8', '1.1.1.1']);
 *  $manager->flushCache();
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class DnsManager
{
    use HasIdValidation;

    private const CMD_PRINT = '/ip/dns/print';
    private const CMD_SET = '/ip/dns/set';
    private const CMD_FLUSH = '/ip/dns/flush';
    private const CMD_CACHE_PRINT = '/ip/dns/cache/print';
    private const CMD_STATIC_PRINT = '/ip/dns/static/print';
    private const CMD_STATIC_ADD = '/ip/dns/static/add';
    private const CMD_STATIC_REMOVE = '/ip/dns/static/remove';
    private const CMD_STATIC_SET = '/ip/dns/static/set';

    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Settings
    // =========================================================

    /**
     * Get current DNS settings.
     *
     * @return array DNS config with servers, allow-remote-requests, cache-size, etc.
     */
    public function getSettings(): array
    {
        $result = $this->client->query(self::CMD_PRINT);

        return $result[0] ?? [];
    }

    /**
     * Set upstream DNS servers.
     *
     * @param  string[] $servers List of DNS server IPs
     * @return void
     *
     * Example:
     *  $manager->setServers(['8.8.8.8', '1.1.1.1']);
     */
    public function setServers(array $servers): void
    {
        $this->client->query(self::CMD_SET, [
            'servers' => implode(',', $servers),
        ]);
    }

    /**
     * Enable DNS server — allow clients to use router as DNS.
     *
     * @return void
     */
    public function enableRemoteRequests(): void
    {
        $this->client->query(self::CMD_SET, [
            'allow-remote-requests' => 'yes',
        ]);
    }

    /**
     * Disable DNS server — stop accepting client DNS queries.
     *
     * @return void
     */
    public function disableRemoteRequests(): void
    {
        $this->client->query(self::CMD_SET, [
            'allow-remote-requests' => 'no',
        ]);
    }

    /**
     * Check if router is accepting remote DNS requests.
     *
     * @return bool
     */
    public function isRemoteRequestsAllowed(): bool
    {
        $settings = $this->getSettings();

        return ($settings['allow-remote-requests'] ?? 'no') === 'yes';
    }

    // =========================================================
    // Cache
    // =========================================================

    /**
     * Get all DNS cache entries.
     *
     * @return array[] Cache entries with name, address, ttl
     */
    public function getCacheEntries(): array
    {
        return $this->client->query(self::CMD_CACHE_PRINT);
    }

    /**
     * Flush DNS cache — clears all cached entries.
     *
     * Useful when DNS changes are made and need to propagate immediately.
     *
     * @return void
     */
    public function flushCache(): void
    {
        $this->client->query(self::CMD_FLUSH);
    }

    // =========================================================
    // Static Entries
    // =========================================================

    /**
     * Get all static DNS entries.
     *
     * @return array[] Static entries with name, address, ttl, disabled
     */
    public function getStaticEntries(): array
    {
        return $this->client->query(self::CMD_STATIC_PRINT);
    }

    /**
     * Get a single static DNS entry by hostname.
     *
     * @param  string     $name Hostname e.g. 'router.local'
     * @return array|null       Entry data or null if not found
     */
    public function getStaticEntry(string $name): ?array
    {
        $entries = $this->getStaticEntries();

        foreach ($entries as $entry) {
            if (($entry['name'] ?? '') === $name) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Add a static DNS entry.
     *
     * Common ISP uses:
     *  - Internal service hostnames (nexalink.local → server IP)
     *  - Domain blocking (ads.example.com → 0.0.0.0)
     *  - CPE management (cpe-001.isp.net → customer router IP)
     *
     * @param  string      $name    Hostname e.g. 'nexalink.local'
     * @param  string      $address IP address to resolve to
     * @param  int|null    $ttl     TTL in seconds (null = router default)
     * @param  string|null $comment Optional comment
     * @return void
     */
    public function addStaticEntry(
        string  $name,
        string  $address,
        ?int    $ttl = null,
        ?string $comment = null
    ): void {
        $data = [
            'name' => $name,
            'address' => $address,
        ];

        if ($ttl !== null) {
            $data['ttl'] = (string) $ttl;
        }

        if ($comment !== null) {
            $data['comment'] = $comment;
        }

        $this->client->query(self::CMD_STATIC_ADD, $data);
    }

    /**
     * Update an existing static DNS entry.
     *
     * @param  string $name Hostname to update
     * @param  array  $data Fields to update e.g. ['address' => '10.0.0.1']
     * @return void
     */
    public function updateStaticEntry(string $name, array $data): void
    {
        $entry = $this->getStaticEntry($name);

        if (! $entry) {
            throw ResourceNotFoundException::for('dns-static-entry', $name);
        }

        $id = $this->extractId($entry, 'dns-static-entry');

        $this->client->query(
            self::CMD_STATIC_SET,
            array_merge(['.id' => $id], $data)
        );
    }

    /**
     * Remove a static DNS entry by hostname.
     *
     * @param  string $name Hostname to remove
     * @return void
     */
    public function removeStaticEntry(string $name): void
    {
        $entry = $this->getStaticEntry($name);

        if (! $entry) {
            throw ResourceNotFoundException::for('dns-static-entry', $name);
        }

        $id = $this->extractId($entry, 'dns-static-entry');

        $this->client->query(
            self::CMD_STATIC_REMOVE,
            ['.id' => $id]
        );
    }

    /**
     * Block a domain by pointing it to 0.0.0.0.
     *
     * Useful for ISP content filtering.
     *
     * @param  string      $domain  Domain to block
     * @param  string|null $comment Optional comment
     * @return void
     */
    public function blockDomain(string $domain, ?string $comment = null): void
    {
        $this->addStaticEntry($domain, '0.0.0.0', comment: $comment ?? 'blocked');
    }

    /**
     * Unblock a domain by removing its static entry.
     *
     * @param  string $domain Domain to unblock
     * @return void
     */
    public function unblockDomain(string $domain): void
    {
        $this->removeStaticEntry($domain);
    }
}
