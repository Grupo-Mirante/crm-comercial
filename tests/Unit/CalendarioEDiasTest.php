<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Calendario;
use App\Services\Calculo\DiasExibicao;
use PHPUnit\Framework\TestCase;

/** RN-23 (dias de exibição), RN-17/RN-20 (meses e vencimento), apoio às regras de período. */
final class CalendarioEDiasTest extends TestCase
{
    protected function setUp(): void
    {
        Calendario::definirFeriados(['2026-12-25']);
    }

    public function testDiasDeExibicaoComoNoGerador(): void
    {
        self::assertSame([1, 2, 3, 4, 5], DiasExibicao::dias('SEG/SEX'));
        self::assertSame([1, 2, 3, 4, 5], DiasExibicao::dias('Seg / Sex'));
        self::assertSame([1, 2, 3, 4, 5, 6], DiasExibicao::dias('SEG/SÁB'));
        self::assertSame([1, 2, 3, 4, 5, 6, 0], DiasExibicao::dias('SEG/DOM'));
        self::assertSame([6, 0], DiasExibicao::dias('Sáb / Dom'));
        self::assertSame([2, 4], DiasExibicao::dias('TER/QUI'), 'dois dias que não começam em SEG = lista');
        self::assertSame([0, 1, 2, 3, 4, 5, 6], DiasExibicao::dias('Diário'));
        self::assertSame([3], DiasExibicao::dias('QUA'));
        self::assertNull(DiasExibicao::dias('Variável'));
        self::assertNull(DiasExibicao::dias(''));
        self::assertSame(0b0111110, DiasExibicao::mascara('SEG/SEX'));
        self::assertSame([1, 2, 3, 4, 5], DiasExibicao::daMascara(0b0111110));
    }

    public function testMesesDoPeriodo(): void
    {
        $m = Calendario::mesesDoPeriodo('2026-11-23', '2026-12-22');
        self::assertCount(2, $m);
        self::assertSame('2026-11', $m[0]['key']);
        self::assertSame(30, $m[0]['days']);
        self::assertSame('dez/26', $m[1]['short']);
        self::assertSame('23 a 30', Calendario::faixaDoMes($m[0], '2026-11-23', '2026-12-22'));
        self::assertSame('1 a 22', Calendario::faixaDoMes($m[1], '2026-11-23', '2026-12-22'));
        self::assertCount(12, Calendario::mesesDoPeriodo('2026-01-01', '2028-01-01'), 'no máximo 12 meses, como no Gerador');
    }

    public function testRn20UltimoDiaUtilDoMesSeguinte(): void
    {
        // Exemplo do documento: PI de novembro → vence em 31/12
        self::assertSame('2026-12-31', Calendario::ultimoDiaUtilMesSeguinte(Calendario::mes('2026-11')));
        // Dezembro/2026 → último dia útil de janeiro/2027 (31/01 é domingo → 29/01, sexta)
        self::assertSame('2027-01-29', Calendario::ultimoDiaUtilMesSeguinte(Calendario::mes('2026-12')));
        // Fevereiro/2026 → 31/03/2026 (terça)
        self::assertSame('2026-03-31', Calendario::ultimoDiaUtilMesSeguinte(Calendario::mes('2026-02')));
    }

    public function testDiasUteisComFeriado(): void
    {
        // 24/12 (qui) → 28/12 (seg): conta 28 (25 é feriado, 26-27 fim de semana)
        self::assertSame(1, Calendario::diasUteisEntre('2026-12-24', '2026-12-28'));
        self::assertSame('2026-12-28', Calendario::somarDiasUteis('2026-12-24', 1));
        self::assertSame(-1, Calendario::diasUteisEntre('2026-12-28', '2026-12-24'));
    }

    public function testDiaInfo(): void
    {
        $M = Calendario::mes('2026-12');
        $d = Calendario::diaInfo($M, '2026-12-05', '2026-12-31', 1);
        self::assertFalse($d['ok'], 'antes do início');
        self::assertSame(2, $d['wd'], '01/12/2026 é terça');
        self::assertFalse(Calendario::diaInfo($M, null, null, 32)['exists']);
        self::assertTrue(Calendario::diaInfo($M, null, null, 6)['weekend'], '06/12/2026 é domingo');
    }
}
