<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;

/**
 * Usuário autenticado e suas permissões. O usuário é relido do banco a cada requisição:
 * desativar um usuário ou mudar o perfil vale na hora (RN-77).
 * O perfil Administrador tem sempre todas as permissões (não pode ser trancado para fora do sistema).
 */
final class Auth
{
    /** @var array<string,mixed>|null|false */
    private static array|null|false $user = false;
    /** @var array<string,true>|null */
    private static ?array $perms = null;

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$user === false) {
            self::$user = null;
            $id = Session::get('usuario_id');
            if ($id) {
                $u = Database::first(
                    'SELECT u.id, u.nome, u.login, u.email, u.matricula, u.telefone, u.perfil_id, u.gestor_id, u.ativo, u.trocar_senha,
                            u.ultimo_acesso_em, p.codigo AS perfil, p.nome AS perfil_nome, g.nome AS gestor_nome
                       FROM usuarios u JOIN perfis p ON p.id = u.perfil_id LEFT JOIN usuarios g ON g.id = u.gestor_id
                      WHERE u.id = ?',
                    [(int) $id]
                );
                if ($u && (int) $u['ativo'] === 1 && (int) Session::get('usuario_versao', 0) === self::versao((int) $u['id'])) {
                    self::$user = $u;
                } else {
                    Session::destroy();
                }
            }
        }
        return self::$user ?: null;
    }

    /** Muda quando a senha é trocada/redefinida: sessões antigas deixam de valer. */
    public static function versao(int $usuarioId): int
    {
        $v = Database::value('SELECT UNIX_TIMESTAMP(COALESCE(senha_alterada_em, created_at)) FROM usuarios WHERE id = ?', [$usuarioId]);
        return (int) $v;
    }

    public static function id(): ?int
    {
        try {
            $u = self::user();
        } catch (\Throwable) {
            return null;
        }
        return $u ? (int) $u['id'] : null;
    }

    public static function check(): bool
    {
        try {
            return self::user() !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function perfil(): ?string
    {
        return self::user()['perfil'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::perfil() === 'admin';
    }

    public static function can(string $permissao): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        if ($u['perfil'] === 'admin') {
            return true;
        }
        if (self::$perms === null) {
            self::$perms = [];
            foreach (Database::all(
                'SELECT CONCAT(m.modulo, \'.\', m.acao) AS k FROM perfil_permissoes pp JOIN permissoes m ON m.id = pp.permissao_id WHERE pp.perfil_id = ?',
                [(int) $u['perfil_id']]
            ) as $r) {
                self::$perms[$r['k']] = true;
            }
        }
        return isset(self::$perms[$permissao]);
    }

    /**
     * RN-73 — escopo de PIs: 'todas' (diretor, administrador, Conferência, Financeiro),
     * 'equipe' (gerente: as suas e as da equipe) ou 'proprias' (executivo e vendedor).
     */
    public static function escopoPis(): string
    {
        if (self::can('pis.ver_todas')) {
            return 'todas';
        }
        return self::can('pis.ver_equipe') ? 'equipe' : 'proprias';
    }

    /** IDs dos usuários que têm o usuário atual como gestor (equipe direta). @return list<int> */
    public static function equipe(?int $gestorId = null): array
    {
        $gid = $gestorId ?? self::id();
        if (!$gid) {
            return [];
        }
        return array_map('intval', array_column(Database::all('SELECT id FROM usuarios WHERE gestor_id = ?', [$gid]), 'id'));
    }

    /**
     * Condição SQL para limitar PIs ao escopo do usuário.
     * @return array{0:string,1:array<string,mixed>}
     */
    public static function filtroPis(string $alias = 'p'): array
    {
        $escopo = self::escopoPis();
        $uid = (int) self::id();
        if ($escopo === 'todas') {
            return ['1=1', []];
        }
        if ($escopo === 'equipe') {
            return ["({$alias}.executivo_id = :esc_uid OR {$alias}.executivo_id IN (SELECT id FROM usuarios WHERE gestor_id = :esc_gid) OR {$alias}.gestor_id = :esc_gid2)", ['esc_uid' => $uid, 'esc_gid' => $uid, 'esc_gid2' => $uid]];
        }
        return ["{$alias}.executivo_id = :esc_uid", ['esc_uid' => $uid]];
    }

    /** @param array<string,mixed> $pi */
    public static function podeVerPi(array $pi): bool
    {
        $escopo = self::escopoPis();
        $uid = (int) self::id();
        if ($escopo === 'todas') {
            return true;
        }
        if ((int) $pi['executivo_id'] === $uid) {
            return true;
        }
        if ($escopo === 'equipe') {
            return (int) ($pi['gestor_id'] ?? 0) === $uid || in_array((int) $pi['executivo_id'], self::equipe(), true);
        }
        return false;
    }

    /** @param array<string,mixed> $usuario */
    public static function entrar(array $usuario): void
    {
        Session::regenerate();
        Session::set('usuario_id', (int) $usuario['id']);
        Session::set('usuario_versao', self::versao((int) $usuario['id']));
        self::reset();
    }

    public static function sair(): void
    {
        Session::destroy();
        self::reset();
    }

    /** Limpa o cache da requisição (uso interno e testes). */
    public static function reset(): void
    {
        self::$user = false;
        self::$perms = null;
    }

    /** Define o usuário atual sem sessão (rotinas de linha de comando e testes). */
    public static function como(?int $usuarioId): void
    {
        self::reset();
        if ($usuarioId === null) {
            self::$user = null;
            return;
        }
        self::$user = Database::first(
            'SELECT u.id, u.nome, u.login, u.email, u.matricula, u.telefone, u.perfil_id, u.gestor_id, u.ativo, u.trocar_senha,
                    u.ultimo_acesso_em, p.codigo AS perfil, p.nome AS perfil_nome, g.nome AS gestor_nome
               FROM usuarios u JOIN perfis p ON p.id = u.perfil_id LEFT JOIN usuarios g ON g.id = u.gestor_id WHERE u.id = ?',
            [$usuarioId]
        );
    }
}
