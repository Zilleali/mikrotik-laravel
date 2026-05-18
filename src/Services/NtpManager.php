<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * NtpManager
 *
 * Manages MikroTik NTP client configuration and system clock.
 *
 * Critical for ISPs:
 *  - Accurate time for PPPoE session logging
 *  - RADIUS accounting timestamps
 *  - Firewall rule scheduling
 *  - Certificate validity checks
 *  - Log correlation across multiple routers
 *
 * Usage:
 *  $manager = new NtpManager($client);
 *  $manager->setServers('216.239.35.0', '216.239.35.4');
 *  $manager->setTimezone('Asia/Karachi');
 *  $manager->isSynced();
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class NtpManager
{
    private const CMD_CLIENT_PRINT = '/system/ntp/client/print';
    private const CMD_CLIENT_SET = '/system/ntp/client/set';
    private const CMD_CLOCK_PRINT = '/system/clock/print';
    private const CMD_CLOCK_SET = '/system/clock/set';

    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // NTP Client
    // =========================================================

    /**
     * Get NTP client settings.
     *
     * @return array NTP config with enabled, primary-ntp, secondary-ntp, synced
     */
    public function getClientSettings(): array
    {
        $result = $this->client->query(self::CMD_CLIENT_PRINT);

        return $result[0] ?? [];
    }

    /**
     * Check if NTP client is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return ($this->getClientSettings()['enabled'] ?? 'no') === 'yes';
    }

    /**
     * Check if router clock is synced with NTP server.
     *
     * @return bool
     */
    public function isSynced(): bool
    {
        return ($this->getClientSettings()['synced'] ?? 'no') === 'yes';
    }

    /**
     * Enable NTP client.
     *
     * @return void
     */
    public function enable(): void
    {
        $this->client->query(self::CMD_CLIENT_SET, ['enabled' => 'yes']);
    }

    /**
     * Disable NTP client.
     *
     * @return void
     */
    public function disable(): void
    {
        $this->client->query(self::CMD_CLIENT_SET, ['enabled' => 'no']);
    }

    /**
     * Set NTP servers by IP address.
     *
     * @param  string      $primary   Primary NTP server IP
     * @param  string|null $secondary Secondary NTP server IP
     * @return void
     *
     * Example:
     *  $manager->setServers('216.239.35.0', '216.239.35.4');
     */
    public function setServers(string $primary, ?string $secondary = null): void
    {
        $data = [
            'enabled' => 'yes',
            'primary-ntp' => $primary,
        ];

        if ($secondary !== null) {
            $data['secondary-ntp'] = $secondary;
        }

        $this->client->query(self::CMD_CLIENT_SET, $data);
    }

    /**
     * Set NTP servers by DNS name.
     *
     * Requires DNS resolution to be working on the router.
     *
     * @param  string      $primary   Primary NTP server DNS name
     * @param  string|null $secondary Secondary NTP server DNS name
     * @return void
     *
     * Example:
     *  $manager->setServersByDns('time.google.com', 'time.cloudflare.com');
     */
    public function setServersByDns(string $primary, ?string $secondary = null): void
    {
        $names = $primary;

        if ($secondary !== null) {
            $names .= ',' . $secondary;
        }

        $this->client->query(self::CMD_CLIENT_SET, [
            'enabled' => 'yes',
            'server-dns-names' => $names,
        ]);
    }

    /**
     * Get NTP sync status details.
     *
     * @return array Full NTP client settings including sync status
     */
    public function getSyncStatus(): array
    {
        return $this->getClientSettings();
    }

    // =========================================================
    // System Clock
    // =========================================================

    /**
     * Get system clock information.
     *
     * @return array Clock info with time, date, time-zone-name
     */
    public function getSystemClock(): array
    {
        $result = $this->client->query(self::CMD_CLOCK_PRINT);

        return $result[0] ?? [];
    }

    /**
     * Set the router timezone.
     *
     * Common ISP timezones:
     *  - Asia/Karachi     (Pakistan — UTC+5)
     *  - Asia/Kolkata     (India — UTC+5:30)
     *  - Asia/Dubai       (UAE — UTC+4)
     *  - Asia/Dhaka       (Bangladesh — UTC+6)
     *  - Europe/London    (UK — UTC+0/+1)
     *  - America/New_York (US East — UTC-5/-4)
     *
     * @param  string $timezone IANA timezone name
     * @return void
     */
    public function setTimezone(string $timezone): void
    {
        $this->client->query(self::CMD_CLOCK_SET, [
            'time-zone-name' => $timezone,
        ]);
    }

    /**
     * Get current system time string.
     *
     * @return string Time string e.g. '14:30:00' or 'unknown'
     */
    public function getCurrentTime(): string
    {
        return $this->getSystemClock()['time'] ?? 'unknown';
    }

    /**
     * Get current system date string.
     *
     * @return string Date string e.g. 'may/18/2026' or 'unknown'
     */
    public function getCurrentDate(): string
    {
        return $this->getSystemClock()['date'] ?? 'unknown';
    }

    /**
     * Get configured timezone name.
     *
     * @return string Timezone name e.g. 'Asia/Karachi' or 'UTC'
     */
    public function getTimezone(): string
    {
        return $this->getSystemClock()['time-zone-name'] ?? 'UTC';
    }
}
