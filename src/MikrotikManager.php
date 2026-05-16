<?php

namespace ZillEAli\MikrotikLaravel;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;
use ZillEAli\MikrotikLaravel\Services\FirewallManager;
use ZillEAli\MikrotikLaravel\Services\HotspotManager;
use ZillEAli\MikrotikLaravel\Services\PppoeManager;
use ZillEAli\MikrotikLaravel\Services\QueueManager;
use ZillEAli\MikrotikLaravel\Services\SystemManager;
use ZillEAli\MikrotikLaravel\Services\DhcpManager;
use ZillEAli\MikrotikLaravel\Services\InterfaceManager;
use ZillEAli\MikrotikLaravel\Services\WirelessManager;
use ZillEAli\MikrotikLaravel\Services\IpPoolManager;
use ZillEAli\MikrotikLaravel\Services\RadiusManager;
use ZillEAli\MikrotikLaravel\Support\CachingProxy;

/**
 * MikrotikManager
 *
 * Central manager — entry point for all RouterOS operations.
 * Supports default router and named multi-router connections.
 *
 * Accessed via Facade:
 *  MikroTik::pppoe()->getActiveSessions()
 *  MikroTik::router('branch')->hotspot()->getActiveHosts()
 *
 * @package ZillEAli\MikrotikLaravel
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class MikrotikManager
{
    /**
     * Active RouterosClient instances keyed by router name.
     * Prevents reconnecting on every method call.
     *
     * @var array<string, RouterosClient>
     */
    protected array $connections = [];

    /**
     * Currently selected router name.
     * Default is 'default' — uses top-level config values.
     */
    protected string $currentRouter = 'default';

    /**
     * @param array $config Full mikrotik config array
     */
    public function __construct(
        protected array $config
    ) {
    }

    // =========================================================
    // Router Selection
    // =========================================================

    /**
     * Select a named router for the next operation.
     *
     * Resets to 'default' after each manager call automatically.
     *
     * Usage:
     *  MikroTik::router('branch')->pppoe()->getSecrets()
     *
     * @param  string $name Router name from config.routers array
     * @return static
     */
    public function router(string $name): static
    {
        $this->currentRouter = $name;

        return $this;
    }

    // =========================================================
    // Connection
    // =========================================================

    /**
     * Get or create a RouterosClient for the current router.
     *
     * Caches connections by router name to avoid reconnecting.
     * Implements retry logic based on config retry_attempts.
     *
     * @return RouterosClient
     * @throws ConnectionException
     */
    protected function getClient(): RouterosClient
    {
        $name = $this->resolveAndResetRouter(); // ← alag method

        if (
            isset($this->connections[$name]) &&
            $this->connections[$name]->isConnected()
        ) {
            return $this->connections[$name];
        }

        $cfg = $this->getRouterConfig($name);

        $client = new RouterosClient(
            host: $cfg['host'],
            port: $cfg['port'] ?? 8728,
            username: $cfg['username'] ?? 'admin',
            password: $cfg['password'] ?? '',
            timeout: $cfg['timeout'] ?? 10,
        );

        $attempts = $this->config['retry_attempts'] ?? 1;
        $delay = $this->config['retry_delay'] ?? 1000;

        $this->connectWithRetry($client, $attempts, $delay);

        $this->connections[$name] = $client;

        return $client;
    }


    /**
     * Connect with automatic retry on failure.
     *
     * @param  RouterosClient $client
     * @param  int            $attempts  Max connection attempts
     * @param  int            $delay     Delay between retries in milliseconds
     * @return void
     * @throws ConnectionException After all attempts fail
     */
    protected function connectWithRetry(
        RouterosClient $client,
        int $attempts,
        int $delay
    ): void {
        $lastException = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $client->connect();
                return; // connected — done
            } catch (ConnectionException $e) {
                $lastException = $e;

                // Don't wait after the last attempt
                if ($i < $attempts) {
                    usleep($delay * 1000); // ms to microseconds
                }
            }
        }

        throw new ConnectionException(
            "Failed to connect after {$attempts} attempt(s): " .
            $lastException?->getMessage()
        );
    }

    /**
     * Get config array for a named router.
     *
     * 'default' uses top-level config keys.
     * Named routers use config.routers.{name}.
     *
     * @param  string $name
     * @return array
     * @throws ConnectionException If router name not found in config
     */
    protected function getRouterConfig(string $name): array
    {
        if ($name === 'default') {
            return [
                'host' => $this->config['host'] ?? '192.168.88.1',
                'port' => $this->config['port'] ?? 8728,
                'username' => $this->config['username'] ?? 'admin',
                'password' => $this->config['password'] ?? '',
                'timeout' => $this->config['timeout'] ?? 10,
            ];
        }

        if (!isset($this->config['routers'][$name])) {
            throw new ConnectionException(
                "Router '{$name}' not found in config/mikrotik.php routers array."
            );
        }

        return $this->config['routers'][$name];
    }

    /**
     * Disconnect a specific router connection.
     *
     * @param  string $name Router name, or 'default'
     * @return void
     */
    public function disconnect(string $name = 'default'): void
    {
        if (isset($this->connections[$name])) {
            $this->connections[$name]->disconnect();
            unset($this->connections[$name]);
        }
    }

    /**
     * Disconnect all active router connections.
     *
     * @return void
     */
    public function disconnectAll(): void
    {
        foreach ($this->connections as $client) {
            $client->disconnect();
        }

        $this->connections = [];
    }

    // =========================================================
    // Service Managers
    // =========================================================

    /**
     * Get PPPoE manager for the current router.
     *
     * @return PppoeManager
     */
    public function pppoe(): PppoeManager
    {
        return new PppoeManager($this->getClient());
    }

    /**
     * Get Hotspot manager for the current router.
     *
     * @return HotspotManager
     */
    public function hotspot(): HotspotManager
    {
        return new HotspotManager($this->getClient());
    }

    /**
     * Get Queue manager for the current router.
     *
     * @return QueueManager
     */
    public function queue(): QueueManager
    {
        return new QueueManager($this->getClient());
    }

    /**
     * Get Firewall manager for the current router.
     *
     * @return FirewallManager
     */
    public function firewall(): FirewallManager
    {
        return new FirewallManager($this->getClient());
    }

    /**
     * Get System manager for the current router.
     *
     * @return SystemManager
     */
    public function system(): SystemManager
    {
        return new SystemManager($this->getClient());
    }
    /**
     * Return current router name and reset to default.
     */
    protected function resolveAndResetRouter(): string
    {
        $name = $this->currentRouter;
        $this->currentRouter = 'default';
        return $name;
    }

    /**
     * Get Interface manager for the current router.
     *
     * @return InterfaceManager
     */
    public function interfaces(): InterfaceManager
    {
        return new InterfaceManager($this->getClient());
    }

    /**
     * Get DHCP manager for the current router.
     *
     * @return DhcpManager
     */
    public function dhcp(): DhcpManager
    {
        return new DhcpManager($this->getClient());
    }

    /**
     * Get Wireless manager for the current router.
     *
     * @return WirelessManager
     */
    public function wireless(): WirelessManager
    {
        return new WirelessManager($this->getClient());
    }

    /**
     * Get IP Pool manager for the current router.
     *
     * @return IpPoolManager
     */
    public function ipPool(): IpPoolManager
    {
        return new IpPoolManager($this->getClient());
    }

    /**
     * Get RADIUS manager for the current router.
     *
     * @return RadiusManager
     */
    public function radius(): RadiusManager
    {
        return new RadiusManager($this->getClient());
    }

    /**
     * Wrap a manager with caching layer.
     *
     * Usage:
     *  MikroTik::cache(30)->pppoe()->getSecrets()
     *
     * Or wrap specific manager:
     *  $cached = MikroTik::pppoe()->withCache(30);
     *  $cached->getSecrets(); // cached
     *
     * @param  int $ttl Cache TTL in seconds
     * @return CachingProxy
     */
    public function withCache(object $manager, int $ttl = 30): CachingProxy
    {
        return new CachingProxy($manager, $ttl);
    }
}