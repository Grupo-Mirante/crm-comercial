<?php

declare(strict_types=1);

/*
 * Instalador do CRM pelo terminal do servidor. Na pasta do CRM, rode:
 *
 *     php instalar.php
 *
 * Faz o mesmo que o assistente web (/instalar): confere o servidor, conecta ao MySQL, grava o .env,
 * cria as tabelas e os dados iniciais de produção, cria o primeiro administrador e trava a instalação.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$argv = [__DIR__ . '/bin/console.php', 'instalar'];
$GLOBALS['argv'] = $argv;
require __DIR__ . '/bin/console.php';
