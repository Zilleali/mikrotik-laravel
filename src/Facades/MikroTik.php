<?php

namespace ZillEAli\MikrotikLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use ZillEAli\MikrotikLaravel\MikrotikManager;
use ZillEAli\MikrotikLaravel\Services\FirewallManager;
use ZillEAli\MikrotikLaravel\Services\HotspotManager;
use ZillEAli\MikrotikLaravel\Services\PppoeManager;
use ZillEAli\MikrotikLaravel\Services\QueueManager;
use ZillEAli\MikrotikLaravel\Services\SystemManager;

/**
 * MikroTik Facade
 *
 * Provides static access to MikrotikManager methods.
 *
 * @method static MikrotikManager  router(string $name)
 * @method static PppoeManager     pppoe()
 * @method static HotspotManager   hotspot()
 * @method static QueueManager     queue()
 * @method static FirewallManager  firewall()
 * @method static SystemManager    system()
 * @method static void             disconnect(string $name = 'default')
 * @method static void             disconnectAll()
 * @method static DhcpManager      dhcp()
 * @method static InterfaceManager  interfaces()
 * @method static WirelessManager   wireless()
 * @method static IpPoolManager    ipPools()
 * @method static RadiusManager    radius()
 * @method static RouterUserManager routerUsers()
 * @method static void dispatchSessionCreated(string $username, string $ip, string $service = 'pppoe', ?string $mac = null)
 * @method static void dispatchSessionDisconnected(string $username, ?string $ip = null, ?string $uptime = null, string $reason = 'manual')
 * @method static VpnManager vpn() // VPN Manager for WireGuard and OpenVPN support
 * @method static BridgeManager bridge() // Bridge Manager for managing Mikrotik bridges
 * @method static IpAddressManager ipAddress() // IP Address Manager for managing IP addresses on Mikrotik devices
 * @method static ArpManager arp() // ARP Manager for managing ARP entries on Mikrotik devices
 * @method static DnsManager dns() // DNS Manager for managing DNS settings and static entries on Mikrotik devices
 * @method static RouteManager routes() // Route Manager for managing static routes and routing table on Mikrotik devices
 * @method static NtpManager ntp() // NTP Manager for managing NTP client settings and status on Mikrotik devices
 * @method static ScriptManager scripts() // Script Manager for managing scripts and schedulers on Mikrotik devices
 * @method static SyslogManager syslog() // Syslog Manager for managing syslog settings and logs on Mikrotik devices
 * @method static SessionMonitor sessionMonitor() // Session Monitor for real-time monitoring of active sessions on Mikrotik devices
 *
 * @see MikrotikManager
 *
 * @package ZillEAli\MikrotikLaravel\Facades
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class MikroTik extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'mikrotik';
    }
}
