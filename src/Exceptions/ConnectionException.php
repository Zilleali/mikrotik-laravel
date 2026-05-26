<?php

namespace ZillEAli\MikrotikLaravel\Exceptions;

use RuntimeException;

/**
 * ConnectionException
 *
 * Thrown when RouterOS API connection fails.
 *
 * @package ZillEAli\MikrotikLaravel\Exceptions
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class ConnectionException extends RuntimeException
{
    public function __construct(
        string     $message = '',
        private readonly string $host = '',
        private readonly int    $port = 8728,
        private readonly string $router = 'default',
        int        $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for unreachable host.
     */
    public static function unreachable(
        string $host,
        int    $port,
        string $router = 'default',
        string $reason = '',
    ): self {
        $message = "Cannot connect to router '{$router}' at {$host}:{$port}";

        if ($reason !== '') {
            $message .= " — {$reason}";
        }

        return new self($message, $host, $port, $router);
    }

    /**
     * Create exception for authentication failure.
     */
    public static function authenticationFailed(
        string $host,
        int    $port,
        string $username,
        string $router = 'default',
    ): self {
        return new self(
            "Authentication failed for user '{$username}' on router '{$router}' ({$host}:{$port}). Check username and password.",
            $host,
            $port,
            $router,
        );
    }

    /**
     * Create exception for timeout.
     */
    public static function timeout(
        string $host,
        int    $port,
        int    $timeout,
        string $router = 'default',
    ): self {
        return new self(
            "Connection to router '{$router}' ({$host}:{$port}) timed out after {$timeout} seconds.",
            $host,
            $port,
            $router,
        );
    }

    /**
     * Create exception for unknown router.
     */
    public static function routerNotFound(string $name): self
    {
        return new self(
            "Router '{$name}' not found in config/mikrotik.php routers array. Add it under 'routers' key.",
            router: $name,
        );
    }

    /**
     * Create exception for retry exhaustion.
     */
    public static function retriesExhausted(
        string      $host,
        int         $port,
        int         $attempts,
        string      $router = 'default',
        ?\Throwable $previous = null,
    ): self {
        return new self(
            "Failed to connect to router '{$router}' ({$host}:{$port}) after {$attempts} attempt(s).",
            $host,
            $port,
            $router,
            0,
            $previous,
        );
    }

    public function getHost(): string
    {
        return $this->host;
    }
    public function getPort(): int
    {
        return $this->port;
    }
    public function getRouter(): string
    {
        return $this->router;
    }
}
