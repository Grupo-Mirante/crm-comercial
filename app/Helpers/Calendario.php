<?php

declare(strict_types=1);

namespace App\Helpers;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Datas sem fuso (UTC interno, como o Gerador original com Date.UTC), meses do período e dias úteis.
 */
final class Calendario
{
    public const MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    public const MES3 = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    /** @var array<string,true>|null */
    private static ?array $feriados = null;

    public static function data(?string $iso): ?DateTimeImmutable
    {
        if ($iso === null || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($iso), $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        return new DateTimeImmutable($m[0] . ' 00:00:00', new DateTimeZone('UTC'));
    }

    public static function iso(DateTimeImmutable $d): string
    {
        return $d->format('Y-m-d');
    }

    /** Número de dias entre duas datas (b − a). */
    public static function diferenca(DateTimeImmutable $a, DateTimeImmutable $b): int
    {
        return (int) round(($b->getTimestamp() - $a->getTimestamp()) / 86400);
    }

    /**
     * Mês do calendário ("2026-11").
     * @return array{key:string,y:int,m:int,days:int,label:string,short:string,start:string,end:string}
     */
    public static function mes(string $key): array
    {
        [$y, $m] = array_map('intval', explode('-', $key));
        $days = self::diasNoMes($m, $y);
        return [
            'key' => $key, 'y' => $y, 'm' => $m - 1, 'days' => $days,
            'label' => self::MESES[$m - 1] . ' / ' . $y,
            'short' => self::MES3[$m - 1] . '/' . substr((string) $y, 2),
            'start' => $key . '-01', 'end' => $key . '-' . str_pad((string) $days, 2, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * Meses cobertos pelo período (no máximo 12, como no Gerador original).
     * @return list<array{key:string,y:int,m:int,days:int,label:string,short:string,start:string,end:string}>
     */
    public static function mesesDoPeriodo(?string $ini, ?string $fim): array
    {
        $a = self::data($ini);
        if (!$a) {
            return [];
        }
        $b = self::data($fim) ?? $a;
        $out = [];
        $y = (int) $a->format('Y');
        $m = (int) $a->format('n') - 1;
        $ey = (int) $b->format('Y');
        $em = (int) $b->format('n') - 1;
        while (($y < $ey || ($y === $ey && $m <= $em)) && count($out) < 12) {
            $out[] = self::mes(sprintf('%04d-%02d', $y, $m + 1));
            $m++;
            if ($m > 11) {
                $m = 0;
                $y++;
            }
        }
        return $out ?: [self::mes($a->format('Y-m'))];
    }

    /**
     * Informação do dia {day} do mês M em relação ao período da campanha.
     * @param array{y:int,m:int,days:int} $M
     * @return array{ok:bool,exists:bool,wd?:int,letter?:string,weekend?:bool}
     */
    public static function diaInfo(array $M, ?string $ini, ?string $fim, int $day): array
    {
        if ($day < 1 || $day > $M['days']) {
            return ['ok' => false, 'exists' => false];
        }
        $dt = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $M['y'], $M['m'] + 1, $day), new DateTimeZone('UTC'));
        $a = self::data($ini);
        $b = self::data($fim);
        $inP = (!$a || $dt >= $a) && (!$b || $dt <= $b);
        $wd = (int) $dt->format('w');
        return ['ok' => $inP, 'exists' => true, 'wd' => $wd, 'letter' => ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'][$wd], 'weekend' => $wd === 0 || $wd === 6];
    }

    /** "23 a 30" — dias do mês dentro do período. @param array{y:int,m:int,days:int} $M */
    public static function faixaDoMes(array $M, ?string $ini, ?string $fim): string
    {
        $a = 0;
        $b = 0;
        for ($d = 1; $d <= $M['days']; $d++) {
            if (self::diaInfo($M, $ini, $fim, $d)['ok']) {
                if (!$a) {
                    $a = $d;
                }
                $b = $d;
            }
        }
        return $a ? "{$a} a {$b}" : 'nenhum dia';
    }

    /**
     * RN-20 — último dia útil do mês seguinte ao mês M (por padrão só pula sábado e domingo, como o Gerador original).
     * @param array{y:int,m:int} $M
     */
    public static function ultimoDiaUtilMesSeguinte(array $M, bool $considerarFeriados = false): string
    {
        $y = $M['y'];
        $m = $M['m'] + 2; // mês seguinte (1-12) a partir de m zero-based
        if ($m > 12) {
            $m -= 12;
            $y++;
        }
        $d = new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $y, $m, self::diasNoMes($m, $y)), new DateTimeZone('UTC'));
        while (!self::diaUtil($d, $considerarFeriados)) {
            $d = $d->modify('-1 day');
        }
        return self::iso($d);
    }

    public static function diaUtil(DateTimeImmutable $d, bool $considerarFeriados = true): bool
    {
        $wd = (int) $d->format('w');
        if ($wd === 0 || $wd === 6) {
            return false;
        }
        return !$considerarFeriados || !isset(self::feriados()[$d->format('Y-m-d')]);
    }

    /** Dias úteis entre duas datas (exclui a inicial, inclui a final). */
    public static function diasUteisEntre(string $deIso, string $ateIso): int
    {
        $a = self::data(substr($deIso, 0, 10));
        $b = self::data(substr($ateIso, 0, 10));
        if (!$a || !$b) {
            return 0;
        }
        $sinal = 1;
        if ($b < $a) {
            [$a, $b] = [$b, $a];
            $sinal = -1;
        }
        $n = 0;
        for ($d = $a->modify('+1 day'); $d <= $b; $d = $d->modify('+1 day')) {
            if (self::diaUtil($d)) {
                $n++;
            }
        }
        return $n * $sinal;
    }

    /** Soma N dias úteis a uma data. */
    public static function somarDiasUteis(string $iso, int $n): string
    {
        $d = self::data(substr($iso, 0, 10)) ?? new DateTimeImmutable('today', new DateTimeZone('UTC'));
        while ($n > 0) {
            $d = $d->modify('+1 day');
            if (self::diaUtil($d)) {
                $n--;
            }
        }
        return self::iso($d);
    }

    /** @return array<string,true> */
    private static function feriados(): array
    {
        if (self::$feriados === null) {
            self::$feriados = [];
            try {
                foreach (\App\Core\Database::all('SELECT data FROM feriados') as $r) {
                    self::$feriados[(string) $r['data']] = true;
                }
            } catch (\Throwable) {
                self::$feriados = [];
            }
        }
        return self::$feriados;
    }

    /** @param list<string> $datas */
    public static function definirFeriados(array $datas): void
    {
        self::$feriados = array_fill_keys($datas, true);
    }

    /** Dias do mês sem depender da extensão calendar do PHP. */
    public static function diasNoMes(int $m, int $y): int
    {
        return (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m), new DateTimeZone('UTC')))->format('t');
    }
}
