<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * BridgeManager
 *
 * Manages MikroTik bridge interfaces, ports, host table,
 * and bridge filter rules.
 *
 * Bridges are critical in ISP deployments for:
 *  - Combining multiple physical interfaces into one L2 domain
 *  - VLAN-aware bridging (802.1Q)
 *  - Transparent firewall/filtering at L2
 *  - Hotspot deployments (bridge + hotspot on same interface)
 *
 * Usage:
 *  $manager = new BridgeManager($client);
 *  $manager->getBridges();
 *  $manager->getBridgePortsByBridge('bridge1');
 *  $manager->addBridgePort(['bridge' => 'bridge1', 'interface' => 'ether5']);
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class BridgeManager
{
    private const CMD_BRIDGE_PRINT = '/interface/bridge/print';
    private const CMD_BRIDGE_ADD = '/interface/bridge/add';
    private const CMD_BRIDGE_REMOVE = '/interface/bridge/remove';
    private const CMD_PORT_PRINT = '/interface/bridge/port/print';
    private const CMD_PORT_ADD = '/interface/bridge/port/add';
    private const CMD_PORT_REMOVE = '/interface/bridge/port/remove';
    private const CMD_HOST_PRINT = '/interface/bridge/host/print';
    private const CMD_FILTER_PRINT = '/interface/bridge/filter/print';
    private const CMD_FILTER_ADD = '/interface/bridge/filter/add';

    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Bridges
    // =========================================================

    /**
     * Get all bridge interfaces.
     *
     * @return array[] Bridges with name, mtu, disabled, running, mac-address
     */
    public function getBridges(): array
    {
        return $this->client->query(self::CMD_BRIDGE_PRINT);
    }

    /**
     * Get a single bridge by name.
     *
     * @param  string     $name Bridge name e.g. 'bridge1'
     * @return array|null       Bridge data or null if not found
     */
    public function getBridge(string $name): ?array
    {
        $bridges = $this->client->query(
            self::CMD_BRIDGE_PRINT,
            queries: ["name={$name}"]
        );

        return $bridges[0] ?? null;
    }

    /**
     * Create a new bridge interface.
     *
     * @param  array $data Required: name. Optional: mtu, comment, vlan-filtering, etc.
     * @return void
     *
     * Example:
     *  $manager->addBridge([
     *      'name'           => 'bridge1',
     *      'vlan-filtering' => 'yes',
     *      'comment'        => 'Main LAN bridge',
     *  ]);
     */
    public function addBridge(array $data): void
    {
        $this->client->query(self::CMD_BRIDGE_ADD, $data);
    }

    /**
     * Remove a bridge interface by name.
     *
     * WARNING: Removing a bridge will disconnect all ports
     * and hosts attached to it.
     *
     * @param  string $name Bridge name to remove
     * @return void
     */
    public function removeBridge(string $name): void
    {
        $bridge = $this->getBridge($name);

        if (! $bridge) {
            return;
        }

        $this->client->query(
            self::CMD_BRIDGE_REMOVE,
            ['.id' => $bridge['.id']]
        );
    }

    // =========================================================
    // Bridge Ports
    // =========================================================

    /**
     * Get all bridge port entries.
     *
     * @return array[] Ports with interface, bridge, disabled, horizon, etc.
     */
    public function getBridgePorts(): array
    {
        return $this->client->query(self::CMD_PORT_PRINT);
    }

    /**
     * Get all ports belonging to a specific bridge.
     *
     * @param  string  $bridgeName Bridge name to filter by
     * @return array[]             Ports on that bridge
     */
    public function getBridgePortsByBridge(string $bridgeName): array
    {
        $ports = $this->getBridgePorts();

        return array_values(
            array_filter($ports, fn ($p) => ($p['bridge'] ?? '') === $bridgeName)
        );
    }

    /**
     * Get count of ports on a specific bridge.
     *
     * @param  string $bridgeName Bridge name
     * @return int                Number of ports
     */
    public function getPortCount(string $bridgeName): int
    {
        return count($this->getBridgePortsByBridge($bridgeName));
    }

    /**
     * Add an interface as a port to a bridge.
     *
     * @param  array $data Required: bridge, interface. Optional: horizon, edge, comment.
     * @return void
     *
     * Example:
     *  $manager->addBridgePort([
     *      'bridge'    => 'bridge1',
     *      'interface' => 'ether5',
     *      'comment'   => 'Uplink port',
     *  ]);
     */
    public function addBridgePort(array $data): void
    {
        $this->client->query(self::CMD_PORT_ADD, $data);
    }

    /**
     * Remove an interface from a bridge by interface name.
     *
     * @param  string $interfaceName Interface name e.g. 'ether5'
     * @return void
     */
    public function removeBridgePort(string $interfaceName): void
    {
        $ports = $this->client->query(
            self::CMD_PORT_PRINT,
            queries: ["interface={$interfaceName}"]
        );

        if (empty($ports)) {
            return;
        }

        $this->client->query(
            self::CMD_PORT_REMOVE,
            ['.id' => $ports[0]['.id']]
        );
    }

    // =========================================================
    // Bridge Host Table
    // =========================================================

    /**
     * Get the bridge host (MAC) table.
     *
     * Shows all MAC addresses learned by the bridge and
     * which port they were learned on.
     *
     * @return array[] Host entries with mac-address, bridge, on-interface, age
     */
    public function getBridgeHosts(): array
    {
        return $this->client->query(self::CMD_HOST_PRINT);
    }

    /**
     * Get bridge hosts for a specific bridge.
     *
     * @param  string  $bridgeName Bridge name to filter by
     * @return array[]             Host entries on that bridge
     */
    public function getBridgeHostsByBridge(string $bridgeName): array
    {
        $hosts = $this->getBridgeHosts();

        return array_values(
            array_filter($hosts, fn ($h) => ($h['bridge'] ?? '') === $bridgeName)
        );
    }

    // =========================================================
    // Bridge Filters
    // =========================================================

    /**
     * Get all bridge filter rules.
     *
     * Bridge filters work at Layer 2 — they can filter
     * based on MAC address, VLAN, protocol, etc.
     *
     * @return array[] Filter rules with chain, action, mac-protocol, etc.
     */
    public function getBridgeFilters(): array
    {
        return $this->client->query(self::CMD_FILTER_PRINT);
    }

    /**
     * Add a bridge filter rule.
     *
     * @param  array $data Required: chain, action. Optional: mac-protocol, src-mac, dst-mac.
     * @return void
     *
     * Example:
     *  $manager->addBridgeFilter([
     *      'chain'        => 'forward',
     *      'action'       => 'drop',
     *      'mac-protocol' => 'ip',
     *      'comment'      => 'block IP forwarding',
     *  ]);
     */
    public function addBridgeFilter(array $data): void
    {
        $this->client->query(self::CMD_FILTER_ADD, $data);
    }
}
