<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * RN-61 / RN-62 — CPF e CNPJ conferidos pelos dígitos verificadores, inclusive o CNPJ alfanumérico
 * da Receita Federal (IN RFB 2.229/2024: 12 caracteres de letras e números + 2 dígitos).
 * Porta fiel de docCheck() do Gerador de PI: cada caractere vale (código ASCII − 48), pesos 2 a 9.
 *
 * Exemplos do documento: 11.222.333/0001-81 válido · 11.222.333/0001-80 inválido · 12.ABC.345/01DE-35 válido.
 */
final class DocumentoValidator
{
    /**
     * @return array{ok:bool,vazio:bool,tipo:?string,formatado:?string,chave:?string}
     */
    public static function verificar(?string $valor): array
    {
        $v = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) $valor));
        $base = ['ok' => false, 'vazio' => false, 'tipo' => null, 'formatado' => null, 'chave' => null];
        if ($v === '') {
            return ['vazio' => true] + $base;
        }
        if (strlen($v) === 11 && ctype_digit($v)) {
            if (preg_match('/^(\d)\1{10}$/', $v)) {
                return ['tipo' => 'CPF'] + $base;
            }
            $w1 = [10, 9, 8, 7, 6, 5, 4, 3, 2];
            $w2 = [11, ...$w1];
            $ok = self::dv(substr($v, 0, 9), $w1) === (int) $v[9] && self::dv(substr($v, 0, 10), $w2) === (int) $v[10];
            return ['ok' => $ok, 'tipo' => 'CPF', 'formatado' => substr($v, 0, 3) . '.' . substr($v, 3, 3) . '.' . substr($v, 6, 3) . '-' . substr($v, 9), 'chave' => $v] + $base;
        }
        if (strlen($v) === 14 && preg_match('/^[0-9A-Z]{12}\d{2}$/', $v)) {
            if (preg_match('/^(.)\1{13}$/', $v)) {
                return ['tipo' => 'CNPJ'] + $base;
            }
            $w1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $w2 = [6, ...$w1];
            $ok = self::dv(substr($v, 0, 12), $w1) === (int) $v[12] && self::dv(substr($v, 0, 13), $w2) === (int) $v[13];
            return ['ok' => $ok, 'tipo' => 'CNPJ', 'formatado' => substr($v, 0, 2) . '.' . substr($v, 2, 3) . '.' . substr($v, 5, 3) . '/' . substr($v, 8, 4) . '-' . substr($v, 12), 'chave' => $v] + $base;
        }
        return ['tipo' => strlen($v) > 11 ? 'CNPJ' : 'CPF'] + $base;
    }

    /** @param list<int> $pesos */
    private static function dv(string $s, array $pesos): int
    {
        $t = 0;
        foreach (str_split($s) as $i => $ch) {
            $t += (ord($ch) - 48) * $pesos[$i];
        }
        $r = $t % 11;
        return $r < 2 ? 0 : 11 - $r;
    }

    public static function valido(?string $valor): bool
    {
        return self::verificar($valor)['ok'];
    }

    public static function cnpjValido(?string $valor): bool
    {
        $r = self::verificar($valor);
        return $r['ok'] && $r['tipo'] === 'CNPJ';
    }
}
