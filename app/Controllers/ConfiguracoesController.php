<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\Auditoria;
use App\Services\Auth;
use App\Services\Configuracao;
use App\Services\Mailer;

/** Parâmetros administrativos: alçadas, prazos (SLAs), reservas, segurança, notificações e jurídico. */
final class ConfiguracoesController extends Controller
{
    public function index(Request $r): Response
    {
        $grupos = [];
        foreach (Database::all('SELECT * FROM configuracoes WHERE editavel = 1 ORDER BY FIELD(grupo, \'Alçadas\', \'Prazos\', \'Reservas\', \'Financeiro\', \'Jurídico\', \'Segurança\', \'Notificações\', \'Geral\'), chave') as $c) {
            $grupos[$c['grupo']][] = $c;
        }
        return $this->view('configuracoes/index', ['titulo' => 'Configurações', 'grupos' => $grupos, 'smtp' => Mailer::configurado()]);
    }

    public function salvar(Request $r): Response
    {
        $entrada = (array) $r->input('cfg', []);
        $e = [];
        $mudou = [];
        foreach (Database::all('SELECT * FROM configuracoes WHERE editavel = 1') as $c) {
            if (!array_key_exists($c['chave'], $entrada)) {
                continue;
            }
            $v = trim((string) $entrada[$c['chave']]);
            $campo = 'cfg_' . $c['chave'];
            switch ($c['tipo']) {
                case 'inteiro':
                    if ($v !== '' && !preg_match('/^\d+$/', $v)) {
                        $e[$campo] = 'Use um número inteiro.';
                    }
                    break;
                case 'decimal':
                    if ($v !== '') {
                        $n = valor_decimal($v);
                        if ($n < 0) {
                            $e[$campo] = 'Use um número positivo.';
                        }
                        $v = rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
                    }
                    break;
                case 'booleano':
                    $v = $v === '1' ? '1' : '0';
                    break;
                case 'hora':
                    if ($v !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
                        $e[$campo] = 'Hora no formato HH:MM.';
                    }
                    break;
                case 'email':
                    if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                        $e[$campo] = 'E-mail inválido.';
                    }
                    break;
            }
            if (in_array($c['chave'], ['login_max_tentativas', 'senha_tamanho_minimo', 'reserva_validade_horas', 'parcelas_maximo'], true) && $v !== '' && (int) $v < 1) {
                $e[$campo] = 'Precisa ser pelo menos 1.';
            }
            if ($c['chave'] === 'senha_tamanho_minimo' && (int) $v < 8) {
                $e[$campo] = 'O mínimo é 8 caracteres (RN-75).';
            }
            if ($c['chave'] === 'parcelas_maximo' && (int) $v > 60) {
                $e[$campo] = 'No máximo 60 parcelas.';
            }
            if ((string) $c['valor'] !== $v) {
                $mudou[$c['chave']] = [$c['valor'], $v];
            }
        }
        if ($e) {
            throw new ValidationException($e);
        }
        foreach ($mudou as $k => [$antes, $depois]) {
            Configuracao::definir($k, $depois !== '' ? $depois : null, Auth::id());
            Auditoria::registrar('configuracao_alterada', 'configuracao', $k, "Configuração {$k} alterada", ['valor' => $antes], ['valor' => $depois]);
        }
        return $this->voltar('/configuracoes', $mudou ? count($mudou) . ' configuração(ões) alterada(s).' : 'Nenhuma alteração.', $mudou ? 'success' : 'info');
    }
}
