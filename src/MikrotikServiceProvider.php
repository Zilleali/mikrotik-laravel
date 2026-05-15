<?php

namespace ZillEAli\MikrotikLaravel;

use Illuminate\Support\ServiceProvider;

class MikrotikServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mikrotik.php',
            'mikrotik'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/mikrotik.php' => config_path('mikrotik.php'),
        ], 'mikrotik-config');
    }
}