<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AnexoService;
use App\Services\AprovacaoService;
use App\Services\Auth;

/**
 * Aprovações do gestor (RN-41), filas da OPEC e do Financeiro (fluxo de 9 etapas) e anexos da PI.
 */
final class OperacaoController extends Controller
{
    // ---------------------------------------------------------------- aprovações
    public function aprovacoes(Request $r): Response
    {
        $uid = (int) Auth::id();
        $pendentes = [];
        foreach (Database::all(
            "SELECT a.*, s.nome AS solicitante, s.gestor_id, r.estado AS rascunho_estado, p.numero
               FROM pi_aprovacoes a JOIN usuarios s ON s.id = a.solicitante_id LEFT JOIN pi_rascunhos r ON r.id = a.rascunho_id LEFT JOIN pis p ON p.id = a.pi_id
              WHERE a.situacao = 'pendente' ORDER BY a.solicitada_em"
        ) as $a) {
            if (AprovacaoService::podeResponder($a) && (int) $a['solicitante_id'] !== $uid) {
                $a['manuais'] = json_decode((string) $a['precos_manuais'], true) ?: [];
                $pendentes[] = $a;
            }
        }
        $minhas = Database::all(
            'SELECT a.*, p.numero FROM pi_aprovacoes a LEFT JOIN pis p ON p.id = a.pi_id WHERE a.solicitante_id = ? ORDER BY a.id DESC LIMIT 30', [$uid]
        );
        $respondidas = Database::all(
            "SELECT a.*, s.nome AS solicitante, p.numero FROM pi_aprovacoes a JOIN usuarios s ON s.id = a.solicitante_id LEFT JOIN pis p ON p.id = a.pi_id
              WHERE a.aprovador_id = ? ORDER BY a.respondida_em DESC LIMIT 30", [$uid]
        );
        return $this->view('operacao/aprovacoes', ['titulo' => 'Aprovações', 'pendentes' => $pendentes, 'minhas' => $minhas, 'respondidas' => $respondidas,
            'alcada' => \App\Services\Configuracao::decimal('alcada_gestor_desconto_pct'), 'alcadaB' => \App\Services\Configuracao::decimal('alcada_gestor_bonificacao_valor')]);
    }

    public function responder(Request $r): Response
    {
        $aprovar = $r->str('decisao') === 'aprovar';
        AprovacaoService::responder($this->id($r), $aprovar, $r->str('resposta'));
        return $this->voltar('/aprovacoes', $aprovar ? 'Aprovado. O executivo foi avisado e a pendência D06 sai da PI.' : 'Recusado. O executivo foi avisado com o motivo.', $aprovar ? 'success' : 'info');
    }

    // ---------------------------------------------------------------- OPEC e Financeiro
    public function faturamento(Request $r): Response
    {
        [$w, $p] = Auth::filtroPis('p');
        $sql = "SELECT p.id, p.numero, p.situacao, p.cliente_razao, p.valor_liquido, p.data_inicio, p.data_fim, p.data_fim_veiculacao, p.nf_numero, p.nf_valor, p.faturada_em,
                       p.aprovada_em, p.programada_em, v.nome AS veiculo, u.nome AS executivo,
                       (SELECT MIN(pa.vencimento) FROM pi_parcelas pa WHERE pa.pi_id = p.id) AS venc
                  FROM pis p JOIN veiculos v ON v.id = p.veiculo_id JOIN usuarios u ON u.id = p.executivo_id WHERE {$w} AND p.situacao = :s ORDER BY p.data_inicio, p.id";
        $listas = [];
        foreach (['Aprovada', 'Em programação', 'Em veiculação', 'Pronta para faturamento', 'Faturada'] as $s) {
            $listas[$s] = Database::all($sql, $p + ['s' => $s]);
        }
        $div = Database::all("SELECT p.id, p.numero, p.cliente_razao, p.valor_liquido, p.nf_numero, p.nf_valor FROM pis p WHERE {$w} AND p.nf_valor IS NOT NULL AND ABS(p.nf_valor - p.valor_liquido) >= 0.01 ORDER BY p.faturada_em DESC LIMIT 50", $p);
        return $this->view('operacao/faturamento', ['titulo' => 'Operação e faturamento', 'listas' => $listas, 'divergencias' => $div]);
    }

    // ---------------------------------------------------------------- anexos
    public function enviarAnexo(Request $r): Response
    {
        $id = $this->id($r);
        AnexoService::enviar($id, $r->str('tipo'), (array) ($r->files['arquivo'] ?? []));
        return $this->voltar('/pis/' . $id, 'Anexo incluído.');
    }

    public function baixarAnexo(Request $r): Response
    {
        [$path, $a] = AnexoService::abrir($this->id($r));
        $inline = in_array($a['mime'], ['application/pdf', 'image/jpeg', 'image/png'], true);
        return Response::download($path, (string) $a['nome_original'], (string) $a['mime'], false, $inline);
    }

    public function excluirAnexo(Request $r): Response
    {
        $id = $this->id($r);
        [, $a] = AnexoService::abrir($id);
        AnexoService::excluir($id);
        return $this->voltar('/pis/' . $a['entidade_id'], 'Anexo excluído.', 'info');
    }
}
