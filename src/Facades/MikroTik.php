<?php

namespace ZillEAli\MikrotikLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use ZillEAli\MikrotikLaravel\Connections\ConnectionPool;
use ZillEAli\MikrotikLaravel\MikrotikManager;
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
 * MikroTik Facade
 *
 * @method static MikrotikManager router(string $name)
 * @method static PppoeManager pppoe()
 * @method static HotspotManager hotspot()
 * @method static QueueManager queue()
 * @method static FirewallManager firewall()
 * @method static SystemManager system()
 * @method static InterfaceManager interfaces()
 * @method static DhcpManager dhcp()
 * @method static WirelessManager wireless()
 * @method static IpPoolManager ipPool()
 * @method static RadiusManager radius()
 * @method static RouterUserManager routerUsers()
 * @method static VpnManager vpn()
 * @method static BridgeManager bridge()
 * @method static IpAddressManager ipAddress()
 * @method static ArpManager arp()
 * @method static DnsManager dns()
 * @method static RouteManager routes()
 * @method static NtpManager ntp()
 * @method static ScriptManager scripts()
 * @method static SyslogManager syslog()
 * @method static UsageTracker usageTracker()
 * @method static SessionMonitor sessionMonitor()
 * @method static ConnectionPool getPool()
 * @method static CachingProxy withCache(object $manager, int $ttl = 30)
 * @method static void disconnect(string $name = 'default')
 * @method static void disconnectAll()
 * @method static void dispatchSessionCreated(string $username, string $ip, string $service = 'pppoe', ?string $mac = null)
 * @method static void dispatchSessionDisconnected(string $username, ?string $ip = null, ?string $uptime = null, string $reason = 'manual')
 *
 * @see MikrotikManager
 *
 * @package ZillEAli\MikrotikLaravel\Facades
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class MikroTik extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mikrotik';
    }
}
