<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * SystemManager
 *
 * Manages MikroTik RouterOS system-level operations:
 * resources, health, identity, logs, ping, and reboot.
 *
 * Usage:
 *  $manager = new SystemManager($client);
 *  $manager->getResources();
 *  $manager->getCpuLoad();
 *  $manager->ping('8.8.8.8', count: 3);
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class SystemManager
{
    /**
     * RouterOS API commands
     */
    private const CMD_RESOURCE = '/system/resource/print';
    private const CMD_HEALTH = '/system/health/print';
    private const CMD_IDENTITY = '/system/identity/print';
    private const CMD_LOGS = '/log/print';
    private const CMD_REBOOT = '/system/reboot';
    private const CMD_PING = '/ping';

    /**
     * @param RouterosClient $client Authenticated RouterOS client
     */
    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Resources
    // =========================================================

    /**
     * Get full system resource information.
     *
     * Returns: cpu-load, free-memory, total-memory, uptime,
     *          version, board-name, architecture-name, etc.
     *
     * @return array<string, string> Resource key-value pairs, empty if unavailable
     */
    public function getResources(): array
    {
        $result = $this->client->query(self::CMD_RESOURCE);

        return $result[0] ?? [];
    }

    /**
     * Get current CPU load percentage as integer.
     *
     * @return int CPU load 0–100, or 0 if unavailable
     */
    public function getCpuLoad(): int
    {
        return (int) ($this->getResources()['cpu-load'] ?? 0);
    }

    /**
     * Get router uptime as RouterOS formatted string.
     *
     * Example return values: "14d6h30m", "2h15m", "45m20s"
     *
     * @return string Uptime string, or "0s" if unavailable
     */
    public function getUptime(): string
    {
        return $this->getResources()['uptime'] ?? '0s';
    }

    /**
     * Get free memory in bytes.
     *
     * @return int Free memory bytes, or 0 if unavailable
     */
    public function getFreeMemory(): int
    {
        return (int) ($this->getResources()['free-memory'] ?? 0);
    }

    /**
     * Get total memory in bytes.
     *
     * @return int Total memory bytes, or 0 if unavailable
     */
    public function getTotalMemory(): int
    {
        return (int) ($this->getResources()['total-memory'] ?? 0);
    }

    /**
     * Get RouterOS firmware version string.
     *
     * @return string Version string e.g. "7.14.3", or "Unknown"
     */
    public function getVersion(): string
    {
        return $this->getResources()['version'] ?? 'Unknown';
    }

    // =========================================================
    // Health
    // =========================================================

    /**
     * Get system health data (temperature, voltage, fan speed).
     *
     * Note: Available on supported hardware only (RB, CCR, CRS).
     *       hAP and home routers may return empty array.
     *
     * @return array<string, string> Health data, empty if hardware unsupported
     */
    public function getHealth(): array
    {
        $result = $this->client->query(self::CMD_HEALTH);

        return $result[0] ?? [];
    }

    /**
     * Get board temperature in Celsius as integer.
     *
     * @return int|null Temperature or null if not supported
     */
    public function getTemperature(): ?int
    {
        $temp = $this->getHealth()['temperature'] ?? null;

        return $temp !== null ? (int) $temp : null;
    }

    // =========================================================
    // Identity
    // =========================================================

    /**
     * Get the router's configured identity (hostname).
     *
     * @return string Router name, or "Unknown" if not set
     */
    public function getIdentity(): string
    {
        $result = $this->client->query(self::CMD_IDENTITY);

        return $result[0]['name'] ?? 'Unknown';
    }

    /**
     * Set the router's identity (hostname).
     *
     * @param  string $name New router name
     * @return void
     */
    public function setIdentity(string $name): void
    {
        $this->client->query(
            '/system/identity/set',
            ['name' => $name]
        );
    }

    // =========================================================
    // Logs
    // =========================================================

    /**
     * Get recent system log entries.
     *
     * @param  int|null $limit  Max entries to return. Null = all entries.
     * @return array[]          Log entries with time, topics, message keys
     */
    public function getLogs(?int $limit = null): array
    {
        $logs = $this->client->query(self::CMD_LOGS);

        if ($limit !== null) {
            return array_slice($logs, 0, $limit);
        }

        return $logs;
    }

    /**
     * Get log entries filtered by topic.
     *
     * Common topics: system, pppoe, hotspot, firewall, dhcp, wireless
     *
     * @param  string   $topic  RouterOS log topic to filter by
     * @param  int|null $limit  Max entries to return
     * @return array[]          Filtered log entries
     */
    public function getLogsByTopic(string $topic, ?int $limit = null): array
    {
        $logs = $this->client->query(
            self::CMD_LOGS,
            queries: ["topics={$topic}"]
        );

        if ($limit !== null) {
            return array_slice($logs, 0, $limit);
        }

        return $logs;
    }

    // =========================================================
    // Ping
    // =========================================================

    /**
     * Send ping from the router to a target address.
     *
     * Useful for testing connectivity from the router's perspective,
     * not from the Laravel server.
     *
     * @param  string $address Target IP or hostname to ping
     * @param  int    $count   Number of ping packets to send
     * @return array[]         Ping results with time, size, host per packet
     */
    public function ping(string $address, int $count = 4): array
    {
        return $this->client->query(self::CMD_PING, [
            'address' => $address,
            'count' => (string) $count,
        ]);
    }

    /**
     * Check if a host is reachable from the router.
     *
     * @param  string $address Target IP or hostname
     * @return bool            True if at least one ping reply received
     */
    public function isReachable(string $address): bool
    {
        $results = $this->ping($address, count: 1);

        return ! empty($results) && isset($results[0]['time']);
    }

    // =========================================================
    // Reboot
    // =========================================================

    /**
     * Reboot the router.
     *
     * WARNING: This will disconnect all active sessions.
     * Connection to the API will be lost immediately after this call.
     *
     * @return void
     */
    public function reboot(): void
    {
        $this->client->query(self::CMD_REBOOT);
    }
}
