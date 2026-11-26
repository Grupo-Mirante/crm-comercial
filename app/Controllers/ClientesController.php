<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;
use App\Services\ClienteService;

/** Módulo 5 — Clientes (RN-61 a RN-64). */
final class ClientesController extends Controller
{
    public function index(Request $r): Response
    {
        $q = $r->str('q');
        $sit = $r->str('situacao');
        $w = ['1=1'];
        $p = [];
        if ($q !== '') {
            $doc = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $q));
            $w[] = '(c.razao_social LIKE :q OR c.nome_fantasia LIKE :q2 OR c.codigo_interno = :q3' . ($doc !== '' ? ' OR c.documento LIKE :d' : '') . ')';
            $p += ['q' => "%{$q}%", 'q2' => "%{$q}%", 'q3' => $q];
            if ($doc !== '') {
                $p['d'] = $doc . '%';
            }
        }
        if (in_array($sit, ['Ativo', 'Em análise', 'Bloqueado', 'Inativo'], true)) {
            $w[] = 'c.situacao_cadastral = :s';
            $p['s'] = $sit;
        }
        [$esc, $pe] = Auth::filtroPis('pp');
        $sql = 'SELECT c.*, (SELECT COUNT(*) FROM pis pp WHERE pp.cliente_id = c.id AND ' . $esc . ') AS qtd_pis FROM clientes c WHERE ' . implode(' AND ', $w);
        $pag = $this->paginar($sql, $p + $pe, 'c.razao_social', $r);
        return $this->view('clientes/index', ['titulo' => 'Clientes', 'p' => $pag, 'q' => $q, 'sit' => $sit]);
    }

    public function novo(Request $r): Response
    {
        return $this->view('clientes/form', ['titulo' => 'Novo cliente', 'c' => ['uf' => 'MA', 'situacao_cadastral' => 'Ativo']]);
    }

    public function criar(Request $r): Response
    {
        $id = ClienteService::salvar(null, $r->all());
        return $this->voltar('/clientes/' . $id, 'Cliente cadastrado.');
    }

    public function ver(Request $r): Response
    {
        $c = $this->cliente($this->id($r));
        [$esc, $pe] = Auth::filtroPis('p');
        $pis = Database::all(
            "SELECT p.id, p.numero, p.data_emissao, p.situacao, p.valor_negociado, p.valor_liquido, p.data_inicio, p.data_fim, v.nome AS veiculo, u.nome AS executivo
               FROM pis p JOIN veiculos v ON v.id = p.veiculo_id JOIN usuarios u ON u.id = p.executivo_id
              WHERE p.cliente_id = :cid AND {$esc} ORDER BY p.data_emissao DESC, p.id DESC LIMIT 200",
            ['cid' => (int) $c['id']] + $pe
        );
        return $this->view('clientes/ver', ['titulo' => $c['razao_social'], 'c' => $c, 'pis' => $pis]);
    }

    public function editar(Request $r): Response
    {
        $c = $this->cliente($this->id($r));
        return $this->view('clientes/form', ['titulo' => 'Editar cliente', 'c' => $c]);
    }

    public function atualizar(Request $r): Response
    {
        $id = $this->id($r);
        $this->cliente($id);
        ClienteService::salvar($id, $r->all());
        return $this->voltar('/clientes/' . $id, 'Dados do cliente atualizados.');
    }

    public function excluir(Request $r): Response
    {
        $res = ClienteService::excluir($this->id($r));
        return $this->voltar('/clientes', $res === 'excluido' ? 'Cliente excluído.' : 'O cliente tem PIs e foi desativado (as PIs continuam com os dados originais).', $res === 'excluido' ? 'success' : 'info');
    }

    /** Busca rápida para formulários (RN-64): razão social, fantasia, CNPJ/CPF ou código. */
    public function buscar(Request $r): Response
    {
        $itens = [];
        foreach (ClienteService::buscar($r->str('q')) as $c) {
            $full = Database::first('SELECT * FROM clientes WHERE id = ?', [(int) $c['id']]);
            $itens[] = [
                'titulo' => $c['razao_social'],
                'sub' => trim($c['documento_formatado'] . ($c['nome_fantasia'] ? ' · ' . $c['nome_fantasia'] : '') . ($c['cidade'] ? ' · ' . $c['cidade'] . '/' . $c['uf'] : '')),
                'dados' => [
                    'id' => (int) $full['id'], 'cli_razao' => $full['razao_social'], 'cli_fant' => $full['nome_fantasia'], 'cli_cnpj' => $full['documento_formatado'],
                    'cli_ie' => $full['inscricao_estadual'], 'cli_end' => $full['endereco'], 'cli_bairro' => $full['bairro'], 'cli_cid' => $full['cidade'], 'cli_uf' => $full['uf'],
                    'cli_cep' => $full['cep'], 'cli_contato' => $full['contato'], 'cli_tel' => $full['telefone'], 'cli_email' => $full['email_faturamento'], 'cli_cod' => $full['codigo_interno'],
                    'situacao' => $full['situacao_cadastral'],
                ],
            ];
        }
        return $this->json(['ok' => true, 'itens' => $itens]);
    }

    /** @return array<string,mixed> */
    private function cliente(int $id): array
    {
        $c = Database::first('SELECT * FROM clientes WHERE id = ?', [$id]);
        if (!$c) {
            throw new HttpException(404, 'Cliente não encontrado.');
        }
        return $c;
    }
}
