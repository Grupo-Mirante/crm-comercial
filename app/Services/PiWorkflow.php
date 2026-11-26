<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;

/**
 * Fluxo da PI depois de gerada (ciclo de vida, seção 10 das regras e fluxo de 9 etapas do documento).
 *
 *   Rascunho → Enviada → (Conferência) → Aprovada → Em programação → Em veiculação → Pronta para faturamento → Faturada → Encerrada
 *                         ↘ Devolvida → (correção, mesma PI e número) → Corrigida → Conferência
 *   Cancelada (com motivo, irreversível) · Substituída (por Cancela/substitui)
 *
 *  RN-52 devolver exige código(s) D01–D15 e o que corrigir; fica no nome do executivo e alimenta o ranking
 *  RN-54 aprovar é da Conferência
 *  RN-55 cancelar exige motivo, não pode ser desfeito, sai dos totais e devolve as cotas; TV: cada PI mensal separada
 *  RN-57 veiculação finalizada exige a data de fim e deixa a PI Pronta para faturamento
 *  RN-74 quem pode cada ação (conferida no servidor)
 *  Segunda devolução da mesma PI: o gestor é avisado automaticamente.
 *  A Conferência nunca altera dados comerciais: ela só aprova ou devolve.
 */
final class PiWorkflow
{
    /** @return array<string,mixed> */
    private static function travar(int $id): array
    {
        $pi = Database::first('SELECT * FROM pis WHERE id = ? FOR UPDATE', [$id]);
        if (!$pi) {
            throw new HttpException(404, 'PI não encontrada.');
        }
        if (!Auth::podeVerPi($pi)) {
            throw new HttpException(403);
        }
        return $pi;
    }

    /** @param list<string> $de */
    private static function exigirSituacao(array $pi, array $de, string $acao): void
    {
        if (!in_array($pi['situacao'], $de, true)) {
            throw new BusinessException("Não é possível {$acao}: a PI {$pi['numero']} está {$pi['situacao']}.");
        }
    }

    private static function mudar(array $pi, string $para, array $campos, string $evento, ?array $detalhes = null): void
    {
        $n = Database::query(
            'UPDATE pis SET situacao = ?, updated_by = ?' . implode('', array_map(fn ($c) => ", `{$c}` = ?", array_keys($campos))) . ' WHERE id = ? AND situacao = ?',
            array_merge([$para, Auth::id()], array_values($campos), [(int) $pi['id'], $pi['situacao']])
        )->rowCount();
        if ($n !== 1) {
            throw new BusinessException('A PI foi alterada por outra pessoa agora há pouco. Recarregue a página.');
        }
        PiService::historico((int) $pi['id'], $pi['situacao'], $para, $evento, $detalhes);
        Auditoria::registrar('pi_' . self::slug($para), 'pi', (int) $pi['id'], "PI {$pi['numero']}: {$evento}", ['situacao' => $pi['situacao']] + array_intersect_key($pi, $campos), ['situacao' => $para] + $campos);
    }

    private static function slug(string $s): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', normaliza($s)), '_');
    }

    /** @return list<array<string,mixed>> itens do checklist de 10 itens */
    public static function checklist(): array
    {
        return Database::all('SELECT id, ordem, descricao, codigos FROM checklist_conferencia WHERE ativo = 1 ORDER BY ordem');
    }

    /**
     * Aprovação na Conferência: só com todos os itens do checklist marcados.
     * @param list<int|string> $marcados ids dos itens marcados
     */
    public static function aprovar(int $id, array $marcados, string $observacao = ''): void
    {
        if (!Auth::can('conferencia.conferir')) {
            throw new HttpException(403, 'Aprovar é ação da Conferência (RN-54/RN-74).');
        }
        Database::transaction(function () use ($id, $marcados, $observacao) {
            $pi = self::travar($id);
            self::exigirSituacao($pi, ['Enviada', 'Corrigida'], 'aprovar');
            if ((int) $pi['qtd_pendencias'] > 0) {
                throw new BusinessException('A PI tem pendências na conferência automática: devolva com o código correspondente.');
            }
            $itens = self::checklist();
            $marc = array_map('intval', $marcados);
            $faltam = array_filter($itens, fn ($i) => !in_array((int) $i['id'], $marc, true));
            if ($faltam) {
                throw new ValidationException(['checklist' => 'A PI só é aprovada com todos os itens do checklist marcados. Faltam: ' . implode('; ', array_map(fn ($i) => $i['ordem'] . '. ' . $i['descricao'], $faltam)) . '.']);
            }
            $confId = Database::insert('pi_conferencias', [
                'pi_id' => $id, 'versao' => (int) $pi['versao_atual'], 'conferente_id' => (int) Auth::id(), 'resultado' => 'aprovada',
                'checklist' => json_encode(array_map(fn ($i) => ['id' => (int) $i['id'], 'item' => $i['descricao'], 'ok' => true], $itens), JSON_UNESCAPED_UNICODE),
                'observacao' => mb_substr(trim($observacao), 0, 500) ?: null, 'concluida_em' => date('Y-m-d H:i:s'),
            ]);
            self::mudar($pi, 'Aprovada', ['aprovada_em' => date('Y-m-d H:i:s'), 'aprovada_por' => Auth::id(), 'aprovada_primeira' => (int) $pi['qtd_devolucoes'] === 0 ? 1 : 0],
                'Aprovada na conferência' . (trim($observacao) !== '' ? ': ' . trim($observacao) : ''), ['conferencia_id' => $confId]);
            // Q9: PI do tipo Cancelamento, quando aprovada, cancela a PI de origem
            if ($pi['tipo'] === 'Cancelamento' && $pi['pi_origem_id']) {
                $orig = Database::first('SELECT * FROM pis WHERE id = ? FOR UPDATE', [(int) $pi['pi_origem_id']]);
                if ($orig && !in_array($orig['situacao'], ['Cancelada', 'Substituída', 'Pronta para faturamento', 'Faturada', 'Encerrada'], true)) {
                    self::mudar($orig, 'Cancelada', ['cancelada_em' => date('Y-m-d H:i:s'), 'cancelada_por' => Auth::id(), 'motivo_cancelamento' => 'Cancelada pela PI de cancelamento ' . $pi['numero']],
                        'Cancelada pela PI de cancelamento ' . $pi['numero']);
                }
            }
            Notificacoes::enviar((int) $pi['executivo_id'], 'aprovada', "PI {$pi['numero']} aprovada na conferência", 'Liberada para Financeiro, OPEC e Marketing.', '/pis/' . $id);
        });
    }

    /**
     * RN-52 — devolução com um ou mais códigos D01–D15 e a descrição do que corrigir.
     * @param list<string> $codigos
     * @param array<int|string,bool> $checklist itens marcados como OK (registro do que foi conferido)
     */
    public static function devolver(int $id, array $codigos, string $descricao, array $checklist = []): void
    {
        if (!Auth::can('conferencia.conferir')) {
            throw new HttpException(403, 'Devolver é ação da Conferência (RN-74).');
        }
        $codigos = array_values(array_unique(array_filter(array_map(fn ($c) => strtoupper(trim((string) $c)), $codigos))));
        $validos = array_column(Database::all('SELECT codigo FROM motivos_devolucao WHERE ativo = 1'), 'codigo');
        $e = [];
        if (!$codigos || array_diff($codigos, $validos)) {
            $e['codigos'] = 'Escolha ao menos um código de motivo (D01 a D15). Sem código, a devolução não vale.';
        }
        if (mb_strlen(trim($descricao)) < 5) {
            $e['descricao'] = 'Explique em uma linha o que o executivo precisa corrigir.';
        }
        if ($e) {
            throw new ValidationException($e);
        }
        Database::transaction(function () use ($id, $codigos, $descricao, $checklist) {
            $pi = self::travar($id);
            self::exigirSituacao($pi, ['Enviada', 'Corrigida'], 'devolver');
            $itens = self::checklist();
            $confId = Database::insert('pi_conferencias', [
                'pi_id' => $id, 'versao' => (int) $pi['versao_atual'], 'conferente_id' => (int) Auth::id(), 'resultado' => 'devolvida',
                'checklist' => json_encode(array_map(fn ($i) => ['id' => (int) $i['id'], 'item' => $i['descricao'], 'ok' => !empty($checklist[$i['id']])], $itens), JSON_UNESCAPED_UNICODE),
                'observacao' => mb_substr(trim($descricao), 0, 500), 'concluida_em' => date('Y-m-d H:i:s'),
            ]);
            $seq = (int) $pi['qtd_devolucoes'] + 1;
            $devId = Database::insert('pi_devolucoes', [
                'pi_id' => $id, 'conferencia_id' => $confId, 'sequencia' => $seq, 'descricao' => trim($descricao), 'devolvida_por' => (int) Auth::id(),
                'executivo_id' => (int) $pi['executivo_id'], 'devolvida_em' => date('Y-m-d H:i:s'), 'gestor_notificado' => $seq >= 2 ? 1 : 0,
            ]);
            foreach ($codigos as $c) {
                Database::insert('pi_devolucao_codigos', ['devolucao_id' => $devId, 'motivo_codigo' => $c]);
            }
            self::mudar($pi, 'Devolvida', ['qtd_devolucoes' => $seq], 'Devolvida (' . implode(', ', $codigos) . '): ' . trim($descricao), ['devolucao_id' => $devId, 'codigos' => $codigos]);
            Notificacoes::enviar((int) $pi['executivo_id'], 'devolvida', "PI {$pi['numero']} devolvida (" . implode(', ', $codigos) . ')',
                trim($descricao) . ' — corrija em até ' . Configuracao::inteiro('sla_correcao_dias_uteis', 1) . ' dia útil e reenvie a mesma PI.', '/pis/' . $id);
            if ($seq >= 2 && $pi['gestor_id']) {
                Notificacoes::enviar((int) $pi['gestor_id'], 'devolvida_gestor', "2ª devolução: PI {$pi['numero']}",
                    "A PI {$pi['numero']} foi devolvida {$seq} vezes. Resolva com o executivo hoje. Motivo: " . implode(', ', $codigos) . ' — ' . trim($descricao), '/pis/' . $id);
            }
        });
    }

    /** Liberação para as áreas: Financeiro, OPEC e Marketing começam (Em programação). */
    public static function programar(int $id, string $obs = ''): void
    {
        if (!Auth::can('operacao.programar')) {
            throw new HttpException(403);
        }
        Database::transaction(function () use ($id, $obs) {
            $pi = self::travar($id);
            self::exigirSituacao($pi, ['Aprovada'], 'liberar para programação');
            self::mudar($pi, 'Em programação', ['programada_em' => date('Y-m-d H:i:s')], 'Grade reservada e mapa de inserção em montagem' . ($obs !== '' ? ': ' . $obs : ''));
        });
    }

    public static function iniciarVeiculacao(int $id, string $obs = ''): void
    {
        if (!Auth::can('operacao.programar')) {
            throw new HttpException(403);
        }
        Database::transaction(function () use ($id, $obs) {
            $pi = self::travar($id);
            self::exigirSituacao($pi, ['Em programação'], 'marcar o início da veiculação');
            self::mudar($pi, 'Em veiculação', ['veiculacao_iniciada_em' => date('Y-m-d H:i:s')], 'Veiculação iniciada' . ($obs !== '' ? ': ' . $obs : ''));
        });
    }

    /** RN-57 — veiculação finalizada (comprovação), com a data de fim. */
    public static function finalizarVeiculacao(int $id, string $dataFim, string $obs = ''): void
    {
        if (!Auth::can('operacao.finalizar')) {
            throw new HttpException(403, 'Veiculação finalizada: administrador, diretor e Conferência (RN-74).');
        }
        $d = data_iso($dataFim);
        if ($d === '') {
            throw new ValidationException(['data_fim' => 'Informe a data de fim da veiculação.']);
        }
        Database::transaction(function () use ($id, $d, $obs) {
            $pi = self::travar($id);
            self::exigirSituacao($pi, ['Enviada', 'Corrigida', 'Aprovada', 'Em programação', 'Em veiculação'], 'finalizar a veiculação');
            self::mudar($pi, 'Pronta para faturamento', ['data_fim_veiculacao' => $d, 'pronta_faturamento_em' => date('Y-m-d H:i:s')],
                'Veiculação finalizada em ' . data_br($d) . ($obs !== '' ? ': ' . $obs : ''));
            $fin = Notificacoes::usuariosDoPerfil('financeiro');
            Notificacoes::enviarVarios($fin, 'faturamento', "PI {$pi['numero']} pronta para faturamento", 'Líquido a faturar: ' . moeda($pi['valor_liquido']), '/pis/' . $id);
        });
    }

    /** Faturamento pelo Financeiro (nota fiscal). Divergência PI × nota fica registrada para o indicador. */
    public static function faturar(int $id, string $nfNumero, mixed $nfValor, string $obs = ''): void
    {
        if (!Auth::can('financeiro.faturar')) {
            throw new HttpException(403);
        }
        $valor = valor_decimal($nfValor);
        $e = [];
        if (trim($nfNumero) === '') {
            $e['nf_numero'] = 'Informe o número da nota fiscal.';
        }
        if ($valor <= 0) {
            $e['nf_valor'] = 'Informe o valor da nota.';
        }
        if ($e) {
            throw new ValidationException($e);
        }
        Database::transaction(function () use ($id, $nfNumero, $valor, $obs) {
            $pi = self::travar($id);
            self::exigirSituacao($pi, ['Pronta para faturamento'], 'faturar');
            $div = abs($valor - (float) $pi['valor_liquido']) >= 0.01;
            self::mudar($pi, 'Faturada', ['nf_numero' => mb_substr(trim($nfNumero), 0, 40), 'nf_valor' => round($valor, 2), 'faturada_em' => date('Y-m-d H:i:s')],
                'Faturada: NF ' . trim($nfNumero) . ' · ' . moeda($valor) . ($div ? ' (diferente do líquido da PI ' . moeda($pi['valor_liquido']) . ')' : '') . ($obs !== '' ? ' · ' . $obs : ''));
        });
    }

    /** Encerramento após o pagamento. */
    public static function encerrar(int $id, string $obs = ''): void
    {
        if (!Auth::can('financeiro.faturar')) {
            throw new HttpException(403);
        }
        Database::transaction(function () use ($id, $obs) {
            $pi = self::travar($id);
            self::exigirSituacao($pi, ['Faturada'], 'encerrar');
            self::mudar($pi, 'Encerrada', ['encerrada_em' => date('Y-m-d H:i:s')], 'Pagamento recebido: PI encerrada' . ($obs !== '' ? ' · ' . $obs : ''));
        });
    }

    /**
     * RN-55 — cancelar exige motivo e não pode ser desfeito; a PI sai dos totais e as cotas voltam.
     * Administrador, diretor e gerente cancelam qualquer PI que veem; executivo e vendedor só o próprio Rascunho (RN-74).
     */
    public static function cancelar(int $id, string $motivo): void
    {
        if (mb_strlen(trim($motivo)) < 5) {
            throw new ValidationException(['motivo' => 'Informe o motivo do cancelamento.']);
        }
        Database::transaction(function () use ($id, $motivo) {
            $pi = self::travar($id);
            $podeQualquer = Auth::can('pis.cancelar');
            $proprioRascunho = (int) $pi['executivo_id'] === Auth::id() && $pi['situacao'] === 'Rascunho' && Auth::can('pis.criar');
            if (!$podeQualquer && !$proprioRascunho) {
                throw new HttpException(403, 'Executivo e vendedor cancelam só o próprio Rascunho; peça ao gestor (RN-74).');
            }
            self::exigirSituacao($pi, ['Rascunho', 'Enviada', 'Devolvida', 'Corrigida', 'Aprovada', 'Em programação', 'Em veiculação'], 'cancelar');
            self::mudar($pi, 'Cancelada', ['cancelada_em' => date('Y-m-d H:i:s'), 'cancelada_por' => Auth::id(), 'motivo_cancelamento' => mb_substr(trim($motivo), 0, 500)],
                'Cancelada: ' . trim($motivo));
            if ((int) $pi['executivo_id'] !== Auth::id()) {
                Notificacoes::enviar((int) $pi['executivo_id'], 'cancelada', "PI {$pi['numero']} cancelada", trim($motivo), '/pis/' . $id);
            }
        });
    }

    /** Ações disponíveis para a PI e o usuário atual (para os botões da tela). @return array<string,string> */
    public static function acoes(array $pi): array
    {
        $s = $pi['situacao'];
        $meu = (int) $pi['executivo_id'] === Auth::id();
        $altera = PiService::podeAlterar($pi);
        $A = [];
        if (in_array($s, ['Rascunho', 'Enviada', 'Corrigida', 'Aprovada'], true) && $altera) {
            $A['editar'] = 'Abrir e alterar';
        }
        if ($s === 'Devolvida' && $altera) {
            $A['corrigir'] = 'Corrigir';
        }
        if (Auth::can('conferencia.conferir') && in_array($s, ['Enviada', 'Corrigida'], true)) {
            $A['conferir'] = 'Conferir (aprovar ou devolver)';
        }
        if (Auth::can('operacao.programar') && $s === 'Aprovada') {
            $A['programar'] = 'Liberar para programação';
        }
        if (Auth::can('operacao.programar') && $s === 'Em programação') {
            $A['veicular'] = 'Marcar início da veiculação';
        }
        if (Auth::can('operacao.finalizar') && in_array($s, ['Enviada', 'Corrigida', 'Aprovada', 'Em programação', 'Em veiculação'], true)) {
            $A['finalizar'] = 'Veiculação finalizada';
        }
        if (Auth::can('financeiro.faturar') && $s === 'Pronta para faturamento') {
            $A['faturar'] = 'Registrar faturamento';
        }
        if (Auth::can('financeiro.faturar') && $s === 'Faturada') {
            $A['encerrar'] = 'Encerrar (pagamento recebido)';
        }
        if (Auth::can('pis.substituir') && ($altera || Auth::can('pis.cancelar')) && in_array($s, ['Enviada', 'Corrigida', 'Aprovada'], true)) {
            $A['substituir'] = 'Cancela/substitui';
        }
        if (Auth::can('pis.criar') && ($altera || Auth::can('pis.cancelar')) && !in_array($s, ['Cancelada', 'Substituída'], true)) {
            $A['renovar'] = 'Renovar (PI nova)';
        }
        if (!in_array($s, ['Cancelada', 'Substituída', 'Pronta para faturamento', 'Faturada', 'Encerrada'], true) && (Auth::can('pis.cancelar') || ($meu && $s === 'Rascunho' && Auth::can('pis.criar')))) {
            $A['cancelar'] = 'Cancelar PI';
        }
        if (Auth::can('pis.exportar')) {
            $A['excel'] = 'Baixar Excel';
        }
        return $A;
    }
}
