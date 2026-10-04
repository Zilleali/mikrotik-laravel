<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;
use ZillEAli\MikrotikLaravel\MikrotikManager;
use ZillEAli\MikrotikLaravel\Support\FleetResult;
use ZillEAli\MikrotikLaravel\Support\HasValidation;
use ZillEAli\MikrotikLaravel\Support\MikrotikLogger;

/**
 * FleetManager
 *
 * Manage every configured router from one place. Runs the same operation
 * on each router in turn and returns a FleetResult, so an unreachable
 * site is reported instead of aborting the whole run.
 *
 * Routers can be tagged with groups in config/mikrotik.php:
 *  'routers' => ['tower-1' => ['host' => ..., 'groups' => ['north', 'fiber']]]
 *
 * Usage:
 *  MikroTik::fleet()->health()
 *  MikroTik::fleet()->group('north')->activeSessionCounts()
 *  MikroTik::fleet()->findPppoeSession('ali-home')
 *  MikroTik::fleet()->only('main', 'branch')->each(
 *      fn (MikrotikManager $router, string $name) => $router->queue()->getSimpleQueues()
 *  )
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class FleetManager
{
    use HasValidation;

    /**
     * Explicit router selection; null means "all configured routers".
     *
     * @var list<string>|null
     */
    protected ?array $selected = null;

    /**
     * @param MikrotikManager $manager        Root manager (connections are pooled per router)
     * @param bool            $includeDefault Include the 'default' router when no selection is made
     */
    public function __construct(
        protected MikrotikManager $manager,
        protected bool $includeDefault = true,
    ) {
    }

    // =========================================================
    // Router Selection
    // =========================================================

    /**
     * Router names this fleet instance will operate on.
     *
     * @return list<string>
     */
    public function routers(): array
    {
        if ($this->selected !== null) {
            return $this->selected;
        }

        $names = $this->manager->getRouterNames();

        if (! $this->includeDefault) {
            $names = array_values(array_filter($names, fn (string $n) => $n !== 'default'));
        }

        return $names;
    }

    /**
     * Limit the fleet to the given routers.
     *
     * @param  string ...$names Router names, 'default' allowed
     * @return static
     * @throws ConnectionException If a name is not configured
     */
    public function only(string ...$names): static
    {
        $known = $this->manager->getRouterNames();

        foreach ($names as $name) {
            if (! in_array($name, $known, true)) {
                throw ConnectionException::routerNotFound($name);
            }
        }

        $fleet = clone $this;
        $fleet->selected = array_values(array_unique($names));

        return $fleet;
    }

    /**
     * Exclude the given routers from the current selection.
     *
     * @param  string ...$names
     * @return static
     */
    public function except(string ...$names): static
    {
        $fleet = clone $this;
        $fleet->selected = array_values(array_diff($this->routers(), $names));

        return $fleet;
    }

    /**
     * Limit the fleet to routers tagged with a group ('groups' config key).
     *
     * @param  string $group
     * @return static
     */
    public function group(string $group): static
    {
        $this->validateNotEmpty($group, 'group');

        $fleet = clone $this;
        $fleet->selected = $this->manager->getRouterNames($group);

        return $fleet;
    }

    // =========================================================
    // Generic Execution
    // =========================================================

    /**
     * Run a callback against every selected router.
     *
     * The callback receives a manager pinned to that router (see
     * MikrotikManager::on()) and the router name. Any exception is caught,
     * logged and stored in the result; the remaining routers still run.
     *
     * @template TValue
     * @param  callable(MikrotikManager, string): TValue $callback
     * @return FleetResult<TValue>
     */
    public function each(callable $callback): FleetResult
    {
        $results = [];
        $errors = [];

        foreach ($this->routers() as $name) {
            try {
                $results[$name] = $callback($this->manager->on($name), $name);
            } catch (\Throwable $e) {
                $errors[$name] = $e;

                MikrotikLogger::warning('fleet', "Operation failed on router [{$name}]", [
                    'router' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new FleetResult($results, $errors);
    }

    // =========================================================
    // Monitoring
    // =========================================================

    /**
     * Health snapshot of every router.
     *
     * @return FleetResult<array{identity: string, version: string, board: string, uptime: string, cpu_load: int, free_memory: int, total_memory: int, memory_used_percent: float, latency_ms: float}>
     */
    public function health(): FleetResult
    {
        return $this->each(function (MikrotikManager $router): array {
            $start = microtime(true);
            $system = $router->system();
            $resources = $system->getResources();
            $latency = round((microtime(true) - $start) * 1000, 2);

            $free = (int) ($resources['free-memory'] ?? 0);
            $total = (int) ($resources['total-memory'] ?? 0);

            return [
                'identity' => $system->getIdentity(),
                'version' => $resources['version'] ?? 'Unknown',
                'board' => $resources['board-name'] ?? 'Unknown',
                'uptime' => $resources['uptime'] ?? '0s',
                'cpu_load' => (int) ($resources['cpu-load'] ?? 0),
                'free_memory' => $free,
                'total_memory' => $total,
                'memory_used_percent' => $total > 0 ? round(($total - $free) / $total * 100, 1) : 0.0,
                'latency_ms' => $latency,
            ];
        });
    }

    /**
     * Router names that are reachable right now.
     *
     * @return list<string>
     */
    public function reachable(): array
    {
        $alive = $this->each(fn (MikrotikManager $router) => $router->diagnostics()->isAlive());

        return array_map('strval', array_keys(array_filter($alive->successful())));
    }

    /**
     * Router names that failed to respond.
     *
     * @return list<string>
     */
    public function unreachable(): array
    {
        return array_values(array_diff($this->routers(), $this->reachable()));
    }

    /**
     * Active PPPoE and Hotspot session counts per router.
     *
     * @return FleetResult<array{pppoe: int, hotspot: int}>
     */
    public function activeSessionCounts(): FleetResult
    {
        return $this->each(fn (MikrotikManager $router): array => [
            'pppoe' => count($router->pppoe()->getActiveSessions()),
            'hotspot' => count($router->hotspot()->getActiveHosts()),
        ]);
    }

    /**
     * Sum of active sessions across all routers that responded.
     *
     * @return array{pppoe: int, hotspot: int, total: int}
     */
    public function totalActiveSessions(): array
    {
        $pppoe = 0;
        $hotspot = 0;

        foreach ($this->activeSessionCounts() as $counts) {
            $pppoe += $counts['pppoe'];
            $hotspot += $counts['hotspot'];
        }

        return ['pppoe' => $pppoe, 'hotspot' => $hotspot, 'total' => $pppoe + $hotspot];
    }

    // =========================================================
    // Subscriber Lookup
    // =========================================================

    /**
     * Find which router(s) a PPPoE user is currently connected to.
     *
     * A user can appear on several NAS routers when sessions are stale or
     * credentials are shared, so every match is returned.
     *
     * @param  string $username PPPoE username
     * @return array<string, array<string, string>> Active session keyed by router name
     */
    public function findPppoeSession(string $username): array
    {
        $this->validateNotEmpty($username, 'username');

        return $this->findSession(
            fn (MikrotikManager $router) => $router->pppoe()->getActiveSessions(),
            fn (array $session) => ($session['name'] ?? null) === $username,
        );
    }

    /**
     * Find which router(s) an IP address is active on (PPPoE sessions).
     *
     * @param  string $ip
     * @return array<string, array<string, string>> Active session keyed by router name
     */
    public function findSessionByIp(string $ip): array
    {
        $this->validateIp($ip);

        return $this->findSession(
            fn (MikrotikManager $router) => $router->pppoe()->getActiveSessions(),
            fn (array $session) => ($session['address'] ?? null) === $ip,
        );
    }

    /**
     * Find which router(s) a Hotspot user is currently logged in on.
     *
     * @param  string $username Hotspot username
     * @return array<string, array<string, string>> Active host keyed by router name
     */
    public function findHotspotSession(string $username): array
    {
        $this->validateNotEmpty($username, 'username');

        return $this->findSession(
            fn (MikrotikManager $router) => $router->hotspot()->getActiveHosts(),
            fn (array $host) => ($host['user'] ?? null) === $username,
        );
    }

    /**
     * Disconnect a PPPoE user on every router where they are online.
     *
     * Typical billing use: suspend the secret, then kick the live session
     * wherever it is, without knowing which NAS the subscriber is on.
     *
     * @param  string $username PPPoE username
     * @return list<string> Router names the session was kicked from
     */
    public function kickPppoeEverywhere(string $username): array
    {
        $this->validateNotEmpty($username, 'username');

        $kicked = [];

        foreach (array_keys($this->findPppoeSession($username)) as $name) {
            try {
                $this->manager->on($name)->pppoe()->kickSession($username);
                $kicked[] = $name;

                MikrotikLogger::critical('fleet', 'kickPppoeEverywhere', $username, $name);
            } catch (\Throwable $e) {
                MikrotikLogger::error('fleet', "Kick failed on router [{$name}]", [
                    'router' => $name,
                    'username' => $username,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $kicked;
    }

    // =========================================================
    // Internal
    // =========================================================

    /**
     * Search a list of rows on every router and return the first match per router.
     *
     * @param  callable(MikrotikManager): array<int, array<string, string>> $fetch
     * @param  callable(array<string, string>): bool                        $match
     * @return array<string, array<string, string>>
     */
    protected function findSession(callable $fetch, callable $match): array
    {
        $found = [];

        foreach ($this->each($fetch)->successful() as $name => $rows) {
            foreach ($rows as $row) {
                if ($match($row)) {
                    $found[$name] = $row;

                    break;
                }
            }
        }

        return $found;
    }
}
