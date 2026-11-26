<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/**
 * Exige a permissão no servidor (nunca só no JavaScript). Aceita alternativas separadas por "|":
 * "perm:pis.ver_todas|pis.criar".
 */
final class Permissao
{
    public function __construct(private readonly string $permissao)
    {
    }

    public function handle(Request $request): ?Response
    {
        foreach (explode('|', $this->permissao) as $p) {
            if (Auth::can(trim($p))) {
                return null;
            }
        }
        throw new HttpException(403);
    }
}
