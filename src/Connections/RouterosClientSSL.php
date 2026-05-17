<?php

namespace ZillEAli\MikrotikLaravel\Connections;

use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;

/**
 * RouterosClientSSL
 *
 * TLS-encrypted connection to MikroTik RouterOS API.
 * Uses port 8729 (API-SSL) instead of plain 8728.
 *
 * MikroTik must have API-SSL service enabled:
 *  IP → Services → api-ssl → enabled
 *  IP → Services → api-ssl → certificate (set a valid cert)
 *
 * By default, self-signed certificates are accepted —
 * suitable for most ISP deployments. For strict security,
 * enable verifyPeer and provide your CA certificate path.
 *
 * Usage:
 *  $client = new RouterosClientSSL(
 *      host:       '192.168.88.1',
 *      username:   'admin',
 *      password:   'secret',
 *  );
 *  $client->connect();
 *  $client->query('/ppp/secret/print');
 *
 * Strict mode (production with valid cert):
 *  $client = new RouterosClientSSL(
 *      host:       'router.yourisp.com',
 *      verifyPeer: true,
 *      caCertPath: '/etc/ssl/certs/ca-certificates.crt',
 *  );
 *
 * @package ZillEAli\MikrotikLaravel\Connections
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class RouterosClientSSL extends RouterosClient
{
    /**
     * SSL stream context options.
     *
     * @var array<string, mixed>
     */
    protected array $sslContext = [];

    /**
     * Create a new SSL RouterOS client.
     *
     * @param string      $host        Router IP or hostname
     * @param int         $port        API-SSL port (default 8729)
     * @param string      $username    RouterOS username
     * @param string      $password    RouterOS password
     * @param int         $timeout     Connection + read timeout in seconds
     * @param bool        $verifyPeer  Verify SSL certificate (false = accept self-signed)
     * @param string|null $caCertPath  Path to CA certificate file (required if verifyPeer=true)
     */
    public function __construct(
        string  $host,
        int     $port = 8729,
        string  $username = 'admin',
        string  $password = '',
        int     $timeout = 10,
        bool    $verifyPeer = false,
        ?string $caCertPath = null,
    ) {
        parent::__construct($host, $port, $username, $password, $timeout);

        $this->sslContext = $this->buildSslContext($verifyPeer, $caCertPath);
    }

    // =========================================================
    // Connection — SSL override
    // =========================================================

    /**
     * Open a TLS-encrypted TCP connection to the router.
     *
     * Overrides parent connect() to use stream_socket_client
     * with SSL context instead of plain fsockopen.
     *
     * @return static
     * @throws ConnectionException If connection or login fails
     */
    public function connect(): static
    {
        $context = stream_context_create($this->sslContext);

        $address = "ssl://{$this->host}:{$this->port}";

        $this->socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $this->socket) {
            throw new ConnectionException(
                "SSL connection failed to {$this->host}:{$this->port} — {$errstr} ({$errno}). " .
                "Ensure API-SSL service is enabled: IP → Services → api-ssl → enabled"
            );
        }

        stream_set_timeout($this->socket, $this->timeout);

        $this->connected = true;

        $this->login();

        return $this;
    }

    // =========================================================
    // SSL Context
    // =========================================================

    /**
     * Build SSL stream context options.
     *
     * @param  bool        $verifyPeer  Whether to verify the server certificate
     * @param  string|null $caCertPath  CA certificate file path
     * @return array<string, mixed>
     */
    protected function buildSslContext(bool $verifyPeer, ?string $caCertPath): array
    {
        $ssl = [
            'verify_peer' => $verifyPeer,
            'verify_peer_name' => $verifyPeer,
            'allow_self_signed' => ! $verifyPeer,
        ];

        if ($verifyPeer && $caCertPath !== null) {
            $ssl['cafile'] = $caCertPath;
        }

        return ['ssl' => $ssl];
    }

    // =========================================================
    // Info
    // =========================================================

    /**
     * Get connection info including SSL status.
     *
     * @return array<string, mixed>
     */
    public function getConnectionInfo(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'ssl' => true,
            'connected' => $this->connected,
            'verify_peer' => $this->sslContext['ssl']['verify_peer'] ?? false,
        ];
    }
}
