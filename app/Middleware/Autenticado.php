<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Auditoria;
use App\Services\Auth;
use App\Services\Notificacoes;

/**
 * RN-71 — o sistema só abre depois do login. Com senha provisória, a pessoa é levada à criação da senha pessoal
 * antes de qualquer outra tela.
 */
final class Autenticado
{
    public function handle(Request $request): ?Response
    {
        Auditoria::contexto($request->ip(), $request->userAgent());
        $u = Auth::user();
        if (!$u) {
            if ($request->wantsJson()) {
                throw new HttpException(401, 'Sua sessão terminou. Entre novamente.');
            }
            if ($request->method === 'GET') {
                Session::set('_destino', $request->path . ($request->query ? '?' . http_build_query($request->query) : ''));
            }
            return Response::redirect('/login');
        }
        if ((int) $u['trocar_senha'] === 1 && !in_array($request->path, ['/primeiro-acesso', '/sair'], true)) {
            if ($request->wantsJson()) {
                throw new HttpException(403, 'Crie sua senha pessoal antes de continuar.');
            }
            return Response::redirect('/primeiro-acesso');
        }
        View::share('usuario', $u);
        View::share('naoLidas', Notificacoes::naoLidas((int) $u['id']));
        View::share('rotaAtual', $request->path);
        return null;
    }
}
