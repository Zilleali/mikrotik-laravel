<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * IpPoolManager
 *
 * Manages MikroTik IP pools — address ranges assigned
 * to PPPoE, DHCP, and Hotspot services.
 *
 * Usage:
 *  $manager = new IpPoolManager($client);
 *  $manager->getPools();
 *  $manager->getUsedAddresses('pppoe-pool');
 *  $manager->getUsedCount('pppoe-pool');
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class IpPoolManager
{
    private const CMD_POOL_PRINT = '/ip/pool/print';
    private const CMD_POOL_ADD = '/ip/pool/add';
    private const CMD_POOL_REMOVE = '/ip/pool/remove';
    private const CMD_USED_PRINT = '/ip/pool/used/print';

    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Pools
    // =========================================================

    /**
     * Get all IP pools configured on the router.
     *
     * @return array[] Pools with name, ranges, next-pool
     */
    public function getPools(): array
    {
        return $this->client->query(self::CMD_POOL_PRINT);
    }

    /**
     * Get a single IP pool by name.
     *
     * @param  string     $name Pool name e.g. 'pppoe-pool'
     * @return array|null       Pool data or null if not found
     */
    public function getPool(string $name): ?array
    {
        $pools = $this->client->query(
            self::CMD_POOL_PRINT,
            queries: ["name={$name}"]
        );

        return $pools[0] ?? null;
    }

    /**
     * Create a new IP pool.
     *
     * @param  array $data Required: name, ranges. Optional: next-pool, comment
     * @return void
     *
     * Example:
     *  $manager->createPool([
     *      'name'      => 'pppoe-pool',
     *      'ranges'    => '10.0.0.1-10.0.0.254',
     *      'comment'   => 'PPPoE user pool',
     *  ]);
     */
    public function createPool(array $data): void
    {
        $this->client->query(self::CMD_POOL_ADD, $data);
    }

    /**
     * Delete an IP pool by name.
     *
     * @param  string $name Pool name to delete
     * @return void
     */
    public function deletePool(string $name): void
    {
        $pool = $this->getPool($name);

        if (! $pool) {
            return;
        }

        $this->client->query(
            self::CMD_POOL_REMOVE,
            ['.id' => $pool['.id']]
        );
    }

    // =========================================================
    // Used Addresses
    // =========================================================

    /**
     * Get all currently used IP addresses from a pool.
     *
     * Each entry shows which user/service is using the address.
     *
     * @param  string  $poolName Pool name to query
     * @return array[]           Used addresses with pool, address, info
     */
    public function getUsedAddresses(string $poolName): array
    {
        return $this->client->query(
            self::CMD_USED_PRINT,
            queries: ["pool={$poolName}"]
        );
    }

    /**
     * Get count of used IP addresses in a pool.
     *
     * @param  string $poolName Pool name
     * @return int              Number of used addresses
     */
    public function getUsedCount(string $poolName): int
    {
        return count($this->getUsedAddresses($poolName));
    }
}
