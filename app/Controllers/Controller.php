<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Auth;

/** Base dos controllers: renderização com dados antigos/erros, redirecionamento com aviso, paginação e checagem de permissão. */
abstract class Controller
{
    /** @param array<string,mixed> $dados */
    protected function view(string $view, array $dados = [], ?string $layout = 'app', int $status = 200): Response
    {
        [$old, $errors] = Session::pullInput();
        $GLOBALS['__old'] = $old;
        $GLOBALS['__errors'] = $errors;
        $dados['flash'] = Session::pullFlash();
        return Response::html(View::render($view, $dados, $layout), $status);
    }

    protected function voltar(string $para, string $msg = '', string $tipo = 'success'): Response
    {
        if ($msg !== '') {
            Session::flash($tipo, $msg);
        }
        return Response::redirect($para);
    }

    protected function exigir(string $permissao): void
    {
        if (!Auth::can($permissao)) {
            throw new HttpException(403);
        }
    }

    protected function id(Request $r, string $nome = 'id'): int
    {
        $v = $r->param($nome);
        if (!ctype_digit($v)) {
            throw new HttpException(404);
        }
        return (int) $v;
    }

    /**
     * Paginação simples.
     * @param array<string,mixed> $params
     * @return array{itens:list<array<string,mixed>>,total:int,pagina:int,paginas:int,por:int}
     */
    protected function paginar(string $sqlBase, array $params, string $ordem, Request $r, int $por = 25): array
    {
        $pagina = max(1, $r->int('pagina', 1));
        $total = (int) Database::value("SELECT COUNT(*) FROM ({$sqlBase}) t", $params);
        $paginas = max(1, (int) ceil($total / $por));
        $pagina = min($pagina, $paginas);
        $itens = Database::all("{$sqlBase} ORDER BY {$ordem} LIMIT " . $por . ' OFFSET ' . (($pagina - 1) * $por), $params);
        return ['itens' => $itens, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas, 'por' => $por];
    }

    /** @param array<string,mixed> $dados */
    protected function json(array $dados, int $status = 200): Response
    {
        return Response::json($dados, $status);
    }
}
