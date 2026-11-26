<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;

/** Proteção contra CSRF: toda requisição que altera dados precisa do token da sessão. */
final class VerificaCsrf
{
    public function handle(Request $request): void
    {
        $token = $request->header('X-CSRF-Token');
        if ($token === '') {
            $t = $request->input('_token');
            $token = is_string($t) ? $t : '';
        }
        if (!Csrf::valid($token)) {
            throw new HttpException(419);
        }
    }
}
