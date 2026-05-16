<?php

namespace ZillEAli\MikrotikLaravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * RouterUnreachable
 *
 * Fired when a connection attempt to a MikroTik router fails
 * after all retry attempts are exhausted.
 *
 * Listen to this event to:
 *  - Send NOC alert (Slack, email, SMS)
 *  - Create incident ticket
 *  - Switch to backup router
 *  - Update monitoring dashboard
 *
 * @package ZillEAli\MikrotikLaravel\Events
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class RouterUnreachable
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param string         $host      Router IP or hostname
     * @param int            $port      API port that was attempted
     * @param string         $router    Router name from config
     * @param int            $attempts  Number of connection attempts made
     * @param string         $error     Error message from last attempt
     * @param Throwable|null $exception Original exception
     */
    public function __construct(
        public readonly string     $host,
        public readonly int        $port = 8728,
        public readonly string     $router = 'default',
        public readonly int        $attempts = 1,
        public readonly string     $error = '',
        public readonly ?Throwable $exception = null,
    ) {
    }
}
