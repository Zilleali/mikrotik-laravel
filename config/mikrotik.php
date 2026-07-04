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
    | Socket Options
    |--------------------------------------------------------------------------
    |
    | socket_timeout     — How long to wait for a response word from the router (seconds).
    | socket_blocking    — Whether the socket runs in blocking mode. Set false only for
    |                      health-check use cases with a very short socket_timeout.
    | throw_timeout_exception — If true, a socket timeout throws ConnectionException.
    |                           If false, timeout returns an empty result gracefully.
    |
    */

    'socket_timeout' => (int) env('MIKROTIK_SOCKET_TIMEOUT', 30),
    'socket_blocking' => (bool) env('MIKROTIK_SOCKET_BLOCKING', true),
    'throw_timeout_exception' => (bool) env('MIKROTIK_THROW_TIMEOUT_EXCEPTION', true),

    /*
    |--------------------------------------------------------------------------
    | SSH Export Options
    |--------------------------------------------------------------------------
    |
    | Used by ExportManager to fetch router config via SSH (/export command).
    | Requires SSH service enabled on the router and an SSH private key.
    |
    */

    'ssh_port' => (int) env('MIKROTIK_SSH_PORT', 22),
    'ssh_private_key' => env('MIKROTIK_SSH_KEY', '~/.ssh/id_rsa'),
    'ssh_timeout' => (int) env('MIKROTIK_SSH_TIMEOUT', 30),

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