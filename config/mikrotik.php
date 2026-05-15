<?php

return [
    'host'     => env('MIKROTIK_HOST', '192.168.88.1'),
    'port'     => env('MIKROTIK_PORT', 8728),
    'username' => env('MIKROTIK_USER', 'admin'),
    'password' => env('MIKROTIK_PASS', ''),
    'timeout'  => env('MIKROTIK_TIMEOUT', 10),
];