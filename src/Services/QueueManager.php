<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * QueueManager
 *
 * Manages MikroTik Simple and Tree queues for
 * bandwidth limiting and traffic shaping.
 *
 * Usage:
 *  $manager = new QueueManager($client);
 *  $manager->getSimpleQueues();
 *  $manager->setLimit('ali-home', '10M', '10M');
 *  $manager->bulkSetLimit($users);
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class QueueManager
{
    /**
     * RouterOS API commands
     */
    private const CMD_SIMPLE_PRINT   = '/queue/simple/print';
    private const CMD_SIMPLE_ADD     = '/queue/simple/add';
    private const CMD_SIMPLE_SET     = '/queue/simple/set';
    private const CMD_SIMPLE_REMOVE  = '/queue/simple/remove';
    private const CMD_SIMPLE_ENABLE  = '/queue/simple/enable';
    private const CMD_SIMPLE_DISABLE = '/queue/simple/disable';
    private const CMD_TREE_PRINT     = '/queue/tree/print';
    private const CMD_TREE_ADD       = '/queue/tree/add';

    /**
     * @param RouterosClient $client Authenticated RouterOS client
     */
    public function __construct(
        protected RouterosClient $client
    ) {}

    // =========================================================
    // Simple Queues
    // =========================================================

    /**
     * Get all simple queues.
     *
     * @return array[] List of queues with name, target, max-limit, etc.
     */
    public function getSimpleQueues(): array
    {
        return $this->client->query(self::CMD_SIMPLE_PRINT);
    }

    /**
     * Get a single simple queue by name.
     *
     * @param  string     $name Queue name
     * @return array|null       Queue data or null if not found
     */
    public function getSimpleQueue(string $name): ?array
    {
        $queues = $this->client->query(
            self::CMD_SIMPLE_PRINT,
            queries: ["name={$name}"]
        );

        return $queues[0] ?? null;
    }

    /**
     * Create a new simple queue.
     *
     * @param  array $data Required: name, target. Optional: max-limit, burst-limit, etc.
     * @return void
     *
     * Example:
     *  $manager->createSimpleQueue([
     *      'name'      => 'ali-home',
     *      'target'    => '10.0.0.45/32',
     *      'max-limit' => '10M/10M',
     *      'comment'   => 'Ali House',
     *  ]);
     */
    public function createSimpleQueue(array $data): void
    {
        $this->client->query(self::CMD_SIMPLE_ADD, $data);
    }

    /**
     * Update an existing simple queue.
     *
     * @param  string $name Queue name to update
     * @param  array  $data Fields to update e.g. ['max-limit' => '20M/20M']
     * @return void
     */
    public function updateQueue(string $name, array $data): void
    {
        $queue = $this->getSimpleQueue($name);

        if (! $queue) {
            return;
        }

        $this->client->query(
            self::CMD_SIMPLE_SET,
            array_merge(['.id' => $queue['.id'] ?? ''], $data)
        );
    }

    /**
     * Delete a simple queue permanently.
     *
     * @param  string $name Queue name to delete
     * @return void
     */
    public function deleteQueue(string $name): void
    {
        $queue = $this->getSimpleQueue($name);

        if (! $queue) {
            return;
        }

        $this->client->query(
            self::CMD_SIMPLE_REMOVE,
            ['.id' => $queue['.id'] ?? '']
        );
    }

    /**
     * Enable a disabled simple queue.
     *
     * @param  string $name Queue name
     * @return void
     */
    public function enableQueue(string $name): void
    {
        $queue = $this->getSimpleQueue($name);

        if (! $queue) {
            return;
        }

        $this->client->query(
            self::CMD_SIMPLE_ENABLE,
            ['.id' => $queue['.id'] ?? '']
        );
    }

    /**
     * Disable a simple queue (pauses bandwidth limiting).
     *
     * @param  string $name Queue name
     * @return void
     */
    public function disableQueue(string $name): void
    {
        $queue = $this->getSimpleQueue($name);

        if (! $queue) {
            return;
        }

        $this->client->query(
            self::CMD_SIMPLE_DISABLE,
            ['.id' => $queue['.id'] ?? '']
        );
    }

    // =========================================================
    // Bandwidth Shortcuts
    // =========================================================

    /**
     * Quickly set upload/download limit for a queue.
     *
     * @param  string $name Queue name
     * @param  string $ul   Upload limit e.g. "10M", "512k"
     * @param  string $dl   Download limit e.g. "10M", "1G"
     * @return void
     */
    public function setLimit(string $name, string $ul, string $dl): void
    {
        $this->updateQueue($name, [
            'max-limit' => "{$ul}/{$dl}",
        ]);
    }

    /**
     * Set bandwidth limits for multiple queues at once.
     *
     * @param  array[] $users Each item: ['name' => '...', 'ul' => '...', 'dl' => '...']
     * @return void
     *
     * Example:
     *  $manager->bulkSetLimit([
     *      ['name' => 'ali-home',   'ul' => '10M', 'dl' => '10M'],
     *      ['name' => 'zain-fiber', 'ul' => '20M', 'dl' => '20M'],
     *  ]);
     */
    public function bulkSetLimit(array $users): void
    {
        foreach ($users as $user) {
            $this->setLimit(
                $user['name'],
                $user['ul'],
                $user['dl']
            );
        }
    }

    // =========================================================
    // Tree Queues
    // =========================================================

    /**
     * Get all tree queues (HTB — Hierarchical Token Bucket).
     *
     * Used for advanced traffic shaping with parent/child hierarchy.
     *
     * @return array[] List of tree queues
     */
    public function getTreeQueues(): array
    {
        return $this->client->query(self::CMD_TREE_PRINT);
    }

    /**
     * Create a new tree queue.
     *
     * @param  array $data Required: name, parent. Optional: max-limit, priority, etc.
     * @return void
     */
    public function createTreeQueue(array $data): void
    {
        $this->client->query(self::CMD_TREE_ADD, $data);
    }
}