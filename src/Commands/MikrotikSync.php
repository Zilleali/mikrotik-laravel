<?php

namespace ZillEAli\MikrotikLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;
use ZillEAli\MikrotikLaravel\MikrotikManager;

/**
 * MikrotikSync
 *
 * Sync active PPPoE sessions and system stats from router
 * to Laravel cache for use in dashboards without
 * hitting the router on every page load.
 *
 * Usage:
 *  php artisan mikrotik:sync                  # sync default router
 *  php artisan mikrotik:sync --router=branch  # sync named router
 *  php artisan mikrotik:sync --all            # sync all routers
 *
 * Schedule in routes/console.php:
 *  Schedule::command('mikrotik:sync')->everyMinute();
 *
 * @package ZillEAli\MikrotikLaravel\Commands
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class MikrotikSync extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mikrotik:sync
                            {--router=default : Router name to sync}
                            {--all : Sync all configured routers}
                            {--ttl=60 : Cache TTL in seconds}';

    /**
     * The console command description.
     */
    protected $description = 'Sync MikroTik router data (sessions, stats) to Laravel cache';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $config = config('mikrotik', []);
        $ttl = (int) $this->option('ttl');

        if ($this->option('all')) {
            return $this->syncAll($config, $ttl);
        }

        $routerName = $this->option('router');

        return $this->syncRouter($routerName, $ttl);
    }

    /**
     * Sync all configured routers.
     *
     * @param  array $config Full mikrotik config
     * @param  int   $ttl    Cache TTL
     * @return int
     */
    protected function syncAll(array $config, int $ttl): int
    {
        $routers = array_keys(['default' => true] + ($config['routers'] ?? []));
        $allOk = true;

        foreach ($routers as $name) {
            $result = $this->syncRouter($name, $ttl);
            if ($result !== self::SUCCESS) {
                $allOk = false;
            }
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Sync a single router's data to cache.
     *
     * @param  string $routerName Router name
     * @param  int    $ttl        Cache TTL in seconds
     * @return int
     */
    protected function syncRouter(string $routerName, int $ttl): int
    {
        $this->line("  Syncing <fg=yellow>[{$routerName}]</> ...");

        try {
            $manager = app(MikrotikManager::class);

            if ($routerName !== 'default') {
                $manager->router($routerName);
            }

            // Sync PPPoE active sessions
            $sessions = $manager->pppoe()->getActiveSessions();
            cache()->put(
                "mikrotik.{$routerName}.pppoe.sessions",
                $sessions,
                $ttl
            );

            // Sync system resources
            $resources = $manager->system()->getResources();
            cache()->put(
                "mikrotik.{$routerName}.system.resources",
                $resources,
                $ttl
            );

            // Sync hotspot active hosts
            $hosts = $manager->hotspot()->getActiveHosts();
            cache()->put(
                "mikrotik.{$routerName}.hotspot.hosts",
                $hosts,
                $ttl
            );

            $this->line(sprintf(
                '  <fg=green>✓</> [%s] PPPoE: %d sessions, Hotspot: %d hosts, CPU: %s%%',
                $routerName,
                count($sessions),
                count($hosts),
                $resources['cpu-load'] ?? '?',
            ));

            Log::info("MikroTik sync completed for [{$routerName}]", [
                'pppoe_sessions' => count($sessions),
                'hotspot_hosts' => count($hosts),
                'cpu_load' => $resources['cpu-load'] ?? null,
            ]);

            return self::SUCCESS;

        } catch (ConnectionException $e) {
            $this->error("  ✗ [{$routerName}] Connection failed: {$e->getMessage()}");
            Log::error("MikroTik sync failed for [{$routerName}]", [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error("  ✗ [{$routerName}] Error: {$e->getMessage()}");
            Log::error("MikroTik sync error for [{$routerName}]", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
