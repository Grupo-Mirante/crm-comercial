<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AgenciaService;
use App\Services\Auth;

/** Módulo 6 — Agências (RN-42, RN-79, RN-80). */
final class AgenciasController extends Controller
{
    public function index(Request $r): Response
    {
        $q = $r->str('q');
        $carta = $r->str('carta');
        $w = ['1=1'];
        $p = [];
        if ($q !== '') {
            $doc = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $q));
            $w[] = '(a.razao_social LIKE :q' . ($doc !== '' ? ' OR a.cnpj LIKE :d' : '') . ')';
            $p['q'] = "%{$q}%";
            if ($doc !== '') {
                $p['d'] = $doc . '%';
            }
        }
        if ($carta === 'vencida') {
            $w[] = '(a.carta_validade IS NOT NULL AND a.carta_validade < CURDATE())';
        } elseif ($carta === 'vence30') {
            $w[] = 'a.carta_validade BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY';
        }
        [$esc, $pe] = Auth::filtroPis('pp');
        $sql = 'SELECT a.*, (SELECT COUNT(*) FROM pis pp WHERE pp.agencia_id = a.id AND ' . $esc . ') AS qtd_pis FROM agencias a WHERE ' . implode(' AND ', $w);
        return $this->view('agencias/index', ['titulo' => 'Agências', 'p' => $this->paginar($sql, $p + $pe, 'a.razao_social', $r), 'q' => $q, 'carta' => $carta]);
    }

    public function nova(Request $r): Response
    {
        return $this->view('agencias/form', ['titulo' => 'Nova agência', 'a' => ['uf' => 'MA']]);
    }

    public function criar(Request $r): Response
    {
        $id = AgenciaService::salvar(null, $r->all());
        return $this->voltar('/agencias/' . $id, 'Agência cadastrada.');
    }

    public function ver(Request $r): Response
    {
        $a = $this->agencia($this->id($r));
        [$esc, $pe] = Auth::filtroPis('p');
        $pis = Database::all(
            "SELECT p.id, p.numero, p.data_emissao, p.situacao, p.cliente_razao, p.valor_negociado, p.valor_comissao, p.comissao_pct, v.nome AS veiculo
               FROM pis p JOIN veiculos v ON v.id = p.veiculo_id WHERE p.agencia_id = :aid AND {$esc} ORDER BY p.data_emissao DESC, p.id DESC LIMIT 200",
            ['aid' => (int) $a['id']] + $pe
        );
        return $this->view('agencias/ver', ['titulo' => $a['razao_social'], 'a' => $a, 'pis' => $pis]);
    }

    public function editar(Request $r): Response
    {
        return $this->view('agencias/form', ['titulo' => 'Editar agência', 'a' => $this->agencia($this->id($r))]);
    }

    public function atualizar(Request $r): Response
    {
        $id = $this->id($r);
        $this->agencia($id);
        AgenciaService::salvar($id, $r->all());
        return $this->voltar('/agencias/' . $id, 'Dados da agência atualizados.');
    }

    public function excluir(Request $r): Response
    {
        $res = AgenciaService::excluir($this->id($r));
        return $this->voltar('/agencias', $res === 'excluida' ? 'Agência excluída.' : 'A agência tem PIs e foi desativada.', $res === 'excluida' ? 'success' : 'info');
    }

    public function buscar(Request $r): Response
    {
        $itens = [];
        foreach (AgenciaService::buscar($r->str('q')) as $a) {
            $vencida = $a['carta_validade'] && $a['carta_validade'] < date('Y-m-d');
            $itens[] = [
                'titulo' => $a['razao_social'],
                'sub' => $a['cnpj_formatado'] . ' · comissão ' . number_format((float) $a['comissao_padrao'], 2, ',', '.') . '%' . ($vencida ? ' · CARTA VENCIDA' : ''),
                'dados' => [
                    'id' => (int) $a['id'], 'ag_nome' => $a['razao_social'], 'ag_cnpj' => $a['cnpj_formatado'], 'ag_com' => (float) $a['comissao_padrao'],
                    'ag_carta' => $a['carta_numero'], 'ag_val' => $a['carta_validade'], 'ag_end' => $a['endereco'],
                    'ag_cid' => trim(($a['cidade'] ?? '') . ($a['uf'] ? ' / ' . $a['uf'] : '')), 'ag_tel' => $a['telefone'],
                    'ag_contato' => trim(($a['contato'] ?? '') . ($a['email'] ? ' · ' . $a['email'] : '')), 'carta_vencida' => $vencida,
                ],
            ];
        }
        return $this->json(['ok' => true, 'itens' => $itens]);
    }

    /** @return array<string,mixed> */
    private function agencia(int $id): array
    {
        $a = Database::first('SELECT * FROM agencias WHERE id = ?', [$id]);
        if (!$a) {
            throw new HttpException(404, 'Agência não encontrada.');
        }
        return $a;
    }
}
