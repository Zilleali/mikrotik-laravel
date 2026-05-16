<?php

namespace ZillEAli\MikrotikLaravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * SessionCreated
 *
 * Fired when a new PPPoE or Hotspot session is created.
 *
 * Listen to this event to:
 *  - Log new connections
 *  - Send welcome SMS/notification to customer
 *  - Update billing system
 *  - Record session start time in database
 *
 * Usage in listener:
 *  public function handle(SessionCreated $event): void
 *  {
 *      Log::info("New session: {$event->username} @ {$event->ip}");
 *  }
 *
 * @package ZillEAli\MikrotikLaravel\Events
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class SessionCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param string      $username   PPPoE/Hotspot username
     * @param string      $ip         Assigned IP address
     * @param string      $router     Router name from config e.g. 'main', 'branch'
     * @param string      $service    Service type: 'pppoe' or 'hotspot'
     * @param string|null $macAddress Client MAC address (hotspot only)
     * @param array       $raw        Full raw session data from RouterOS
     */
    public function __construct(
        public readonly string  $username,
        public readonly string  $ip,
        public readonly string  $router = 'default',
        public readonly string  $service = 'pppoe',
        public readonly ?string $macAddress = null,
        public readonly array   $raw = [],
    ) {
    }
}
