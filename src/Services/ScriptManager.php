<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ResourceNotFoundException;
use ZillEAli\MikrotikLaravel\Support\HasIdValidation;
use ZillEAli\MikrotikLaravel\Support\MikrotikLogger;

/**
 * ScriptManager
 *
 * Manages MikroTik RouterOS scripts and schedulers.
 *
 * Critical for ISPs:
 *  - Automated backup scripts
 *  - Scheduled maintenance tasks
 *  - Auto-reboot schedulers
 *  - PPPoE session cleanup scripts
 *  - Dynamic firewall rule scripts
 *  - Bandwidth report generation
 *
 * Usage:
 *  $manager = new ScriptManager($client);
 *  $manager->addScript('flush-dns', '/ip dns flush');
 *  $manager->runScript('flush-dns');
 *  $manager->addScheduler('daily-backup', 'backup-config', '1d');
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class ScriptManager
{
    use HasIdValidation;

    private const CMD_SCRIPT_PRINT = '/system/script/print';
    private const CMD_SCRIPT_ADD = '/system/script/add';
    private const CMD_SCRIPT_SET = '/system/script/set';
    private const CMD_SCRIPT_REMOVE = '/system/script/remove';
    private const CMD_SCRIPT_RUN = '/system/script/run';
    private const CMD_SCHED_PRINT = '/system/scheduler/print';
    private const CMD_SCHED_ADD = '/system/scheduler/add';
    private const CMD_SCHED_SET = '/system/scheduler/set';
    private const CMD_SCHED_REMOVE = '/system/scheduler/remove';

    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Scripts
    // =========================================================

    /**
     * Get all scripts on the router.
     *
     * @return array[] Scripts with name, owner, policy, source, disabled
     */
    public function getScripts(): array
    {
        return $this->client->query(self::CMD_SCRIPT_PRINT);
    }

    /**
     * Get a single script by name.
     *
     * @param  string     $name Script name
     * @return array|null       Script data or null if not found
     */
    public function getScript(string $name): ?array
    {
        $scripts = $this->getScripts();

        foreach ($scripts as $script) {
            if (($script['name'] ?? '') === $name) {
                return $script;
            }
        }

        return null;
    }

    /**
     * Get total number of scripts.
     *
     * @return int
     */
    public function getScriptCount(): int
    {
        return count($this->getScripts());
    }

    /**
     * Add a new RouterOS script.
     *
     * @param  string      $name    Script name
     * @param  string      $source  RouterOS script source code
     * @param  string      $policy  Comma-separated policies e.g. 'read,write,api'
     * @param  string|null $comment Optional comment
     * @return void
     *
     * Example:
     *  $manager->addScript(
     *      'flush-dns',
     *      '/ip dns flush',
     *      comment: 'Flush DNS cache'
     *  );
     */
    public function addScript(
        string  $name,
        string  $source,
        string  $policy = 'read,write',
        ?string $comment = null
    ): void {
        $data = [
            'name' => $name,
            'source' => $source,
            'policy' => $policy,
        ];

        if ($comment !== null) {
            $data['comment'] = $comment;
        }

        $this->client->query(self::CMD_SCRIPT_ADD, $data);
    }

    /**
     * Update an existing script.
     *
     * @param  string $name Script name to update
     * @param  array  $data Fields to update e.g. ['source' => '...']
     * @return void
     */
    public function updateScript(string $name, array $data): void
    {
        $script = $this->getScript($name);

        if (! $script) {
            throw ResourceNotFoundException::for('script', $name);
        }

        $id = $this->extractId($script, 'script');

        $this->client->query(
            self::CMD_SCRIPT_SET,
            array_merge(['.id' => $id], $data)
        );
    }

    /**
     * Remove a script by name.
     *
     * @param  string $name Script name to remove
     * @return void
     */
    public function removeScript(string $name): void
    {
        $script = $this->getScript($name);

        if (! $script) {
            throw ResourceNotFoundException::for('script', $name);
        }

        $id = $this->extractId($script, 'script');

        $this->client->query(
            self::CMD_SCRIPT_REMOVE,
            ['.id' => $id]
        );

        MikrotikLogger::critical('script', 'removeScript', $name);
    }

    /**
     * Run a script by name.
     *
     * Executes the script immediately on the router.
     * Script must exist and have run policy.
     *
     * @param  string $name Script name to run
     * @return void
     */
    public function runScript(string $name): void
    {
        $script = $this->getScript($name);

        if (! $script) {
            throw ResourceNotFoundException::for('script', $name);
        }

        $id = $this->extractId($script, 'script');

        MikrotikLogger::critical('script', 'runScript', $name);

        $this->client->query(
            self::CMD_SCRIPT_RUN,
            ['.id' => $id]
        );
    }

    // =========================================================
    // Schedulers
    // =========================================================

    /**
     * Get all scheduled tasks.
     *
     * @return array[] Schedulers with name, interval, on-event, disabled
     */
    public function getSchedulers(): array
    {
        return $this->client->query(self::CMD_SCHED_PRINT);
    }

    /**
     * Get a single scheduler by name.
     *
     * @param  string     $name Scheduler name
     * @return array|null       Scheduler data or null if not found
     */
    public function getScheduler(string $name): ?array
    {
        $schedulers = $this->getSchedulers();

        foreach ($schedulers as $scheduler) {
            if (($scheduler['name'] ?? '') === $name) {
                return $scheduler;
            }
        }

        return null;
    }

    /**
     * Add a new scheduler.
     *
     * @param  string      $name     Scheduler name
     * @param  string      $onEvent  Script name to run
     * @param  string      $interval Run interval e.g. '1d', '1h', '30m'
     * @param  string      $startTime When to start e.g. '00:00:00' (midnight)
     * @param  string|null $comment  Optional comment
     * @return void
     *
     * Example:
     *  $manager->addScheduler(
     *      name:     'daily-backup',
     *      onEvent:  'backup-config',
     *      interval: '1d',
     *      startTime: '02:00:00',
     *      comment:  'Run backup at 2am daily'
     *  );
     */
    public function addScheduler(
        string  $name,
        string  $onEvent,
        string  $interval = '1d',
        string  $startTime = '00:00:00',
        ?string $comment = null
    ): void {
        $data = [
            'name' => $name,
            'on-event' => $onEvent,
            'interval' => $interval,
            'start-time' => $startTime,
        ];

        if ($comment !== null) {
            $data['comment'] = $comment;
        }

        $this->client->query(self::CMD_SCHED_ADD, $data);
    }

    /**
     * Update an existing scheduler.
     *
     * @param  string $name Scheduler name to update
     * @param  array  $data Fields to update
     * @return void
     */
    public function updateScheduler(string $name, array $data): void
    {
        $scheduler = $this->getScheduler($name);

        if (! $scheduler) {
            throw ResourceNotFoundException::for('scheduler', $name);
        }

        $id = $this->extractId($scheduler, 'scheduler');

        $this->client->query(
            self::CMD_SCHED_SET,
            array_merge(['.id' => $id], $data)
        );
    }

    /**
     * Remove a scheduler by name.
     *
     * @param  string $name Scheduler name to remove
     * @return void
     */
    public function removeScheduler(string $name): void
    {
        $scheduler = $this->getScheduler($name);

        if (! $scheduler) {
            throw ResourceNotFoundException::for('scheduler', $name);
        }

        $id = $this->extractId($scheduler, 'scheduler');

        $this->client->query(
            self::CMD_SCHED_REMOVE,
            ['.id' => $id]
        );
    }
}
