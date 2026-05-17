<?php

namespace ZillEAli\MikrotikLaravel\Filament\Widgets;

use ZillEAli\MikrotikLaravel\MikrotikManager;

/**
 * InterfaceTableWidget
 *
 * Filament TableWidget for router interface status monitoring.
 * Requires filament/filament ^3.0 in your application.
 *
 * @package ZillEAli\MikrotikLaravel\Filament\Widgets
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class InterfaceTableWidget
{
    public string $pollingInterval = '60s';

    public string $heading = 'Interfaces';

    public function getInterfaces(): array
    {
        try {
            $manager    = app(MikrotikManager::class);
            $interfaces = $manager->interfaces()->getInterfaces();

            return array_map(function ($iface) {
                return array_merge($iface, [
                    'running'  => ($iface['running']  ?? 'false') === 'true',
                    'disabled' => ($iface['disabled'] ?? 'false') === 'true',
                ]);
            }, $interfaces);

        } catch (\Throwable) {
            return [];
        }
    }
}