<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;
use App\Services\PiService;
use App\Services\PiWorkflow;
use App\Services\SlaService;

/**
 * Módulo 4 — Conferência de PI: a porta de entrada da OPEC.
 * Fila de PIs Enviadas/Corrigidas com prazo, e tela de conferência com o checklist de 10 itens:
 * aprova (todos os itens marcados) ou devolve com código(s) D01–D15 e o que corrigir.
 * A Conferência nunca altera os dados comerciais da PI.
 */
final class ConferenciaController extends Controller
{
    public function index(Request $r): Response
    {
        [$w, $p] = Auth::filtroPis('p');
        $fila = Database::all(
            "SELECT p.id, p.numero, p.situacao, p.cliente_razao, p.valor_liquido, p.data_inicio, p.data_fim, p.enviada_em, p.updated_at, p.qtd_devolucoes, p.fora_do_prazo, p.parte,
                    v.nome AS veiculo, u.nome AS executivo,
                    (SELECT MAX(h.criado_em) FROM pi_historico h WHERE h.pi_id = p.id AND h.situacao_nova IN ('Enviada','Corrigida')) AS recebida_em
               FROM pis p JOIN veiculos v ON v.id = p.veiculo_id JOIN usuarios u ON u.id = p.executivo_id
              WHERE {$w} AND p.situacao IN ('Enviada','Corrigida') ORDER BY recebida_em, p.id",
            $p
        );
        foreach ($fila as &$f) {
            $f['prazo'] = SlaService::situacao(SlaService::prazoConferencia((string) ($f['recebida_em'] ?? $f['enviada_em'] ?? $f['updated_at'])));
        }
        unset($f);
        $devolvidas = Database::all(
            "SELECT p.id, p.numero, p.cliente_razao, u.nome AS executivo, d.devolvida_em, d.descricao, d.sequencia,
                    (SELECT GROUP_CONCAT(c.motivo_codigo SEPARATOR ', ') FROM pi_devolucao_codigos c WHERE c.devolucao_id = d.id) AS codigos
               FROM pis p JOIN usuarios u ON u.id = p.executivo_id
               JOIN pi_devolucoes d ON d.pi_id = p.id AND d.corrigida_em IS NULL
              WHERE {$w} AND p.situacao = 'Devolvida' ORDER BY d.devolvida_em",
            $p
        );
        foreach ($devolvidas as &$d) {
            $d['prazo'] = SlaService::situacao(SlaService::prazoCorrecao((string) $d['devolvida_em']));
        }
        unset($d);
        $proximas = Database::all(
            "SELECT p.id, p.numero, p.situacao, p.cliente_razao, p.data_inicio, u.nome AS executivo FROM pis p JOIN usuarios u ON u.id = p.executivo_id
              WHERE {$w} AND p.situacao IN ('Rascunho','Enviada','Devolvida','Corrigida') AND p.data_inicio BETWEEN CURDATE() AND CURDATE() + INTERVAL :d DAY ORDER BY p.data_inicio",
            $p + ['d' => \App\Services\Configuracao::inteiro('sla_alerta_inicio_dias', 5)]
        );
        return $this->view('conferencia/index', ['titulo' => 'Conferência', 'fila' => $fila, 'devolvidas' => $devolvidas, 'proximas' => $proximas]);
    }

    public function ver(Request $r): Response
    {
        $pi = PiService::obter($this->id($r));
        $id = (int) $pi['id'];
        $v = Database::first('SELECT * FROM pi_versoes WHERE pi_id = ? ORDER BY versao DESC LIMIT 1', [$id]);
        $anexos = Database::all("SELECT id, tipo, nome_original, criado_em FROM anexos WHERE entidade = 'pi' AND entidade_id = ? AND excluido_em IS NULL ORDER BY id", [$id]);
        return $this->view('conferencia/ver', [
            'titulo' => 'Conferir ' . $pi['numero'], 'pi' => $pi, 'R' => $v ? json_decode((string) $v['resultado'], true) : [], 'snap' => json_decode((string) $pi['snapshot'], true) ?: [],
            'checklist' => PiWorkflow::checklist(),
            'motivos' => Database::all('SELECT codigo, descricao, exemplo FROM motivos_devolucao WHERE ativo = 1 ORDER BY ordem'),
            'devolucoes' => Database::all("SELECT d.*, u.nome AS por, GROUP_CONCAT(c.motivo_codigo ORDER BY c.motivo_codigo SEPARATOR ', ') AS codigos FROM pi_devolucoes d JOIN usuarios u ON u.id = d.devolvida_por
                                            LEFT JOIN pi_devolucao_codigos c ON c.devolucao_id = d.id WHERE d.pi_id = ? GROUP BY d.id ORDER BY d.sequencia", [$id]),
            'cliente' => $pi['cliente_id'] ? Database::first('SELECT * FROM clientes WHERE id = ?', [(int) $pi['cliente_id']]) : null,
            'agencia' => $pi['agencia_id'] ? Database::first('SELECT * FROM agencias WHERE id = ?', [(int) $pi['agencia_id']]) : null,
            'anexos' => $anexos,
            'podeConferir' => Auth::can('conferencia.conferir') && in_array($pi['situacao'], ['Enviada', 'Corrigida'], true),
            'js' => ['js/conferencia.js'],
        ]);
    }

    public function aprovar(Request $r): Response
    {
        $id = $this->id($r);
        PiWorkflow::aprovar($id, array_map('intval', (array) $r->input('checklist', [])), $r->str('observacao'));
        $prox = $this->proxima($id);
        return $this->voltar($prox ? '/conferencia/' . $prox : '/conferencia', 'PI aprovada na conferência e liberada para Financeiro, OPEC e Marketing.' . ($prox ? ' Abrindo a próxima da fila.' : ''));
    }

    public function devolver(Request $r): Response
    {
        $id = $this->id($r);
        $marcados = array_fill_keys(array_map('intval', (array) $r->input('checklist', [])), true);
        PiWorkflow::devolver($id, (array) $r->input('codigos', []), $r->str('descricao'), $marcados);
        $prox = $this->proxima($id);
        return $this->voltar($prox ? '/conferencia/' . $prox : '/conferencia', 'PI devolvida ao executivo com o(s) código(s) informado(s).' . ($prox ? ' Abrindo a próxima da fila.' : ''), 'info');
    }

    private function proxima(int $atual): ?int
    {
        [$w, $p] = Auth::filtroPis('p');
        $id = Database::value("SELECT p.id FROM pis p WHERE {$w} AND p.situacao IN ('Enviada','Corrigida') AND p.id <> :atual ORDER BY p.updated_at, p.id LIMIT 1", $p + ['atual' => $atual]);
        return $id ? (int) $id : null;
    }
}
