<?php

declare(strict_types=1);

/*
 * Processo auxiliar dos testes de concorrência: cada execução é uma "pessoa" diferente, com conexão própria ao MySQL.
 * Todos esperam o mesmo instante (barreira) e disparam juntos.
 *   php worker.php numero  <veiculo_id> <ano> <barreira>
 *   php worker.php gerar   <usuario_id> <barreira> <estado-json-base64>
 */

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\Env;
use App\Services\Auth;
use App\Services\NumeracaoService;
use App\Services\PiService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

date_default_timezone_set('America/Sao_Paulo');
foreach (['HOST', 'PORT', 'SOCKET', 'USERNAME', 'PASSWORD'] as $k) {
    if (($v = getenv('TEST_DB_' . $k)) !== false) {
        Env::set('DB_' . $k, $v);
    }
}
Env::set('APP_ENV', 'testing');
Env::set('DB_DATABASE', 'crm_grupo_mirante_teste');
Env::set('MAIL_HOST', '');

$modo = $argv[1] ?? '';
$esperar = function (float $barreira): void {
    while (microtime(true) < $barreira) {
        usleep(500);
    }
};

try {
    Database::connection();
    if ($modo === 'numero') {
        [$vid, $ano, $barreira] = [(int) $argv[2], (int) $argv[3], (float) $argv[4]];
        NumeracaoService::prepararSequencia($vid, $ano);
        $esperar($barreira);
        $r = Database::transaction(function () use ($vid, $ano) {
            $n = NumeracaoService::proximo($vid, $ano);
            usleep(150000); // segura a transação aberta para forçar a disputa
            return $n;
        }, 4);
        echo json_encode(['ok' => true, 'numero' => $r['numero']]);
    } elseif ($modo === 'gerar') {
        [$uid, $barreira, $estado] = [(int) $argv[2], (float) $argv[3], json_decode(base64_decode($argv[4]), true)];
        Auth::como($uid);
        $esperar($barreira);
        try {
            $r = PiService::gerar($estado);
            echo json_encode(['ok' => true, 'numero' => $r['pis'][0]['numero'], 'id' => $r['pis'][0]['id']]);
        } catch (BusinessException $e) {
            echo json_encode(['ok' => false, 'codigo' => $e->codigo, 'mensagem' => $e->getMessage()]);
        }
    } else {
        throw new RuntimeException('modo inválido');
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'erro' => get_class($e) . ': ' . $e->getMessage()]);
    exit(1);
}
