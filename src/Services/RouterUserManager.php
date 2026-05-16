<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * RouterUserManager
 *
 * Manages MikroTik router user accounts — the users that
 * log into the router via Winbox, SSH, API, or web interface.
 *
 * NOT to be confused with PPPoE/Hotspot users (those are managed
 * by PppoeManager and HotspotManager).
 *
 * Common use cases:
 *  - Create read-only NOC user for monitoring
 *  - Create dedicated API user for NexaLink
 *  - Disable compromised admin accounts remotely
 *  - Audit who is currently logged into the router
 *
 * Recommended: Create a dedicated group for API-only access:
 *  /user/group/add name=api-readonly policy=read,api,!write,!policy
 *
 * Usage:
 *  $manager = new RouterUserManager($client);
 *  $manager->addUser(['name' => 'noc', 'password' => 'pass', 'group' => 'read']);
 *  $manager->getActiveSessions();
 *  $manager->changePassword('admin', 'NewSecurePass!');
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class RouterUserManager
{
    private const CMD_USER_PRINT = '/user/print';
    private const CMD_USER_ADD = '/user/add';
    private const CMD_USER_SET = '/user/set';
    private const CMD_USER_REMOVE = '/user/remove';
    private const CMD_USER_ENABLE = '/user/enable';
    private const CMD_USER_DISABLE = '/user/disable';
    private const CMD_GROUP_PRINT = '/user/group/print';
    private const CMD_ACTIVE_PRINT = '/user/active/print';

    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // Users
    // =========================================================

    /**
     * Get all router users.
     *
     * @return array[] Users with name, group, disabled, comment, last-logged-in
     */
    public function getUsers(): array
    {
        return $this->client->query(self::CMD_USER_PRINT);
    }

    /**
     * Get a single router user by name.
     *
     * @param  string     $name Router username
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
     * Add a new router user.
     *
     * @param  array $data Required: name, password, group. Optional: comment, address.
     * @return void
     *
     * Example:
     *  $manager->addUser([
     *      'name'     => 'noc-operator',
     *      'password' => 'SecurePass123!',
     *      'group'    => 'read',
     *      'comment'  => 'NOC read-only access',
     *      'address'  => '192.168.1.0/24', // restrict by IP
     *  ]);
     */
    public function addUser(array $data): void
    {
        $this->client->query(self::CMD_USER_ADD, $data);
    }

    /**
     * Update a router user's properties.
     *
     * @param  string $name Router username to update
     * @param  array  $data Fields to update e.g. ['group' => 'read', 'comment' => '...']
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
     * Delete a router user permanently.
     *
     * WARNING: Do not delete the last admin user —
     * you will lose access to the router.
     *
     * @param  string $name Router username to delete
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
     * Change a router user's password.
     *
     * @param  string $name     Router username
     * @param  string $password New password
     * @return void
     */
    public function changePassword(string $name, string $password): void
    {
        $this->updateUser($name, ['password' => $password]);
    }

    /**
     * Enable a disabled router user.
     *
     * @param  string $name Router username
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
     * Disable a router user (blocks login without deleting).
     *
     * Useful for temporarily revoking access without losing config.
     *
     * @param  string $name Router username
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
    // Groups
    // =========================================================

    /**
     * Get all user groups configured on the router.
     *
     * Default groups: full, read, write.
     * Custom groups can be created for restricted access.
     *
     * @return array[] Groups with name, policy
     */
    public function getGroups(): array
    {
        return $this->client->query(self::CMD_GROUP_PRINT);
    }

    /**
     * Add a custom user group with specific policies.
     *
     * @param  string $name   Group name
     * @param  string $policy Comma-separated policies
     * @return void
     *
     * Example — read-only API group:
     *  $manager->addGroup('api-readonly', 'read,api,!write,!policy,!sensitive');
     */
    public function addGroup(string $name, string $policy): void
    {
        $this->client->query('/user/group/add', [
            'name' => $name,
            'policy' => $policy,
        ]);
    }

    // =========================================================
    // Active Sessions
    // =========================================================

    /**
     * Get all currently active router user sessions.
     *
     * Shows who is currently logged into the router via
     * Winbox, SSH, Telnet, API, or web interface.
     *
     * @return array[] Active sessions with name, address, via, when
     */
    public function getActiveSessions(): array
    {
        return $this->client->query(self::CMD_ACTIVE_PRINT);
    }

    /**
     * Check if a specific user has an active session.
     *
     * @param  string $name Router username to check
     * @return bool         True if user is currently logged in
     */
    public function isUserActive(string $name): bool
    {
        $sessions = $this->getActiveSessions();

        foreach ($sessions as $session) {
            if (($session['name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }
}
