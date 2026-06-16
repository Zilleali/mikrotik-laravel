<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ResourceNotFoundException;
use ZillEAli\MikrotikLaravel\Support\HasIdValidation;

/**
 * IpAddressManager
 *
 * Manages MikroTik IP address assignments on interfaces.
 *
 * Critical for ISPs:
 *  - Assign gateway IPs to interfaces
 *  - Manage IP ranges per interface
 *  - Enable/disable IP assignments dynamically
 *
 * Usage:
 *  $manager = new IpAddressManager($client);
 *  $manager->getAddresses();
 *  $manager->addAddress(['address' => '192.168.1.1/24', 'interface' => 'ether1']);
 *  $manager->getAddressesByInterface('ether1');
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class IpAddressManager
{
    use HasIdValidation;

    private const CMD_PRINT   = '/ip/address/print';
    private const CMD_ADD     = '/ip/address/add';
    private const CMD_SET     = '/ip/address/set';
    private const CMD_REMOVE  = '/ip/address/remove';
    private const CMD_ENABLE  = '/ip/address/enable';
    private const CMD_DISABLE = '/ip/address/disable';

    public function __construct(
        protected RouterosClient $client
    ) {}

    // =========================================================
    // Read
    // =========================================================

    /**
     * Get all IP addresses assigned on the router.
     *
     * @return array[] Addresses with address, interface, network, disabled
     */
    public function getAddresses(): array
    {
        return $this->client->query(self::CMD_PRINT);
    }

    /**
     * Get IP addresses assigned to a specific interface.
     *
     * @param  string  $interface Interface name e.g. 'ether1', 'bridge1'
     * @return array[]
     */
    public function getAddressesByInterface(string $interface): array
    {
        $all = $this->getAddresses();

        return array_values(
            array_filter($all, fn ($a) => ($a['interface'] ?? '') === $interface)
        );
    }

    /**
     * Get a single address entry by IP/CIDR.
     *
     * @param  string     $address IP with prefix e.g. '192.168.1.1/24'
     * @return array|null          Address data or null if not found
     */
    public function getAddress(string $address): ?array
    {
        $all = $this->getAddresses();

        foreach ($all as $entry) {
            if (($entry['address'] ?? '') === $address) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Get total count of assigned IP addresses.
     *
     * @return int
     */
    public function getAddressCount(): int
    {
        return count($this->getAddresses());
    }

    /**
     * Check if a specific IP/CIDR is assigned on the router.
     *
     * @param  string $address IP with prefix e.g. '192.168.1.1/24'
     * @return bool
     */
    public function isAddressAssigned(string $address): bool
    {
        return $this->getAddress($address) !== null;
    }

    // =========================================================
    // Write
    // =========================================================

    /**
     * Add a new IP address to an interface.
     *
     * @param  array $data Required: address, interface. Optional: comment, disabled.
     * @return void
     *
     * Example:
     *  $manager->addAddress([
     *      'address'   => '192.168.1.1/24',
     *      'interface' => 'ether1',
     *      'comment'   => 'LAN gateway',
     *  ]);
     */
    public function addAddress(array $data): void
    {
        $this->client->query(self::CMD_ADD, $data);
    }

    /**
     * Update an existing IP address entry.
     *
     * @param  string $address IP/CIDR to update
     * @param  array  $data    Fields to update
     * @return void
     */
    public function updateAddress(string $address, array $data): void
    {
        $entry = $this->getAddress($address);

        if (! $entry) {
            throw ResourceNotFoundException::for('ip-address', $address);
        }

        $id = $this->extractId($entry, 'ip-address');

        $this->client->query(
            self::CMD_SET,
            array_merge(['.id' => $id], $data)
        );
    }

    /**
     * Remove an IP address by IP/CIDR.
     *
     * @param  string $address IP/CIDR to remove e.g. '192.168.1.1/24'
     * @return void
     */
    public function removeAddress(string $address): void
    {
        $entry = $this->getAddress($address);

        if (! $entry) {
            throw ResourceNotFoundException::for('ip-address', $address);
        }

        $id = $this->extractId($entry, 'ip-address');

        $this->client->query(
            self::CMD_REMOVE,
            ['.id' => $id]
        );
    }

    /**
     * Enable a disabled IP address.
     *
     * @param  string $address IP/CIDR to enable
     * @return void
     */
    public function enableAddress(string $address): void
    {
        $entry = $this->getAddress($address);

        if (! $entry) {
            throw ResourceNotFoundException::for('ip-address', $address);
        }

        $id = $this->extractId($entry, 'ip-address');

        $this->client->query(
            self::CMD_ENABLE,
            ['.id' => $id]
        );
    }

    /**
     * Disable an IP address without removing it.
     *
     * @param  string $address IP/CIDR to disable
     * @return void
     */
    public function disableAddress(string $address): void
    {
        $entry = $this->getAddress($address);

        if (! $entry) {
            throw ResourceNotFoundException::for('ip-address', $address);
        }

        $id = $this->extractId($entry, 'ip-address');

        $this->client->query(
            self::CMD_DISABLE,
            ['.id' => $id]
        );
    }
}