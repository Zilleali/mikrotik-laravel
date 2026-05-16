<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * PppoeManager
 *
 * Manages MikroTik PPPoE secrets (users), profiles,
 * and active sessions via the RouterOS API.
 *
 * Usage:
 *  $manager = new PppoeManager($client);
 *  $manager->getActiveSessions();
 *  $manager->kickSession('ali-home');
 *  $manager->bulkEnable(['user1', 'user2']);
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class PppoeManager
{
    /**
     * RouterOS API commands
     */
    private const CMD_SECRET_PRINT = '/ppp/secret/print';
    private const CMD_SECRET_ADD = '/ppp/secret/add';
    private const CMD_SECRET_SET = '/ppp/secret/set';
    private const CMD_SECRET_REMOVE = '/ppp/secret/remove';
    private const CMD_SECRET_ENABLE = '/ppp/secret/enable';
    private const CMD_SECRET_DISABLE = '/ppp/secret/disable';
    private const CMD_ACTIVE_PRINT = '/ppp/active/print';
    private const CMD_ACTIVE_REMOVE = '/ppp/active/remove';
    private const CMD_PROFILE_PRINT = '/ppp/profile/print';
    private const CMD_PROFILE_ADD = '/ppp/profile/add';

    /**
     * @param RouterosClient $client Authenticated RouterOS client
     */
    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Secrets (PPPoE Users)
    // =========================================================

    /**
     * Get all PPPoE secrets (users) from the router.
     *
     * @return array[] List of secrets with all RouterOS properties
     */
    public function getSecrets(): array
    {
        return $this->client->query(self::CMD_SECRET_PRINT);
    }

    /**
     * Get a single PPPoE secret by username.
     *
     * @param  string     $name PPPoE username
     * @return array|null       Secret data or null if not found
     */
    public function getSecret(string $name): ?array
    {
        $secrets = $this->client->query(
            self::CMD_SECRET_PRINT,
            queries: ["name={$name}"]
        );

        return $secrets[0] ?? null;
    }

    /**
     * Create a new PPPoE secret.
     *
     * @param  array $data Required: name, password. Optional: service, profile, comment, etc.
     * @return void
     *
     * Example:
     *  $manager->createSecret([
     *      'name'     => 'ali-home',
     *      'password' => 'pass123',
     *      'service'  => 'pppoe',
     *      'profile'  => '10mbps',
     *      'comment'  => 'Ali House Connection',
     *  ]);
     */
    public function createSecret(array $data): void
    {
        $this->client->query(self::CMD_SECRET_ADD, $data);
    }

    /**
     * Update an existing PPPoE secret by username.
     *
     * @param  string $name PPPoE username to update
     * @param  array  $data Fields to update (password, profile, comment, etc.)
     * @return void
     */
    public function updateSecret(string $name, array $data): void
    {
        $secret = $this->getSecret($name);

        if (! $secret) {
            return;
        }

        $this->client->query(
            self::CMD_SECRET_SET,
            array_merge(['.id' => $secret['.id']], $data)
        );
    }

    /**
     * Delete a PPPoE secret permanently.
     *
     * @param  string $name PPPoE username to delete
     * @return void
     */
    public function deleteSecret(string $name): void
    {
        $secret = $this->getSecret($name);

        if (! $secret) {
            return;
        }

        $this->client->query(
            self::CMD_SECRET_REMOVE,
            ['.id' => $secret['.id']]
        );
    }

    /**
     * Enable a disabled PPPoE secret.
     *
     * @param  string $name PPPoE username
     * @return void
     */
    public function enableSecret(string $name): void
    {
        $secret = $this->getSecret($name);

        if (! $secret) {
            return;
        }

        $this->client->query(
            self::CMD_SECRET_ENABLE,
            ['.id' => $secret['.id']]
        );
    }

    /**
     * Disable a PPPoE secret (blocks login without deleting).
     *
     * @param  string $name PPPoE username
     * @return void
     */
    public function disableSecret(string $name): void
    {
        $secret = $this->getSecret($name);

        if (! $secret) {
            return;
        }

        $this->client->query(
            self::CMD_SECRET_DISABLE,
            ['.id' => $secret['.id']]
        );
    }

    // =========================================================
    // Bulk Operations
    // =========================================================

    /**
     * Enable multiple PPPoE secrets at once.
     *
     * @param  string[] $names List of PPPoE usernames
     * @return void
     */
    public function bulkEnable(array $names): void
    {
        foreach ($names as $name) {
            $this->enableSecret($name);
        }
    }

    /**
     * Disable multiple PPPoE secrets at once.
     *
     * @param  string[] $names List of PPPoE usernames
     * @return void
     */
    public function bulkDisable(array $names): void
    {
        foreach ($names as $name) {
            $this->disableSecret($name);
        }
    }

    /**
     * Kick (disconnect) multiple active sessions at once.
     *
     * @param  string[] $names List of PPPoE usernames to disconnect
     * @return void
     */
    public function bulkKick(array $names): void
    {
        foreach ($names as $name) {
            $this->kickSession($name);
        }
    }

    // =========================================================
    // Active Sessions
    // =========================================================

    /**
     * Get all currently active PPPoE sessions.
     *
     * @return array[] List of active sessions with name, address, uptime, etc.
     */
    public function getActiveSessions(): array
    {
        return $this->client->query(self::CMD_ACTIVE_PRINT);
    }

    /**
     * Find an active session by IP address.
     *
     * Useful for reverse lookup — find which user is on a given IP.
     *
     * @param  string     $ip IP address to search for
     * @return array|null     Active session data or null if not found
     */
    public function getSecretByIp(string $ip): ?array
    {
        $sessions = $this->getActiveSessions();

        foreach ($sessions as $session) {
            if (($session['address'] ?? '') === $ip) {
                return $session;
            }
        }

        return null;
    }

    /**
     * Disconnect (kick) an active PPPoE session by username.
     *
     * @param  string $name PPPoE username to disconnect
     * @return void
     */
    public function kickSession(string $name): void
    {
        $sessions = $this->client->query(
            self::CMD_ACTIVE_PRINT,
            queries: ["name={$name}"]
        );

        if (empty($sessions)) {
            return;
        }

        $session = $sessions[0];

        $this->client->query(
            self::CMD_ACTIVE_REMOVE,
            ['.id' => $session['.id']]
        );

        // Dispatch event — listeners handle notifications/billing
        if (class_exists(\Illuminate\Support\Facades\Event::class)) {
            \Illuminate\Support\Facades\Event::dispatch(
                new \ZillEAli\MikrotikLaravel\Events\SessionDisconnected(
                    username: $name,
                    ip: $session['address'] ?? null,
                    uptime: $session['uptime'] ?? null,
                    reason: 'manual',
                    raw: $session,
                )
            );
        }
    }

    // =========================================================
    // Profiles
    // =========================================================

    /**
     * Get all PPPoE profiles (bandwidth plans).
     *
     * @return array[] List of profiles with rate-limit, session-timeout, etc.
     */
    public function getProfiles(): array
    {
        return $this->client->query(self::CMD_PROFILE_PRINT);
    }

    /**
     * Create a new PPPoE profile (bandwidth plan).
     *
     * @param  array $data Required: name. Optional: rate-limit, session-timeout, etc.
     * @return void
     *
     * Example:
     *  $manager->createProfile([
     *      'name'          => '10mbps',
     *      'rate-limit'    => '10M/10M',
     *      'session-timeout' => '30d',
     *  ]);
     */
    public function createProfile(array $data): void
    {
        $this->client->query(self::CMD_PROFILE_ADD, $data);
    }
}
