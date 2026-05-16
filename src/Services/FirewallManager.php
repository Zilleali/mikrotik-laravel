<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * FirewallManager
 *
 * Manages MikroTik firewall filter rules, NAT rules,
 * mangle rules, and address lists.
 *
 * Usage:
 *  $manager = new FirewallManager($client);
 *  $manager->addToAddressList('1.2.3.4', 'blocked');
 *  $manager->isIpInList('1.2.3.4', 'blocked');
 *  $manager->getNatRules();
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class FirewallManager
{
    /**
     * RouterOS API commands
     */
    private const CMD_FILTER_PRINT  = '/ip/firewall/filter/print';
    private const CMD_FILTER_ADD    = '/ip/firewall/filter/add';
    private const CMD_FILTER_REMOVE = '/ip/firewall/filter/remove';
    private const CMD_NAT_PRINT     = '/ip/firewall/nat/print';
    private const CMD_NAT_ADD       = '/ip/firewall/nat/add';
    private const CMD_MANGLE_PRINT  = '/ip/firewall/mangle/print';
    private const CMD_MANGLE_ADD    = '/ip/firewall/mangle/add';
    private const CMD_ADDRLIST_PRINT  = '/ip/firewall/address-list/print';
    private const CMD_ADDRLIST_ADD    = '/ip/firewall/address-list/add';
    private const CMD_ADDRLIST_REMOVE = '/ip/firewall/address-list/remove';

    /**
     * @param RouterosClient $client Authenticated RouterOS client
     */
    public function __construct(
        protected RouterosClient $client
    ) {}

    // =========================================================
    // Filter Rules
    // =========================================================

    /**
     * Get all firewall filter rules.
     *
     * @return array[] Filter rules with chain, action, protocol, etc.
     */
    public function getFilterRules(): array
    {
        return $this->client->query(self::CMD_FILTER_PRINT);
    }

    /**
     * Add a new firewall filter rule.
     *
     * @param  array $data Required: chain, action. Optional: protocol, src-address, dst-port, etc.
     * @return void
     *
     * Example:
     *  $manager->addFilterRule([
     *      'chain'       => 'input',
     *      'action'      => 'drop',
     *      'src-address' => '1.2.3.4',
     *      'comment'     => 'block spam',
     *  ]);
     */
    public function addFilterRule(array $data): void
    {
        $this->client->query(self::CMD_FILTER_ADD, $data);
    }

    /**
     * Remove a firewall filter rule by its RouterOS ID.
     *
     * @param  string $id RouterOS internal ID (e.g. "*1")
     * @return void
     */
    public function removeFilterRule(string $id): void
    {
        $this->client->query(
            self::CMD_FILTER_REMOVE,
            ['.id' => $id]
        );
    }

    // =========================================================
    // NAT Rules
    // =========================================================

    /**
     * Get all NAT rules.
     *
     * @return array[] NAT rules with chain, action, out-interface, etc.
     */
    public function getNatRules(): array
    {
        return $this->client->query(self::CMD_NAT_PRINT);
    }

    /**
     * Add a new NAT rule.
     *
     * @param  array $data Required: chain, action. Optional: out-interface, src-address, etc.
     * @return void
     *
     * Example:
     *  $manager->addNatRule([
     *      'chain'         => 'srcnat',
     *      'action'        => 'masquerade',
     *      'out-interface' => 'ether1',
     *  ]);
     */
    public function addNatRule(array $data): void
    {
        $this->client->query(self::CMD_NAT_ADD, $data);
    }

    // =========================================================
    // Mangle Rules
    // =========================================================

    /**
     * Get all mangle rules.
     *
     * Used for traffic marking, routing marks, and QoS.
     *
     * @return array[] Mangle rules with chain, action, marks, etc.
     */
    public function getMangleRules(): array
    {
        return $this->client->query(self::CMD_MANGLE_PRINT);
    }

    /**
     * Add a new mangle rule.
     *
     * @param  array $data Required: chain, action. Optional: new-packet-mark, passthrough, etc.
     * @return void
     */
    public function addMangleRule(array $data): void
    {
        $this->client->query(self::CMD_MANGLE_ADD, $data);
    }

    // =========================================================
    // Address Lists
    // =========================================================

    /**
     * Get all address list entries.
     *
     * @return array[] Address list entries with list name, address, comment, etc.
     */
    public function getAddressLists(): array
    {
        return $this->client->query(self::CMD_ADDRLIST_PRINT);
    }

    /**
     * Get all entries from a specific address list.
     *
     * @param  string  $listName Address list name e.g. "blocked", "whitelist"
     * @return array[]           Entries in that list
     */
    public function getAddressList(string $listName): array
    {
        return $this->client->query(
            self::CMD_ADDRLIST_PRINT,
            queries: ["list={$listName}"]
        );
    }

    /**
     * Add an IP address to a named address list.
     *
     * Common use: blocking IPs, whitelisting trusted hosts.
     *
     * @param  string      $ip      IP address or range e.g. "1.2.3.4", "192.168.0.0/24"
     * @param  string      $list    Address list name e.g. "blocked"
     * @param  string|null $comment Optional comment for this entry
     * @return void
     */
    public function addToAddressList(string $ip, string $list, ?string $comment = null): void
    {
        $data = [
            'address' => $ip,
            'list'    => $list,
        ];

        if ($comment !== null) {
            $data['comment'] = $comment;
        }

        $this->client->query(self::CMD_ADDRLIST_ADD, $data);
    }

    /**
     * Remove an IP address from a named address list.
     *
     * @param  string $ip   IP address to remove
     * @param  string $list Address list name
     * @return void
     */
    public function removeFromAddressList(string $ip, string $list): void
    {
        $entries = $this->client->query(
            self::CMD_ADDRLIST_PRINT,
            queries: ["address={$ip}", "list={$list}"]
        );

        if (empty($entries)) {
            return;
        }

        $this->client->query(
            self::CMD_ADDRLIST_REMOVE,
            ['.id' => $entries[0]['.id']]
        );
    }

    /**
     * Check if an IP address is present in a named address list.
     *
     * @param  string $ip   IP address to check
     * @param  string $list Address list name e.g. "blocked"
     * @return bool         True if IP is in the list
     */
    public function isIpInList(string $ip, string $list): bool
    {
        $entries = $this->client->query(
            self::CMD_ADDRLIST_PRINT,
            queries: ["address={$ip}", "list={$list}"]
        );

        return ! empty($entries);
    }
}