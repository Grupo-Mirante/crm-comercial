<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/** Executa arquivos .sql comando a comando, respeitando aspas, comentários e ';' dentro de textos. */
final class SqlScript
{
    /** @return list<string> */
    public static function split(string $sql): array
    {
        $out = [];
        $buf = '';
        $len = strlen($sql);
        $quote = null;
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';
            if ($quote !== null) {
                $buf .= $ch;
                if ($ch === '\\' && $quote !== '`') {
                    $buf .= $next;
                    $i++;
                } elseif ($ch === $quote) {
                    if ($next === $quote) {
                        $buf .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($ch === '-' && $next === '-') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
                $buf .= "\n";
                continue;
            }
            if ($ch === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $len : $end + 1;
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buf .= $ch;
                continue;
            }
            if ($ch === ';') {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }
        return $out;
    }

    public static function runFile(PDO $pdo, string $file): int
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new \RuntimeException('Não foi possível ler ' . $file);
        }
        $n = 0;
        foreach (self::split($sql) as $stmt) {
            $pdo->exec($stmt);
            $n++;
        }
        return $n;
    }
}
