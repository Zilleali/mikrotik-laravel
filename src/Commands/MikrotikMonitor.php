<?php

namespace ZillEAli\MikrotikLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;
use ZillEAli\MikrotikLaravel\MikrotikManager;

/**
 * MikrotikMonitor
 *
 * Continuous real-time monitoring of MikroTik router health.
 * Polls the router at configured intervals and displays
 * live stats in the terminal — useful for NOC operators.
 *
 * Usage:
 *  php artisan mikrotik:monitor                      # monitor default router
 *  php artisan mikrotik:monitor --router=main        # monitor named router
 *  php artisan mikrotik:monitor --interval=10        # poll every 10 seconds
 *  php artisan mikrotik:monitor --interval=5 --once  # run once and exit
 *
 * @package ZillEAli\MikrotikLaravel\Commands
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class MikrotikMonitor extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mikrotik:monitor
                            {--router=default : Router name to monitor}
                            {--interval=30 : Polling interval in seconds}
                            {--once : Run once and exit (no loop)}';

    /**
     * The console command description.
     */
    protected $description = 'Real-time MikroTik router health monitoring in terminal';

    /**
     * Whether the monitoring loop should continue.
     */
    protected bool $running = true;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $routerName = $this->option('router');
        $interval = max(1, (int) $this->option('interval'));
        $once = $this->option('once');

        $this->showHeader($routerName);

        // Single run mode
        if ($once) {
            return $this->poll($routerName);
        }

        // Continuous loop
        $this->line('  Press <fg=yellow>Ctrl+C</> to stop monitoring.');
        $this->newLine();

        while ($this->running) {
            $this->poll($routerName);
            sleep($interval);
        }

        return self::SUCCESS;
    }

    /**
     * Poll router once and display stats.
     *
     * @param  string $routerName
     * @return int
     */
    protected function poll(string $routerName): int
    {
        try {
            $manager = app(MikrotikManager::class);

            if ($routerName !== 'default') {
                $manager->router($routerName);
            }

            $resources = $manager->system()->getResources();
            $identity = $manager->system()->getIdentity();
            $sessions = $manager->pppoe()->getActiveSessions();
            $hosts = $manager->hotspot()->getActiveHosts();

            $this->displayStats(
                routerName: $routerName,
                identity:   $identity,
                resources:  $resources,
                sessions:   count($sessions),
                hosts:      count($hosts),
            );

            return self::SUCCESS;

        } catch (ConnectionException $e) {
            $this->line(sprintf(
                '  [%s] <fg=red>UNREACHABLE</> — %s',
                $routerName,
                $e->getMessage(),
            ));

            Log::warning("MikroTik monitor: router [{$routerName}] unreachable", [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Display formatted stats for one poll cycle.
     *
     * @param  string $routerName
     * @param  string $identity
     * @param  array  $resources
     * @param  int    $sessions
     * @param  int    $hosts
     * @return void
     */
    protected function displayStats(
        string $routerName,
        string $identity,
        array  $resources,
        int    $sessions,
        int    $hosts,
    ): void {
        $cpu = $resources['cpu-load'] ?? '?';
        $uptime = $resources['uptime'] ?? '?';
        $mem = $resources['free-memory'] ?? 0;
        $total = $resources['total-memory'] ?? 1;
        $memPct = $total > 0 ? round((1 - $mem / $total) * 100) : 0;
        $time = now()->format('H:i:s');

        $cpuColor = match(true) {
            $cpu > 80 => 'red',
            $cpu > 50 => 'yellow',
            default => 'green',
        };

        $this->line(sprintf(
            '  <fg=gray>%s</>  <fg=cyan>%-14s</>  CPU: <fg=%s>%3s%%</>  RAM: <fg=yellow>%3s%%</>  PPPoE: <fg=green>%3d</>  HS: <fg=green>%3d</>  Up: <fg=gray>%s</>',
            $time,
            "[{$identity}]",
            $cpuColor,
            $cpu,
            $memPct,
            $sessions,
            $hosts,
            $uptime,
        ));
    }

    /**
     * Display monitor header.
     *
     * @param  string $routerName
     * @return void
     */
    protected function showHeader(string $routerName): void
    {
        $this->newLine();
        $this->line('  <fg=cyan>zilleali/mikrotik-laravel</> — Router Monitor');
        $this->line(sprintf('  Router : <fg=yellow>%s</>', $routerName));
        $this->line(sprintf('  Started: <fg=gray>%s</>', now()->format('Y-m-d H:i:s')));
        $this->line('  ' . str_repeat('─', 70));
        $this->line('  <fg=gray>Time      Router          CPU     RAM     PPPoE  HS    Uptime</>');
        $this->line('  ' . str_repeat('─', 70));
    }
}
