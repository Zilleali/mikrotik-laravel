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
use ZillEAli\MikrotikLaravel\Services\DiagnosticsManager;
use ZillEAli\MikrotikLaravel\Services\ExportManager;
use ZillEAli\MikrotikLaravel\Services\ArpManager;
use ZillEAli\MikrotikLaravel\Services\BridgeManager;
use ZillEAli\MikrotikLaravel\Services\DhcpManager;
use ZillEAli\MikrotikLaravel\Services\DnsManager;
use ZillEAli\MikrotikLaravel\Services\FirewallManager;
use ZillEAli\MikrotikLaravel\Services\FleetManager;
use ZillEAli\MikrotikLaravel\Services\HotspotManager;
use ZillEAli\MikrotikLaravel\Services\InterfaceManager;
use ZillEAli\MikrotikLaravel\Services\IpAddressManager;
use ZillEAli\MikrotikLaravel\Services\IpPoolManager;
use ZillEAli\MikrotikLaravel\Services\NtpManager;
use ZillEAli\MikrotikLaravel\Services\PppoeManager;
use ZillEAli\MikrotikLaravel\Services\QueueManager;
use ZillEAli\MikrotikLaravel\Services\RadiusManager;
use ZillEAli\MikrotikLaravel\Services\RouteManager;
use ZillEAli\MikrotikLaravel\Services\RouterUserManager;
use ZillEAli\MikrotikLaravel\Services\ScriptManager;
use ZillEAli\MikrotikLaravel\Services\SessionMonitor;
use ZillEAli\MikrotikLaravel\Services\SyslogManager;
use ZillEAli\MikrotikLaravel\Services\SystemManager;
use ZillEAli\MikrotikLaravel\Services\UsageTracker;
use ZillEAli\MikrotikLaravel\Services\VpnManager;
use ZillEAli\MikrotikLaravel\Services\WirelessManager;
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
 *  MikroTik::on('branch')->system()->getResources()   // pinned, reusable
 *  MikroTik::fleet()->health()                         // every router at once
 *
 * @package ZillEAli\MikrotikLaravel
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class MikrotikManager
{
    /**
     * Connection pool — persistent RouterosClient instances keyed by router name.
     */
    protected ConnectionPool $pool;

    /**
     * Currently selected router name.
     */
    protected string $currentRouter = 'default';

    /**
     * Router this instance is permanently bound to (set by on()).
     */
    protected ?string $pinnedRouter = null;

    /**
     * @param array<string, mixed> $config Full mikrotik config array
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
     * @param  string $name Router name from config.routers array
     * @return static
     */
    public function router(string $name): static
    {
        $this->currentRouter = $name;

        return $this;
    }

    /**
     * Get a manager instance permanently bound to a named router.
     *
     * Unlike router(), the selection does not reset after one call, so the
     * returned instance can be reused for several manager calls. Shares the
     * connection pool with this instance.
     *
     *  $branch = MikroTik::on('branch');
     *  $branch->system()->getResources();
     *  $branch->pppoe()->getActiveSessions();
     *
     * @param  string $name Router name from config.routers, or 'default'
     * @return static
     * @throws ConnectionException If router name not found in config
     */
    public function on(string $name): static
    {
        $this->getRouterConfig($name);

        $scoped = clone $this;
        $scoped->pinnedRouter = $name;
        $scoped->currentRouter = $name;

        return $scoped;
    }

    /**
     * Name of the router the next manager call will target.
     *
     * @return string
     */
    public function currentRouterName(): string
    {
        return $this->pinnedRouter ?? $this->currentRouter;
    }

    /**
     * Get all configured router names.
     *
     * Includes 'default' first, followed by every key in config.routers.
     * Pass a group to return only routers tagged with it via their
     * 'groups' config key (the default router is never part of a group).
     *
     * @param  string|null $group Optional group filter e.g. 'north', 'fiber'
     * @return list<string>
     */
    public function getRouterNames(?string $group = null): array
    {
        /** @var array<string, array<string, mixed>> $routers */
        $routers = $this->config['routers'] ?? [];

        if ($group !== null) {
            $names = [];

            foreach ($routers as $name => $cfg) {
                if (in_array($group, (array) ($cfg['groups'] ?? []), true)) {
                    $names[] = (string) $name;
                }
            }

            return $names;
        }

        return array_values(array_unique(array_merge(
            ['default'],
            array_map('strval', array_keys($routers)),
        )));
    }

    // =========================================================
    // Connection
    // =========================================================

    /**
     * Get or create a RouterosClient for the current router.
     *
     * @return RouterosClient
     * @throws ConnectionException
     */
    protected function getClient(): RouterosClient
    {
        $name = $this->resolveAndResetRouter();

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

        $client->configure([
            'socket_timeout'          => $this->config['socket_timeout'] ?? 30,
            'socket_blocking'         => $this->config['socket_blocking'] ?? true,
            'throw_timeout_exception' => $this->config['throw_timeout_exception'] ?? true,
        ]);

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
        if ($this->pinnedRouter !== null) {
            return $this->pinnedRouter;
        }

        $name = $this->currentRouter;
        $this->currentRouter = 'default';

        return $name;
    }

    /**
     * Connect with automatic retry on failure.
     *
     * @param  RouterosClient      $client
     * @param  int                 $attempts   Max connection attempts
     * @param  int                 $delay      Delay between retries in milliseconds
     * @param  string              $routerName
     * @param  array<string, mixed> $cfg
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

        throw ConnectionException::retriesExhausted(
            $cfg['host'] ?? '',
            $cfg['port'] ?? 8728,
            $attempts,
            $routerName,
            $lastException,
        );
    }

    /**
     * Get config array for a named router.
     *
     * @param  string $name
     * @return array<string, mixed>
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
                'ssl' => $this->config['ssl'] ?? false,
                'verify_peer' => $this->config['verify_peer'] ?? false,
                'ca_cert_path' => $this->config['ca_cert_path'] ?? null,
            ];
        }

        if (! isset($this->config['routers'][$name])) {
            throw ConnectionException::routerNotFound($name);
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

    /** @return IpAddressManager */
    public function ipAddress(): IpAddressManager
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

    /** @return SyslogManager */
    public function syslog(): SyslogManager
    {
        return new SyslogManager($this->getClient());
    }

    /** @return UsageTracker */
    public function usageTracker(): UsageTracker
    {
        return new UsageTracker($this->getClient());
    }

    /** @return SessionMonitor */
    public function sessionMonitor(): SessionMonitor
    {
        return new SessionMonitor($this->getClient());
    }

    /**
     * SSH-based config export and diff manager.
     *
     * Requires SSH service enabled on the router and an SSH private key
     * configured via MIKROTIK_SSH_KEY / ssh_private_key in config.
     *
     * @return ExportManager
     */
    public function export(): ExportManager
    {
        $cfg = $this->getRouterConfig($this->resolveAndResetRouter());

        return new ExportManager(
            host:          $cfg['host'],
            sshPort:       (int) ($this->config['ssh_port'] ?? 22),
            sshUser:       $cfg['username'] ?? 'admin',
            sshPrivateKey: $this->config['ssh_private_key'] ?? '~/.ssh/id_rsa',
            sshTimeout:    (int) ($this->config['ssh_timeout'] ?? 30),
        );
    }

    /**
     * Connection diagnostics: ping, latency, raw API inspection.
     *
     * @return DiagnosticsManager
     */
    public function diagnostics(): DiagnosticsManager
    {
        return new DiagnosticsManager($this->getClient());
    }

    /**
     * Multi-router fleet operations — run the same task on every router.
     *
     *  MikroTik::fleet()->health()
     *  MikroTik::fleet()->group('north')->findPppoeSession('ali-home')
     *  MikroTik::fleet()->each(fn ($router) => $router->queue()->getSimpleQueues())
     *
     * @return FleetManager
     */
    public function fleet(): FleetManager
    {
        return new FleetManager(
            $this,
            (bool) ($this->config['fleet']['include_default'] ?? true),
        );
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
            router:     $this->currentRouterName(),
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
            router:   $this->currentRouterName(),
            ip:       $ip,
            uptime:   $uptime,
            reason:   $reason,
        ));
    }
}
