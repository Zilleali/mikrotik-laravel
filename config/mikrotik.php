<?php

return [
    'host'     => env('MIKROTIK_HOST', '192.168.10.1'),
    'port'     => env('MIKROTIK_PORT', 8728),
    'username' => env('MIKROTIK_USER', 'test'),
    'password' => env('MIKROTIK_PASS', 'test'),
    'timeout'  => env('MIKROTIK_TIMEOUT', 10),
];