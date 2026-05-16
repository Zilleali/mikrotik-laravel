<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * WirelessManager
 *
 * Manages MikroTik wireless interfaces, registration table
 * (connected clients), and access lists.
 *
 * Critical for ISPs running wireless distribution networks.
 *
 * Usage:
 *  $manager = new WirelessManager($client);
 *  $manager->getRegistrationTable();
 *  $manager->getConnectedClientsCount();
 *  $manager->addToAccessList('AA:BB:CC:DD:EE:FF', ['interface' => 'wlan1']);
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class WirelessManager
{
    private const CMD_IFACE_PRINT = '/interface/wireless/print';
    private const CMD_REG_TABLE = '/interface/wireless/registration-table/print';
    private const CMD_ACCESS_LIST_PRINT = '/interface/wireless/access-list/print';
    private const CMD_ACCESS_LIST_ADD = '/interface/wireless/access-list/add';
    private const CMD_ACCESS_LIST_REMOVE = '/interface/wireless/access-list/remove';

    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Wireless Interfaces
    // =========================================================

    /**
     * Get all wireless interfaces.
     *
     * @return array[] Interfaces with ssid, band, frequency, disabled, etc.
     */
    public function getInterfaces(): array
    {
        return $this->client->query(self::CMD_IFACE_PRINT);
    }

    // =========================================================
    // Registration Table (Connected Clients)
    // =========================================================

    /**
     * Get the full wireless registration table.
     *
     * Each entry is a currently connected wireless client with
     * signal strength, TX/RX rate, uptime, MAC address, etc.
     *
     * @return array[]
     */
    public function getRegistrationTable(): array
    {
        return $this->client->query(self::CMD_REG_TABLE);
    }

    /**
     * Get connected clients for a specific wireless interface.
     *
     * @param  string  $interface Interface name e.g. 'wlan1'
     * @return array[]
     */
    public function getRegistrationByInterface(string $interface): array
    {
        $all = $this->getRegistrationTable();

        return array_values(
            array_filter($all, fn ($c) => ($c['interface'] ?? '') === $interface)
        );
    }

    /**
     * Get total count of connected wireless clients across all interfaces.
     *
     * @return int
     */
    public function getConnectedClientsCount(): int
    {
        return count($this->getRegistrationTable());
    }

    // =========================================================
    // Access List
    // =========================================================

    /**
     * Get all wireless access list entries.
     *
     * The access list controls which MAC addresses can connect
     * to the wireless interface.
     *
     * @return array[]
     */
    public function getAccessList(): array
    {
        return $this->client->query(self::CMD_ACCESS_LIST_PRINT);
    }

    /**
     * Add a MAC address to the wireless access list.
     *
     * @param  string $mac  Device MAC address e.g. 'AA:BB:CC:DD:EE:FF'
     * @param  array  $data Optional: interface, authentication, comment, etc.
     * @return void
     *
     * Example:
     *  $manager->addToAccessList('AA:BB:CC:DD:EE:FF', [
     *      'interface'      => 'wlan1',
     *      'authentication' => 'true',
     *      'comment'        => 'office laptop',
     *  ]);
     */
    public function addToAccessList(string $mac, array $data = []): void
    {
        $this->client->query(
            self::CMD_ACCESS_LIST_ADD,
            array_merge(['mac-address' => $mac], $data)
        );
    }

    /**
     * Remove a MAC address from the wireless access list.
     *
     * @param  string $mac Device MAC address to remove
     * @return void
     */
    public function removeFromAccessList(string $mac): void
    {
        $entries = $this->client->query(
            self::CMD_ACCESS_LIST_PRINT,
            queries: ["mac-address={$mac}"]
        );

        if (empty($entries)) {
            return;
        }

        $this->client->query(
            self::CMD_ACCESS_LIST_REMOVE,
            ['.id' => $entries[0]['.id']]
        );
    }
}
