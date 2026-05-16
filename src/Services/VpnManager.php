<?php

namespace ZillEAli\MikrotikLaravel\Services;

use ZillEAli\MikrotikLaravel\Connections\RouterosClient;

/**
 * VpnManager
 *
 * Manages MikroTik VPN tunnels — WireGuard, L2TP, and PPTP.
 *
 * Primary use cases for ISPs:
 *  - WireGuard: site-to-site tunnels between ISP locations
 *  - L2TP: remote office connections
 *  - PPTP: legacy Windows VPN clients
 *
 * NexaLink uses WireGuard (10.8.0.0/24) for internal
 * infrastructure tunneling.
 *
 * Usage:
 *  $manager = new VpnManager($client);
 *  $manager->getWireGuardPeers();
 *  $manager->addWireGuardPeer([...]);
 *  $manager->getActiveVpnCount();
 *
 * @package ZillEAli\MikrotikLaravel\Services
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class VpnManager
{
    private const CMD_WG_IFACE_PRINT = '/interface/wireguard/print';
    private const CMD_WG_PEER_PRINT = '/interface/wireguard/peers/print';
    private const CMD_WG_PEER_ADD = '/interface/wireguard/peers/add';
    private const CMD_WG_PEER_REMOVE = '/interface/wireguard/peers/remove';
    private const CMD_L2TP_SESSION = '/interface/l2tp-server/session/print';
    private const CMD_PPTP_SESSION = '/interface/pptp-server/session/print';
    private const CMD_PPP_SECRET = '/ppp/secret/print';

    public function __construct(
        protected RouterosClient $client
    ) {
    }

    // =========================================================
    // WireGuard
    // =========================================================

    /**
     * Get all WireGuard interfaces.
     *
     * @return array[] Interfaces with name, listen-port, public-key, disabled
     */
    public function getWireGuardInterfaces(): array
    {
        return $this->client->query(self::CMD_WG_IFACE_PRINT);
    }

    /**
     * Get all WireGuard peers across all interfaces.
     *
     * @return array[] Peers with interface, public-key, endpoint, allowed-address
     */
    public function getWireGuardPeers(): array
    {
        return $this->client->query(self::CMD_WG_PEER_PRINT);
    }

    /**
     * Get WireGuard peers for a specific interface.
     *
     * @param  string  $interface WireGuard interface name e.g. 'wg0'
     * @return array[]
     */
    public function getWireGuardPeersByInterface(string $interface): array
    {
        $peers = $this->getWireGuardPeers();

        return array_values(
            array_filter($peers, fn ($p) => ($p['interface'] ?? '') === $interface)
        );
    }

    /**
     * Add a new WireGuard peer.
     *
     * @param  array $data Required: interface, public-key, allowed-address.
     *                     Optional: endpoint-address, endpoint-port, comment.
     * @return void
     *
     * Example:
     *  $manager->addWireGuardPeer([
     *      'interface'        => 'wg0',
     *      'public-key'       => 'base64publickey==',
     *      'allowed-address'  => '10.8.0.2/32',
     *      'endpoint-address' => '1.2.3.4',
     *      'endpoint-port'    => '13231',
     *      'comment'          => 'Branch Office Lahore',
     *  ]);
     */
    public function addWireGuardPeer(array $data): void
    {
        $this->client->query(self::CMD_WG_PEER_ADD, $data);
    }

    /**
     * Remove a WireGuard peer by public key.
     *
     * @param  string $publicKey Peer's WireGuard public key
     * @return void
     */
    public function removeWireGuardPeer(string $publicKey): void
    {
        $peers = $this->client->query(
            self::CMD_WG_PEER_PRINT,
            queries: ["public-key={$publicKey}"]
        );

        if (empty($peers)) {
            return;
        }

        $this->client->query(
            self::CMD_WG_PEER_REMOVE,
            ['.id' => $peers[0]['.id']]
        );
    }

    /**
     * Get count of connected WireGuard peers.
     *
     * A peer is considered connected if it has a current-endpoint-address.
     *
     * @return int
     */
    public function getConnectedWireGuardCount(): int
    {
        $peers = $this->getWireGuardPeers();

        return count(array_filter(
            $peers,
            fn ($p) => ! empty($p['current-endpoint-address'])
        ));
    }

    // =========================================================
    // L2TP
    // =========================================================

    /**
     * Get all active L2TP server sessions.
     *
     * @return array[] Sessions with name, user, address, uptime, etc.
     */
    public function getL2tpSessions(): array
    {
        return $this->client->query(self::CMD_L2TP_SESSION);
    }

    /**
     * Get L2TP secrets from PPP secret list.
     *
     * Filters PPP secrets where service = 'l2tp'.
     *
     * @return array[]
     */
    public function getL2tpSecrets(): array
    {
        $secrets = $this->client->query(self::CMD_PPP_SECRET);

        return array_values(
            array_filter($secrets, fn ($s) => ($s['service'] ?? '') === 'l2tp')
        );
    }

    // =========================================================
    // PPTP
    // =========================================================

    /**
     * Get all active PPTP server sessions.
     *
     * Note: PPTP is deprecated and insecure. Consider migrating to
     * WireGuard or L2TP/IPSec for new deployments.
     *
     * @return array[] Sessions with name, user, address, uptime, etc.
     */
    public function getPptpSessions(): array
    {
        return $this->client->query(self::CMD_PPTP_SESSION);
    }

    // =========================================================
    // Summary
    // =========================================================

    /**
     * Get total count of active VPN sessions across all protocols.
     *
     * Combines: WireGuard connected peers + L2TP sessions + PPTP sessions.
     *
     * @return int Total active VPN connections
     */
    public function getActiveVpnCount(): int
    {
        return $this->getConnectedWireGuardCount()
            + count($this->getL2tpSessions())
            + count($this->getPptpSessions());
    }
}
