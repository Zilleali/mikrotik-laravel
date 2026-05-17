<?php

namespace ZillEAli\MikrotikLaravel\Filament\Widgets;

use ZillEAli\MikrotikLaravel\MikrotikManager;

/**
 * ActiveSessionsWidget
 *
 * Filament StatsOverviewWidget for live PPPoE and Hotspot counts.
 * Requires filament/filament ^3.0 in your application.
 *
 * Register in panel provider:
 *  ->widgets([ActiveSessionsWidget::class])
 *
 * @package ZillEAli\MikrotikLaravel\Filament\Widgets
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class ActiveSessionsWidget
{
    public string $pollingInterval = '30s';

    public string $heading = 'Active Sessions';

    public function getStats(): array
    {
        try {
            $manager = app(MikrotikManager::class);
            $pppoe   = count($manager->pppoe()->getActiveSessions());
            $hotspot = count($manager->hotspot()->getActiveHosts());

            return [
                ['label' => 'Total Active',    'value' => $pppoe + $hotspot],
                ['label' => 'PPPoE Sessions',  'value' => $pppoe],
                ['label' => 'Hotspot Hosts',   'value' => $hotspot],
            ];

        } catch (\Throwable) {
            return [
                ['label' => 'Router', 'value' => 'Unreachable'],
            ];
        }
    }
}