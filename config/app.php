<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'CRM Comercial · Grupo Mirante'),
    'env' => env('APP_ENV', 'production'),          // production | staging | local | testing
    'debug' => (bool) env('APP_DEBUG', false),        // nunca true em produção
    'url' => rtrim((string) env('APP_URL', ''), '/'),
    'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo'),
    'locale' => 'pt_BR',
    'trust_proxy' => (bool) env('APP_TRUST_PROXY', false),
    'session' => [
        'cookie' => env('SESSION_COOKIE', 'crm_mirante'),
        'idle_minutes' => (int) env('SESSION_IDLE_MINUTES', 120),
        'absolute_minutes' => (int) env('SESSION_ABSOLUTE_MINUTES', 720), // RN-77: no máximo 12 horas
    ],
    'uploads' => [
        'max_mb' => (int) env('UPLOAD_MAX_MB', 10),
        'tipos' => ['application/pdf', 'image/jpeg', 'image/png', 'message/rfc822', 'application/vnd.ms-outlook',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain'],
    ],
    'backup' => [
        'dir' => env('BACKUP_DIR', dirname(__DIR__) . '/storage/backups'),
        'retencao_dias' => (int) env('BACKUP_RETENCAO_DIAS', 30),
        'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
        'mysql' => env('MYSQL_PATH', 'mysql'),
    ],
];
