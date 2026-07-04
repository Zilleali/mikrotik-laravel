<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;

/**
 * DiagnosticsManager
 *
 * Connection health checks, latency measurement, and raw API protocol
 * inspection. Extended beyond the upstream library with structured
 * ping results, latency averaging, and typed connection info.
 *
 * Usage:
 *  MikroTik::diagnostics()->isAlive()
 *  MikroTik::diagnostics()->ping()
 *  MikroTik::diagnostics()->latency()
 *  MikroTik::diagnostics()->getRaw('/ip/address/print')
 *  MikroTik::diagnostics()->connectionInfo()
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class DiagnosticsManager
{
    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Health Checks
    // =========================================================

    /**
     * Check whether the router is reachable and responding.
     *
     * Sends a lightweight /system/identity/print query.
     * Returns true if a valid response is received, false on any error.
     *
     * @return bool
     */
    public function isAlive(): bool
    {
        try {
            $result = $this->client->query('/system/identity/print');

            return ! empty($result);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Perform a structured ping check with timing and status details.
     *
     * @return array{host: string, port: int, connected: bool, alive: bool, latency_ms: float|null, error: string|null}
     */
    public function ping(): array
    {
        $host = $this->client->getHost();
        $port = $this->client->getPort();

        if (! $this->client->isConnected()) {
            return [
                'host'       => $host,
                'port'       => $port,
                'connected'  => false,
                'alive'      => false,
                'latency_ms' => null,
                'error'      => 'Not connected',
            ];
        }

        $start = hrtime(true);

        try {
            $result = $this->client->query('/system/identity/print');
            $latencyMs = (hrtime(true) - $start) / 1_000_000;

            return [
                'host'       => $host,
                'port'       => $port,
                'connected'  => true,
                'alive'      => ! empty($result),
                'latency_ms' => round($latencyMs, 2),
                'error'      => null,
            ];
        } catch (\Throwable $e) {
            return [
                'host'       => $host,
                'port'       => $port,
                'connected'  => true,
                'alive'      => false,
                'latency_ms' => null,
                'error'      => $e->getMessage(),
            ];
        }
    }

    /**
     * Measure average round-trip latency over multiple API queries.
     *
     * @param  int   $samples Number of queries to average (1–10)
     * @return float Average latency in milliseconds, or -1.0 if all fail
     */
    public function latency(int $samples = 3): float
    {
        $samples = max(1, min(10, $samples));
        $times = [];

        for ($i = 0; $i < $samples; $i++) {
            $start = hrtime(true);
            try {
                $this->client->query('/system/identity/print');
                $times[] = (hrtime(true) - $start) / 1_000_000;
            } catch (\Throwable) {
                // Skip failed samples
            }
        }

        if (empty($times)) {
            return -1.0;
        }

        return round(array_sum($times) / count($times), 2);
    }

    // =========================================================
    // Raw Protocol Inspection
    // =========================================================

    /**
     * Send a RouterOS command and return the raw unprocessed response words.
     *
     * Useful for protocol debugging and verifying API responses before parsing.
     *
     * Example:
     *  $raw = MikroTik::diagnostics()->getRaw('/ip/address/print');
     *  // returns: ['!re', '=.id=*1', '=address=192.168.1.1/24', '', '!done']
     *
     * @param  string   $command RouterOS command
     * @param  string[] $params  Additional =key=value parameters
     * @return string[]          Raw response words
     *
     * @throws ConnectionException
     */
    public function getRaw(string $command, array $params = []): array
    {
        return $this->client->sendRaw($command, $params);
    }

    // =========================================================
    // Connection Info
    // =========================================================

    /**
     * Return current connection metadata as a structured array.
     *
     * @return array{host: string, port: int, connected: bool, protocol: string}
     */
    public function connectionInfo(): array
    {
        return [
            'host'      => $this->client->getHost(),
            'port'      => $this->client->getPort(),
            'connected' => $this->client->isConnected(),
            'protocol'  => $this->client->getPort() === 8729 ? 'API-SSL' : 'API',
        ];
    }
}
