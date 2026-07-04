<?php

namespace ZillEAli\MikrotikLaravel;

use Illuminate\Support\ServiceProvider;
use ZillEAli\MikrotikLaravel\Commands\MikrotikMonitor;
use ZillEAli\MikrotikLaravel\Commands\MikrotikPing;
use ZillEAli\MikrotikLaravel\Commands\MikrotikSync;
use ZillEAli\MikrotikLaravel\Pulse\Livewire\RouterHealthCard;

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
        // Load package views — required for Pulse card's view() call
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mikrotik-laravel');

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

        // Filament widgets publish — only if Filament installed
        if (class_exists(\Filament\FilamentServiceProvider::class)) {
            $this->publishes([
                __DIR__ . '/../resources/views/filament' => resource_path('views/vendor/mikrotik-laravel/filament'),
            ], 'mikrotik-filament-views');
        }

        // Laravel Pulse card — only if Pulse is installed
        if (class_exists(\Laravel\Pulse\PulseServiceProvider::class)) {
            // Defer Livewire component registration until Livewire is resolved
            $this->callAfterResolving('livewire', function () {
                \Livewire\Livewire::component('pulse.mikrotik-router-health', RouterHealthCard::class);
            });

            $this->publishes([
                __DIR__ . '/../resources/views/pulse' => resource_path('views/vendor/mikrotik-laravel/pulse'),
            ], 'mikrotik-pulse-views');
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
