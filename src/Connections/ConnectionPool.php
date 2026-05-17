<?php

namespace ZillEAli\MikrotikLaravel\Connections;

/**
 * ConnectionPool
 *
 * Manages a pool of persistent RouterosClient connections,
 * keyed by router name. Prevents reconnecting on every
 * manager call in long-running processes.
 *
 * Used internally by MikrotikManager to cache connections
 * per named router across multiple manager calls.
 *
 * Usage:
 *  $pool = new ConnectionPool();
 *  $pool->add('main', $client);
 *  $pool->get('main');
 *  $pool->isAlive('main');
 *  $pool->pruneDeadConnections();
 *  $pool->flush();
 *
 * @package ZillEAli\MikrotikLaravel\Connections
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class ConnectionPool
{
    /**
     * Active client connections keyed by router name.
     *
     * @var array<string, RouterosClient>
     */
    protected array $connections = [];

    // =========================================================
    // Store / Retrieve
    // =========================================================

    /**
     * Add a client connection to the pool.
     *
     * @param  string         $name   Router name e.g. 'main', 'branch'
     * @param  RouterosClient $client Authenticated client instance
     * @return void
     */
    public function add(string $name, RouterosClient $client): void
    {
        $this->connections[$name] = $client;
    }

    /**
     * Get a client connection by router name.
     *
     * @param  string              $name Router name
     * @return RouterosClient|null       Client or null if not in pool
     */
    public function get(string $name): ?RouterosClient
    {
        return $this->connections[$name] ?? null;
    }

    /**
     * Check if a connection exists in the pool.
     *
     * Note: Does NOT check if the connection is still alive.
     * Use isAlive() for that.
     *
     * @param  string $name Router name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    // =========================================================
    // Health Check
    // =========================================================

    /**
     * Check if a connection exists and is still connected.
     *
     * @param  string $name Router name
     * @return bool         True if connection exists and isConnected() returns true
     */
    public function isAlive(string $name): bool
    {
        if (! $this->has($name)) {
            return false;
        }

        return $this->connections[$name]->isConnected();
    }

    /**
     * Get all connections that are currently alive.
     *
     * @return array<string, RouterosClient> Alive connections keyed by name
     */
    public function getAliveConnections(): array
    {
        return array_filter(
            $this->connections,
            fn ($client) => $client->isConnected()
        );
    }

    // =========================================================
    // Remove / Cleanup
    // =========================================================

    /**
     * Remove a single connection from the pool.
     *
     * Calls disconnect() on the client before removing.
     * Safe to call even if name does not exist.
     *
     * @param  string $name Router name to remove
     * @return void
     */
    public function remove(string $name): void
    {
        if (! $this->has($name)) {
            return;
        }

        $this->connections[$name]->disconnect();

        unset($this->connections[$name]);
    }

    /**
     * Remove all dead (disconnected) connections from the pool.
     *
     * Call this periodically in long-running processes to
     * free resources from dropped router connections.
     *
     * @return int Number of connections removed
     */
    public function pruneDeadConnections(): int
    {
        $before = count($this->connections);

        foreach ($this->connections as $name => $client) {
            if (! $client->isConnected()) {
                unset($this->connections[$name]);
            }
        }

        return $before - count($this->connections);
    }

    /**
     * Disconnect and remove all connections from the pool.
     *
     * @return void
     */
    public function flush(): void
    {
        foreach ($this->connections as $client) {
            $client->disconnect();
        }

        $this->connections = [];
    }

    // =========================================================
    // Info
    // =========================================================

    /**
     * Get the total number of connections in the pool.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->connections);
    }

    /**
     * Get all router names currently in the pool.
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Get a summary of all connections and their status.
     *
     * @return array<string, array{connected: bool}>
     */
    public function getSummary(): array
    {
        $summary = [];

        foreach ($this->connections as $name => $client) {
            $summary[$name] = [
                'connected' => $client->isConnected(),
            ];
        }

        return $summary;
    }
}
