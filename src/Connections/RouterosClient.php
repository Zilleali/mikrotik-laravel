<?php

namespace ZillEAli\MikrotikLaravel\Connections;

use ZillEAli\MikrotikLaravel\Exceptions\ApiException;
use ZillEAli\MikrotikLaravel\Exceptions\ConnectionException;

/**
 * RouterosClient
 *
 * Low-level TCP client for the MikroTik RouterOS API.
 *
 * Implements the RouterOS API sentence protocol:
 *  - Each "sentence" is a list of words sent over TCP
 *  - Each word is prefixed with its encoded byte-length
 *  - A sentence ends with an empty word (0x00)
 *  - Router responds with: !re (data row), !done (end),
 *    !trap (error), !fatal (disconnect)
 *
 * Supports:
 *  - RouterOS v6.43+ plain-text login
 *  - RouterOS <v6.43 MD5 challenge-response login
 *  - Variable-length encoding (1 to 5 bytes)
 *  - Auto-disconnect via destructor
 *
 * Usage:
 *  $client = new RouterosClient('192.168.88.1');
 *  $client->connect();
 *  $result = $client->query('/ip/address/print');
 *  $client->disconnect();
 *
 * @package ZillEAli\MikrotikLaravel\Connections
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class RouterosClient
{
    /**
     * Active TCP socket resource.
     * Null when not connected.
     */
    protected mixed $socket = null;

    /**
     * Whether the client is currently connected
     * and authenticated with the router.
     */
    protected bool $connected = false;

    /**
     * Create a new RouterosClient instance.
     *
     * @param string $host     Router IP or hostname
     * @param int    $port     API port (8728 plain, 8729 SSL)
     * @param string $username RouterOS username
     * @param string $password RouterOS password
     * @param int    $timeout  TCP connect + read timeout in seconds
     */
    public function __construct(
        protected string $host,
        protected int    $port = 8728,
        protected string $username = 'admin',
        protected string $password = '',
        protected int    $timeout = 10,
    ) {
    }

    // =========================================================
    // Connection Management
    // =========================================================

    /**
     * Open a TCP connection to the router and authenticate.
     *
     * @return static
     * @throws ConnectionException If TCP connect fails or login is rejected
     */
    public function connect(): static
    {
        $this->socket = @fsockopen(
            $this->host,
            $this->port,
            $errno,
            $errstr,
            $this->timeout
        );

        if (! $this->socket) {
            throw new ConnectionException(
                "Cannot connect to {$this->host}:{$this->port} — {$errstr} ({$errno})"
            );
        }

        stream_set_timeout($this->socket, $this->timeout);

        $this->connected = true;

        $this->login();

        return $this;
    }

    /**
     * Close the TCP socket and reset connection state.
     * Safe to call multiple times.
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
            $this->connected = false;
        }
    }

    /**
     * Check whether the client is connected and authenticated.
     */
    public function isConnected(): bool
    {
        return $this->connected && is_resource($this->socket);
    }

    // =========================================================
    // Authentication
    // =========================================================

    /**
     * Authenticate with the router.
     *
     * Tries plain login first (RouterOS v6.43+).
     * Falls back to MD5 challenge-response for older firmware.
     *
     * @throws ConnectionException If login is rejected
     */
    protected function login(): void
    {
        $response = $this->send([
            '/login',
            "=name={$this->username}",
            "=password={$this->password}",
        ]);

        // Older RouterOS returns a challenge token starting with =ret=
        // Respond with MD5(null_byte + password + challenge)
        if (isset($response[0]) && str_starts_with($response[0], '=ret=')) {
            $challenge = pack('H*', substr($response[0], 5));
            $md5 = md5("\x00{$this->password}{$challenge}", true);

            $response = $this->send([
                '/login',
                "=name={$this->username}",
                "=response=00" . bin2hex($md5),
            ]);
        }

        foreach ($response as $word) {
            if (str_starts_with($word, '!trap')) {
                throw new ConnectionException(
                    "Login failed for user '{$this->username}' on {$this->host}"
                );
            }
        }
    }

    // =========================================================
    // Send / Receive
    // =========================================================

    /**
     * Send a sentence (array of words) and return the raw response words.
     *
     * @param  string[] $words  RouterOS API words e.g. ['/ip/address/print']
     * @return string[]         Raw response words from the router
     *
     * @throws ConnectionException If not connected or connection lost
     * @throws ApiException        If router returns !trap or !fatal
     */
    public function send(array $words): array
    {
        if (! $this->isConnected()) {
            throw new ConnectionException('Not connected. Call connect() first.');
        }

        $this->writeSentence($words);

        return $this->readResponse();
    }

    /**
     * Write all words of a sentence to the socket,
     * followed by an empty word to signal end-of-sentence.
     *
     * @param string[] $words
     */
    protected function writeSentence(array $words): void
    {
        foreach ($words as $word) {
            $this->writeWord($word);
        }

        // Empty word = end of sentence in RouterOS protocol
        $this->writeWord('');
    }

    /**
     * Write a single word to the socket.
     * Prefixes the word with its encoded byte-length.
     *
     * @param string $word
     */
    protected function writeWord(string $word): void
    {
        $len = strlen($word);
        fwrite($this->socket, $this->encodeLength($len) . $word);
    }

    /**
     * Read the full response from the router until !done is received.
     *
     * @return string[] Response words (excluding !re and !done markers)
     *
     * @throws ApiException        On !trap or !fatal response
     * @throws ConnectionException On lost connection
     */
    protected function readResponse(): array
    {
        $response = [];

        while (true) {
            $word = $this->readWord();

            // !done signals end of response
            if ($word === '!done') {
                break;
            }

            // !trap = command error, !fatal = disconnect
            if (str_starts_with($word, '!trap') || str_starts_with($word, '!fatal')) {
                $message = $this->readTrapMessage();

                throw new ApiException("RouterOS error: {$message}");
            }

            // !re = data row marker — skip the marker itself, keep the data words
            if ($word !== '' && $word !== '!re') {
                $response[] = $word;
            }
        }

        return $response;
    }

    /**
     * Read a single word from the socket.
     * Decodes the length prefix first, then reads that many bytes.
     *
     * @return string
     * @throws ConnectionException If connection is lost mid-read
     */
    protected function readWord(): string
    {
        $len = $this->decodeLength();

        if ($len === 0) {
            return '';
        }

        $word = '';
        $remaining = $len;

        // Read in chunks — TCP may not deliver all bytes at once
        while ($remaining > 0) {
            $chunk = fread($this->socket, $remaining);

            if ($chunk === false || $chunk === '') {
                throw new ConnectionException(
                    'Connection lost while reading response from router'
                );
            }

            $word .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $word;
    }

    /**
     * Read and discard remaining words after a !trap,
     * extracting the =message= value if present.
     *
     * @return string Human-readable error message
     */
    protected function readTrapMessage(): string
    {
        $message = '';

        while (true) {
            $word = $this->readWord();

            if ($word === '' || $word === '!done') {
                break;
            }

            if (str_starts_with($word, '=message=')) {
                $message = substr($word, 9);
            }
        }

        return $message ?: 'Unknown RouterOS error';
    }

    // =========================================================
    // RouterOS Length Encoding
    // =========================================================

    /**
     * Encode an integer length into RouterOS variable-length format.
     *
     * Encoding table:
     *  0 – 0x7F        → 1 byte
     *  0x80 – 0x3FFF   → 2 bytes (OR with 0x8000)
     *  0x4000 – 0x1FFFFF   → 3 bytes (OR with 0xC00000)
     *  0x200000 – 0xFFFFFFF → 4 bytes (OR with 0xE0000000)
     *  >= 0x10000000   → 5 bytes (0xF0 prefix)
     *
     * @param  int    $len
     * @return string Binary encoded length
     */
    protected function encodeLength(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }

        if ($len < 0x4000) {
            $len |= 0x8000;

            return chr(($len >> 8) & 0xFF) . chr($len & 0xFF);
        }

        if ($len < 0x200000) {
            $len |= 0xC00000;

            return chr(($len >> 16) & 0xFF)
                . chr(($len >> 8) & 0xFF)
                . chr($len & 0xFF);
        }

        if ($len < 0x10000000) {
            $len |= 0xE0000000;

            return chr(($len >> 24) & 0xFF)
                . chr(($len >> 16) & 0xFF)
                . chr(($len >> 8) & 0xFF)
                . chr($len & 0xFF);
        }

        // 5-byte encoding for very large words (rare in practice)
        return chr(0xF0)
            . chr(($len >> 24) & 0xFF)
            . chr(($len >> 16) & 0xFF)
            . chr(($len >> 8) & 0xFF)
            . chr($len & 0xFF);
    }

    /**
     * Decode a RouterOS variable-length integer from the socket.
     *
     * Reads 1 byte first to determine total byte count,
     * then reads additional bytes as needed.
     *
     * @return int Decoded length value
     */
    protected function decodeLength(): int
    {
        $firstByte = ord(fread($this->socket, 1));

        // 1-byte: 0xxxxxxx
        if ($firstByte < 0x80) {
            return $firstByte;
        }

        // 2-byte: 10xxxxxx xxxxxxxx
        if ($firstByte < 0xC0) {
            $b2 = ord(fread($this->socket, 1));

            return (($firstByte & 0x3F) << 8) | $b2;
        }

        // 3-byte: 110xxxxx xxxxxxxx xxxxxxxx
        if ($firstByte < 0xE0) {
            $bytes = fread($this->socket, 2);

            return (($firstByte & 0x1F) << 16)
                | (ord($bytes[0]) << 8)
                | ord($bytes[1]);
        }

        // 4-byte: 1110xxxx xxxxxxxx xxxxxxxx xxxxxxxx
        if ($firstByte < 0xF0) {
            $bytes = fread($this->socket, 3);

            return (($firstByte & 0x0F) << 24)
                | (ord($bytes[0]) << 16)
                | (ord($bytes[1]) << 8)
                | ord($bytes[2]);
        }

        // 5-byte: 11110000 xxxxxxxx xxxxxxxx xxxxxxxx xxxxxxxx
        $bytes = fread($this->socket, 4);

        return (ord($bytes[0]) << 24)
            | (ord($bytes[1]) << 16)
            | (ord($bytes[2]) << 8)
            | ord($bytes[3]);
    }

    // =========================================================
    // High-Level Query Helper
    // =========================================================

    /**
     * Send a RouterOS command and return results as structured array.
     *
     * Converts raw API response words into associative arrays.
     * Each !re block becomes one array entry.
     *
     * Example:
     *  $client->query('/ip/address/print');
     *  // returns: [['address' => '192.168.1.1/24', 'interface' => 'ether1'], ...]
     *
     * @param  string   $command  RouterOS command e.g. '/ppp/secret/print'
     * @param  string[] $params   Key-value pairs sent as =key=value
     * @param  string[] $queries  Filter queries sent as ?key=value
     * @return array[]            Parsed response rows
     *
     * @throws ConnectionException
     * @throws ApiException
     */
    public function query(string $command, array $params = [], array $queries = []): array
    {
        $words = [$command];

        // Append =key=value parameters
        foreach ($params as $key => $value) {
            $words[] = "={$key}={$value}";
        }

        // Append ?query filters
        foreach ($queries as $query) {
            $words[] = "?{$query}";
        }

        $raw = $this->send($words);
        $result = [];
        $row = [];

        foreach ($raw as $word) {
            // New row starts — save previous row if exists
            if ($word === '!re') {
                if (! empty($row)) {
                    $result[] = $row;
                    $row = [];
                }

                continue;
            }

            // Parse =key=value words into associative array
            if (str_starts_with($word, '=')) {
                $parts = explode('=', substr($word, 1), 2);
                $row[$parts[0]] = $parts[1] ?? '';
            }
        }

        // Capture last row
        if (! empty($row)) {
            $result[] = $row;
        }

        return $result;
    }

    // =========================================================
    // Destructor
    // =========================================================

    /**
     * Auto-disconnect when object is destroyed.
     * Prevents socket leaks in long-running processes.
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
