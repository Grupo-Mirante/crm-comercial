<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BusinessException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth;
use App\Services\AuthService;
use App\Services\Mailer;

/** Entrar, sair, primeiro acesso, alteração e recuperação de senha (RN-71, RN-75, RN-77, RN-78). */
final class AuthController extends Controller
{
    public function loginForm(Request $r): Response
    {
        return $this->view('auth/login', ['titulo' => 'Entrar', 'recuperacaoEmail' => Mailer::configurado()], 'simples');
    }

    public function login(Request $r): Response
    {
        try {
            $u = AuthService::tentarLogin($r->str('login'), (string) $r->input('senha', ''), $r->ip());
        } catch (BusinessException $e) {
            Session::withInput(['login' => $r->str('login')]);
            return $this->voltar('/login', $e->getMessage(), 'error');
        }
        if ((int) $u['trocar_senha'] === 1) {
            return Response::redirect('/primeiro-acesso');
        }
        $destino = (string) Session::get('_destino', '/');
        Session::forget('_destino');
        return Response::redirect(str_starts_with($destino, '/') && !str_starts_with($destino, '//') ? $destino : '/');
    }

    public function sair(Request $r): Response
    {
        AuthService::sair();
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $this->voltar('/login', 'Você saiu do sistema.', 'info');
    }

    public function primeiroAcessoForm(Request $r): Response
    {
        $u = Auth::user();
        if ((int) $u['trocar_senha'] !== 1) {
            return Response::redirect('/');
        }
        return $this->view('auth/senha', ['titulo' => 'Criar senha pessoal', 'obrigatoria' => true, 'acao' => '/primeiro-acesso', 'minimo' => AuthService::minimoPara((int) Auth::id())], 'simples');
    }

    public function primeiroAcesso(Request $r): Response
    {
        AuthService::trocarSenha((int) Auth::id(), null, (string) $r->input('nova_senha', ''), (string) $r->input('confirmacao', ''), true);
        return $this->voltar('/', 'Senha pessoal criada. Bem-vindo(a) ao CRM.');
    }

    public function minhaSenhaForm(Request $r): Response
    {
        return $this->view('auth/senha', ['titulo' => 'Alterar senha', 'obrigatoria' => false, 'acao' => '/minha-senha', 'minimo' => AuthService::minimoPara((int) Auth::id())], 'app');
    }

    public function minhaSenha(Request $r): Response
    {
        AuthService::trocarSenha((int) Auth::id(), (string) $r->input('senha_atual', ''), (string) $r->input('nova_senha', ''), (string) $r->input('confirmacao', ''), false);
        return $this->voltar('/', 'Senha alterada.');
    }

    public function esqueciForm(Request $r): Response
    {
        return $this->view('auth/esqueci', ['titulo' => 'Recuperar senha', 'emailAtivo' => Mailer::configurado()], 'simples');
    }

    public function esqueci(Request $r): Response
    {
        AuthService::solicitarRecuperacao($r->str('email'), $r->ip());
        return $this->voltar('/login', 'Se o e-mail estiver cadastrado, você receberá um link para criar uma nova senha em alguns minutos.', 'info');
    }

    public function redefinirForm(Request $r): Response
    {
        $u = AuthService::usuarioDoToken($r->param('token'));
        if (!$u) {
            return $this->voltar('/esqueci-senha', 'Link inválido ou expirado. Peça um novo.', 'error');
        }
        return $this->view('auth/senha', ['titulo' => 'Nova senha', 'obrigatoria' => true, 'acao' => '/redefinir-senha/' . $r->param('token'), 'nomeUsuario' => $u['nome'], 'minimo' => AuthService::minimoPara((int) $u['id'])], 'simples');
    }

    public function redefinir(Request $r): Response
    {
        AuthService::redefinirComToken($r->param('token'), (string) $r->input('nova_senha', ''), (string) $r->input('confirmacao', ''));
        return $this->voltar('/login', 'Senha redefinida. Entre com a nova senha.');
    }
}
