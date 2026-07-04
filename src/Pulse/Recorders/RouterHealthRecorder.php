<?php

namespace ZillEAli\MikrotikLaravel\Pulse\Recorders;

use Illuminate\Support\Facades\Config;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Facades\Pulse;
use ZillEAli\MikrotikLaravel\MikrotikManager;

/**
 * RouterHealthRecorder
 *
 * Records MikroTik router health metrics into Laravel Pulse.
 *
 * Listens to SharedBeat (emitted every ~15s by `php artisan pulse:check`)
 * and polls the router once per minute to record:
 *  - CPU load percentage   (mikrotik_cpu)
 *  - Memory usage %        (mikrotik_memory)
 *  - API round-trip latency (mikrotik_latency)
 *  - Uptime beat           (mikrotik_uptime_beat) — 1=alive, 0=down
 *
 * Register in config/pulse.php:
 *
 *   'recorders' => [
 *       \ZillEAli\MikrotikLaravel\Pulse\Recorders\RouterHealthRecorder::class => [
 *           'router_name' => env('PULSE_MIKROTIK_ROUTER', 'default'),
 *       ],
 *   ],
 *
 * @package ZillEAli\MikrotikLaravel\Pulse\Recorders
 * @author  Zill E Ali <zilleali1245@gmail.com>
 */
class RouterHealthRecorder
{
    /**
     * Events this recorder listens for.
     * Pulse wires these automatically from `$listen`.
     *
     * @var list<class-string>
     */
    public array $listen = [
        SharedBeat::class,
    ];

    public function __construct(protected MikrotikManager $manager)
    {
    }

    /**
     * Record router health metrics.
     *
     * Throttled to once per minute — skips beats where second != 0.
     */
    public function record(SharedBeat $event): void
    {
        // Only poll on the first beat of each minute (second == 0)
        if ($event->time->second % 60 !== 0) {
            return;
        }

        $routerKey = Config::get(
            'pulse.recorders.' . static::class . '.router_name',
            'default'
        );

        try {
            $resources = $this->manager->system()->getResources();
            $latency   = $this->manager->diagnostics()->latency(samples: 1);

            $cpu    = (int) ($resources['cpu-load'] ?? 0);
            $free   = (int) ($resources['free-memory'] ?? 0);
            $total  = (int) ($resources['total-memory'] ?? 1);
            $memPct = $total > 0 ? (int) round((1 - $free / $total) * 100) : 0;
            $latMs  = max(0, (int) $latency);

            Pulse::record('mikrotik_cpu',         $routerKey, $cpu)->avg()->max();
            Pulse::record('mikrotik_memory',       $routerKey, $memPct)->avg()->max();
            Pulse::record('mikrotik_latency',      $routerKey, $latMs)->avg()->max();
            Pulse::record('mikrotik_uptime_beat',  $routerKey, 1)->count()->sum();

        } catch (\Throwable) {
            // Router unreachable — record a down beat only
            Pulse::record('mikrotik_uptime_beat', $routerKey, 0)->count()->sum();
        }
    }
}
