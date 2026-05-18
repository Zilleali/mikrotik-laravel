<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * RouteManager
 *
 * Manages MikroTik static routes and routing table.
 *
 * Critical for ISPs:
 *  - Add static routes for multi-site networks
 *  - Manage default gateway
 *  - Policy routing via route marks
 *  - Failover routes with distance/check-gateway
 *
 * Usage:
 *  $manager = new RouteManager($client);
 *  $manager->getRoutes();
 *  $manager->getDefaultRoute();
 *  $manager->addRoute('10.0.0.0/8', '192.168.1.1', distance: 1);
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class RouteManager
{
    private const CMD_PRINT = '/ip/route/print';
    private const CMD_ADD = '/ip/route/add';
    private const CMD_SET = '/ip/route/set';
    private const CMD_REMOVE = '/ip/route/remove';
    private const CMD_ENABLE = '/ip/route/enable';
    private const CMD_DISABLE = '/ip/route/disable';

    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Read
    // =========================================================

    /**
     * Get all routes from the routing table.
     *
     * @return array[] Routes with dst-address, gateway, distance, active, disabled
     */
    public function getRoutes(): array
    {
        return $this->client->query(self::CMD_PRINT);
    }

    /**
     * Get only active routes.
     *
     * @return array[]
     */
    public function getActiveRoutes(): array
    {
        $routes = $this->getRoutes();

        return array_values(
            array_filter($routes, fn ($r) => ($r['active'] ?? 'false') === 'true')
        );
    }

    /**
     * Get the default route (0.0.0.0/0).
     *
     * @return array|null Default route or null if not configured
     */
    public function getDefaultRoute(): ?array
    {
        return $this->getRouteByDestination('0.0.0.0/0');
    }

    /**
     * Get a route by destination network.
     *
     * @param  string     $destination CIDR e.g. '192.168.2.0/24'
     * @return array|null              Route data or null if not found
     */
    public function getRouteByDestination(string $destination): ?array
    {
        $routes = $this->getRoutes();

        foreach ($routes as $route) {
            if (($route['dst-address'] ?? '') === $destination) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Get all routes via a specific gateway.
     *
     * @param  string  $gateway Gateway IP address
     * @return array[]
     */
    public function getRoutesByGateway(string $gateway): array
    {
        $routes = $this->getRoutes();

        return array_values(
            array_filter($routes, fn ($r) => ($r['gateway'] ?? '') === $gateway)
        );
    }

    // =========================================================
    // Write
    // =========================================================

    /**
     * Add a static route.
     *
     * @param  string      $destination Destination CIDR e.g. '10.0.0.0/8'
     * @param  string      $gateway     Gateway IP address
     * @param  int         $distance    Administrative distance (default 1)
     * @param  string|null $comment     Optional comment
     * @return void
     *
     * Example:
     *  $manager->addRoute('10.0.0.0/8', '192.168.1.1', distance: 1, comment: 'branch');
     */
    public function addRoute(
        string  $destination,
        string  $gateway,
        int     $distance = 1,
        ?string $comment = null
    ): void {
        $data = [
            'dst-address' => $destination,
            'gateway' => $gateway,
            'distance' => (string) $distance,
        ];

        if ($comment !== null) {
            $data['comment'] = $comment;
        }

        $this->client->query(self::CMD_ADD, $data);
    }

    /**
     * Update an existing route.
     *
     * @param  string $destination Destination CIDR to update
     * @param  array  $data        Fields to update
     * @return void
     */
    public function updateRoute(string $destination, array $data): void
    {
        $route = $this->getRouteByDestination($destination);

        if (! $route) {
            return;
        }

        $this->client->query(
            self::CMD_SET,
            array_merge(['.id' => $route['.id']], $data)
        );
    }

    /**
     * Remove a static route by destination.
     *
     * @param  string $destination Destination CIDR to remove
     * @return void
     */
    public function removeRoute(string $destination): void
    {
        $route = $this->getRouteByDestination($destination);

        if (! $route) {
            return;
        }

        $this->client->query(
            self::CMD_REMOVE,
            ['.id' => $route['.id']]
        );
    }

    /**
     * Enable a disabled route.
     *
     * @param  string $destination Destination CIDR
     * @return void
     */
    public function enableRoute(string $destination): void
    {
        $route = $this->getRouteByDestination($destination);

        if (! $route) {
            return;
        }

        $this->client->query(
            self::CMD_ENABLE,
            ['.id' => $route['.id']]
        );
    }

    /**
     * Disable a route without removing it.
     *
     * Useful for failover testing — disable primary route
     * to force traffic through backup.
     *
     * @param  string $destination Destination CIDR
     * @return void
     */
    public function disableRoute(string $destination): void
    {
        $route = $this->getRouteByDestination($destination);

        if (! $route) {
            return;
        }

        $this->client->query(
            self::CMD_DISABLE,
            ['.id' => $route['.id']]
        );
    }
}
