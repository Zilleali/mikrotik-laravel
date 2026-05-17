<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Router Connection
    |--------------------------------------------------------------------------
    |
    | Host, port, credentials for the default router.
    | Used when MikroTik::pppoe() is called without router() selector.
    |
    */

    'host' => env('MIKROTIK_HOST', '192.168.10.1'), // Server IP or hostname
    'port' => (int) env('MIKROTIK_PORT', 8728), // Port number (8728 for non-SSL, 8729 for SSL)
    'username' => env('MIKROTIK_USER', 'test'), // Router username
    'password' => env('MIKROTIK_PASS', 'test'), // Router password
    'timeout' => (int) env('MIKROTIK_TIMEOUT', 10), // Connection timeout in seconds
    'ssl' => env('MIKROTIK_SSL', false), // Use SSL connection (true/false)
    'verify_peer' => env('MIKROTIK_SSL_VERIFY', false), // Verify SSL certificate (true/false)
    'ca_cert_path' => env('MIKROTIK_SSL_CA_CERT', null), // Path to CA certificate file for SSL verification (required if verify_peer=true)
    /*
    |--------------------------------------------------------------------------
    | Retry Mechanism
    |--------------------------------------------------------------------------
    |
    | On connection failure, retry this many times with a delay.
    | retry_delay is in milliseconds.
    |
    */

    'retry_attempts' => (int) env('MIKROTIK_RETRY_ATTEMPTS', 3),
    'retry_delay' => (int) env('MIKROTIK_RETRY_DELAY', 1000),

    /*
    |--------------------------------------------------------------------------
    | Multiple Routers
    |--------------------------------------------------------------------------
    |
    | Named routers for multi-site ISP setups.
    | Access via: MikroTik::router('branch')->pppoe()->getSessions()
    |
    */

    'routers' => [
        'main' => [
            'host' => env('MIKROTIK_MAIN_HOST', '192.168.88.1'),
            'port' => (int) env('MIKROTIK_MAIN_PORT', 8728),
            'username' => env('MIKROTIK_MAIN_USER', 'admin'),
            'password' => env('MIKROTIK_MAIN_PASS', ''),
            'timeout' => 10,
        ],
        'branch' => [
            'host' => env('MIKROTIK_BRANCH_HOST', '10.0.0.1'),
            'port' => (int) env('MIKROTIK_BRANCH_PORT', 8728),
            'username' => env('MIKROTIK_BRANCH_USER', 'admin'),
            'password' => env('MIKROTIK_BRANCH_PASS', ''),
            'timeout' => 10,
        ],
    ],

];