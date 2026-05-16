<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * HotspotManager
 *
 * Manages MikroTik Hotspot users, profiles,
 * active hosts, and voucher generation.
 *
 * Usage:
 *  $manager = new HotspotManager($client);
 *  $manager->getActiveHosts();
 *  $manager->generateVouchers(10, prefix: 'VIP');
 *  $manager->kickHost('user1');
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class HotspotManager
{
    /**
     * RouterOS API commands
     */
    private const CMD_USER_PRINT   = '/ip/hotspot/user/print';
    private const CMD_USER_ADD     = '/ip/hotspot/user/add';
    private const CMD_USER_SET     = '/ip/hotspot/user/set';
    private const CMD_USER_REMOVE  = '/ip/hotspot/user/remove';
    private const CMD_USER_ENABLE  = '/ip/hotspot/user/enable';
    private const CMD_USER_DISABLE = '/ip/hotspot/user/disable';
    private const CMD_ACTIVE_PRINT  = '/ip/hotspot/active/print';
    private const CMD_ACTIVE_REMOVE = '/ip/hotspot/active/remove';
    private const CMD_PROFILE_PRINT = '/ip/hotspot/user/profile/print';
    private const CMD_PROFILE_ADD   = '/ip/hotspot/user/profile/add';

    /**
     * @param RouterosClient $client Authenticated RouterOS client
     */
    public function __construct(
        protected RouterosClient $client
    ) {}

    // =========================================================
    // Users
    // =========================================================

    /**
     * Get all hotspot users.
     *
     * @return array[] List of hotspot users
     */
    public function getUsers(): array
    {
        return $this->client->query(self::CMD_USER_PRINT);
    }

    /**
     * Get a single hotspot user by name.
     *
     * @param  string     $name Hotspot username
     * @return array|null       User data or null if not found
     */
    public function getUser(string $name): ?array
    {
        $users = $this->client->query(
            self::CMD_USER_PRINT,
            queries: ["name={$name}"]
        );

        return $users[0] ?? null;
    }

    /**
     * Create a new hotspot user.
     *
     * @param  array $data Required: name, password. Optional: profile, comment, limit-uptime, etc.
     * @return void
     *
     * Example:
     *  $manager->createUser([
     *      'name'     => 'guest001',
     *      'password' => 'pass123',
     *      'profile'  => 'default',
     *      'comment'  => '1 hour voucher',
     *  ]);
     */
    public function createUser(array $data): void
    {
        $this->client->query(self::CMD_USER_ADD, $data);
    }

    /**
     * Update an existing hotspot user.
     *
     * @param  string $name Hotspot username to update
     * @param  array  $data Fields to update
     * @return void
     */
    public function updateUser(string $name, array $data): void
    {
        $user = $this->getUser($name);

        if (! $user) {
            return;
        }

        $this->client->query(
            self::CMD_USER_SET,
            array_merge(['.id' => $user['.id']], $data)
        );
    }

    /**
     * Delete a hotspot user permanently.
     *
     * @param  string $name Hotspot username to delete
     * @return void
     */
    public function deleteUser(string $name): void
    {
        $user = $this->getUser($name);

        if (! $user) {
            return;
        }

        $this->client->query(
            self::CMD_USER_REMOVE,
            ['.id' => $user['.id']]
        );
    }

    /**
     * Enable a hotspot user.
     *
     * @param  string $name Hotspot username
     * @return void
     */
    public function enableUser(string $name): void
    {
        $user = $this->getUser($name);

        if (! $user) {
            return;
        }

        $this->client->query(
            self::CMD_USER_ENABLE,
            ['.id' => $user['.id']]
        );
    }

    /**
     * Disable a hotspot user (blocks login without deleting).
     *
     * @param  string $name Hotspot username
     * @return void
     */
    public function disableUser(string $name): void
    {
        $user = $this->getUser($name);

        if (! $user) {
            return;
        }

        $this->client->query(
            self::CMD_USER_DISABLE,
            ['.id' => $user['.id']]
        );
    }

    // =========================================================
    // Active Hosts
    // =========================================================

    /**
     * Get all currently active hotspot sessions.
     *
     * @return array[] Active hosts with user, address, uptime, etc.
     */
    public function getActiveHosts(): array
    {
        return $this->client->query(self::CMD_ACTIVE_PRINT);
    }

    /**
     * Disconnect (kick) an active hotspot session by username.
     *
     * @param  string $name Hotspot username to disconnect
     * @return void
     */
    public function kickHost(string $name): void
    {
        $sessions = $this->client->query(
            self::CMD_ACTIVE_PRINT,
            queries: ["user={$name}"]
        );

        if (empty($sessions)) {
            return;
        }

        $this->client->query(
            self::CMD_ACTIVE_REMOVE,
            ['.id' => $sessions[0]['.id']]
        );
    }

    // =========================================================
    // Profiles
    // =========================================================

    /**
     * Get all hotspot user profiles.
     *
     * @return array[] Profiles with rate-limit, session-timeout, etc.
     */
    public function getProfiles(): array
    {
        return $this->client->query(self::CMD_PROFILE_PRINT);
    }

    /**
     * Create a new hotspot user profile.
     *
     * @param  array $data Required: name. Optional: rate-limit, session-timeout, etc.
     * @return void
     */
    public function createProfile(array $data): void
    {
        $this->client->query(self::CMD_PROFILE_ADD, $data);
    }

    // =========================================================
    // Voucher Generation
    // =========================================================

    /**
     * Generate hotspot vouchers and create them on the router.
     *
     * Each voucher gets a unique random name with optional prefix.
     * Vouchers are created on the router immediately.
     *
     * @param  int    $count    Number of vouchers to generate
     * @param  string $profile  Hotspot profile to assign
     * @param  string $prefix   Name prefix for vouchers e.g. "VIP", "GUEST"
     * @param  int    $length   Random suffix length (characters)
     * @return array[]          Generated vouchers with name and password
     */
    public function generateVouchers(
        int    $count,
        string $profile = 'default',
        string $prefix  = 'VC',
        int    $length  = 6
    ): array {
        $vouchers = [];

        for ($i = 0; $i < $count; $i++) {
            $suffix   = strtoupper(substr(bin2hex(random_bytes($length)), 0, $length));
            $name     = $prefix . $suffix;
            $password = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $this->createUser([
                'name'     => $name,
                'password' => $password,
                'profile'  => $profile,
                'comment'  => 'voucher',
            ]);

            $vouchers[] = [
                'name'     => $name,
                'password' => $password,
                'profile'  => $profile,
            ];
        }

        return $vouchers;
    }
}