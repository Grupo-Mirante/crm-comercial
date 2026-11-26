<?php

declare(strict_types=1);

return [
    // Sem MAIL_HOST, o sistema não envia e-mails: as notificações ficam só dentro do CRM
    // e a recuperação de senha é feita pelo administrador (senha provisória, RN-75).
    'host' => env('MAIL_HOST', ''),
    'port' => (int) env('MAIL_PORT', 587),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'), // tls | ssl | none
    'username' => env('MAIL_USERNAME', ''),
    'password' => env('MAIL_PASSWORD', ''),
    'from_address' => env('MAIL_FROM_ADDRESS', ''),
    'from_name' => env('MAIL_FROM_NAME', 'CRM Grupo Mirante'),
];
