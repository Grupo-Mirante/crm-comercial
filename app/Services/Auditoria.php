<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Log de auditoria (somente inserção): login, alterações de PI, valores, aprovações, permissões e cadastros.
 * Guarda os dados antes e depois da alteração, o usuário, o IP e o navegador.
 */
final class Auditoria
{
    private static string $ip = '';
    private static string $agente = '';

    public static function contexto(string $ip, string $agente): void
    {
        self::$ip = $ip;
        self::$agente = $agente;
    }

    /**
     * @param array<string,mixed>|null $antes
     * @param array<string,mixed>|null $depois
     */
    public static function registrar(string $acao, ?string $entidade = null, int|string|null $entidadeId = null, ?string $descricao = null, ?array $antes = null, ?array $depois = null, ?int $usuarioId = null, ?string $usuarioNome = null): void
    {
        $u = Auth::user();
        [$antes, $depois] = self::diferenca($antes, $depois);
        try {
            Database::insert('logs_auditoria', [
                'usuario_id' => $usuarioId ?? ($u['id'] ?? null),
                'usuario_nome' => $usuarioNome ?? ($u['nome'] ?? (PHP_SAPI === 'cli' ? 'sistema' : null)),
                'acao' => $acao,
                'entidade' => $entidade,
                'entidade_id' => $entidadeId === null ? null : (string) $entidadeId,
                'descricao' => $descricao !== null ? mb_substr($descricao, 0, 255) : null,
                'dados_antes' => $antes !== null ? json_encode(self::limpar($antes), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
                'dados_depois' => $depois !== null ? json_encode(self::limpar($depois), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
                'ip' => self::$ip !== '' ? self::$ip : (PHP_SAPI === 'cli' ? 'cli' : null),
                'user_agent' => self::$agente !== '' ? self::$agente : null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Falha ao gravar auditoria: ' . $e->getMessage(), ['acao' => $acao]);
            throw $e;
        }
    }

    /**
     * Em alterações, guarda só os campos que mudaram.
     * @param array<string,mixed>|null $antes @param array<string,mixed>|null $depois
     * @return array{0:?array<string,mixed>,1:?array<string,mixed>}
     */
    private static function diferenca(?array $antes, ?array $depois): array
    {
        if ($antes === null || $depois === null) {
            return [$antes, $depois];
        }
        $a = [];
        $d = [];
        foreach (array_unique(array_merge(array_keys($antes), array_keys($depois))) as $k) {
            if (in_array($k, ['updated_at', 'created_at'], true)) {
                continue;
            }
            $va = $antes[$k] ?? null;
            $vd = $depois[$k] ?? null;
            if ((string) json_encode($va) !== (string) json_encode($vd) && !(is_numeric($va) && is_numeric($vd) && (float) $va === (float) $vd)) {
                $a[$k] = $va;
                $d[$k] = $vd;
            }
        }
        return [$a, $d];
    }

    /** Nunca grava senhas ou tokens. @param array<string,mixed> $dados @return array<string,mixed> */
    private static function limpar(array $dados): array
    {
        foreach ($dados as $k => $v) {
            if (preg_match('/senha|password|token|hash/i', (string) $k)) {
                $dados[$k] = '***';
            } elseif (is_array($v)) {
                $dados[$k] = self::limpar($v);
            }
        }
        return $dados;
    }
}
