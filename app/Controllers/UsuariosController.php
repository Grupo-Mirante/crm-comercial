<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auditoria;
use App\Services\UsuarioService;

/** Módulo 9 — Usuários (RN-72 a RN-77): cadastro, ativação, senha provisória, gestor, último acesso e atividades. */
final class UsuariosController extends Controller
{
    public function index(Request $r): Response
    {
        $q = $r->str('q');
        $perfil = $r->int('perfil');
        $sit = $r->str('situacao', 'ativos');
        $w = ['1=1'];
        $p = [];
        if ($q !== '') {
            $w[] = '(u.nome LIKE :q OR u.login LIKE :q2 OR u.email LIKE :q3 OR u.matricula = :q4)';
            $p += ['q' => "%{$q}%", 'q2' => "%{$q}%", 'q3' => "%{$q}%", 'q4' => $q];
        }
        if ($perfil) {
            $w[] = 'u.perfil_id = :p';
            $p['p'] = $perfil;
        }
        if ($sit === 'ativos') {
            $w[] = 'u.ativo = 1';
        } elseif ($sit === 'inativos') {
            $w[] = 'u.ativo = 0';
        }
        $sql = 'SELECT u.id, u.perfil_id, u.nome, u.login, u.email, u.matricula, u.telefone, u.ativo, u.ultimo_acesso_em, u.trocar_senha, u.bloqueado_ate, p.nome AS perfil, g.nome AS gestor
                  FROM usuarios u JOIN perfis p ON p.id = u.perfil_id LEFT JOIN usuarios g ON g.id = u.gestor_id WHERE ' . implode(' AND ', $w);
        return $this->view('usuarios/index', [
            'titulo' => 'Usuários', 'p' => $this->paginar($sql, $p, 'u.nome', $r, 30), 'q' => $q, 'perfil' => $perfil, 'sit' => $sit,
            'perfis' => Database::all('SELECT id, nome FROM perfis ORDER BY ordem'),
        ]);
    }

    public function novo(Request $r): Response
    {
        return $this->view('usuarios/form', ['titulo' => 'Novo usuário', 'u' => [], 'perfis' => $this->perfis(), 'gestores' => $this->gestores(null)]);
    }

    public function criar(Request $r): Response
    {
        $res = UsuarioService::criar($r->all());
        Session::set('_senha_unica', ['id' => $res['id'], 'senha' => $res['senha']]);
        return $this->voltar('/usuarios/' . $res['id'], 'Usuário cadastrado. Anote a senha provisória: ela aparece só agora.');
    }

    public function ver(Request $r): Response
    {
        $u = $this->usuario($this->id($r));
        $senha = Session::get('_senha_unica');
        Session::forget('_senha_unica');
        $senhaUnica = is_array($senha) && (int) $senha['id'] === (int) $u['id'] ? (string) $senha['senha'] : null;
        return $this->view('usuarios/ver', [
            'titulo' => $u['nome'], 'u' => $u, 'senhaUnica' => $senhaUnica, 'semRedefinir' => UsuarioService::motivoSemRedefinirSenha($u),
            'equipe' => Database::all('SELECT id, nome, login, ativo FROM usuarios WHERE gestor_id = ? ORDER BY nome', [(int) $u['id']]),
            'atividades' => Database::all('SELECT acao, entidade, entidade_id, descricao, ip, criado_em FROM logs_auditoria WHERE usuario_id = ? ORDER BY id DESC LIMIT 40', [(int) $u['id']]),
            'sessoes' => Database::all('SELECT ip, user_agent, FROM_UNIXTIME(criada_em) AS criada, FROM_UNIXTIME(ultimo_uso) AS uso FROM sessoes WHERE usuario_id = ? AND ultimo_uso > UNIX_TIMESTAMP() - 43200 ORDER BY ultimo_uso DESC', [(int) $u['id']]),
            'tentativas' => Database::all('SELECT ip, sucesso, motivo, criado_em FROM login_tentativas WHERE login = ? ORDER BY id DESC LIMIT 10', [$u['login']]),
        ]);
    }

    public function editar(Request $r): Response
    {
        $u = $this->usuario($this->id($r));
        return $this->view('usuarios/form', ['titulo' => 'Editar usuário', 'u' => $u, 'semRedefinir' => UsuarioService::motivoSemRedefinirSenha($u), 'perfis' => $this->perfis(), 'gestores' => $this->gestores((int) $u['id'])]);
    }

    public function atualizar(Request $r): Response
    {
        $id = $this->id($r);
        $this->usuario($id);
        UsuarioService::atualizar($id, $r->all());
        return $this->voltar('/usuarios/' . $id, 'Usuário atualizado.');
    }

    public function ativo(Request $r): Response
    {
        $id = $this->id($r);
        $ativar = $r->str('ativo') === '1';
        UsuarioService::definirAtivo($id, $ativar);
        return $this->voltar('/usuarios/' . $id, $ativar ? 'Usuário ativado.' : 'Usuário desativado: ele não entra mais no sistema e as sessões abertas foram encerradas.');
    }

    public function redefinirSenha(Request $r): Response
    {
        $id = $this->id($r);
        $senha = UsuarioService::redefinirSenha($id);
        Session::set('_senha_unica', ['id' => $id, 'senha' => $senha]);
        return $this->voltar('/usuarios/' . $id, 'Senha provisória gerada. Anote agora: ela aparece só desta vez. No próximo acesso a pessoa cria a senha pessoal.');
    }

    public function encerrarSessoes(Request $r): Response
    {
        $id = $this->id($r);
        $n = Database::query('DELETE FROM sessoes WHERE usuario_id = ?', [$id])->rowCount();
        Auditoria::registrar('sessoes_encerradas', 'usuario', $id, "{$n} sessão(ões) encerrada(s) pelo administrador");
        return $this->voltar('/usuarios/' . $id, "{$n} sessão(ões) encerrada(s).");
    }

    /** @return array<string,mixed> */
    private function usuario(int $id): array
    {
        $u = Database::first(
            'SELECT u.*, p.nome AS perfil, p.codigo AS perfil_codigo, g.nome AS gestor FROM usuarios u JOIN perfis p ON p.id = u.perfil_id LEFT JOIN usuarios g ON g.id = u.gestor_id WHERE u.id = ?',
            [$id]
        );
        if (!$u) {
            throw new HttpException(404, 'Usuário não encontrado.');
        }
        return $u;
    }

    /** @return array<int,string> */
    private function perfis(): array
    {
        $o = [];
        foreach (Database::all('SELECT id, nome FROM perfis ORDER BY ordem') as $p) {
            $o[(int) $p['id']] = $p['nome'];
        }
        return $o;
    }

    /** @return array<int,string> gerentes, diretores e administradores ativos */
    private function gestores(?int $exceto): array
    {
        $o = [];
        foreach (Database::all("SELECT u.id, u.nome, p.nome AS perfil FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE u.ativo = 1 AND p.codigo IN ('gerente','diretor','admin') AND u.id <> ? ORDER BY u.nome", [$exceto ?? 0]) as $g) {
            $o[(int) $g['id']] = $g['nome'] . ' (' . $g['perfil'] . ')';
        }
        return $o;
    }
}
