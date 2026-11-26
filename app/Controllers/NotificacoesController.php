<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/** Avisos do usuário (aprovações, devoluções, PIs na fila, faturamento). */
final class NotificacoesController extends Controller
{
    public function index(Request $r): Response
    {
        $p = $this->paginar('SELECT id, tipo, titulo, mensagem, link, lida_em, criado_em FROM notificacoes WHERE usuario_id = :u', ['u' => (int) Auth::id()], 'id DESC', $r, 30);
        return $this->view('notificacoes/index', ['titulo' => 'Avisos', 'p' => $p]);
    }

    public function lida(Request $r): Response
    {
        Database::query('UPDATE notificacoes SET lida_em = COALESCE(lida_em, NOW()) WHERE id = ? AND usuario_id = ?', [$this->id($r), (int) Auth::id()]);
        return $this->json(['ok' => true]);
    }

    public function todasLidas(Request $r): Response
    {
        Database::query('UPDATE notificacoes SET lida_em = NOW() WHERE usuario_id = ? AND lida_em IS NULL', [(int) Auth::id()]);
        return $this->voltar('/notificacoes', 'Todos os avisos foram marcados como lidos.');
    }
}
