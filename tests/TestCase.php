<?php

namespace Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ZillEAli\MikrotikLaravel\MikrotikServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MikrotikServiceProvider::class];
    }
}
