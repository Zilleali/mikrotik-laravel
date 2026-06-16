<?php

namespace ZillEAli\MikrotikLaravel\Support;

use Illuminate\Support\Facades\Log;

/**
 * MikrotikLogger
 *
 * Centralized logging for all RouterOS API activity.
 *
 * Two log levels:
 *  - info/debug  — read operations, connections, general activity
 *  - warning     — critical destructive actions (kicks, deletes, reboots)
 *
 * Controlled via config/mikrotik.php:
 *  logging.enabled — true/false
 *  logging.channel — Laravel log channel
 *  logging.level   — base log level
 *
 * Usage:
 *  MikrotikLogger::info('pppoe', 'Session kicked', ['user' => 'ali-home']);
 *  MikrotikLogger::warning('system', 'Router rebooted', ['router' => 'main']);
 *  MikrotikLogger::connection('connected', '192.168.88.1', 8728, 'main');
 *  MikrotikLogger::critical('pppoe', 'kickSession', 'ali-home', 'main');
 *
 * @package ZillEAli\MikrotikLaravel\Support
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class MikrotikLogger
{
    /**
     * Log informational message.
     *
     * Use for: read operations, successful connections, general activity.
     *
     * @param  string               $manager  Manager name (pppoe, hotspot, system...)
     * @param  string               $message  Log message
     * @param  array<string, mixed> $context  Additional context data
     * @return void
     */
    public static function info(string $manager, string $message, array $context = []): void
    {
        if (! self::isEnabled()) {
            return;
        }

        $level = config('mikrotik.logging.level', 'info');

        self::channel()->$level(
            "[mikrotik.{$manager}] {$message}",
            self::buildContext($manager, $context)
        );
    }

    /**
     * Log warning message.
     *
     * Use for: destructive actions, unexpected states, retry attempts.
     *
     * @param  string               $manager
     * @param  string               $message
     * @param  array<string, mixed> $context
     * @return void
     */
    public static function warning(string $manager, string $message, array $context = []): void
    {
        if (! self::isEnabled()) {
            return;
        }

        self::channel()->warning(
            "[mikrotik.{$manager}] {$message}",
            self::buildContext($manager, $context)
        );
    }

    /**
     * Log error message.
     *
     * Use for: API errors, connection failures, exceptions caught internally.
     *
     * @param  string               $manager
     * @param  string               $message
     * @param  array<string, mixed> $context
     * @return void
     */
    public static function error(string $manager, string $message, array $context = []): void
    {
        if (! self::isEnabled()) {
            return;
        }

        self::channel()->error(
            "[mikrotik.{$manager}] {$message}",
            self::buildContext($manager, $context)
        );
    }

    /**
     * Log a critical destructive action at warning level.
     *
     * Use for: kickSession, deleteSecret, reboot, changePassword, runScript.
     * Always logs regardless of configured level — these actions must be auditable.
     *
     * @param  string  $manager    Manager name
     * @param  string  $action     Method name (kickSession, deleteSecret...)
     * @param  string  $target     Target identifier (username, IP, pool name...)
     * @param  string  $router     Router name
     * @return void
     */
    public static function critical(
        string $manager,
        string $action,
        string $target,
        string $router = 'default',
    ): void {
        if (! self::isEnabled()) {
            return;
        }

        self::channel()->warning(
            "[mikrotik.{$manager}] {$action} executed on '{$target}'",
            [
                'manager' => $manager,
                'action' => $action,
                'target' => $target,
                'router' => $router,
            ]
        );
    }

    /**
     * Log a router connection event.
     *
     * @param  string $event   'connected' or 'unreachable' or 'retry'
     * @param  string $host    Router IP
     * @param  int    $port    Router port
     * @param  string $router  Router name
     * @param  array<string, mixed> $extra
     * @return void
     */
    public static function connection(
        string $event,
        string $host,
        int    $port,
        string $router = 'default',
        array  $extra = [],
    ): void {
        if (! self::isEnabled()) {
            return;
        }

        $message = "[mikrotik.connection] {$event} — {$router} ({$host}:{$port})";
        $context = array_merge([
            'event' => $event,
            'host' => $host,
            'port' => $port,
            'router' => $router,
        ], $extra);

        match ($event) {
            'unreachable' => self::channel()->error($message, $context),
            'retry' => self::channel()->warning($message, $context),
            default => self::channel()->info($message, $context),
        };
    }

    // =========================================================
    // Internal helpers
    // =========================================================

    /**
     * Check if logging is enabled in config.
     *
     * @return bool
     */
    protected static function isEnabled(): bool
    {
        return (bool) config('mikrotik.logging.enabled', true);
    }

    /**
     * Get configured log channel.
     *
     * @return \Illuminate\Log\LogManager|\Psr\Log\LoggerInterface
     */
    protected static function channel(): mixed
    {
        $channel = config('mikrotik.logging.channel', 'stack');

        return Log::channel($channel);
    }

    /**
     * Build standardized log context array.
     *
     * @param  string               $manager
     * @param  array<string, mixed> $extra
     * @return array<string, mixed>
     */
    protected static function buildContext(string $manager, array $extra = []): array
    {
        return array_merge(['manager' => $manager], $extra);
    }
}