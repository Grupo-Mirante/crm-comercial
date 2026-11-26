<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auditoria;

/**
 * RN-72 — matriz de permissões por perfil, editável por módulo e ação.
 * O perfil Administrador tem sempre acesso total e não aparece para edição (evita trancar o sistema).
 */
final class PermissoesController extends Controller
{
    public const MODULOS = [
        'dashboard' => 'Painel', 'pis' => 'PIs', 'conferencia' => 'Conferência', 'operacao' => 'Operação (OPEC)', 'financeiro' => 'Financeiro',
        'aprovacoes' => 'Aprovações', 'clientes' => 'Clientes', 'agencias' => 'Agências', 'projetos' => 'Projetos especiais', 'reservas' => 'Reservas',
        'relatorios' => 'Relatórios', 'usuarios' => 'Usuários', 'permissoes' => 'Permissões', 'cadastros' => 'Tabelas e veículos',
        'configuracoes' => 'Configurações', 'auditoria' => 'Auditoria', 'backup' => 'Backup',
    ];

    public function index(Request $r): Response
    {
        $perfis = Database::all("SELECT id, codigo, nome, descricao FROM perfis WHERE codigo <> 'admin' ORDER BY ordem");
        $perms = Database::all('SELECT id, modulo, acao, descricao FROM permissoes ORDER BY ordem, id');
        $marcadas = [];
        foreach (Database::all('SELECT perfil_id, permissao_id FROM perfil_permissoes') as $pp) {
            $marcadas[(int) $pp['perfil_id']][(int) $pp['permissao_id']] = true;
        }
        $porModulo = [];
        foreach ($perms as $p) {
            $porModulo[$p['modulo']][] = $p;
        }
        return $this->view('permissoes/index', ['titulo' => 'Permissões por perfil', 'perfis' => $perfis, 'porModulo' => $porModulo, 'marcadas' => $marcadas]);
    }

    public function salvar(Request $r): Response
    {
        $entrada = (array) $r->input('perm', []);
        $perfis = Database::all("SELECT id, nome FROM perfis WHERE codigo <> 'admin'");
        $validas = array_map('intval', array_column(Database::all('SELECT id FROM permissoes'), 'id'));
        $nomes = [];
        foreach (Database::all('SELECT id, CONCAT(modulo, \'.\', acao) k FROM permissoes') as $p) {
            $nomes[(int) $p['id']] = $p['k'];
        }
        Database::transaction(function () use ($perfis, $entrada, $validas, $nomes) {
            foreach ($perfis as $pf) {
                $pid = (int) $pf['id'];
                $antes = array_map('intval', array_column(Database::all('SELECT permissao_id FROM perfil_permissoes WHERE perfil_id = ?', [$pid]), 'permissao_id'));
                $novas = array_values(array_intersect($validas, array_map('intval', (array) ($entrada[$pid] ?? []))));
                sort($antes);
                sort($novas);
                if ($antes === $novas) {
                    continue;
                }
                Database::query('DELETE FROM perfil_permissoes WHERE perfil_id = ?', [$pid]);
                foreach ($novas as $n) {
                    Database::insert('perfil_permissoes', ['perfil_id' => $pid, 'permissao_id' => $n]);
                }
                Auditoria::registrar('permissoes_alteradas', 'perfil', $pid, 'Permissões do perfil ' . $pf['nome'] . ' alteradas',
                    ['retiradas' => array_values(array_map(fn ($i) => $nomes[$i] ?? $i, array_diff($antes, $novas)))],
                    ['incluidas' => array_values(array_map(fn ($i) => $nomes[$i] ?? $i, array_diff($novas, $antes)))]);
            }
        });
        return $this->voltar('/permissoes', 'Permissões salvas. Valem a partir da próxima tela que cada pessoa abrir.');
    }
}
