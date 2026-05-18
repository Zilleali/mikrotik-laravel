<?php

namespace ZillEAli\MikrotikLaravel;

use Illuminate\Support\Facades\Event;
use ZillEAli\MikrotikLaravel\Connections\ConnectionPool;
use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Connections\RouterosClientSSL;
use ZillEAli\MikrotikLaravel\Events\RouterConnected;
use ZillEAli\MikrotikLaravel\Events\RouterUnreachable;
use ZillEAli\MikrotikLaravel\Events\SessionCreated;
use ZillEAli\MikrotikLaravel\Events\SessionDisconnected;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;
use ZillEAli\MikrotikLaravel\Services\ArpManager;
use ZillEAli\MikrotikLaravel\Services\BridgeManager;
use ZillEAli\MikrotikLaravel\Services\DhcpManager;
use ZillEAli\MikrotikLaravel\Services\DnsManager;
use ZillEAli\MikrotikLaravel\Services\FirewallManager;
use ZillEAli\MikrotikLaravel\Services\HotspotManager;
use ZillEAli\MikrotikLaravel\Services\InterfaceManager;
use ZillEAli\MikrotikLaravel\Services\IpAddressManager;
use ZillEAli\MikrotikLaravel\Services\IpPoolManager;
use ZillEAli\MikrotikLaravel\Services\NtpManager;
use ZillEAli\MikrotikLaravel\Services\PppoeManager; // VPN Manager for WireGuard and OpenVPN support
use ZillEAli\MikrotikLaravel\Services\QueueManager; // New SSL connection class for secure API access
use ZillEAli\MikrotikLaravel\Services\RadiusManager; // New manager for managing bridges and VLANs
use ZillEAli\MikrotikLaravel\Services\RouteManager; // New connection pool for efficient connection reuse
use ZillEAli\MikrotikLaravel\Services\RouterUserManager; // New manager for IP address management on interfaces
use ZillEAli\MikrotikLaravel\Services\SystemManager; // New manager for ARP table management
use ZillEAli\MikrotikLaravel\Services\VpnManager; // New manager for DNS settings and static entries
use ZillEAli\MikrotikLaravel\Services\WirelessManager; // New manager for routing table management and policy routing
use ZillEAli\MikrotikLaravel\Support\CachingProxy; // New manager for NTP client configuration and status monitoring

// New manager for managing scripts and scheduler on Mikrotik devices


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
     * Connection pool — persistent RouterosClient instances keyed by router name.
     * Prevents reconnecting on every method call.
     */
    protected ConnectionPool $pool;

    /**
     * Currently selected router name.
     * Default is 'default' — uses top-level config values.
     */
    protected string $currentRouter = 'default';

    /**
     * @param array $config Full mikrotik config array
     */
    public function __construct(protected array $config)
    {
        $this->pool = new ConnectionPool();
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
     * Uses ConnectionPool to cache connections.
     * Implements SSL/plain selection and retry logic.
     *
     * @return RouterosClient
     * @throws ConnectionException
     */
    protected function getClient(): RouterosClient
    {
        $name = $this->resolveAndResetRouter();

        // Return cached alive connection
        if ($this->pool->isAlive($name)) {
            return $this->pool->get($name);
        }

        $cfg = $this->getRouterConfig($name);
        $useSSL = $cfg['ssl'] ?? $this->config['ssl'] ?? false;

        if ($useSSL) {
            $client = new RouterosClientSSL(
                host:       $cfg['host'],
                port:       $cfg['port'] ?? 8729,
                username:   $cfg['username'] ?? 'admin',
                password:   $cfg['password'] ?? '',
                timeout:    $cfg['timeout'] ?? 10,
                verifyPeer: $cfg['verify_peer'] ?? false,
                caCertPath: $cfg['ca_cert_path'] ?? null,
            );
        } else {
            $client = new RouterosClient(
                host:     $cfg['host'],
                port:     $cfg['port'] ?? 8728,
                username: $cfg['username'] ?? 'admin',
                password: $cfg['password'] ?? '',
                timeout:  $cfg['timeout'] ?? 10,
            );
        }

        $attempts = $this->config['retry_attempts'] ?? 1;
        $delay = $this->config['retry_delay'] ?? 1000;

        $this->connectWithRetry($client, $attempts, $delay, $name, $cfg);

        $this->pool->add($name, $client);

        return $client;
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
     * Connect with automatic retry on failure.
     *
     * @param  RouterosClient $client
     * @param  int            $attempts  Max connection attempts
     * @param  int            $delay     Delay between retries in milliseconds
     * @param  string         $routerName
     * @param  array          $cfg
     * @return void
     * @throws ConnectionException After all attempts fail
     */
    protected function connectWithRetry(
        RouterosClient $client,
        int $attempts,
        int $delay,
        string $routerName = 'default',
        array $cfg = [],
    ): void {
        $lastException = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $client->connect();

                Event::dispatch(new RouterConnected(
                    host:   $cfg['host'] ?? '',
                    port:   $cfg['port'] ?? 8728,
                    router: $routerName,
                ));

                return;

            } catch (ConnectionException $e) {
                $lastException = $e;

                if ($i < $attempts) {
                    usleep($delay * 1000);
                }
            }
        }

        Event::dispatch(new RouterUnreachable(
            host:      $cfg['host'] ?? '',
            port:      $cfg['port'] ?? 8728,
            router:    $routerName,
            attempts:  $attempts,
            error:     $lastException?->getMessage() ?? '',
            exception: $lastException,
        ));

        throw new ConnectionException(
            "Failed to connect after {$attempts} attempt(s): " .
            $lastException?->getMessage()
        );
    }

    /**
     * Get config array for a named router.
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

        if (! isset($this->config['routers'][$name])) {
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
        $this->pool->remove($name);
    }

    /**
     * Disconnect all active router connections.
     *
     * @return void
     */
    public function disconnectAll(): void
    {
        $this->pool->flush();
    }

    /**
     * Get the connection pool instance.
     *
     * @return ConnectionPool
     */
    public function getPool(): ConnectionPool
    {
        return $this->pool;
    }

    // =========================================================
    // Service Managers
    // =========================================================

    /** @return PppoeManager */
    public function pppoe(): PppoeManager
    {
        return new PppoeManager($this->getClient());
    }

    /** @return HotspotManager */
    public function hotspot(): HotspotManager
    {
        return new HotspotManager($this->getClient());
    }

    /** @return QueueManager */
    public function queue(): QueueManager
    {
        return new QueueManager($this->getClient());
    }

    /** @return FirewallManager */
    public function firewall(): FirewallManager
    {
        return new FirewallManager($this->getClient());
    }

    /** @return SystemManager */
    public function system(): SystemManager
    {
        return new SystemManager($this->getClient());
    }

    /** @return InterfaceManager */
    public function interfaces(): InterfaceManager
    {
        return new InterfaceManager($this->getClient());
    }

    /** @return DhcpManager */
    public function dhcp(): DhcpManager
    {
        return new DhcpManager($this->getClient());
    }

    /** @return WirelessManager */
    public function wireless(): WirelessManager
    {
        return new WirelessManager($this->getClient());
    }

    /** @return IpPoolManager */
    public function ipPool(): IpPoolManager
    {
        return new IpPoolManager($this->getClient());
    }

    /** @return RadiusManager */
    public function radius(): RadiusManager
    {
        return new RadiusManager($this->getClient());
    }

    /** @return RouterUserManager */
    public function routerUsers(): RouterUserManager
    {
        return new RouterUserManager($this->getClient());
    }

    /** @return VpnManager */
    public function vpn(): VpnManager
    {
        return new VpnManager($this->getClient());
    }

    /** @return BridgeManager */
    public function bridge(): BridgeManager
    {
        return new BridgeManager($this->getClient());
    }

    // =========================================================
    // Caching
    // =========================================================

    /**
     * Wrap a manager with caching layer.
     *
     * @param  object $manager Any manager instance
     * @param  int    $ttl     Cache TTL in seconds
     * @return CachingProxy
     */
    public function withCache(object $manager, int $ttl = 30): CachingProxy
    {
        return new CachingProxy($manager, $ttl);
    }

    // =========================================================
    // Events
    // =========================================================

    /**
     * Dispatch SessionCreated event.
     *
     * @param  string      $username
     * @param  string      $ip
     * @param  string      $service
     * @param  string|null $mac
     * @return void
     */
    public function dispatchSessionCreated(
        string $username,
        string $ip,
        string $service = 'pppoe',
        ?string $mac = null,
    ): void {
        Event::dispatch(new SessionCreated(
            username:   $username,
            ip:         $ip,
            router:     $this->currentRouter,
            service:    $service,
            macAddress: $mac,
        ));
    }

    /**
     * Dispatch SessionDisconnected event.
     *
     * @param  string      $username
     * @param  string|null $ip
     * @param  string|null $uptime
     * @param  string      $reason
     * @return void
     */
    public function dispatchSessionDisconnected(
        string $username,
        ?string $ip = null,
        ?string $uptime = null,
        string $reason = 'manual',
    ): void {
        Event::dispatch(new SessionDisconnected(
            username: $username,
            router:   $this->currentRouter,
            ip:       $ip,
            uptime:   $uptime,
            reason:   $reason,
        ));
    }
    // ========================================================
    // New Services
    // =========================================================
    /** @return IpAddressManager */
    public function ipAddresses(): IpAddressManager
    {
        return new IpAddressManager($this->getClient());
    }

    /** @return ArpManager */
    public function arp(): ArpManager
    {
        return new ArpManager($this->getClient());
    }

    /** @return DnsManager */
    public function dns(): DnsManager
    {
        return new DnsManager($this->getClient());
    }

    /** @return RouteManager */
    public function routes(): RouteManager
    {
        return new RouteManager($this->getClient());
    }

    /** @return NtpManager */
    public function ntp(): NtpManager
    {
        return new NtpManager($this->getClient());
    }

    /** @return ScriptManager */
public function scripts(): ScriptManager
{
    return new ScriptManager($this->getClient());
}
}
