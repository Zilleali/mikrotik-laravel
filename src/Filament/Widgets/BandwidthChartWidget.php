<?php

namespace ZillEAli\MikrotikLaravel\Filament\Widgets;

use ZillEAli\MikrotikLaravel\MikrotikManager;

/**
 * BandwidthChartWidget
 *
 * Filament ChartWidget for real-time TX/RX bandwidth monitoring.
 * Requires filament/filament ^3.0 in your application.
 *
 * @package ZillEAli\MikrotikLaravel\Filament\Widgets
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class BandwidthChartWidget
{
    public string $pollingInterval = '30s';

    public string $heading = 'Bandwidth — ether1 (TX / RX)';

    public string $interface = 'ether1';

    protected static array $dataPoints = [];

    protected int $maxPoints = 20;

    public function getData(): array
    {
        try {
            $manager = app(MikrotikManager::class);
            $traffic = $manager->interfaces()->getTraffic($this->interface);

            $tx   = (int) (($traffic['tx-bits-per-second'] ?? 0) / 1_000_000);
            $rx   = (int) (($traffic['rx-bits-per-second'] ?? 0) / 1_000_000);
            $time = now()->format('H:i:s');

            static::$dataPoints[] = compact('tx', 'rx', 'time');

            if (count(static::$dataPoints) > $this->maxPoints) {
                array_shift(static::$dataPoints);
            }

        } catch (\Throwable) {}

        return [
            'datasets' => [
                [
                    'label'       => 'TX (Mbps)',
                    'data'        => array_column(static::$dataPoints, 'tx'),
                    'borderColor' => '#1D9E75',
                ],
                [
                    'label'       => 'RX (Mbps)',
                    'data'        => array_column(static::$dataPoints, 'rx'),
                    'borderColor' => '#1F6FEB',
                ],
            ],
            'labels' => array_column(static::$dataPoints, 'time'),
        ];
    }

    public function getType(): string
    {
        return 'line';
    }
}