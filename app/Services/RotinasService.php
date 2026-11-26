<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\Calendario;

/**
 * Rotinas automáticas (cron a cada 5 minutos: php bin/console.php rotinas).
 *  - expira reservas de 24 h vencidas (RN-67)
 *  - avisa PIs paradas na Conferência além do prazo, devolvidas sem correção em 1 dia útil, aprovações pendentes
 *  - avisa PIs com início próximo ainda sem aprovação
 *  - fila diária às 9h para Conferência e gestores (relatório diário)
 *  - backup diário do banco (após as 2h, uma vez por dia)
 * Cada aviso usa uma chave, então não se repete no mesmo dia.
 */
final class RotinasService
{
    /** @return array<string,int|string> resumo do que foi feito */
    public static function executar(bool $comBackup = true): array
    {
        $hoje = date('Y-m-d');
        $r = ['reservas_expiradas' => ReservaService::expirarVencidas()];

        // Conferência atrasada
        $n = 0;
        foreach (Database::all("SELECT p.id, p.numero, p.cliente_razao, (SELECT MAX(h.criado_em) FROM pi_historico h WHERE h.pi_id = p.id AND h.situacao_nova IN ('Enviada','Corrigida')) AS recebida
                                  FROM pis p WHERE p.situacao IN ('Enviada','Corrigida')") as $p) {
            if ($p['recebida'] && date('Y-m-d H:i:s') > SlaService::prazoConferencia((string) $p['recebida'])) {
                Notificacoes::enviarVarios(Notificacoes::usuariosDoPerfil('conf'), 'sla', "Conferência atrasada: PI {$p['numero']}", 'Recebida em ' . data_hora_br($p['recebida']) . ' · ' . $p['cliente_razao'], '/conferencia/' . $p['id'], "sla-conf-{$p['id']}-{$hoje}");
                $n++;
            }
        }
        $r['conferencias_atrasadas'] = $n;

        // Correção atrasada (1 dia útil): executivo e gestor
        $n = 0;
        foreach (Database::all("SELECT d.id, d.devolvida_em, p.id AS pi_id, p.numero, p.executivo_id, p.gestor_id FROM pi_devolucoes d JOIN pis p ON p.id = d.pi_id WHERE d.corrigida_em IS NULL AND p.situacao = 'Devolvida'") as $d) {
            if (date('Y-m-d H:i:s') > SlaService::prazoCorrecao((string) $d['devolvida_em'])) {
                Notificacoes::enviarVarios(array_filter([(int) $d['executivo_id'], (int) $d['gestor_id']]), 'sla', "Correção atrasada: PI {$d['numero']}", 'Devolvida em ' . data_hora_br($d['devolvida_em']) . ': o prazo de correção é de 1 dia útil.', '/pis/' . $d['pi_id'], "sla-corr-{$d['id']}-{$hoje}");
                $n++;
            }
        }
        $r['correcoes_atrasadas'] = $n;

        // Início próximo sem aprovação
        $dias = Configuracao::inteiro('sla_alerta_inicio_dias', 5);
        $n = 0;
        foreach (Database::all("SELECT id, numero, executivo_id, data_inicio, situacao FROM pis WHERE situacao IN ('Rascunho','Enviada','Devolvida','Corrigida') AND data_inicio BETWEEN CURDATE() AND CURDATE() + INTERVAL ? DAY", [$dias]) as $p) {
            Notificacoes::enviar((int) $p['executivo_id'], 'sla', "PI {$p['numero']} começa em " . data_br($p['data_inicio']) . ' sem aprovação', "Situação: {$p['situacao']}. Resolva as pendências para a OPEC conseguir programar.", '/pis/' . $p['id'], "sla-ini-{$p['id']}-{$hoje}");
            $n++;
        }
        $r['inicios_sem_aprovacao'] = $n;

        // Aprovações pendentes há mais de 1 dia útil
        $n = 0;
        foreach (Database::all("SELECT a.id, a.resumo, a.tipo, a.solicitada_em, u.gestor_id FROM pi_aprovacoes a JOIN usuarios u ON u.id = a.solicitante_id WHERE a.situacao = 'pendente'") as $a) {
            if (Calendario::diasUteisEntre((string) $a['solicitada_em'], $hoje) >= Configuracao::inteiro('sla_aprovacao_gestor_dias_uteis', 1)) {
                $dest = $a['tipo'] === 'diretoria' || !$a['gestor_id'] ? Notificacoes::usuariosComPermissao('aprovacoes.aprovar_diretoria') : [(int) $a['gestor_id']];
                Notificacoes::enviarVarios($dest, 'aprovacao', 'Aprovação aguardando: ' . $a['resumo'], 'Pedida em ' . data_hora_br($a['solicitada_em']) . '. Prazo do gestor: 1 dia útil.', '/aprovacoes', "sla-apr-{$a['id']}-{$hoje}");
                $n++;
            }
        }
        $r['aprovacoes_atrasadas'] = $n;

        // Fila diária (9h, dias úteis)
        $r['fila_diaria'] = 0;
        $hora = Configuracao::texto('fila_diaria_hora', '09:00');
        $diaUtil = Calendario::diaUtil(Calendario::data($hoje));
        if ($diaUtil && date('H:i') >= $hora) {
            $fila = (int) Database::value("SELECT COUNT(*) FROM pis WHERE situacao IN ('Enviada','Corrigida')");
            $dev = (int) Database::value("SELECT COUNT(*) FROM pis WHERE situacao = 'Devolvida'");
            $prox = (int) Database::value("SELECT COUNT(*) FROM pis WHERE situacao IN ('Rascunho','Enviada','Devolvida','Corrigida') AND data_inicio BETWEEN CURDATE() AND CURDATE() + INTERVAL ? DAY", [$dias]);
            $dest = array_unique(array_merge(Notificacoes::usuariosDoPerfil('conf'), Notificacoes::usuariosDoPerfil('gerente'), Notificacoes::usuariosDoPerfil('financeiro')));
            Notificacoes::enviarVarios($dest, 'fila', 'Fila do dia ' . data_br($hoje), "{$fila} PI(s) aguardando conferência · {$dev} devolvida(s) aguardando correção · {$prox} com início nos próximos {$dias} dias sem aprovação.", '/conferencia', "fila-{$hoje}");
            $r['fila_diaria'] = count($dest);
        }

        // Backup diário
        $r['backup'] = '';
        if ($comBackup && (int) date('G') >= 2 && substr(Configuracao::texto('backup_ultima_execucao', ''), 0, 10) !== $hoje) {
            try {
                $r['backup'] = basename(BackupService::gerar('automático'));
            } catch (\Throwable $e) {
                $r['backup'] = 'ERRO: ' . $e->getMessage();
                \App\Core\Logger::error('Backup automático falhou: ' . $e->getMessage());
                Notificacoes::enviarVarios(Notificacoes::usuariosDoPerfil('admin'), 'backup', 'Falha no backup automático', $e->getMessage(), '/backup', "backup-falha-{$hoje}");
            }
        }
        return $r;
    }
}
