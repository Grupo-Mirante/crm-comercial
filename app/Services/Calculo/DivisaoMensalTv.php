<?php

declare(strict_types=1);

namespace App\Services\Calculo;

use App\Helpers\Calendario;
use App\Services\Configuracao;

/**
 * RN-17 — na TV, período que passa de um mês gera uma PI por mês, cada uma com número próprio,
 * os dias daquele mês, sua grade e uma parcela. Porta de splitByMonth() do Gerador original.
 * RN-20 — a parcela de cada PI mensal vence na data informada para aquele mês; sem data,
 *         no último dia útil do mês seguinte ao de veiculação.
 * RN-38 — a cota de projeto especial vai só na PI do primeiro mês.
 * Ajuste registrado (pendência A-01): produção/taxas e entregas especiais também ficam só na PI do
 * primeiro mês, para não serem cobradas/executadas uma vez por mês (no Gerador original eram repetidas).
 */
final class DivisaoMensalTv
{
    public function __construct(private readonly CalculadoraPi $calc)
    {
    }

    /**
     * @param array<string,mixed> $S
     * @return list<array<string,mixed>> estados das PIs (uma por mês) — ou o próprio estado se não divide
     */
    public function dividir(array $S): array
    {
        $S = CalculadoraPi::normalizar($S);
        $R = $this->calc->calcular($S);
        if (!$R['splitTV']) {
            return [$S];
        }
        $ini = Calendario::data((string) $S['f']['ini']);
        $fim = Calendario::data((string) $S['f']['fim']);
        $total = count($R['months']);
        $parcInformadas = array_values(array_filter($S['parc'], fn ($x) => trim((string) ($x['venc'] ?? '')) !== '' || (float) ($x['val'] ?? 0)));
        $feriados = Configuracao::booleano('rn20_considerar_feriados', false);
        $partes = [];
        foreach ($R['months'] as $k => $M) {
            $s = $S;
            $a = max($ini, Calendario::data($M['start']));
            $b = min($fim, Calendario::data($M['end']));
            $s['f']['ini'] = Calendario::iso($a);
            $s['f']['fim'] = Calendario::iso($b);
            $s['f']['num'] = (string) ($S['f']['nums'][$M['key']] ?? '');
            $ins = [];
            foreach ($s['ins'] as $l) {
                $l['gm'] = [$M['key'] => $l['gm'][$M['key']] ?? []];
                $temQtd = false;
                foreach ($l['gm'][$M['key']] as $q) {
                    if ((int) $q > 0) {
                        $temQtd = true;
                    }
                }
                if ($temQtd) {
                    $ins[] = $l;
                }
            }
            $s['ins'] = $ins;
            if ($k > 0) {
                $s['prj'] = [];
                $s['ent'] = [];
                $s['f']['producao'] = '';
            }
            $s['f']['mesKey'] = $M['key'];
            $s['f']['parte'] = ($k + 1) . ' de ' . $total;
            $liq = $this->calc->calcular($s)['T']['liq'];
            $venc = (count($parcInformadas) === $total && trim((string) ($parcInformadas[$k]['venc'] ?? '')) !== '')
                ? (string) $parcInformadas[$k]['venc']
                : Calendario::ultimoDiaUtilMesSeguinte($M, $feriados);
            $s['parc'] = [['venc' => $venc, 'val' => CalculadoraPi::r2($liq)]];
            $partes[] = $s;
        }
        return $partes;
    }
}
