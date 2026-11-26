<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Calendario;

/**
 * Prazos (SLAs) do documento de processos.
 *  - Conferência: mesmo dia para PIs recebidas até 15h; até 12h do dia útil seguinte para as demais
 *  - Correção de PI devolvida: 1 dia útil
 *  - Envio à Conferência: 5 dias úteis antes da 1ª veiculação (10 com entregas especiais)
 */
final class SlaService
{
    /** Prazo da Conferência para uma PI recebida em $recebida (Y-m-d H:i:s). */
    public static function prazoConferencia(string $recebida): string
    {
        $corte = Configuracao::texto('sla_conferencia_hora_corte', '15:00');
        $limite = Configuracao::texto('sla_conferencia_hora_limite', '12:00');
        $data = substr($recebida, 0, 10);
        $hora = substr($recebida, 11, 5);
        $dia = Calendario::data($data);
        if ($dia && Calendario::diaUtil($dia) && $hora <= $corte) {
            return $data . ' 23:59:59';
        }
        return Calendario::somarDiasUteis($data, 1) . ' ' . $limite . ':00';
    }

    /** Prazo de correção (1 dia útil após a devolução, até o fim do dia). */
    public static function prazoCorrecao(string $devolvida): string
    {
        return Calendario::somarDiasUteis(substr($devolvida, 0, 10), Configuracao::inteiro('sla_correcao_dias_uteis', 1)) . ' 23:59:59';
    }

    /** Situação do prazo: ['texto','classe'] para exibir. @return array{texto:string,atrasado:bool} */
    public static function situacao(string $prazo): array
    {
        $agora = date('Y-m-d H:i:s');
        if ($agora > $prazo) {
            return ['texto' => 'atrasada desde ' . data_hora_br($prazo), 'atrasado' => true];
        }
        return ['texto' => 'até ' . data_hora_br($prazo), 'atrasado' => false];
    }
}
