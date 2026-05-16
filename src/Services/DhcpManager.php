<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * DhcpManager
 *
 * Manages MikroTik DHCP server leases and servers.
 *
 * Usage:
 *  $manager = new DhcpManager($client);
 *  $manager->getLeases();
 *  $manager->getLeaseByMac('AA:BB:CC:DD:EE:FF');
 *  $manager->makeLeaseStatic('AA:BB:CC:DD:EE:FF');
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class DhcpManager
{
    private const CMD_LEASE_PRINT  = '/ip/dhcp-server/lease/print';
    private const CMD_LEASE_SET    = '/ip/dhcp-server/lease/set';
    private const CMD_LEASE_REMOVE = '/ip/dhcp-server/lease/remove';
    private const CMD_SERVER_PRINT = '/ip/dhcp-server/print';

    public function __construct(
        protected RouterosClient $client
    ) {}

    // =========================================================
    // Leases
    // =========================================================

    /**
     * Get all DHCP leases (active, waiting, expired).
     *
     * @return array[]
     */
    public function getLeases(): array
    {
        return $this->client->query(self::CMD_LEASE_PRINT);
    }

    /**
     * Get a DHCP lease by MAC address.
     *
     * @param  string     $mac MAC address e.g. 'AA:BB:CC:DD:EE:FF'
     * @return array|null      Lease data or null if not found
     */
    public function getLeaseByMac(string $mac): ?array
    {
        $leases = $this->getLeases();

        foreach ($leases as $lease) {
            if (strtoupper($lease['mac-address'] ?? '') === strtoupper($mac)) {
                return $lease;
            }
        }

        return null;
    }

    /**
     * Get a DHCP lease by IP address.
     *
     * @param  string     $ip IP address e.g. '192.168.1.10'
     * @return array|null     Lease data or null if not found
     */
    public function getLeaseByIp(string $ip): ?array
    {
        $leases = $this->client->query(
            self::CMD_LEASE_PRINT,
            queries: ["address={$ip}"]
        );

        return $leases[0] ?? null;
    }

    /**
     * Get count of currently active (bound) leases.
     *
     * @return int
     */
    public function getActiveLeasesCount(): int
    {
        $leases = $this->getLeases();

        return count(
            array_filter($leases, fn ($l) => ($l['status'] ?? '') === 'bound')
        );
    }

    /**
     * Convert a dynamic lease to a static lease.
     *
     * Ensures the device always gets the same IP on the network.
     *
     * @param  string $mac MAC address of the lease to make static
     * @return void
     */
    public function makeLeaseStatic(string $mac): void
    {
        $lease = $this->getLeaseByMac($mac);

        if (! $lease) {
            return;
        }

        $this->client->query(
            self::CMD_LEASE_SET,
            ['.id' => $lease['.id'], 'address' => $lease['address']]
        );
    }

    /**
     * Delete a DHCP lease by MAC address.
     *
     * @param  string $mac MAC address of the lease to delete
     * @return void
     */
    public function deleteLease(string $mac): void
    {
        $lease = $this->getLeaseByMac($mac);

        if (! $lease) {
            return;
        }

        $this->client->query(
            self::CMD_LEASE_REMOVE,
            ['.id' => $lease['.id']]
        );
    }

    // =========================================================
    // Servers
    // =========================================================

    /**
     * Get all DHCP servers configured on the router.
     *
     * @return array[]
     */
    public function getServers(): array
    {
        return $this->client->query(self::CMD_SERVER_PRINT);
    }
}