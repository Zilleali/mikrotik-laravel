<?php

namespace ZillEAli\MikrotikLaravel\Exceptions;

use RuntimeException;

/**
 * ApiException
 *
 * Thrown when RouterOS API returns an error response.
 *
 * @package ZillEAli\MikrotikLaravel\Exceptions
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class ApiException extends RuntimeException
{
    public function __construct(
        string      $message = '',
        private readonly string $command = '',
        private readonly string $category = '',
        private readonly string $detail = '',
        int         $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception from RouterOS !trap response.
     *
     * @param  array<string, string> $trapData
     */
    public static function fromTrap(string $command, array $trapData): self
    {
        $message = $trapData['message'] ?? 'Unknown API error';
        $category = $trapData['category'] ?? '';
        $detail = $trapData['detail'] ?? '';

        $fullMessage = "RouterOS API error on command '{$command}': {$message}";

        if ($detail !== '') {
            $fullMessage .= " ({$detail})";
        }

        return new self($fullMessage, $command, $category, $detail);
    }

    /**
     * Create exception for permission denied.
     */
    public static function permissionDenied(string $command, string $username): self
    {
        return new self(
            "Permission denied for user '{$username}' on command '{$command}'. Check RouterOS user group policies.",
            $command,
        );
    }

    /**
     * Create exception for not found.
     */
    public static function notFound(string $command, string $identifier): self
    {
        return new self(
            "Item '{$identifier}' not found via command '{$command}'.",
            $command,
        );
    }

    public function getCommand(): string
    {
        return $this->command;
    }
    public function getCategory(): string
    {
        return $this->category;
    }
    public function getDetail(): string
    {
        return $this->detail;
    }
}
