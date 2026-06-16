<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Support\HasIdValidation;

/**
 * SyslogManager
 *
 * Manages MikroTik logging targets and rules.
 * Supports remote syslog (UDP/514) for centralized logging.
 *
 * Critical for ISPs:
 *  - Send PPPoE session logs to central syslog server
 *  - Forward firewall logs for security monitoring
 *  - NOC alert integration via syslog
 *  - Audit trail for subscriber management
 *  - Integration with NexaLink logging pipeline
 *
 * Usage:
 *  $manager = new SyslogManager($client);
 *  $manager->addRemoteTarget('nexalink-log', '192.168.1.100', 514);
 *  $manager->addRule('pppoe,hotspot', 'nexalink-log');
 *  $manager->hasRemoteLogging();
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class SyslogManager
{
    use HasIdValidation;

    private const CMD_ACTION_PRINT  = '/system/logging/action/print';
    private const CMD_ACTION_ADD    = '/system/logging/action/add';
    private const CMD_ACTION_SET    = '/system/logging/action/set';
    private const CMD_ACTION_REMOVE = '/system/logging/action/remove';
    private const CMD_RULE_PRINT    = '/system/logging/print';
    private const CMD_RULE_ADD      = '/system/logging/add';
    private const CMD_RULE_REMOVE   = '/system/logging/remove';

    public function __construct(
        protected RouterosClient $client
    ) {}

    // =========================================================
    // Targets (Actions)
    // =========================================================

    /**
     * Get all logging targets (actions).
     *
     * Built-in targets: memory, disk, echo, remote.
     *
     * @return array[] Targets with name, target, remote, remote-port, etc.
     */
    public function getTargets(): array
    {
        return $this->client->query(self::CMD_ACTION_PRINT);
    }

    /**
     * Get a single logging target by name.
     *
     * @param  string     $name Target name e.g. 'remote'
     * @return array|null       Target data or null if not found
     */
    public function getTarget(string $name): ?array
    {
        $targets = $this->getTargets();

        foreach ($targets as $target) {
            if (($target['name'] ?? '') === $name) {
                return $target;
            }
        }

        return null;
    }

    /**
     * Get only remote syslog targets.
     *
     * @return array[]
     */
    public function getRemoteTargets(): array
    {
        $targets = $this->getTargets();

        return array_values(
            array_filter($targets, fn ($t) => ($t['target'] ?? '') === 'remote')
        );
    }

    /**
     * Check if any remote syslog target is configured.
     *
     * @return bool
     */
    public function hasRemoteLogging(): bool
    {
        return count($this->getRemoteTargets()) > 0;
    }

    /**
     * Add a remote syslog target.
     *
     * Sends logs to a remote syslog server via UDP.
     * Compatible with rsyslog, syslog-ng, Graylog, ELK.
     *
     * @param  string      $name       Target name
     * @param  string      $remoteIp   Syslog server IP
     * @param  int         $remotePort Syslog UDP port (default 514)
     * @param  string|null $comment    Optional comment
     * @return void
     *
     * Example:
     *  $manager->addRemoteTarget(
     *      name:       'nexalink-syslog',
     *      remoteIp:   '192.168.1.100',
     *      remotePort: 514,
     *      comment:    'NexaLink central logging'
     *  );
     */
    public function addRemoteTarget(
        string  $name,
        string  $remoteIp,
        int     $remotePort = 514,
        ?string $comment    = null
    ): void {
        $data = [
            'name'        => $name,
            'target'      => 'remote',
            'remote'      => $remoteIp,
            'remote-port' => (string) $remotePort,
        ];

        if ($comment !== null) {
            $data['comment'] = $comment;
        }

        $this->client->query(self::CMD_ACTION_ADD, $data);
    }

    /**
     * Update an existing logging target.
     *
     * @param  string $name Target name to update
     * @param  array  $data Fields to update
     * @return void
     */
    public function updateTarget(string $name, array $data): void
    {
        $target = $this->getTarget($name);

        if (! $target) {
            return;
        }

        $id = $this->extractId($target);
        if ($id === null) {
            return;
        }

        $this->client->query(
            self::CMD_ACTION_SET,
            array_merge(['.id' => $id], $data)
        );
    }

    /**
     * Remove a logging target by name.
     *
     * Note: Built-in targets (memory, disk, echo) cannot be removed.
     *
     * @param  string $name Target name to remove
     * @return void
     */
    public function removeTarget(string $name): void
    {
        $target = $this->getTarget($name);

        if (! $target) {
            return;
        }

        $id = $this->extractId($target);
        if ($id === null) {
            return;
        }

        $this->client->query(
            self::CMD_ACTION_REMOVE,
            ['.id' => $id]
        );
    }

    // =========================================================
    // Rules
    // =========================================================

    /**
     * Get all logging rules.
     *
     * Rules define which topics get sent to which target.
     *
     * @return array[] Rules with topics, action, disabled
     */
    public function getRules(): array
    {
        return $this->client->query(self::CMD_RULE_PRINT);
    }

    /**
     * Add a logging rule.
     *
     * Common ISP topics:
     *  - pppoe       → PPPoE session events
     *  - hotspot     → Hotspot login/logout
     *  - firewall    → Firewall drops
     *  - dhcp        → DHCP leases
     *  - system      → System events
     *  - info        → General info
     *  - error       → Errors only
     *  - warning     → Warnings
     *
     * @param  string $topics Comma-separated topics e.g. 'pppoe,hotspot'
     * @param  string $action Target name to send logs to
     * @return void
     *
     * Example:
     *  $manager->addRule('pppoe,hotspot', 'nexalink-syslog');
     */
    public function addRule(string $topics, string $action): void
    {
        $this->client->query(self::CMD_RULE_ADD, [
            'topics' => $topics,
            'action' => $action,
        ]);
    }

    /**
     * Remove a logging rule by topics and action.
     *
     * @param  string $topics Topics to match
     * @param  string $action Action to match
     * @return void
     */
    public function removeRule(string $topics, string $action): void
    {
        $rules = $this->getRules();

        foreach ($rules as $rule) {
            if (
                ($rule['topics'] ?? '') === $topics &&
                ($rule['action']  ?? '') === $action
            ) {
                $id = $this->extractId($rule);
                if ($id === null) {
                    return;
                }

                $this->client->query(
                    self::CMD_RULE_REMOVE,
                    ['.id' => $id]
                );
                return;
            }
        }
    }

    /**
     * Setup complete remote syslog in one call.
     *
     * Creates target + adds rules for common ISP topics.
     *
     * @param  string $name       Target name
     * @param  string $remoteIp   Syslog server IP
     * @param  int    $remotePort UDP port (default 514)
     * @param  array  $topics     Topics to forward (default: ISP essentials)
     * @return void
     */
    public function setupRemoteSyslog(
        string $name,
        string $remoteIp,
        int    $remotePort = 514,
        array  $topics     = ['info', 'error', 'warning', 'pppoe', 'hotspot', 'firewall']
    ): void {
        $this->addRemoteTarget($name, $remoteIp, $remotePort);

        foreach ($topics as $topic) {
            $this->addRule($topic, $name);
        }
    }
}