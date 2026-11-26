<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Conexão PDO única com MySQL. Toda consulta usa prepared statements (proteção contra SQL Injection).
 * O fuso da sessão MySQL acompanha o fuso da aplicação (America/Sao_Paulo).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect(Config::get('database'));
        }
        return self::$pdo;
    }

    /** @param array<string,mixed> $cfg */
    public static function connect(array $cfg, bool $withDatabase = true): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4%s',
            $cfg['host'],
            (int) $cfg['port'],
            $withDatabase && !empty($cfg['database']) ? ';dbname=' . $cfg['database'] : ''
        );
        if (!empty($cfg['socket'])) {
            $dsn = sprintf('mysql:unix_socket=%s;charset=utf8mb4%s', $cfg['socket'], $withDatabase && !empty($cfg['database']) ? ';dbname=' . $cfg['database'] : '');
        }
        $pdo = new PDO($dsn, (string) $cfg['username'], (string) $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $offset = (new \DateTimeImmutable('now', new \DateTimeZone((string) Config::get('app.timezone', 'America/Sao_Paulo'))))->format('P');
        $pdo->exec("SET time_zone = '{$offset}', sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        return $pdo;
    }

    public static function setConnection(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    /** @param array<int|string,mixed> $params */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @param array<int|string,mixed> $params @return array<string,mixed>|null */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<int|string,mixed> $params @return list<array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** @param array<int|string,mixed> $params */
    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** @param array<string,mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            self::ident($table),
            implode(', ', array_map(fn ($c) => '`' . self::ident($c) . '`', $cols)),
            implode(', ', array_map(fn ($c) => ':' . $c, $cols))
        );
        self::query($sql, $data);
        return (int) self::connection()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public static function update(string $table, array $data, array $where): int
    {
        $set = [];
        $params = [];
        foreach ($data as $col => $val) {
            $set[] = '`' . self::ident($col) . '` = :s_' . $col;
            $params['s_' . $col] = $val;
        }
        $cond = [];
        foreach ($where as $col => $val) {
            $cond[] = '`' . self::ident($col) . '` = :w_' . $col;
            $params['w_' . $col] = $val;
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', self::ident($table), implode(', ', $set), implode(' AND ', $cond));
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Executa a função dentro de uma transação. Em transações aninhadas, apenas a mais externa confirma.
     * Com $tentativas > 1, repete a transação inteira se o MySQL apontar deadlock (1213) ou espera de trava
     * esgotada (1205) — situação normal quando várias pessoas disputam a mesma numeração ou a mesma cota.
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public static function transaction(callable $fn, int $tentativas = 1): mixed
    {
        $pdo = self::connection();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        for ($t = 1; ; $t++) {
            $pdo->beginTransaction();
            try {
                $result = $fn();
                $pdo->commit();
                return $result;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $codigo = $e instanceof \PDOException ? (int) ($e->errorInfo[1] ?? 0) : 0;
                if ($t < $tentativas && in_array($codigo, [1213, 1205], true)) {
                    usleep(random_int(30000, 150000) * $t);
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * Lista literal e citada para constantes do próprio código (ex.: situações), quando a mesma lista aparece
     * várias vezes na consulta. NUNCA usar com dados vindos do usuário — para isso use in().
     * @param list<string> $valores
     */
    public static function lista(array $valores): string
    {
        return '(' . implode(', ', array_map(fn ($v) => self::connection()->quote((string) $v), $valores)) . ')';
    }

    /** Cria "IN (:p0, :p1…)" com os parâmetros correspondentes. @param list<mixed> $values @return array{0:string,1:array<string,mixed>} */
    public static function in(array $values, string $prefix = 'p'): array
    {
        if ($values === []) {
            return ['(NULL)', []];
        }
        $names = [];
        $params = [];
        foreach (array_values($values) as $i => $v) {
            $names[] = ':' . $prefix . $i;
            $params[$prefix . $i] = $v;
        }
        return ['(' . implode(', ', $names) . ')', $params];
    }

    private static function ident(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Identificador SQL inválido: ' . $name);
        }
        return $name;
    }
}
