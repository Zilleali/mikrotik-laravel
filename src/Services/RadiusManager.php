<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * RadiusManager
 *
 * Manages MikroTik RADIUS client configuration.
 *
 * MikroTik acts as a RADIUS client — this manager configures
 * which RADIUS servers the router authenticates against for
 * PPPoE, Hotspot, and other services.
 *
 * Integrates directly with NexaLink's FreeRADIUS CT (172.16.24.17).
 *
 * Usage:
 *  $manager = new RadiusManager($client);
 *  $manager->getServers();
 *  $manager->isServerActive('172.16.24.17');
 *  $manager->addServer(['address' => '172.16.24.17', 'secret' => 'testing123']);
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class RadiusManager
{
    private const CMD_PRINT = '/radius/print';
    private const CMD_ADD = '/radius/add';
    private const CMD_SET = '/radius/set';
    private const CMD_REMOVE = '/radius/remove';
    private const CMD_ENABLE = '/radius/enable';
    private const CMD_DISABLE = '/radius/disable';
    private const CMD_INCOMING = '/radius/incoming/print';

    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Servers
    // =========================================================

    /**
     * Get all configured RADIUS servers.
     *
     * @return array[] Servers with address, service, secret, timeout, disabled
     */
    public function getServers(): array
    {
        return $this->client->query(self::CMD_PRINT);
    }

    /**
     * Get a single RADIUS server by IP address.
     *
     * @param  string     $address RADIUS server IP
     * @return array|null          Server config or null if not found
     */
    public function getServer(string $address): ?array
    {
        $servers = $this->getServers();

        foreach ($servers as $server) {
            if (($server['address'] ?? '') === $address) {
                return $server;
            }
        }

        return null;
    }

    /**
     * Add a new RADIUS server.
     *
     * @param  array $data Required: address, secret. Optional: service, port, timeout, etc.
     * @return void
     *
     * Example:
     *  $manager->addServer([
     *      'address'          => '172.16.24.17',
     *      'secret'           => 'testing123',
     *      'service'          => 'ppp,hotspot',
     *      'authentication-port' => '1812',
     *      'accounting-port'  => '1813',
     *      'timeout'          => '300ms',
     *  ]);
     */
    public function addServer(array $data): void
    {
        $this->client->query(self::CMD_ADD, $data);
    }

    /**
     * Update an existing RADIUS server configuration.
     *
     * @param  string $address RADIUS server IP to update
     * @param  array  $data    Fields to update
     * @return void
     */
    public function updateServer(string $address, array $data): void
    {
        $server = $this->getServer($address);

        if (! $server) {
            return;
        }

        $this->client->query(
            self::CMD_SET,
            array_merge(['.id' => $server['.id']], $data)
        );
    }

    /**
     * Remove a RADIUS server by IP address.
     *
     * @param  string $address RADIUS server IP to remove
     * @return void
     */
    public function removeServer(string $address): void
    {
        $server = $this->getServer($address);

        if (! $server) {
            return;
        }

        $this->client->query(
            self::CMD_REMOVE,
            ['.id' => $server['.id']]
        );
    }

    /**
     * Enable a RADIUS server.
     *
     * @param  string $address RADIUS server IP
     * @return void
     */
    public function enableServer(string $address): void
    {
        $server = $this->getServer($address);

        if (! $server) {
            return;
        }

        $this->client->query(
            self::CMD_ENABLE,
            ['.id' => $server['.id']]
        );
    }

    /**
     * Disable a RADIUS server.
     *
     * @param  string $address RADIUS server IP
     * @return void
     */
    public function disableServer(string $address): void
    {
        $server = $this->getServer($address);

        if (! $server) {
            return;
        }

        $this->client->query(
            self::CMD_DISABLE,
            ['.id' => $server['.id']]
        );
    }

    /**
     * Check if a RADIUS server exists and is enabled.
     *
     * @param  string $address RADIUS server IP
     * @return bool            True if server exists and is not disabled
     */
    public function isServerActive(string $address): bool
    {
        $server = $this->getServer($address);

        if (! $server) {
            return false;
        }

        return ($server['disabled'] ?? 'true') === 'false';
    }

    // =========================================================
    // Incoming
    // =========================================================

    /**
     * Get RADIUS incoming configuration.
     *
     * Controls whether the router accepts CoA (Change of Authorization)
     * and Disconnect-Message packets from the RADIUS server.
     *
     * Critical for NexaLink — allows FreeRADIUS to disconnect
     * PPPoE sessions remotely via Disconnect-Message (port 3799).
     *
     * @return array Incoming config with accept, port keys
     */
    public function getIncomingConfig(): array
    {
        $result = $this->client->query(self::CMD_INCOMING);

        return $result[0] ?? [];
    }

    /**
     * Enable RADIUS incoming (CoA / Disconnect-Message).
     *
     * After enabling, FreeRADIUS can send disconnect packets
     * directly to the router on port 3799.
     *
     * @return void
     */
    public function enableIncoming(): void
    {
        $this->client->query(
            '/radius/incoming/set',
            ['accept' => 'yes']
        );
    }
}
