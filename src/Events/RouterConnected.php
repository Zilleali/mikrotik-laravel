<?php

namespace ZillEAli\MikrotikLaravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * RouterConnected
 *
 * Fired when a successful connection is established to a router.
 *
 * Listen to this event to:
 *  - Clear previous "router down" alerts
 *  - Log connection restore time
 *  - Resume monitoring
 *
 * @package ZillEAli\MikrotikLaravel\Events
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class RouterConnected
{
    use Dispatchable, SerializesModels;

    /**
     * @param string $host    Router IP or hostname
     * @param int    $port    API port connected on
     * @param string $router  Router name from config
     */
    public function __construct(
        public readonly string $host,
        public readonly int    $port   = 8728,
        public readonly string $router = 'default',
    ) {}
}