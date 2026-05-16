<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * InterfaceManager
 *
 * Manages MikroTik network interfaces — ethernet, VLAN,
 * wireless, PPPoE tunnels. Monitor traffic, enable/disable.
 *
 * Usage:
 *  $manager = new InterfaceManager($client);
 *  $manager->getRunningInterfaces();
 *  $manager->getTraffic('ether1');
 *  $manager->disableInterface('ether4');
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class InterfaceManager
{
    private const CMD_PRINT = '/interface/print';
    private const CMD_ENABLE = '/interface/enable';
    private const CMD_DISABLE = '/interface/disable';
    private const CMD_TRAFFIC = '/interface/monitor-traffic';
    private const CMD_VLAN = '/interface/vlan/print';

    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Read
    // =========================================================

    /**
     * Get all interfaces (ethernet, wlan, vlan, pppoe, etc.)
     *
     * @return array[]
     */
    public function getInterfaces(): array
    {
        return $this->client->query(self::CMD_PRINT);
    }

    /**
     * Get a single interface by name.
     *
     * @param  string     $name Interface name e.g. 'ether1', 'wlan1'
     * @return array|null       Interface data or null if not found
     */
    public function getInterface(string $name): ?array
    {
        $interfaces = $this->client->query(
            self::CMD_PRINT,
            queries: ["name={$name}"]
        );

        return $interfaces[0] ?? null;
    }

    /**
     * Get only running (link-up) interfaces.
     *
     * @return array[]
     */
    public function getRunningInterfaces(): array
    {
        $all = $this->getInterfaces();

        return array_values(
            array_filter($all, fn ($i) => ($i['running'] ?? 'false') === 'true')
        );
    }

    /**
     * Get only disabled interfaces.
     *
     * @return array[]
     */
    public function getDisabledInterfaces(): array
    {
        $all = $this->getInterfaces();

        return array_values(
            array_filter($all, fn ($i) => ($i['disabled'] ?? 'false') === 'true')
        );
    }

    /**
     * Get only ethernet interfaces.
     *
     * @return array[]
     */
    public function getEthernetInterfaces(): array
    {
        $all = $this->getInterfaces();

        return array_values(
            array_filter($all, fn ($i) => ($i['type'] ?? '') === 'ether')
        );
    }

    /**
     * Get all VLAN interfaces.
     *
     * @return array[]
     */
    public function getVlans(): array
    {
        return $this->client->query(self::CMD_VLAN);
    }

    // =========================================================
    // Traffic
    // =========================================================

    /**
     * Get real-time traffic stats for an interface.
     *
     * Returns TX/RX bits per second from the router.
     *
     * @param  string $name      Interface name e.g. 'ether1'
     * @param  int    $duration  Sample duration in seconds
     * @return array             Traffic data or empty array
     */
    public function getTraffic(string $name, int $duration = 1): array
    {
        $result = $this->client->query(self::CMD_TRAFFIC, [
            'interface' => $name,
            'duration' => "{$duration}s",
            'once' => '',
        ]);

        return $result[0] ?? [];
    }

    // =========================================================
    // Enable / Disable
    // =========================================================

    /**
     * Enable a disabled interface.
     *
     * @param  string $name Interface name
     * @return void
     */
    public function enableInterface(string $name): void
    {
        $interface = $this->getInterface($name);

        if (! $interface) {
            return;
        }

        $this->client->query(
            self::CMD_ENABLE,
            ['.id' => $interface['.id'] ?? $name]
        );
    }

    /**
     * Disable an interface (brings link down).
     *
     * WARNING: Disabling your WAN or management interface
     * will cut access to the router.
     *
     * @param  string $name Interface name
     * @return void
     */
    public function disableInterface(string $name): void
    {
        $interface = $this->getInterface($name);

        if (! $interface) {
            return;
        }

        $this->client->query(
            self::CMD_DISABLE,
            ['.id' => $interface['.id'] ?? $name]
        );
    }
}
