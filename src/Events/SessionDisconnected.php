<?php

namespace ZillEAli\MikrotikLaravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SessionDisconnected
 *
 * Fired when a PPPoE or Hotspot session ends.
 *
 * Listen to this event to:
 *  - Calculate session duration for billing
 *  - Update customer last-seen timestamp
 *  - Alert NOC if session drops unexpectedly
 *  - Clean up session records in database
 *
 * @package ZillEAli\MikrotikLaravel\Events
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class SessionDisconnected
{
    use Dispatchable, SerializesModels;

    /**
     * @param string      $username  PPPoE/Hotspot username
     * @param string      $router    Router name from config
     * @param string      $service   Service type: 'pppoe' or 'hotspot'
     * @param string|null $ip        IP address that was assigned
     * @param string|null $uptime    Session uptime at disconnect e.g. '2h14m'
     * @param string      $reason    Disconnect reason: 'manual', 'timeout', 'error'
     * @param array       $raw       Full raw session data from RouterOS
     */
    public function __construct(
        public readonly string  $username,
        public readonly string  $router   = 'default',
        public readonly string  $service  = 'pppoe',
        public readonly ?string $ip       = null,
        public readonly ?string $uptime   = null,
        public readonly string  $reason   = 'manual',
        public readonly array   $raw      = [],
    ) {}
}