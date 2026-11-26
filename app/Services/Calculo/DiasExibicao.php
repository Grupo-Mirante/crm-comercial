<?php

declare(strict_types=1);

namespace App\Services\Calculo;

/**
 * RN-23 — dias em que o programa vai ao ar, a partir do texto do cadastro (ex.: "SEG/SEX", "Seg / Sab", "TER/QUI", "Diário").
 * Porta fiel de airDays() do Gerador de PI:
 *   - "DIÁRIO" = todos os dias;
 *   - dois dias começando por SEG (ou SÁB/DOM) = intervalo (SEG/SEX = seg a sex; SEG/DOM = seg a dom);
 *   - demais combinações = lista (TER/QUI = terça e quinta);
 *   - vazio ou texto não reconhecido ("Variável") = sem restrição (null).
 * Dia da semana: 0 = domingo … 6 = sábado. Máscara: bit (1 << dia).
 */
final class DiasExibicao
{
    private const N = ['DOM' => 0, 'SEG' => 1, 'TER' => 2, 'QUA' => 3, 'QUI' => 4, 'SEX' => 5, 'SAB' => 6];
    public const LETRAS = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];

    /** @return list<int>|null */
    public static function dias(?string $texto): ?array
    {
        if ($texto === null || trim($texto) === '') {
            return null;
        }
        $t = strtoupper(preg_replace('/\s+/', '', sem_acento($texto)) ?? '');
        if ($t === 'DIARIO') {
            return [0, 1, 2, 3, 4, 5, 6];
        }
        $parts = explode('/', $t);
        foreach ($parts as $p) {
            if (!array_key_exists($p, self::N)) {
                return null;
            }
        }
        if (count($parts) === 2 && ($parts[0] === 'SEG' || ($parts[0] === 'SAB' && $parts[1] === 'DOM'))) {
            $a = self::N[$parts[0]];
            $b = self::N[$parts[1]];
            $out = [];
            for ($d = $a; ; $d = ($d + 1) % 7) {
                $out[] = $d;
                if ($d === $b) {
                    break;
                }
            }
            return $out;
        }
        return array_values(array_map(fn ($p) => self::N[$p], $parts));
    }

    public static function mascara(?string $texto): ?int
    {
        $dias = self::dias($texto);
        if ($dias === null) {
            return null;
        }
        $m = 0;
        foreach ($dias as $d) {
            $m |= 1 << $d;
        }
        return $m;
    }

    /** @return list<int>|null */
    public static function daMascara(?int $mascara): ?array
    {
        if ($mascara === null) {
            return null;
        }
        $out = [];
        for ($d = 0; $d < 7; $d++) {
            if ($mascara & (1 << $d)) {
                $out[] = $d;
            }
        }
        return $out;
    }
}
