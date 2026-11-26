<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Router;
use App\Services\Auth;

/** Monta o menu lateral conforme as permissões do usuário e as telas existentes. */
final class Menu
{
    /** @return list<array{grupo:?string,itens:list<array<string,mixed>>}> */
    public static function montar(string $rotaAtual): array
    {
        $out = [];
        foreach ((array) Config::get('menu') as $g) {
            $itens = [];
            foreach ($g['itens'] as $it) {
                if (!in_array($it['url'], Router::$caminhos, true)) {
                    continue;
                }
                $ok = false;
                foreach (explode('|', $it['perm']) as $p) {
                    $ok = $ok || Auth::can($p);
                }
                if (!$ok) {
                    continue;
                }
                $it['ativo'] = $it['url'] === '/' ? $rotaAtual === '/' : ($rotaAtual === $it['url'] || str_starts_with($rotaAtual, $it['url'] . '/'));
                if ($it['url'] === '/pis' && str_starts_with($rotaAtual, '/pis/nova')) {
                    $it['ativo'] = false;
                }
                $it['n'] = isset($it['contador']) ? self::contador((string) $it['contador']) : 0;
                $itens[] = $it;
            }
            if ($itens) {
                $out[] = ['grupo' => $g['grupo'], 'itens' => $itens];
            }
        }
        return $out;
    }

    private static function contador(string $tipo): int
    {
        try {
            return match ($tipo) {
                'conferencia' => Auth::can('conferencia.conferir')
                    ? (int) Database::value("SELECT COUNT(*) FROM pis WHERE situacao IN ('Enviada','Corrigida')") : 0,
                'aprovacoes' => self::aprovacoesPendentes(),
                default => 0,
            };
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Mesmo critério da tela Aprovações: o que a pessoa pode decidir, sem os próprios pedidos (quem pede não aprova). */
    public static function aprovacoesPendentes(): int
    {
        $uid = (int) Auth::id();
        if (Auth::can('aprovacoes.aprovar_diretoria')) {
            return (int) Database::value("SELECT COUNT(*) FROM pi_aprovacoes WHERE situacao = 'pendente' AND solicitante_id <> ?", [$uid]);
        }
        if (Auth::can('aprovacoes.aprovar')) {
            return (int) Database::value("SELECT COUNT(*) FROM pi_aprovacoes a JOIN usuarios u ON u.id = a.solicitante_id WHERE a.situacao = 'pendente' AND a.tipo = 'comercial' AND u.gestor_id = ? AND a.solicitante_id <> ?", [$uid, $uid]);
        }
        return 0;
    }
}
