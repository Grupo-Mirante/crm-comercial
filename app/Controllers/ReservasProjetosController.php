<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;
use App\Services\ProjetoService;
use App\Services\ReservaService;

/** Módulo 7 (Projetos especiais) e Módulo 8 (Reservas de 24 horas). */
final class ReservasProjetosController extends Controller
{
    // ---------------------------------------------------------------- reservas (RN-65 a RN-70)
    public function reservas(Request $r): Response
    {
        ReservaService::expirarVencidas();
        $ativas = ReservaService::ativas();
        foreach ($ativas as &$a) {
            $a['itens'] = Database::all('SELECT tipo, sigla, descricao, quantidade, mes_ref, data_inicio, data_fim FROM reservas_itens WHERE reserva_id = ? ORDER BY id', [(int) $a['id']]);
        }
        unset($a);
        $encerradas = Database::all(
            "SELECT r.*, v.nome AS veiculo, u.nome AS executivo, p.numero FROM reservas r JOIN veiculos v ON v.id = r.veiculo_id JOIN usuarios u ON u.id = r.usuario_id LEFT JOIN pis p ON p.id = r.pi_id
              WHERE r.situacao <> 'ativa' " . (Auth::escopoPis() === 'proprias' ? 'AND r.usuario_id = ' . (int) Auth::id() : '') . ' ORDER BY r.encerrada_em DESC LIMIT 30'
        );
        return $this->view('reservas/index', ['titulo' => 'Reservas de 24 horas', 'ativas' => $ativas, 'encerradas' => $encerradas]);
    }

    public function liberar(Request $r): Response
    {
        ReservaService::liberar($this->id($r));
        return $this->voltar('/reservas', 'Reserva liberada: cotas e espaço voltaram a ficar disponíveis.');
    }

    /** RN-70 — só o dono abre a própria reserva (cria uma PI em montagem com o estado reservado). */
    public function abrir(Request $r): Response
    {
        $res = Database::first("SELECT * FROM reservas WHERE id = ? AND situacao = 'ativa' AND expira_em > NOW()", [$this->id($r)]) ?? throw new HttpException(404, 'Reserva não encontrada ou expirada.');
        if ((int) $res['usuario_id'] !== Auth::id()) {
            throw new HttpException(403, 'Só o dono abre a própria reserva (RN-70).');
        }
        $S = json_decode((string) $res['estado_formulario'], true) ?: [];
        $S['f']['res'] = (int) $res['id'];
        $existente = Database::value("SELECT id FROM pi_rascunhos WHERE usuario_id = ? AND JSON_UNQUOTE(JSON_EXTRACT(estado, '$.f.res')) = ?", [(int) Auth::id(), (string) $res['id']]);
        if ($existente) {
            return Response::redirect('/pis/nova?rascunho=' . $existente);
        }
        $id = \App\Services\RascunhoService::criar($S);
        return Response::redirect('/pis/nova?rascunho=' . $id);
    }

    // ---------------------------------------------------------------- projetos especiais (RN-32 a RN-39, RN-50)
    public function projetos(Request $r): Response
    {
        $vend = ProjetoService::vendidas();
        $res = ProjetoService::reservadas();
        $lista = [];
        foreach (Database::all('SELECT * FROM projetos_especiais WHERE excluido_em IS NULL ORDER BY data_limite_venda DESC, nome') as $P) {
            $P['veiculos'] = array_map('intval', array_column(Database::all('SELECT veiculo_id FROM projetos_empresas WHERE projeto_id = ?', [(int) $P['id']]), 'veiculo_id'));
            $P['cotas'] = Database::all('SELECT * FROM projetos_cotas WHERE projeto_id = ? ORDER BY ordem, id', [(int) $P['id']]);
            $P['st'] = ProjetoService::situacao($P, $vend, $res);
            $P['valor_vendido'] = (float) Database::value(
                "SELECT COALESCE(SUM(i.valor_total), 0) FROM pi_itens i JOIN pis p ON p.id = i.pi_id JOIN projetos_cotas c ON c.id = i.projeto_cota_id
                  WHERE i.bloco = 'projeto' AND c.projeto_id = ? AND p.situacao NOT IN ('Cancelada','Substituída')", [(int) $P['id']]);
            $lista[] = $P;
        }
        $nomes = [];
        foreach (Database::all('SELECT id, nome FROM veiculos ORDER BY ordem') as $v) {
            $nomes[(int) $v['id']] = $v['nome'];
        }
        return $this->view('projetos/index', ['titulo' => 'Projetos especiais', 'projetos' => $lista, 'veiculos' => $nomes]);
    }

    public function projeto(Request $r): Response
    {
        $id = $this->id($r);
        $P = Database::first('SELECT * FROM projetos_especiais WHERE id = ?', [$id]) ?? throw new HttpException(404);
        $P['veiculos'] = array_map('intval', array_column(Database::all('SELECT veiculo_id FROM projetos_empresas WHERE projeto_id = ?', [$id]), 'veiculo_id'));
        $P['cotas'] = Database::all('SELECT * FROM projetos_cotas WHERE projeto_id = ? ORDER BY ordem, id', [$id]);
        $st = ProjetoService::situacao($P, ProjetoService::vendidas(), ProjetoService::reservadas());
        [$w, $p] = Auth::filtroPis('p');
        $vendas = Database::all(
            "SELECT p.id, p.numero, p.situacao, p.cliente_razao, p.data_emissao, u.nome AS executivo, c.nome AS cota, i.quantidade, i.valor_total, i.cobranca
               FROM pi_itens i JOIN pis p ON p.id = i.pi_id JOIN projetos_cotas c ON c.id = i.projeto_cota_id JOIN usuarios u ON u.id = p.executivo_id
              WHERE i.bloco = 'projeto' AND c.projeto_id = :pid AND {$w} ORDER BY p.data_emissao DESC",
            ['pid' => $id] + $p
        );
        $reservas = Database::all(
            "SELECT r.id, r.cliente_nome, r.expira_em, u.nome AS executivo, c.nome AS cota, ri.quantidade FROM reservas_itens ri JOIN reservas r ON r.id = ri.reserva_id
               JOIN projetos_cotas c ON c.id = ri.projeto_cota_id JOIN usuarios u ON u.id = r.usuario_id
              WHERE c.projeto_id = ? AND r.situacao = 'ativa' AND r.expira_em > NOW()", [$id]
        );
        $nomes = [];
        foreach (Database::all('SELECT id, nome FROM veiculos ORDER BY ordem') as $v) {
            $nomes[(int) $v['id']] = $v['nome'];
        }
        return $this->view('projetos/ver', ['titulo' => $P['nome'], 'P' => $P, 'st' => $st, 'vendas' => $vendas, 'reservas' => $reservas, 'veiculos' => $nomes]);
    }

    public function formProjeto(Request $r): Response
    {
        $P = ['veiculos' => [], 'cotas' => [['nome' => 'Ouro'], ['nome' => 'Prata'], ['nome' => 'Bronze']], 'ativo' => 1];
        if ($r->param('id') !== '') {
            $P = Database::first('SELECT * FROM projetos_especiais WHERE id = ? AND excluido_em IS NULL', [$this->id($r)]) ?? throw new HttpException(404);
            $P['veiculos'] = array_map('intval', array_column(Database::all('SELECT veiculo_id FROM projetos_empresas WHERE projeto_id = ?', [(int) $P['id']]), 'veiculo_id'));
            $P['cotas'] = Database::all('SELECT * FROM projetos_cotas WHERE projeto_id = ? ORDER BY ordem, id', [(int) $P['id']]);
        }
        return $this->view('projetos/form', ['titulo' => empty($P['id']) ? 'Novo projeto especial' : 'Editar ' . $P['nome'], 'P' => $P,
            'todos' => Database::all('SELECT id, nome FROM veiculos WHERE ativo = 1 ORDER BY ordem')]);
    }

    public function salvarProjeto(Request $r): Response
    {
        $id = $r->param('id') !== '' ? $this->id($r) : null;
        $dados = $r->all();
        $dados['cotas'] = array_values(array_filter((array) ($dados['cotas'] ?? []), fn ($c) => is_array($c) && trim((string) ($c['nome'] ?? '')) !== ''));
        $novo = ProjetoService::salvar($id, $dados);
        $codigo = (string) Database::value('SELECT codigo FROM projetos_especiais WHERE id = ?', [$novo]);
        return $this->voltar('/projetos/' . $novo, $id ? 'Projeto atualizado.' : "Projeto {$codigo} cadastrado: já aparece nas PIs das empresas marcadas.");
    }

    public function excluirProjeto(Request $r): Response
    {
        ProjetoService::excluir($this->id($r));
        return $this->voltar('/projetos', 'Projeto excluído. As PIs já geradas com ele não mudam (RN-39).', 'info');
    }
}
