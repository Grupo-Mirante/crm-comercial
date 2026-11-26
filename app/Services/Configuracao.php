<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Configurações administrativas guardadas na tabela configuracoes (alçadas, prazos, segurança…). */
final class Configuracao
{
    /** @var array<string,?string>|null */
    private static ?array $cache = null;

    private static function carregar(): void
    {
        if (self::$cache === null) {
            // só guarda em cache depois de ler com sucesso (durante a instalação a tabela ainda não existe)
            $c = [];
            foreach (Database::all('SELECT chave, valor FROM configuracoes') as $r) {
                $c[$r['chave']] = $r['valor'];
            }
            self::$cache = $c;
        }
    }

    public static function texto(string $chave, string $padrao = ''): string
    {
        self::carregar();
        $v = self::$cache[$chave] ?? null;
        return $v === null || $v === '' ? $padrao : (string) $v;
    }

    public static function inteiro(string $chave, int $padrao = 0): int
    {
        $v = self::texto($chave);
        return is_numeric($v) ? (int) $v : $padrao;
    }

    /** Retorna null quando vazio (ex.: alçada não definida). */
    public static function decimal(string $chave): ?float
    {
        $v = str_replace(',', '.', self::texto($chave));
        return is_numeric($v) ? (float) $v : null;
    }

    public static function booleano(string $chave, bool $padrao = false): bool
    {
        $v = self::texto($chave);
        return $v === '' ? $padrao : in_array(strtolower($v), ['1', 'true', 'sim', 'yes'], true);
    }

    public static function definir(string $chave, ?string $valor, ?int $usuarioId = null): void
    {
        Database::query('UPDATE configuracoes SET valor = ?, updated_by = ? WHERE chave = ?', [$valor, $usuarioId, $chave]);
        self::$cache = null;
    }

    public static function limparCache(): void
    {
        self::$cache = null;
    }
}
