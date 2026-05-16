<?php

namespace ZillEAli\MikrotikLaravel\Commands;

use Illuminate\Console\Command;
use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;

/**
 * MikrotikPing
 *
 * Test TCP connectivity to one or all configured routers.
 * Does not require full API authentication — just checks
 * if the router API port is reachable.
 *
 * Usage:
 *  php artisan mikrotik:ping            # ping default router
 *  php artisan mikrotik:ping main       # ping named router
 *  php artisan mikrotik:ping --all      # ping all configured routers
 *
 * @package ZillEAli\MikrotikLaravel\Commands
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class MikrotikPing extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mikrotik:ping
                            {router? : Router name from config (default, main, branch, etc.)}
                            {--all : Ping all configured routers}
                            {--timeout=3 : TCP connection timeout in seconds}';

    /**
     * The console command description.
     */
    protected $description = 'Test TCP connectivity to MikroTik router API port';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $config  = config('mikrotik', []);
        $timeout = (int) $this->option('timeout');

        // Ping all routers
        if ($this->option('all')) {
            return $this->pingAll($config, $timeout);
        }

        // Ping specific or default router
        $routerName = $this->argument('router') ?? 'default';
        $routerCfg  = $this->resolveRouterConfig($config, $routerName);

        if (! $routerCfg) {
            $this->error("Router '{$routerName}' not found in config/mikrotik.php");
            return self::FAILURE;
        }

        return $this->pingRouter($routerName, $routerCfg, $timeout);
    }

    /**
     * Ping all routers defined in config.
     *
     * @param  array $config  Full mikrotik config
     * @param  int   $timeout TCP timeout
     * @return int            Exit code
     */
    protected function pingAll(array $config, int $timeout): int
    {
        $routers = ['default' => $config] + ($config['routers'] ?? []);
        $allOk   = true;

        $this->info('Pinging all configured routers...');
        $this->newLine();

        foreach ($routers as $name => $cfg) {
            $result = $this->pingRouter($name, $cfg, $timeout);
            if ($result !== self::SUCCESS) {
                $allOk = false;
            }
        }

        $this->newLine();
        $this->info($allOk ? 'All routers reachable.' : 'Some routers unreachable.');

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Ping a single router and display result.
     *
     * @param  string $name    Router name
     * @param  array  $cfg     Router config array
     * @param  int    $timeout TCP timeout in seconds
     * @return int             Exit code
     */
    protected function pingRouter(string $name, array $cfg, int $timeout): int
    {
        $host = $cfg['host'] ?? 'unknown';
        $port = $cfg['port'] ?? 8728;

        $this->line(sprintf(
            '  <fg=yellow>%-12s</> %s:%d ... ',
            "[{$name}]",
            $host,
            $port,
        ), null, 'v');

        $start  = microtime(true);
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $ms     = round((microtime(true) - $start) * 1000);

        if ($socket) {
            fclose($socket);
            $this->line(sprintf(
                '  <fg=yellow>%-12s</> %s:%d ... <fg=green>OK</> (%dms)',
                "[{$name}]",
                $host,
                $port,
                $ms,
            ));
            return self::SUCCESS;
        }

        $this->line(sprintf(
            '  <fg=yellow>%-12s</> %s:%d ... <fg=red>FAIL</> %s',
            "[{$name}]",
            $host,
            $port,
            $errstr,
        ));

        return self::FAILURE;
    }

    /**
     * Resolve router config by name.
     *
     * @param  array       $config     Full config array
     * @param  string      $routerName Router name
     * @return array|null              Router config or null if not found
     */
    protected function resolveRouterConfig(array $config, string $routerName): ?array
    {
        if ($routerName === 'default') {
            return $config;
        }

        return $config['routers'][$routerName] ?? null;
    }
}