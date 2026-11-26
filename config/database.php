<?php

declare(strict_types=1);

return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'socket' => env('DB_SOCKET', ''),
    'database' => env('DB_DATABASE', 'crm_grupo_mirante'),
    'username' => env('DB_USERNAME', ''),
    'password' => env('DB_PASSWORD', ''),
];
