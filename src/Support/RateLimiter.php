<?php

namespace ZillEAli\MikrotikLaravel\Support;

/**
 * RateLimiter
 *
 * Throttles RouterOS API calls to prevent overloading routers.
 *
 * MikroTik routers have limited API processing capacity.
 * High-frequency polling or bulk operations without throttling
 * can cause router CPU spikes and connection drops.
 *
 * Critical for ISPs:
 *  - NOC dashboards polling multiple routers
 *  - Bulk PPPoE user operations
 *  - Automated billing system sync
 *  - Multi-router management from NexaLink
 *
 * Usage:
 *  $limiter = new RateLimiter(maxCallsPerSecond: 5, maxCallsPerMinute: 60);
 *
 *  if ($limiter->isThrottled()) {
 *      usleep($limiter->getWaitMicroseconds());
 *  }
 *
 *  $limiter->recordCall();
 *  MikroTik::pppoe()->getActiveSessions();
 *
 * @package ZillEAli\MikrotikLaravel\Support
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class RateLimiter
{
    /**
     * Total calls recorded since instantiation or last reset.
     */
    protected int $totalCalls = 0;

    /**
     * Calls recorded in the current second window.
     *
     * @var array<int, int> Timestamps of calls in current second
     */
    protected array $secondWindow = [];

    /**
     * Calls recorded in the current minute window.
     *
     * @var array<int, int> Timestamps of calls in current minute
     */
    protected array $minuteWindow = [];

    /**
     * Create a new RateLimiter.
     *
     * @param int $maxCallsPerSecond Max API calls per second (default 10)
     * @param int $maxCallsPerMinute Max API calls per minute (default 100)
     */
    public function __construct(
        protected int $maxCallsPerSecond = 10,
        protected int $maxCallsPerMinute = 100,
    ) {
    }

    // =========================================================
    // Check
    // =========================================================

    /**
     * Check if a new API call is allowed right now.
     *
     * Returns false if either per-second or per-minute limit is reached.
     *
     * @return bool True if call is allowed
     */
    public function isAllowed(): bool
    {
        return $this->isAllowedPerSecond() && $this->isAllowedPerMinute();
    }

    /**
     * Check if throttled — alias for !isAllowed().
     *
     * @return bool True if rate limit is exceeded
     */
    public function isThrottled(): bool
    {
        return ! $this->isAllowed();
    }

    /**
     * Check if per-second limit allows another call.
     *
     * @return bool
     */
    public function isAllowedPerSecond(): bool
    {
        $this->pruneSecondWindow();

        return count($this->secondWindow) < $this->maxCallsPerSecond;
    }

    /**
     * Check if per-minute limit allows another call.
     *
     * @return bool
     */
    public function isAllowedPerMinute(): bool
    {
        $this->pruneMinuteWindow();

        return count($this->minuteWindow) < $this->maxCallsPerMinute;
    }

    // =========================================================
    // Record
    // =========================================================

    /**
     * Record an API call.
     *
     * Call this after each RouterOS API request.
     *
     * @return void
     */
    public function recordCall(): void
    {
        $now = $this->now();

        $this->secondWindow[] = $now;
        $this->minuteWindow[] = $now;
        $this->totalCalls++;
    }

    /**
     * Record a call and wait if throttled.
     *
     * Convenience method — checks limit, waits if needed, records.
     *
     * @return void
     */
    public function throttle(): void
    {
        if ($this->isThrottled()) {
            usleep($this->getWaitMicroseconds());
        }

        $this->recordCall();
    }

    // =========================================================
    // Info
    // =========================================================

    /**
     * Get total calls recorded since instantiation or reset.
     *
     * @return int
     */
    public function getCallCount(): int
    {
        return $this->totalCalls;
    }

    /**
     * Get max calls per second setting.
     *
     * @return int
     */
    public function getMaxCallsPerSecond(): int
    {
        return $this->maxCallsPerSecond;
    }

    /**
     * Get max calls per minute setting.
     *
     * @return int
     */
    public function getMaxCallsPerMinute(): int
    {
        return $this->maxCallsPerMinute;
    }

    /**
     * Get remaining allowed calls in current second.
     *
     * @return int
     */
    public function getRemainingPerSecond(): int
    {
        $this->pruneSecondWindow();

        return max(0, $this->maxCallsPerSecond - count($this->secondWindow));
    }

    /**
     * Get remaining allowed calls in current minute.
     *
     * @return int
     */
    public function getRemainingPerMinute(): int
    {
        $this->pruneMinuteWindow();

        return max(0, $this->maxCallsPerMinute - count($this->minuteWindow));
    }

    /**
     * Get microseconds to wait before next allowed call.
     *
     * @return int Microseconds to sleep
     */
    public function getWaitMicroseconds(): int
    {
        return (int) (1_000_000 / $this->maxCallsPerSecond);
    }

    /**
     * Get a summary of current rate limiter state.
     *
     * @return array<string, int>
     */
    public function getStats(): array
    {
        $this->pruneSecondWindow();
        $this->pruneMinuteWindow();

        return [
            'total_calls' => $this->totalCalls,
            'calls_this_second' => count($this->secondWindow),
            'calls_this_minute' => count($this->minuteWindow),
            'max_per_second' => $this->maxCallsPerSecond,
            'max_per_minute' => $this->maxCallsPerMinute,
            'remaining_second' => $this->getRemainingPerSecond(),
            'remaining_minute' => $this->getRemainingPerMinute(),
        ];
    }

    // =========================================================
    // Reset
    // =========================================================

    /**
     * Reset all counters.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->totalCalls = 0;
        $this->secondWindow = [];
        $this->minuteWindow = [];
    }

    // =========================================================
    // Internal
    // =========================================================

    /**
     * Get current timestamp in microseconds.
     *
     * @return float
     */
    protected function now(): float
    {
        return microtime(true);
    }

    /**
     * Remove calls older than 1 second from the window.
     *
     * @return void
     */
    protected function pruneSecondWindow(): void
    {
        $cutoff = $this->now() - 1.0;

        $this->secondWindow = array_values(
            array_filter($this->secondWindow, fn ($t) => $t > $cutoff)
        );
    }

    /**
     * Remove calls older than 60 seconds from the window.
     *
     * @return void
     */
    protected function pruneMinuteWindow(): void
    {
        $cutoff = $this->now() - 60.0;

        $this->minuteWindow = array_values(
            array_filter($this->minuteWindow, fn ($t) => $t > $cutoff)
        );
    }
}
