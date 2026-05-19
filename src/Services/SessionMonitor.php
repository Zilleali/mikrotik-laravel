<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * SessionMonitor
 *
 * Real-time monitoring of all active sessions across
 * PPPoE and Hotspot services on MikroTik routers.
 *
 * Critical for ISPs:
 *  - NOC dashboard live session counts
 *  - Check if specific customer is online
 *  - Find long-running sessions for maintenance
 *  - Billing system session verification
 *  - NexaLink real-time subscriber status
 *
 * Usage:
 *  $monitor = new SessionMonitor($client);
 *  $monitor->getAllActiveSessions();
 *  $monitor->isUserOnline('ali-home');
 *  $monitor->getSummary();
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class SessionMonitor
{
    private const CMD_PPPOE_ACTIVE   = '/ppp/active/print';
    private const CMD_HOTSPOT_ACTIVE = '/ip/hotspot/active/print';

    public function __construct(
        protected RouterosClient $client
    ) {}

    // =========================================================
    // All Sessions
    // =========================================================

    /**
     * Get all active sessions across PPPoE and Hotspot.
     *
     * Each session is tagged with 'type' => 'pppoe' or 'hotspot'.
     * PPPoE sessions use 'name' field, Hotspot uses 'user'.
     *
     * @return array[] Combined sessions with type tag
     */
    public function getAllActiveSessions(): array
    {
        $pppoe = array_map(
            fn ($s) => array_merge($s, ['type' => 'pppoe']),
            $this->client->query(self::CMD_PPPOE_ACTIVE)
        );

        $hotspot = array_map(
            fn ($s) => array_merge($s, ['type' => 'hotspot']),
            $this->client->query(self::CMD_HOTSPOT_ACTIVE)
        );

        return array_merge($pppoe, $hotspot);
    }

    /**
     * Get total active session count (PPPoE + Hotspot).
     *
     * @return int
     */
    public function getTotalSessionCount(): int
    {
        return $this->getPppoeSessionCount() + $this->getHotspotSessionCount();
    }

    /**
     * Get active PPPoE session count.
     *
     * @return int
     */
    public function getPppoeSessionCount(): int
    {
        return count($this->client->query(self::CMD_PPPOE_ACTIVE));
    }

    /**
     * Get active Hotspot session count.
     *
     * @return int
     */
    public function getHotspotSessionCount(): int
    {
        return count($this->client->query(self::CMD_HOTSPOT_ACTIVE));
    }

    // =========================================================
    // User Lookup
    // =========================================================

    /**
     * Check if a specific user is currently online.
     *
     * Searches both PPPoE (by 'name') and Hotspot (by 'user').
     *
     * @param  string $username PPPoE or Hotspot username
     * @return bool             True if user has active session
     */
    public function isUserOnline(string $username): bool
    {
        return $this->getUserSession($username) !== null;
    }

    /**
     * Get session data for a specific user.
     *
     * Searches PPPoE first, then Hotspot.
     *
     * @param  string     $username PPPoE or Hotspot username
     * @return array|null           Session data with type tag, or null if offline
     */
    public function getUserSession(string $username): ?array
    {
        // Check PPPoE
        $pppoe = $this->client->query(self::CMD_PPPOE_ACTIVE);

        foreach ($pppoe as $session) {
            if (($session['name'] ?? '') === $username) {
                return array_merge($session, ['type' => 'pppoe']);
            }
        }

        // Check Hotspot
        $hotspot = $this->client->query(self::CMD_HOTSPOT_ACTIVE);

        foreach ($hotspot as $session) {
            if (($session['user'] ?? '') === $username) {
                return array_merge($session, ['type' => 'hotspot']);
            }
        }

        return null;
    }

    // =========================================================
    // Filtering
    // =========================================================

    /**
     * Get sessions running longer than given minutes.
     *
     * Useful for finding stale sessions or long-running connections.
     * Parses RouterOS uptime format: '2h14m', '1d6h', '45m20s'
     *
     * @param  int     $minutes  Minimum session duration in minutes
     * @return array[]           Sessions longer than given duration
     */
    public function getSessionsLongerThan(int $minutes): array
    {
        $sessions = $this->getAllActiveSessions();

        return array_values(array_filter(
            $sessions,
            fn ($s) => $this->parseUptimeMinutes($s['uptime'] ?? '0s') > $minutes
        ));
    }

    /**
     * Get sessions for a specific IP address.
     *
     * @param  string  $ip IP address to filter by
     * @return array[]
     */
    public function getSessionsByIp(string $ip): array
    {
        $sessions = $this->getAllActiveSessions();

        return array_values(array_filter(
            $sessions,
            fn ($s) => ($s['address'] ?? '') === $ip
        ));
    }

    // =========================================================
    // Summary
    // =========================================================

    /**
     * Get a summary of all active sessions.
     *
     * Returns counts per service type and total.
     * Used for NOC dashboard widgets and monitoring.
     *
     * @return array{pppoe: int, hotspot: int, total: int}
     */
    public function getSummary(): array
    {
        $pppoe   = $this->getPppoeSessionCount();
        $hotspot = $this->getHotspotSessionCount();

        return [
            'pppoe'   => $pppoe,
            'hotspot' => $hotspot,
            'total'   => $pppoe + $hotspot,
        ];
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * Parse RouterOS uptime string into total minutes.
     *
     * Supports formats: '2h14m', '1d6h30m', '45m20s', '3w2d'
     *
     * @param  string $uptime RouterOS uptime string
     * @return int            Total minutes
     */
    protected function parseUptimeMinutes(string $uptime): int
    {
        $minutes = 0;

        if (preg_match('/(\d+)w/', $uptime, $m)) {
            $minutes += (int) $m[1] * 7 * 24 * 60;
        }
        if (preg_match('/(\d+)d/', $uptime, $m)) {
            $minutes += (int) $m[1] * 24 * 60;
        }
        if (preg_match('/(\d+)h/', $uptime, $m)) {
            $minutes += (int) $m[1] * 60;
        }
        if (preg_match('/(\d+)m/', $uptime, $m)) {
            $minutes += (int) $m[1];
        }

        return $minutes;
    }
}