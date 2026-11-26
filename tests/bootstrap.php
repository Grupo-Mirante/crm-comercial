<?php

declare(strict_types=1);

/*
 * Prepara o banco de TESTE do zero a cada execução: apaga e recria crm_grupo_mirante_teste,
 * aplica as migrations, os dados de produção (catálogo, perfis, configurações) e os dados fictícios de teste.
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Services\Instalacao\Migrador;
use App\Services\Instalacao\SeedTeste;

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('America/Sao_Paulo');
foreach (['HOST', 'PORT', 'SOCKET', 'USERNAME', 'PASSWORD'] as $k) {
    $v = getenv('TEST_DB_' . $k);
    if ($v !== false) {
        putenv("DB_{$k}={$v}");
        Env::set('DB_' . $k, $v);
    }
}
foreach (['APP_ENV', 'APP_DEBUG', 'APP_URL', 'DB_DATABASE', 'MAIL_HOST'] as $k) {
    if (getenv($k) !== false) {
        Env::set($k, (string) getenv($k));
    }
}
$db = (string) Config::get('database.database');
if (!str_ends_with($db, '_teste')) {
    fwrite(STDERR, "Os testes só rodam em banco terminado em _teste (atual: {$db}).\n");
    exit(1);
}
if (getenv('CRM_TEST_WORKER') !== '1') {
    $cfg = Config::get('database');
    $pdo = Database::connect($cfg, false);
    $pdo->exec("DROP DATABASE IF EXISTS `{$db}`");
    $pdo->exec("CREATE DATABASE `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $pdo->exec("USE `{$db}`");
    (new Migrador($pdo))->migrar(true);
    Database::setConnection(null);
    SeedTeste::executar();
}
