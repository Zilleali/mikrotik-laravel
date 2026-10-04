<?php

namespace ZillEAli\MikrotikLaravel\Commands;

use Illuminate\Console\Command;
use ZillEAli\MikrotikLaravel\MikrotikManager;
use ZillEAli\MikrotikLaravel\Services\FleetManager;

/**
 * MikrotikFleet
 *
 * One-screen health overview of every configured router — identity,
 * RouterOS version, CPU, memory, uptime and active sessions.
 *
 * Usage:
 *  php artisan mikrotik:fleet                       # all routers
 *  php artisan mikrotik:fleet --group=north         # routers tagged 'north'
 *  php artisan mikrotik:fleet --router=main --router=branch
 *  php artisan mikrotik:fleet --json                # machine-readable output
 *
 * Exits with FAILURE when at least one router is unreachable, so it can
 * drive cron alerts or uptime checks.
 *
 * @package ZillEAli\MikrotikLaravel\Commands
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class MikrotikFleet extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mikrotik:fleet
                            {--group= : Only routers tagged with this group}
                            {--router=* : Only these routers (repeatable)}
                            {--json : Output JSON instead of a table}';

    /**
     * The console command description.
     */
    protected $description = 'Health and session overview of all configured MikroTik routers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fleet = $this->resolveFleet();

        if ($fleet->routers() === []) {
            $this->warn('No routers matched the given filter.');

            return self::FAILURE;
        }

        $health = $fleet->health();
        $sessions = $fleet->activeSessionCounts();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'health' => $health->toArray(),
                'sessions' => $sessions->toArray(),
            ], JSON_PRETTY_PRINT));

            return $health->hasFailures() ? self::FAILURE : self::SUCCESS;
        }

        $rows = [];

        foreach ($fleet->routers() as $name) {
            $h = $health->get($name);

            if ($h === null) {
                $rows[] = [
                    $name, '<fg=red>DOWN</>', '-', '-', '-', '-', '-', '-',
                ];

                continue;
            }

            $s = $sessions->get($name, ['pppoe' => 0, 'hotspot' => 0]);

            $rows[] = [
                $name,
                '<fg=green>UP</>',
                $h['identity'],
                $h['version'],
                $h['cpu_load'] . '%',
                $h['memory_used_percent'] . '%',
                $h['uptime'],
                $s['pppoe'] . ' / ' . $s['hotspot'],
            ];
        }

        $this->table(
            ['Router', 'Status', 'Identity', 'Version', 'CPU', 'RAM', 'Uptime', 'PPPoE / HS'],
            $rows,
        );

        foreach ($health->failed() as $name => $e) {
            $this->error("  [{$name}] {$e->getMessage()}");
        }

        $this->info(sprintf(
            '%d/%d routers reachable.',
            count($health->succeededRouters()),
            count($health),
        ));

        return $health->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Build the fleet from the --group / --router options.
     *
     * @return FleetManager
     */
    protected function resolveFleet(): FleetManager
    {
        $fleet = app(MikrotikManager::class)->fleet();

        if ($group = $this->option('group')) {
            $fleet = $fleet->group((string) $group);
        }

        /** @var list<string> $routers */
        $routers = (array) $this->option('router');

        if ($routers !== []) {
            $fleet = $fleet->only(...$routers);
        }

        return $fleet;
    }
}
