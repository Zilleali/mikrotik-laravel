<?php

namespace ZillEAli\MikrotikLaravel\Pulse\Livewire;

use Illuminate\Contracts\View\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

/**
 * RouterHealthCard
 *
 * Laravel Pulse card that displays MikroTik router health metrics:
 *  - CPU usage (avg / max)
 *  - Memory usage (avg / max)
 *  - API latency (avg)
 *  - Uptime ratio (alive beats / total beats)
 *
 * Add to your Pulse dashboard view:
 *
 *   <livewire:pulse.mikrotik-router-health cols="4" rows="2" />
 *
 * Data is populated by RouterHealthRecorder running via:
 *   php artisan pulse:check
 *
 * @package ZillEAli\MikrotikLaravel\Pulse\Livewire
 * @author  Zill E Ali <zilleali1245@gmail.com>
 */
#[Lazy]
class RouterHealthCard extends Card
{
    public function render(): View
    {
        $cpu    = $this->aggregate('mikrotik_cpu',         ['avg', 'max']);
        $memory = $this->aggregate('mikrotik_memory',      ['avg', 'max']);
        $latency = $this->aggregate('mikrotik_latency',    ['avg', 'max']);
        $beats  = $this->aggregate('mikrotik_uptime_beat', ['sum', 'count']);

        return view('mikrotik-laravel::pulse.router-health', [
            'cpu'     => $cpu->first(),
            'memory'  => $memory->first(),
            'latency' => $latency->first(),
            'beats'   => $beats->first(),
        ]);
    }
}
