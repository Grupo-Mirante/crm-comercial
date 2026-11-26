<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Auditoria;
use App\Services\Auth;

/** Telas só para quem ainda não entrou (login, recuperação de senha). */
final class Convidado
{
    public function handle(Request $request): ?Response
    {
        Auditoria::contexto($request->ip(), $request->userAgent());
        return Auth::check() ? Response::redirect('/') : null;
    }
}
