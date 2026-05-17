<?php

namespace ZillEAli\MikrotikLaravel\Filament\Widgets;

use ZillEAli\MikrotikLaravel\MikrotikManager;

/**
 * RouterHealthWidget
 *
 * Filament StatsOverviewWidget for router CPU, RAM and uptime.
 * Requires filament/filament ^3.0 in your application.
 *
 * @package ZillEAli\MikrotikLaravel\Filament\Widgets
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class RouterHealthWidget
{
    public string $pollingInterval = '60s';

    public string $heading = 'Router Health';

    public function getStats(): array
    {
        try {
            $manager   = app(MikrotikManager::class);
            $resources = $manager->system()->getResources();
            $identity  = $manager->system()->getIdentity();

            $cpu      = $resources['cpu-load']    ?? 0;
            $uptime   = $resources['uptime']      ?? 'N/A';
            $version  = $resources['version']     ?? 'N/A';
            $freeMem  = (int) ($resources['free-memory']  ?? 0);
            $totalMem = (int) ($resources['total-memory'] ?? 1);
            $memPct   = $totalMem > 0
                ? round((1 - $freeMem / $totalMem) * 100)
                : 0;

            return [
                ['label' => $identity,     'value' => "CPU {$cpu}%",   'description' => "RouterOS {$version}"],
                ['label' => 'RAM Usage',   'value' => "{$memPct}%",    'description' => 'Memory utilization'],
                ['label' => 'Uptime',      'value' => $uptime,         'description' => 'Router uptime'],
            ];

        } catch (\Throwable) {
            return [
                ['label' => 'Router', 'value' => 'Unreachable'],
            ];
        }
    }
}