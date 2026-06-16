<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ResourceNotFoundException;
use ZillEAli\MikrotikLaravel\Support\HasIdValidation;
use ZillEAli\MikrotikLaravel\Support\HasValidation;

/**
 * ArpManager
 *
 * Manages MikroTik ARP table — dynamic and static entries.
 *
 * Critical for ISPs:
 *  - Track which MAC address is using which IP
 *  - Add static ARP for fixed client bindings
 *  - Flush stale ARP cache entries
 *  - Detect IP conflicts by MAC lookup
 *
 * Usage:
 *  $manager = new ArpManager($client);
 *  $manager->getArpTable();
 *  $manager->getMacByIp('192.168.1.10');
 *  $manager->addStaticArp('192.168.1.100', 'AA:BB:CC:DD:EE:FF', 'ether1');
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class ArpManager
{
    use HasIdValidation;
    use HasValidation;

    private const CMD_PRINT = '/ip/arp/print';
    private const CMD_ADD = '/ip/arp/add';
    private const CMD_REMOVE = '/ip/arp/remove';
    private const CMD_FLUSH = '/ip/arp/flush';

    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Read
    // =========================================================

    /**
     * Get the full ARP table.
     *
     * @return array[] ARP entries with address, mac-address, interface, status
     */
    public function getArpTable(): array
    {
        return $this->client->query(self::CMD_PRINT);
    }

    /**
     * Get ARP entry by IP address.
     *
     * @param  string     $ip IP address to lookup
     * @return array|null     ARP entry or null if not found
     */
    public function getArpByIp(string $ip): ?array
    {
        $table = $this->getArpTable();

        foreach ($table as $entry) {
            if (($entry['address'] ?? '') === $ip) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Get ARP entry by MAC address.
     *
     * @param  string     $mac MAC address e.g. 'AA:BB:CC:DD:EE:FF'
     * @return array|null      ARP entry or null if not found
     */
    public function getArpByMac(string $mac): ?array
    {
        $table = $this->getArpTable();

        foreach ($table as $entry) {
            if (strtoupper($entry['mac-address'] ?? '') === strtoupper($mac)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Get all ARP entries for a specific interface.
     *
     * @param  string  $interface Interface name e.g. 'ether1'
     * @return array[]
     */
    public function getArpByInterface(string $interface): array
    {
        $table = $this->getArpTable();

        return array_values(
            array_filter($table, fn ($e) => ($e['interface'] ?? '') === $interface)
        );
    }

    /**
     * Get only static ARP entries.
     *
     * Static entries are manually added and persist across reboots.
     *
     * @return array[]
     */
    public function getStaticArpEntries(): array
    {
        $table = $this->getArpTable();

        return array_values(
            array_filter($table, fn ($e) => ($e['STATIC'] ?? 'false') === 'true')
        );
    }

    /**
     * Get MAC address for a given IP from ARP table.
     *
     * Useful for ISP billing — identify device by IP.
     *
     * @param  string      $ip IP address to lookup
     * @return string|null     MAC address or null if not found
     */
    public function getMacByIp(string $ip): ?string
    {
        $entry = $this->getArpByIp($ip);

        return $entry['mac-address'] ?? null;
    }

    /**
     * Get IP address for a given MAC from ARP table.
     *
     * @param  string      $mac MAC address to lookup
     * @return string|null      IP address or null if not found
     */
    public function getIpByMac(string $mac): ?string
    {
        $entry = $this->getArpByMac($mac);

        return $entry['address'] ?? null;
    }

    // =========================================================
    // Write
    // =========================================================

    /**
     * Add a static ARP entry.
     *
     * Binds an IP to a MAC address permanently.
     * Used to prevent IP spoofing in ISP networks.
     *
     * @param  string      $ip        IP address
     * @param  string      $mac       MAC address
     * @param  string      $interface Interface name
     * @param  string|null $comment   Optional comment
     * @return void
     */
    public function addStaticArp(
        string  $ip,
        string  $mac,
        string  $interface,
        ?string $comment = null
    ): void {
        $this->validateIp($ip, 'address');
        $this->validateMac($mac, 'mac-address');
        $this->validateNotEmpty($interface, 'interface');
        $data = [
            'address' => $ip,
            'mac-address' => $mac,
            'interface' => $interface,
        ];

        if ($comment !== null) {
            $data['comment'] = $comment;
        }

        $this->client->query(self::CMD_ADD, $data);
    }

    /**
     * Remove an ARP entry by IP address.
     *
     * @param  string $ip IP address of entry to remove
     * @return void
     */
    public function removeArp(string $ip): void
    {
        $this->validateIp($ip, 'address');
        $entry = $this->getArpByIp($ip);

        if (! $entry) {
            throw ResourceNotFoundException::for('arp-entry', $ip);
        }

        $id = $this->extractId($entry, 'arp-entry');

        $this->client->query(
            self::CMD_REMOVE,
            ['.id' => $id]
        );
    }

    /**
     * Flush the ARP cache — removes all dynamic entries.
     *
     * Static entries are preserved.
     * Useful when IP assignments change and stale entries cause issues.
     *
     * @return void
     */
    public function flushArpCache(): void
    {
        $this->client->query(self::CMD_FLUSH);
    }
}
