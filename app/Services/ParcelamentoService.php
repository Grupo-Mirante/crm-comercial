<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Calendario;

/**
 * RN-43 — "Gerar parcelas": divide o líquido em até 12 parcelas mensais, a partir da data escolhida,
 * e joga os centavos na última. A soma das parcelas tem de ser igual ao líquido e toda parcela tem vencimento.
 * Na TV dividida por mês (RN-17/RN-20), cria uma parcela por PI mensal; o valor de cada uma é o líquido da PI do mês.
 */
final class ParcelamentoService
{
    /**
     * @return list<array{venc:string,val:float|string}>
     */
    public static function gerar(float $liquido, int $quantidade, string $primeiroVencimento, bool $tvDividida = false, int $mesesTv = 0): array
    {
        $max = Configuracao::inteiro('parcelas_maximo', 12);
        $n = $tvDividida ? max(1, $mesesTv) : max(1, min($max, $quantidade));
        $d0 = Calendario::data($primeiroVencimento) ?? Calendario::data(date('Y-m-d'));
        $y = (int) $d0->format('Y');
        $m = (int) $d0->format('n');
        $dia = (int) $d0->format('j');
        $base = floor($liquido / $n * 100) / 100;
        $out = [];
        for ($k = 0; $k < $n; $k++) {
            $mm = $m + $k;
            $yy = $y + intdiv($mm - 1, 12);
            $mm = (($mm - 1) % 12) + 1;
            $ult = Calendario::diasNoMes($mm, $yy);
            $venc = sprintf('%04d-%02d-%02d', $yy, $mm, min($dia, $ult));
            $val = $tvDividida ? '' : ($k === $n - 1 ? round(($liquido - $base * ($n - 1)) * 100) / 100 : $base);
            $out[] = ['venc' => $venc, 'val' => $val];
        }
        return $out;
    }
}
