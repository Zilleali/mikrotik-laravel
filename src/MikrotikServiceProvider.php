<?php

namespace ZillEAli\MikrotikLaravel;

use Illuminate\Support\ServiceProvider;
use ZillEAli\MikrotikLaravel\Commands\MikrotikMonitor;
use ZillEAli\MikrotikLaravel\Commands\MikrotikPing;
use ZillEAli\MikrotikLaravel\Commands\MikrotikSync;

/**
 * MikrotikServiceProvider
 *
 * Registers all MikroTik services into the Laravel container
 * and publishes the config file.
 *
 * Auto-discovered via composer.json extra.laravel.providers.
 *
 * @package ZillEAli\MikrotikLaravel
 * @author  Zill E Ali <zilleali1245@gmail.com>
 * @link    https://zilleali.com
 */
class MikrotikServiceProvider extends ServiceProvider
{
    /**
     * Register all package services into the container.
     *
     * Binds MikrotikManager as a singleton so one connection
     * is reused across the request lifecycle.
     */
    public function register(): void
    {
        // Merge package config with published config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mikrotik.php',
            'mikrotik'
        );

        // Bind the main manager as singleton
        $this->app->singleton(MikrotikManager::class, function ($app) {
            return new MikrotikManager($app['config']['mikrotik']);
        });

        // Alias for Facade
        $this->app->alias(MikrotikManager::class, 'mikrotik');
    }

    /**
     * Bootstrap package services.
     *
     * Publishes config file when running:
     * php artisan vendor:publish --tag=mikrotik-config
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/mikrotik.php' => config_path('mikrotik.php'),
            ], 'mikrotik-config');

            // Register artisan commands
            $this->commands([
                MikrotikPing::class,
                MikrotikSync::class,
                MikrotikMonitor::class,
            ]);
        }
    }

    /**
     * Services provided by this provider.
     *
     * @return string[]
     */
    public function provides(): array
    {
        return [
            MikrotikManager::class,
            'mikrotik',
        ];
    }
}
