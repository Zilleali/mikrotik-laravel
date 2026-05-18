<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * UsageTracker
 *
 * Tracks real-time bandwidth usage per user and interface.
 *
 * Critical for ISPs:
 *  - Monitor per-subscriber data usage in real-time
 *  - Find top bandwidth consumers
 *  - NOC dashboard usage widgets
 *  - Billing system usage verification
 *  - Fair usage policy enforcement
 *  - NexaLink subscriber portal data display
 *
 * Note: RouterOS active session counters reset on reconnect.
 * For persistent usage tracking, store snapshots in Laravel DB.
 *
 * Usage:
 *  $tracker = new UsageTracker($client);
 *  $tracker->getPppoeUserUsage('ali-home');
 *  $tracker->getTopUsers(10);
 *  $tracker->getTotalNetworkUsage();
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class UsageTracker
{
    private const CMD_PPPOE_ACTIVE = '/ppp/active/print';
    private const CMD_IFACE_PRINT = '/interface/print';
    private const BYTES_PER_MB = 1048576;
    private const BYTES_PER_GB = 1073741824;

    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Interface Traffic
    // =========================================================

    /**
     * Get traffic stats for a specific interface.
     *
     * Returns cumulative bytes since last router reboot.
     *
     * @param  string $interface Interface name e.g. 'ether1'
     * @return array             Traffic data or empty array if not found
     */
    public function getInterfaceTraffic(string $interface): array
    {
        $interfaces = $this->client->query(self::CMD_IFACE_PRINT);

        foreach ($interfaces as $iface) {
            if (($iface['name'] ?? '') === $interface) {
                return $iface;
            }
        }

        return [];
    }

    /**
     * Get traffic stats for all interfaces.
     *
     * @return array[] All interfaces with traffic counters
     */
    public function getAllInterfaceTraffic(): array
    {
        return $this->client->query(self::CMD_IFACE_PRINT);
    }

    // =========================================================
    // PPPoE User Usage
    // =========================================================

    /**
     * Get real-time usage for a specific PPPoE user.
     *
     * Returns current session TX/RX bytes with MB/GB conversions.
     * Returns null if user is not currently connected.
     *
     * @param  string     $username PPPoE username
     * @return array|null           Usage data or null if offline
     */
    public function getPppoeUserUsage(string $username): ?array
    {
        $sessions = $this->client->query(self::CMD_PPPOE_ACTIVE);

        foreach ($sessions as $session) {
            if (($session['name'] ?? '') === $username) {
                return $this->enrichUsageData($session);
            }
        }

        return null;
    }

    /**
     * Get real-time usage for all active PPPoE users.
     *
     * @return array[] Usage data for each active session
     */
    public function getAllPppoeUsage(): array
    {
        $sessions = $this->client->query(self::CMD_PPPOE_ACTIVE);

        return array_map(
            fn ($session) => $this->enrichUsageData($session),
            $sessions
        );
    }

    /**
     * Get top N users by total data usage (TX + RX).
     *
     * @param  int     $limit Number of top users to return
     * @return array[]        Top users sorted by total usage descending
     */
    public function getTopUsers(int $limit = 10): array
    {
        $usage = $this->getAllPppoeUsage();

        usort($usage, fn ($a, $b) => $b['total-bytes'] <=> $a['total-bytes']);

        return array_slice($usage, 0, $limit);
    }

    // =========================================================
    // Network Totals
    // =========================================================

    /**
     * Get total network usage across all active PPPoE sessions.
     *
     * Aggregates TX and RX bytes from all connected users.
     * Useful for NOC dashboard total bandwidth display.
     *
     * @return array{tx-bytes: int, rx-bytes: int, total-bytes: int, tx-mb: float, rx-mb: float, total-mb: float, tx-gb: float, rx-gb: float, total-gb: float}
     */
    public function getTotalNetworkUsage(): array
    {
        $sessions = $this->client->query(self::CMD_PPPOE_ACTIVE);

        $txBytes = 0;
        $rxBytes = 0;

        foreach ($sessions as $session) {
            $txBytes += (int) ($session['tx-byte'] ?? 0);
            $rxBytes += (int) ($session['rx-byte'] ?? 0);
        }

        $totalBytes = $txBytes + $rxBytes;

        return [
            'tx-bytes' => $txBytes,
            'rx-bytes' => $rxBytes,
            'total-bytes' => $totalBytes,
            'tx-mb' => $this->bytesToMb($txBytes),
            'rx-mb' => $this->bytesToMb($rxBytes),
            'total-mb' => $this->bytesToMb($totalBytes),
            'tx-gb' => $this->bytesToGb($txBytes),
            'rx-gb' => $this->bytesToGb($rxBytes),
            'total-gb' => $this->bytesToGb($totalBytes),
        ];
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * Enrich a PPPoE session with calculated usage fields.
     *
     * Adds: tx-mb, rx-mb, total-mb, tx-gb, rx-gb, total-gb, total-bytes
     *
     * @param  array $session Raw PPPoE active session data
     * @return array          Session with usage fields added
     */
    protected function enrichUsageData(array $session): array
    {
        $txBytes = (int) ($session['tx-byte'] ?? 0);
        $rxBytes = (int) ($session['rx-byte'] ?? 0);
        $totalBytes = $txBytes + $rxBytes;

        return array_merge($session, [
            'tx-mb' => $this->bytesToMb($txBytes),
            'rx-mb' => $this->bytesToMb($rxBytes),
            'total-mb' => $this->bytesToMb($totalBytes),
            'tx-gb' => $this->bytesToGb($txBytes),
            'rx-gb' => $this->bytesToGb($rxBytes),
            'total-gb' => $this->bytesToGb($totalBytes),
            'total-bytes' => $totalBytes,
        ]);
    }

    /**
     * Convert bytes to megabytes.
     *
     * @param  int   $bytes
     * @return float MB rounded to 2 decimal places
     */
    public function bytesToMb(int $bytes): float
    {
        return round($bytes / self::BYTES_PER_MB, 2);
    }

    /**
     * Convert bytes to gigabytes.
     *
     * @param  int   $bytes
     * @return float GB rounded to 2 decimal places
     */
    public function bytesToGb(int $bytes): float
    {
        return round($bytes / self::BYTES_PER_GB, 2);
    }
}
