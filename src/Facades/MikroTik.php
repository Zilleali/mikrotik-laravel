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
 * 
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