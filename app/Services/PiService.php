<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\HttpException;
use App\Helpers\Calendario;
use App\Services\Calculo\CalculadoraPi;
use App\Services\Calculo\Catalogo;
use App\Services\Calculo\ContextoCalculo;
use App\Services\Calculo\DivisaoMensalTv;

/**
 * Geração e gravação da PI — o coração do CRM.
 *  RN-01 toda venda gera PI no CRM · RN-03/RN-04 numeração na mesma transação · RN-05 gerar de novo mantém o número
 *  RN-06 tipos e PI de origem · RN-17/RN-38 divisão mensal da TV · RN-34/RN-36 cotas travadas e descontadas
 *  RN-51 sem pendência = Enviada, com pendência = Rascunho · RN-53 correção mantém número e devoluções
 *  RN-54 Aprovada alterada volta para Enviada · RN-56 Cancela/substitui · RN-58 PIs encerradas só para consulta
 *  RN-63/RN-79 cliente e agência entram no cadastro · RN-69 reserva vira venda · RN-76 dados do executivo vêm do cadastro
 *  Erros de projeto especial (P02–P04) impedem gerar a PI.
 */
final class PiService
{
    /** RN-58 (ampliada): situações em que a PI não pode mais ser gerada de novo — só consulta. */
    public const BLOQUEADAS = ['Cancelada', 'Substituída', 'Pronta para faturamento', 'Faturada', 'Encerrada', 'Em programação', 'Em veiculação'];
    public const ENCERRADAS = ['Cancelada', 'Substituída'];

    /**
     * RN-76 — nome, matrícula, gestor e contato do executivo vêm do cadastro de usuários.
     * Quem tem pis.editar_outras pode emitir em nome de um executivo da sua visão.
     * @param array<string,mixed> $S
     * @return array{0:array<string,mixed>,1:array<string,mixed>} [estado, executivo]
     */
    public static function aplicarExecutivo(array $S, ?int $donoAtual = null): array
    {
        $pedido = (int) ($S['f']['executivo_id'] ?? 0);
        $uid = (int) Auth::id();
        $execId = $donoAtual ?? $uid;
        if ($pedido && $pedido !== $execId && Auth::can('pis.editar_outras')) {
            $alvo = Database::first('SELECT id, gestor_id FROM usuarios WHERE id = ? AND ativo = 1', [$pedido]);
            if ($alvo && Auth::podeVerPi(['executivo_id' => $pedido, 'gestor_id' => $alvo['gestor_id']])) {
                $execId = $pedido;
            }
        }
        $e = Database::first('SELECT u.id, u.nome, u.matricula, u.telefone, u.email, u.gestor_id, g.nome AS gestor_nome FROM usuarios u LEFT JOIN usuarios g ON g.id = u.gestor_id WHERE u.id = ?', [$execId]);
        if (!$e) {
            throw new BusinessException('Executivo não encontrado.');
        }
        $S['f']['executivo_id'] = (int) $e['id'];
        $S['f']['exec'] = (string) $e['nome'];
        $S['f']['mat'] = (string) ($e['matricula'] ?? '');
        $S['f']['gestor'] = (string) ($e['gestor_nome'] ?? '');
        $S['f']['ccom'] = implode(' · ', array_filter([$e['telefone'] ?? '', $e['email'] ?? '']));
        return [$S, $e];
    }

    /**
     * Contexto de cálculo com projetos, cotas ocupadas por terceiros e verificador de aprovação.
     * @param list<int> $piIds PIs sendo geradas de novo (não contam como "vendidas por outros")
     */
    public static function contexto(array $piIds, ?int $reservaId, ?int $rascunhoId): ContextoCalculo
    {
        return new ContextoCalculo(
            new Catalogo(),
            ProjetoService::ativos(),
            ProjetoService::ocupadasFn($piIds, $reservaId),
            AprovacaoService::verificador($rascunhoId, $piIds),
        );
    }

    /**
     * PIs já existentes referenciadas pelo formulário (o número da PI, os números mensais da TV e a PI substituída).
     * @param array<string,mixed> $f
     * @return array{proprias:array<string,array<string,mixed>>,subst:?array<string,mixed>}
     */
    public static function existentes(array $f): array
    {
        $nums = array_values(array_unique(array_filter(array_merge([(string) ($f['num'] ?? '')], array_map('strval', (array) ($f['nums'] ?? []))))));
        $proprias = [];
        if ($nums) {
            [$in, $par] = Database::in($nums);
            foreach (Database::all("SELECT * FROM pis WHERE numero IN {$in}", $par) as $p) {
                $proprias[(string) $p['numero']] = $p;
            }
            foreach ($nums as $n) {
                if (!isset($proprias[$n])) {
                    throw new BusinessException("O número {$n} não existe no registro. O número da PI é dado pelo sistema ao gerar.");
                }
            }
        }
        $subst = null;
        if (trim((string) ($f['subst'] ?? '')) !== '') {
            $subst = Database::first('SELECT * FROM pis WHERE numero = ?', [trim((string) $f['subst'])]);
            if (!$subst) {
                throw new BusinessException('PI de origem do Cancela/substitui não encontrada.');
            }
        }
        return ['proprias' => $proprias, 'subst' => $subst];
    }

    /** @param array<string,mixed> $pi */
    public static function podeAlterar(array $pi): bool
    {
        if (!Auth::can('pis.criar')) {
            return false;
        }
        if ((int) $pi['executivo_id'] === Auth::id()) {
            return true;
        }
        return Auth::can('pis.editar_outras') && Auth::podeVerPi($pi);
    }

    /**
     * Cálculo para a tela (prévia ao vivo), sem gravar nada.
     * @param array<string,mixed> $S
     * @return array<string,mixed>
     */
    public static function previa(array $S, ?int $rascunhoId): array
    {
        $S = CalculadoraPi::normalizar($S);
        $ex = self::existentesSeguro($S['f']);
        $dono = $ex ? (int) reset($ex)['executivo_id'] : null;
        [$S] = self::aplicarExecutivo($S, $dono);
        $ids = array_map(fn ($p) => (int) $p['id'], $ex);
        $R = (new CalculadoraPi(self::contexto($ids, (int) ($S['f']['res'] ?? 0) ?: null, $rascunhoId)))->calcular($S);
        $R['conflitos'] = ReservaService::conflitos($S, $R);
        $R['executivo'] = ['exec' => $S['f']['exec'], 'mat' => $S['f']['mat'], 'gestor' => $S['f']['gestor'], 'ccom' => $S['f']['ccom']];
        return $R;
    }

    /** @param array<string,mixed> $f @return array<string,array<string,mixed>> */
    private static function existentesSeguro(array $f): array
    {
        try {
            return self::existentes($f)['proprias'];
        } catch (BusinessException) {
            return [];
        }
    }

    /**
     * Gera (ou gera de novo) a PI. Tudo numa transação: trava cotas, reserva número(s), grava PI(s), itens, versões e histórico.
     * @param array<string,mixed> $S
     * @return array{pis:list<array<string,mixed>>,grupo:?string}
     */
    public static function gerar(array $S, ?int $rascunhoId = null): array
    {
        if (!Auth::can('pis.criar')) {
            throw new HttpException(403, 'Seu perfil não gera PIs (RN-74).');
        }
        $S = CalculadoraPi::normalizar($S);
        $vid = (int) ($S['f']['veic'] ?? 0);
        if (!$vid || !Database::value('SELECT id FROM veiculos WHERE id = ? AND ativo = 1', [$vid])) {
            throw new BusinessException('Escolha o veículo antes de gerar.');
        }

        // Linhas de numeração criadas antes da transação (ano da emissão e anos do período, para a TV dividida)
        $anos = [(int) substr((string) (($S['f']['emissao'] ?? '') ?: date('Y-m-d')), 0, 4)];
        foreach (Calendario::mesesDoPeriodo((string) ($S['f']['ini'] ?? ''), (string) ($S['f']['fim'] ?? '')) as $M) {
            $anos[] = (int) $M['y'];
        }
        foreach (array_unique($anos) as $a) {
            NumeracaoService::prepararSequencia($vid, $a);
        }

        $resultado = Database::transaction(function () use ($S, $vid, $rascunhoId) {
            $ex = self::existentes($S['f']);
            $proprias = $ex['proprias'];
            $subst = $ex['subst'];
            foreach ($proprias as $p) {
                if (!self::podeAlterar($p)) {
                    throw new HttpException(403, "A PI {$p['numero']} é de outro executivo: só ele ou a gerência pode alterá-la.");
                }
                if (in_array($p['situacao'], self::BLOQUEADAS, true)) {
                    throw new BusinessException("A PI {$p['numero']} está {$p['situacao']} e não pode ser alterada. Use Renovar, Alteração ou Cancela/substitui (RN-58).");
                }
                if ((int) $p['veiculo_id'] !== $vid) {
                    throw new BusinessException("A PI {$p['numero']} é de outro veículo. Para trocar o veículo, use Cancela/substitui.");
                }
            }
            if ($subst) {
                if ($proprias) {
                    throw new BusinessException('Cancela/substitui gera uma PI nova, com número novo.');
                }
                if (!in_array($subst['situacao'], ['Enviada', 'Corrigida', 'Aprovada'], true)) {
                    throw new BusinessException("A PI {$subst['numero']} está {$subst['situacao']}: Cancela/substitui só vale para PI Enviada, Corrigida ou Aprovada (RN-56).");
                }
                if (!self::podeAlterar($subst) && !Auth::can('pis.cancelar')) {
                    throw new HttpException(403, "Você não pode substituir a PI {$subst['numero']}.");
                }
                $S['f']['tipo'] = 'Cancela/substitui';
                $S['f']['origem'] = $subst['numero'];
            }
            $dono = $proprias ? (int) reset($proprias)['executivo_id'] : null;
            [$S, $exec] = self::aplicarExecutivo($S, $dono);

            // RN-34 — trava as cotas pedidas e recalcula com as vendas e reservas atualizadas
            ProjetoService::travarCotas(array_map(fn ($l) => (int) ($l['cota'] ?? 0), $S['prj']));
            $piIdsExcluir = array_map(fn ($p) => (int) $p['id'], array_values($proprias));
            if ($subst) {
                $piIdsExcluir[] = (int) $subst['id']; // RN-56: as cotas da antiga passam para a nova sem conflito
            }
            $reservaId = (int) ($S['f']['res'] ?? 0) ?: null;
            if ($reservaId) {
                $res = Database::first("SELECT * FROM reservas WHERE id = ? AND situacao = 'ativa' AND expira_em > NOW()", [$reservaId]);
                if (!$res || (int) $res['usuario_id'] !== Auth::id()) {
                    $reservaId = null;
                    $S['f']['res'] = '';
                }
            }
            $ctx = self::contexto($piIdsExcluir, $reservaId, $rascunhoId);
            $calc = new CalculadoraPi($ctx);
            $R = $calc->calcular($S);
            if (!$R['V']) {
                throw new BusinessException('Escolha o veículo antes de gerar.');
            }
            if ($R['projBlock']) {
                $msgs = array_map(fn ($c) => $c['fix'], array_filter($R['C'], fn ($c) => in_array($c['code'], CalculadoraPi::BLOQUEIAM, true) && $c['st'] !== 'OK'));
                throw new BusinessException('Venda bloqueada: ' . implode(' ', array_unique($msgs)), 'projeto_bloqueado');
            }
            if ($msg = CalculadoraPi::erroDiasSemExibicao($R)) {
                throw new BusinessException($msg, 'dias_sem_exibicao');
            }

            // RN-03/RN-04/RN-17 — numeração (uma por PI mensal na TV)
            $emissao = (string) ($S['f']['emissao'] ?? '') ?: date('Y-m-d');
            $anoEmissao = (int) substr($emissao, 0, 4);
            if ($R['splitTV']) {
                $chaves = array_map(fn ($M) => $M['key'], $R['months']);
                $S['f']['nums'] = (array) ($S['f']['nums'] ?? []);
                foreach (array_keys($S['f']['nums']) as $mk) {
                    if (!in_array($mk, $chaves, true) && $S['f']['nums'][$mk] !== '') {
                        throw new BusinessException("O período mudou e a PI {$S['f']['nums'][$mk]} ficaria sem mês. Cancele essa PI mensal ou use Cancela/substitui.");
                    }
                }
                if (trim((string) ($S['f']['num'] ?? '')) !== '' && !in_array((string) $S['f']['num'], $S['f']['nums'], true)) {
                    throw new BusinessException('Esta PI de TV era de um mês só e agora atravessa meses: use Cancela/substitui para dividi-la por mês.');
                }
                foreach ($R['months'] as $M) {
                    if (empty($S['f']['nums'][$M['key']])) {
                        $S['f']['nums'][$M['key']] = NumeracaoService::proximo($vid, (int) $M['y'])['numero'];
                    }
                }
                $S['f']['num'] = '';
            } else {
                if (!empty($S['f']['nums']) && count(array_filter((array) $S['f']['nums'])) > 1) {
                    throw new BusinessException('Esta PI de TV estava dividida por mês e agora cabe em um mês: cancele as PIs mensais que sobram ou use Cancela/substitui.');
                }
                if (!empty($S['f']['nums']) && trim((string) ($S['f']['num'] ?? '')) === '') {
                    $S['f']['num'] = (string) reset($S['f']['nums']);
                }
                $S['f']['nums'] = [];
                if (trim((string) ($S['f']['num'] ?? '')) === '') {
                    $S['f']['num'] = NumeracaoService::proximo($vid, $anoEmissao)['numero'];
                }
            }

            // RN-63/RN-79 — cliente e agência no cadastro
            $clienteId = ClienteService::upsertDaPi($S['f']);
            $agenciaId = AgenciaService::upsertDaPi($S['f']);

            $partes = (new DivisaoMensalTv($calc))->dividir($S);
            $grupoDesc = count($partes) > 1 ? implode(' + ', array_map(fn ($p) => $p['f']['num'], $partes)) : null;
            $grupoId = null;
            if ($grupoDesc !== null) {
                $grupoId = null;
                foreach ($proprias as $p) {
                    if ($p['grupo_id']) {
                        $grupoId = (int) $p['grupo_id'];
                    }
                }
                if ($grupoId) {
                    Database::query('UPDATE pi_grupos SET descricao = ? WHERE id = ?', [$grupoDesc, $grupoId]);
                } else {
                    $grupoId = Database::insert('pi_grupos', ['descricao' => $grupoDesc]);
                }
            }

            $gravadas = [];
            foreach ($partes as $s) {
                $Rp = $calc->calcular($s);
                $old = $proprias[$s['f']['num']] ?? null;
                $gravadas[] = self::gravarParte($S, $s, $Rp, $old, $exec, $clienteId, $agenciaId, $grupoId, $reservaId, $subst);
            }
            $ids = array_map(fn ($g) => $g['id'], $gravadas);

            if ($subst) {
                Database::query("UPDATE pis SET situacao = 'Substituída', substituida_por_id = ?, updated_by = ? WHERE id = ? AND situacao IN ('Enviada','Corrigida','Aprovada')", [$ids[0], Auth::id(), (int) $subst['id']]);
                self::historico((int) $subst['id'], $subst['situacao'], 'Substituída', 'Substituída por ' . ($grupoDesc ?? $gravadas[0]['numero']));
                Auditoria::registrar('pi_substituida', 'pi', (int) $subst['id'], "PI {$subst['numero']} substituída por " . ($grupoDesc ?? $gravadas[0]['numero']));
            }
            if ($reservaId) {
                ReservaService::converter($reservaId, $ids[0]);
            }
            if ($rascunhoId) {
                AprovacaoService::vincularPi($rascunhoId, $ids);
            }
            return ['pis' => $gravadas, 'grupo' => $grupoDesc, 'estado' => $S];
        }, 4);

        // Depois de confirmar a transação: avisos e limpeza do rascunho
        foreach ($resultado['pis'] as $g) {
            if ($g['mudou'] && in_array($g['situacao'], ['Enviada', 'Corrigida'], true)) {
                $conf = Notificacoes::usuariosDoPerfil('conf') ?: Notificacoes::usuariosComPermissao('conferencia.conferir');
                Notificacoes::enviarVarios($conf, 'conferencia', "PI {$g['numero']} aguardando conferência",
                    ($g['situacao'] === 'Corrigida' ? 'PI corrigida pelo executivo e reenviada. ' : 'Nova PI enviada à Conferência. ') . 'Cliente: ' . $g['cliente'], '/conferencia/' . $g['id'], 'conf-' . $g['id'] . '-v' . $g['versao']);
            }
        }
        if ($rascunhoId) {
            Database::query('DELETE FROM pi_rascunhos WHERE id = ? AND usuario_id = ?', [$rascunhoId, Auth::id()]);
        }
        return ['pis' => $resultado['pis'], 'grupo' => $resultado['grupo']];
    }

    /**
     * Grava uma PI (ou uma PI mensal da TV) com itens, grade, parcelas, entregas, versão e histórico.
     * @param array<string,mixed> $S estado completo da campanha (vai para estado_formulario)
     * @param array<string,mixed> $s estado desta PI
     * @param array<string,mixed> $R resultado do cálculo desta PI
     * @param array<string,mixed>|null $old registro anterior (geração de novo)
     * @param array<string,mixed> $exec
     * @param array<string,mixed>|null $subst
     * @return array<string,mixed>
     */
    private static function gravarParte(array $S, array $s, array $R, ?array $old, array $exec, ?int $clienteId, ?int $agenciaId, ?int $grupoId, ?int $reservaId, ?array $subst): array
    {
        $f = $s['f'];
        $T = $R['T'];
        $pend = (int) $R['pend'];
        $estadoParte = $s;
        unset($estadoParte['f']['res'], $estadoParte['f']['subst']);
        $hash = hash('sha256', json_encode([self::canonico($estadoParte), $pend, round($T['liq'], 2)], JSON_UNESCAPED_UNICODE));
        if ($old) {
            $ultimoHash = (string) Database::value('SELECT hash FROM pi_versoes WHERE pi_id = ? ORDER BY versao DESC LIMIT 1', [(int) $old['id']]);
            if ($ultimoHash === $hash) {
                // RN-54: baixar de novo sem alteração não muda a situação nem cria versão
                Database::query('UPDATE pis SET estado_formulario = ? WHERE id = ?', [json_encode($S, JSON_UNESCAPED_UNICODE), (int) $old['id']]);
                return ['id' => (int) $old['id'], 'numero' => $old['numero'], 'situacao' => $old['situacao'], 'pendencias' => $pend, 'versao' => (int) $old['versao_atual'], 'mudou' => false, 'cliente' => (string) $old['cliente_razao']];
            }
        }
        $antes = $old['situacao'] ?? null;
        $devolvida = $old && ((int) $old['qtd_devolucoes'] > 0) && in_array($antes, ['Devolvida', 'Corrigida', 'Rascunho'], true);
        $situacao = $pend > 0 ? 'Rascunho' : ($devolvida ? 'Corrigida' : 'Enviada');

        $num = NumeracaoService::decompor((string) $f['num']) ?? throw new \LogicException('Número de PI inválido.');
        $V = $R['V'];
        $tabelaId = null;
        foreach ((new Catalogo())->tabelas((int) $V['id'], (int) $R['ano']) as $t) {
            if ($t['codigo'] === $R['tab']) {
                $tabelaId = (int) $t['id'];
            }
        }
        $origemId = null;
        if (trim((string) ($f['origem'] ?? '')) !== '') {
            $origemId = Database::value('SELECT id FROM pis WHERE numero = ?', [trim((string) $f['origem'])]);
            $origemId = $origemId ? (int) $origemId : null;
        }
        $aprov = $R['aprovacao'] ?? null;
        if ($aprov && $aprov['ok']) {
            $f['gs_nome'] = $aprov['nome'];
            $f['gs_data'] = $aprov['data'];
        }
        $snapshot = [
            'emissora' => ['nome' => $V['nome'], 'titulo' => $V['titulo_pi'], 'razao' => $V['empresa_razao'], 'cnpj' => $V['empresa_cnpj'], 'endereco' => $V['empresa_endereco'],
                'telefone' => $V['empresa_telefone'], 'im' => $V['empresa_im'], 'banco' => $V['empresa_banco'], 'cl6' => $V['clausula_6'], 'cl7' => $V['clausula_7'], 'atendimento' => $V['texto_atendimento']],
            'clausulas' => self::clausulas(),
            'f' => $f,
        ];
        $agora = date('Y-m-d H:i:s');
        $dados = [
            'numero' => $f['num'], 'ano' => $num['ano'], 'sequencial' => $num['sequencial'], 'veiculo_id' => (int) $V['id'], 'empresa_id' => (int) $V['empresa_id'], 'tabela_preco_id' => $tabelaId,
            'tipo' => in_array($f['tipo'] ?? '', ['Nova', 'Renovação', 'Alteração', 'Cancelamento', 'Cancela/substitui'], true) ? $f['tipo'] : 'Nova',
            'pi_origem_id' => $origemId, 'pi_origem_numero' => trim((string) ($f['origem'] ?? '')) ?: null, 'grupo_id' => $grupoId, 'parte' => $f['parte'] ?? null,
            'mes_referencia' => $f['mesKey'] ?? (($R['months'][0]['key'] ?? null)), 'numero_rp' => trim((string) ($f['rp'] ?? '')) ?: null,
            'data_emissao' => Calendario::data((string) ($f['emissao'] ?? '')) ? $f['emissao'] : date('Y-m-d'),
            'executivo_id' => (int) $exec['id'], 'gestor_id' => $exec['gestor_id'] ? (int) $exec['gestor_id'] : null, 'contato_comercial' => mb_substr((string) ($f['ccom'] ?? ''), 0, 160) ?: null,
            'venda' => ($f['venda'] ?? '') === 'Agência' ? 'Agência' : 'Direta', 'cliente_id' => $clienteId, 'cliente_razao' => mb_substr(trim((string) ($f['cli_razao'] ?? '')), 0, 160) ?: null,
            'cliente_documento' => \App\Validators\DocumentoValidator::verificar((string) ($f['cli_cnpj'] ?? ''))['chave'],
            'agencia_id' => ($f['venda'] ?? '') === 'Agência' ? $agenciaId : null, 'agencia_razao' => ($f['venda'] ?? '') === 'Agência' ? (mb_substr(trim((string) ($f['ag_nome'] ?? '')), 0, 160) ?: null) : null,
            'comissao_pct' => round($T['comPct'] * 100, 2), 'titulo' => mb_substr(trim((string) ($f['titulo'] ?? '')), 0, 160) ?: null, 'produto' => mb_substr(trim((string) ($f['produto'] ?? '')), 0, 160) ?: null,
            'data_inicio' => Calendario::data((string) ($f['ini'] ?? '')) ? $f['ini'] : null, 'data_fim' => Calendario::data((string) ($f['fim'] ?? '')) ? $f['fim'] : null,
            'prazo_pagamento_dias' => is_numeric($f['prazo'] ?? null) ? (int) $f['prazo'] : null, 'praca_cobranca' => trim((string) ($f['pcob'] ?? '')) ?: null,
            'forma_pagamento' => trim((string) ($f['forma'] ?? '')) ?: null, 'producao_taxas' => round($T['producao'], 2),
            'valor_tabela' => round($T['tab'], 2), 'valor_bonificado' => round($T['bonif'], 2), 'desconto_nominal' => round($T['desc'], 2), 'desconto_pct' => round($T['descPct'], 6),
            'valor_negociado' => round($T['neg'], 2), 'valor_comissao' => round($T['com'], 2), 'valor_liquido' => round($T['liq'], 2), 'total_insercoes' => (int) $T['nIns'],
            'grp' => round($T['grp'], 5), 'impactos' => (int) $T['imp'], 'cpm' => round($T['cpm'], 2), 'qtd_pendencias' => $pend,
            'pendencias' => json_encode(array_values(array_filter($R['C'], fn ($c) => $c['st'] !== 'OK')), JSON_UNESCAPED_UNICODE),
            'situacao' => $situacao, 'reserva_id' => $reservaId, 'estado_formulario' => json_encode($S, JSON_UNESCAPED_UNICODE),
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE), 'updated_by' => Auth::id(),
        ];
        if (in_array($situacao, ['Enviada', 'Corrigida'], true) && empty($old['enviada_em'])) {
            $dados['enviada_em'] = $agora;
            $dados['fora_do_prazo'] = self::foraDoPrazo($s, $R) ? 1 : 0;
        }
        if ($old) {
            $id = (int) $old['id'];
            $versao = (int) $old['versao_atual'] + 1;
            Database::update('pis', $dados + ['versao_atual' => $versao], ['id' => $id]);
            foreach (['pi_itens', 'pi_parcelas', 'pi_entregas_especiais'] as $t) {
                Database::query("DELETE FROM {$t} WHERE pi_id = ?", [$id]);
            }
            if ($situacao === 'Corrigida') {
                Database::query('UPDATE pi_devolucoes SET corrigida_em = NOW() WHERE pi_id = ? AND corrigida_em IS NULL', [$id]);
            }
        } else {
            $versao = 1;
            $id = Database::insert('pis', $dados + ['versao_atual' => 1, 'created_by' => (int) Auth::id()]);
        }
        self::gravarItens($id, $R, $s);
        $resultadoJson = $R;
        unset($resultadoJson['V']);
        Database::insert('pi_versoes', [
            'pi_id' => $id, 'versao' => $versao, 'estado' => json_encode($s, JSON_UNESCAPED_UNICODE),
            'resultado' => json_encode($resultadoJson, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR), 'hash' => $hash,
            'situacao' => $situacao, 'gerado_por' => (int) Auth::id(), 'gerado_em' => $agora,
        ]);
        $evento = !$old ? ($pend ? "Gerada como rascunho ({$pend} pendência(s))" : 'Gerada e enviada à Conferência')
            : ($antes === 'Devolvida' ? ($pend ? "Correção salva com {$pend} pendência(s)" : 'Corrigida e reenviada à Conferência')
                : ($antes === 'Aprovada' ? 'Alterada após a aprovação: volta para a Conferência (RN-54)' : "Gerada de novo (versão {$versao})"));
        if ($subst && !$old) {
            $evento .= " · substitui a PI {$subst['numero']}";
        }
        self::historico($id, $antes, $situacao, $evento, ['versao' => $versao, 'pendencias' => $pend, 'liquido' => round($T['liq'], 2)]);
        Auditoria::registrar($old ? 'pi_alterada' : 'pi_gerada', 'pi', $id, "PI {$f['num']}: {$evento}", $old ? array_intersect_key($old, $dados) : null, array_diff_key($dados, ['estado_formulario' => 1, 'snapshot' => 1, 'pendencias' => 1]));
        return ['id' => $id, 'numero' => (string) $f['num'], 'situacao' => $situacao, 'pendencias' => $pend, 'versao' => $versao, 'mudou' => true, 'cliente' => (string) ($dados['cliente_razao'] ?? '')];
    }

    /**
     * Ordem estável de chaves e tipos (o MySQL reordena as chaves de colunas JSON), para comparar versões.
     */
    public static function canonico(mixed $v): mixed
    {
        if (is_array($v)) {
            if (!array_is_list($v)) {
                ksort($v, SORT_STRING);
            }
            return array_map([self::class, 'canonico'], $v);
        }
        if (is_int($v) || is_float($v)) {
            return (string) (0 + $v);
        }
        return $v === null ? '' : $v;
    }

    /** @param array<string,mixed> $R @param array<string,mixed> $s */
    private static function gravarItens(int $piId, array $R, array $s): void
    {
        $ordem = 0;
        foreach ($R['ins'] as $x) {
            $l = $x['l'];
            $itemId = Database::insert('pi_itens', [
                'pi_id' => $piId, 'bloco' => 'insercao', 'ordem' => ++$ordem, 'programa_id' => $x['programa_id'], 'sigla' => $x['sigla'] ?: null, 'descricao' => mb_substr((string) $x['nome'], 0, 255) ?: null,
                'mes_ref' => $x['M']['key'] ?? null, 'duracao_seg' => ((int) ($l['dur'] ?? 0)) ?: null, 'indice' => $x['idx'], 'cobranca' => $x['cob'], 'quantidade' => $x['n'],
                'preco_unitario' => $x['unit'] !== null ? round($x['unit'], 2) : null, 'preco_manual' => (float) ($l['man'] ?? 0) > 0 ? 1 : 0, 'desconto_pct' => round((float) $x['desc'], 2),
                'valor_tabela' => round($x['bruto'], 2), 'valor_total' => round($x['total'], 2), 'valor_bonificado' => round($x['bonif'], 2),
                'grp' => $x['grp'] ? round($x['grp'], 5) : null, 'impactos' => $x['imp'] ?: null, 'detalhes' => json_encode(['dias' => $x['dias']], JSON_UNESCAPED_UNICODE),
            ]);
            if ($x['M']) {
                foreach ($x['g'] as $d => $q) {
                    if ((int) $q > 0 && (int) $d >= 1 && (int) $d <= $x['M']['days']) {
                        Database::insert('pi_insercoes_diarias', ['pi_item_id' => $itemId, 'data' => sprintf('%s-%02d', $x['M']['key'], (int) $d), 'quantidade' => (int) $q]);
                    }
                }
            }
        }
        foreach ($R['pat'] as $x) {
            $itemId = Database::insert('pi_itens', [
                'pi_id' => $piId, 'bloco' => 'patrocinio', 'ordem' => ++$ordem, 'programa_id' => $x['programa_id'], 'sigla' => $x['sigla'] ?: null, 'descricao' => mb_substr((string) $x['nome'], 0, 255),
                'mes_ref' => $x['mk'] ?: null, 'cobranca' => $x['cob'], 'quantidade' => $x['cot'], 'preco_unitario' => $x['valor'] !== null ? round($x['valor'] / $x['cot'], 2) : null,
                'preco_manual' => (float) ($x['l']['man'] ?? 0) > 0 ? 1 : 0, 'desconto_pct' => round((float) $x['desc'], 2), 'valor_tabela' => round((float) ($x['valor'] ?? 0), 2),
                'valor_total' => round($x['total'], 2), 'valor_bonificado' => round($x['bonif'], 2), 'observacao' => mb_substr(trim((string) ($x['l']['obs'] ?? '')), 0, 255) ?: null,
                'detalhes' => json_encode(['dias' => $x['dias'], 'insercoes' => $x['n']], JSON_UNESCAPED_UNICODE),
            ]);
            [$a, $b, $c, $d, $e, $g] = $x['esq'];
            Database::insert('pi_patrocinios', ['pi_item_id' => $itemId, 'ins_30' => $a, 'ins_60' => $b, 'vinheta' => $c, 'flash' => $d, 'testemunhal' => $e, 'entrevista' => $g, 'outras' => $x['outras']]);
        }
        foreach ($R['dig'] as $x) {
            Database::insert('pi_itens', [
                'pi_id' => $piId, 'bloco' => 'digital', 'ordem' => ++$ordem, 'formato_id' => $x['formato_id'], 'sigla' => $x['sigla'] ?: null, 'descricao' => mb_substr((string) $x['nome'], 0, 255),
                'cobranca' => $x['cob'], 'quantidade' => $x['mod'] === 'CPM' ? 0 : $x['qtd'], 'impressoes' => $x['mod'] === 'CPM' ? (int) $x['ent'] : null,
                'data_inicio' => $x['ini'] ?: null, 'data_fim' => $x['fim'] ?: null, 'preco_unitario' => $x['unit'] !== null ? round($x['unit'], 2) : null,
                'preco_manual' => (float) ($x['l']['man'] ?? 0) > 0 ? 1 : 0, 'desconto_pct' => round((float) $x['desc'], 2), 'valor_tabela' => round($x['tab'], 2),
                'valor_total' => round($x['total'], 2), 'valor_bonificado' => round($x['bonif'], 2), 'observacao' => mb_substr(trim((string) ($x['l']['obs'] ?? '')), 0, 255) ?: null,
                'detalhes' => json_encode(['modelo' => $x['mod'], 'dias' => $x['dias'], 'meses' => $x['meses'], 'proporcao' => $x['mdet'], 'formato' => $x['formato']], JSON_UNESCAPED_UNICODE),
            ]);
        }
        foreach ($R['avi'] as $x) {
            $l = $x['l'];
            Database::insert('pi_itens', [
                'pi_id' => $piId, 'bloco' => 'publicidade_legal', 'ordem' => ++$ordem, 'formato_id' => $x['formato_id'], 'sigla' => $x['sigla'] ?: null, 'descricao' => mb_substr((string) $x['nome'], 0, 255) ?: null,
                'quantidade' => $x['pub'], 'colunas' => (float) ($l['col'] ?? 0) ?: null, 'centimetros' => (float) ($l['cm'] ?? 0) ?: null,
                'preco_unitario' => $x['unit'] !== null ? round($x['unit'], 2) : null, 'preco_manual' => (float) ($l['man'] ?? 0) > 0 ? 1 : 0, 'desconto_pct' => round((float) $x['desc'], 2),
                'valor_tabela' => round($x['tab'], 2), 'valor_total' => round($x['total'], 2),
                'detalhes' => json_encode(['material' => $l['mat'] ?? '', 'codigo' => $l['cod'] ?? '', 'datas' => $l['datas'] ?? '', 'cmcol' => $x['cmcol']], JSON_UNESCAPED_UNICODE),
            ]);
        }
        foreach ($R['prj'] as $x) {
            Database::insert('pi_itens', [
                'pi_id' => $piId, 'bloco' => 'projeto', 'ordem' => ++$ordem, 'projeto_cota_id' => $x['cota_id'], 'sigla' => $x['codigo'] ?: null,
                'descricao' => mb_substr($x['nome'] . ($x['cota'] ? ' · ' . $x['cota'] : ''), 0, 255), 'cobranca' => $x['cob'], 'quantidade' => $x['q'],
                'preco_unitario' => $x['unit'], 'desconto_pct' => round((float) $x['desc'], 2), 'valor_tabela' => round($x['tab'], 2), 'valor_total' => round($x['total'], 2),
                'valor_bonificado' => round($x['bonif'], 2), 'observacao' => mb_substr(trim((string) ($x['l']['obs'] ?? '')), 0, 255) ?: null,
                'detalhes' => json_encode(['projeto_id' => $x['projeto_id'], 'venda_ate' => $x['ate']], JSON_UNESCAPED_UNICODE),
            ]);
        }
        foreach ($R['ent'] as $i => $e) {
            Database::insert('pi_entregas_especiais', [
                'pi_id' => $piId, 'ordem' => $i + 1, 'tipo' => mb_substr((string) $e['tipo'], 0, 40), 'especificacao' => mb_substr(trim((string) ($e['esp'] ?? '')), 0, 255) ?: null,
                'quantidade' => is_numeric($e['qtd'] ?? null) ? (int) $e['qtd'] : null, 'area_responsavel' => trim((string) ($e['area'] ?? '')) ?: null,
                'numero_briefing' => trim((string) ($e['brief'] ?? '')) ?: null,
                'aval_juridico' => in_array($e['jur'] ?? '', ['Pendente', 'Aprovado'], true) ? $e['jur'] : 'Não se aplica',
            ]);
        }
        $n = 0;
        foreach ($R['parcs'] as $p) {
            if (Calendario::data((string) ($p['venc'] ?? ''))) {
                Database::insert('pi_parcelas', ['pi_id' => $piId, 'numero' => ++$n, 'vencimento' => $p['venc'], 'valor' => round((float) ($p['val'] ?? 0), 2)]);
            }
        }
    }

    /**
     * SLA de envio: menos dias úteis entre o envio e a 1ª veiculação do que o prazo (5, ou 10 com entregas especiais;
     * promoção/sorteio usa o prazo do jurídico quando configurado). PI fora do prazo não é recusada: só é marcada.
     * @param array<string,mixed> $s @param array<string,mixed> $R
     */
    public static function foraDoPrazo(array $s, array $R): bool
    {
        $ini = (string) ($s['f']['ini'] ?? '');
        if (!Calendario::data($ini)) {
            return false;
        }
        $sla = Configuracao::inteiro('sla_envio_dias_uteis', 5);
        if ($R['ent']) {
            $sla = Configuracao::inteiro('sla_envio_entregas_dias_uteis', 10);
            $sorteio = array_filter($R['ent'], fn ($e) => preg_match('/sorteio/i', (string) $e['tipo']));
            if ($sorteio && Configuracao::inteiro('sla_envio_sorteio_dias_uteis', 0) > 0) {
                $sla = Configuracao::inteiro('sla_envio_sorteio_dias_uteis', 0);
            }
        }
        return Calendario::diasUteisEntre(date('Y-m-d'), $ini) < $sla;
    }

    /** RN-47 — cláusulas fixas vigentes, com o texto da multa por rescisão. @return list<string> */
    public static function clausulas(): array
    {
        $multa = Configuracao::texto('multa_rescisao_texto', '[texto a definir pelo jurídico]');
        return array_map(fn ($r) => str_replace('{MULTA_RESCISAO}', $multa, (string) $r['texto']), Database::all('SELECT texto FROM clausulas WHERE ativo = 1 ORDER BY ordem'));
    }

    /** @param array<string,mixed>|null $detalhes */
    public static function historico(int $piId, ?string $de, ?string $para, string $evento, ?array $detalhes = null): void
    {
        $u = Auth::user();
        Database::insert('pi_historico', [
            'pi_id' => $piId, 'situacao_anterior' => $de, 'situacao_nova' => $para, 'evento' => mb_substr($evento, 0, 255),
            'detalhes' => $detalhes ? json_encode($detalhes, JSON_UNESCAPED_UNICODE) : null, 'usuario_id' => $u['id'] ?? null, 'usuario_nome' => $u['nome'] ?? 'sistema',
        ]);
    }

    /** @return array<string,mixed> PI com permissão de leitura conferida */
    public static function obter(int $id): array
    {
        $pi = Database::first(
            'SELECT p.*, v.nome AS veiculo, v.tipo AS veiculo_tipo, v.prefixo, e.nome_curto AS empresa, u.nome AS executivo, g.nome AS gestor
               FROM pis p JOIN veiculos v ON v.id = p.veiculo_id JOIN empresas e ON e.id = p.empresa_id
               JOIN usuarios u ON u.id = p.executivo_id LEFT JOIN usuarios g ON g.id = p.gestor_id WHERE p.id = ?',
            [$id]
        );
        if (!$pi) {
            throw new HttpException(404, 'PI não encontrada.');
        }
        if (!Auth::podeVerPi($pi)) {
            throw new HttpException(403, 'Esta PI não está entre as que você pode ver (RN-73).');
        }
        return $pi;
    }

    /**
     * Abre a PI no formulário (RN-49/RN-53/RN-56): cria um rascunho de trabalho a partir do estado gravado.
     * @param 'edicao'|'correcao'|'renovacao'|'substituicao' $modo
     */
    public static function abrir(int $piId, string $modo): int
    {
        $pi = self::obter($piId);
        $S = json_decode((string) $pi['estado_formulario'], true) ?: [];
        $S = CalculadoraPi::normalizar($S);
        $hoje = date('Y-m-d');
        if ($modo === 'edicao' || $modo === 'correcao') {
            if (!self::podeAlterar($pi)) {
                throw new HttpException(403, 'Só o executivo da PI ou a gerência pode alterá-la.');
            }
            if (in_array($pi['situacao'], self::BLOQUEADAS, true)) {
                throw new BusinessException("A PI {$pi['numero']} está {$pi['situacao']}: abre só para consulta (RN-58).");
            }
            $S['f']['res'] = '';
            $S['f']['subst'] = '';
        } elseif ($modo === 'renovacao') {
            if (!Auth::can('pis.criar') || !(self::podeAlterar($pi) || Auth::can('pis.cancelar'))) {
                throw new HttpException(403);
            }
            if (in_array($pi['situacao'], self::ENCERRADAS, true)) {
                throw new BusinessException('PI cancelada ou substituída não pode ser renovada.');
            }
            // RN-49: Renovação = PI nova, número novo, mês seguinte como período, grade vazia, aceite e aprovações em branco
            $f = &$S['f'];
            $f['origem'] = $pi['numero'];
            $f['num'] = '';
            $f['nums'] = [];
            $f['tipo'] = 'Renovação';
            $f['emissao'] = $hoje;
            $base = Calendario::data((string) (($f['fim'] ?? '') ?: ($f['ini'] ?? '')));
            if ($base) {
                $n = $base->modify('first day of next month');
                $f['ini'] = $n->format('Y-m-d');
                $f['fim'] = $n->format('Y-m-t');
            }
            foreach ($S['ins'] as &$l) {
                $l['gm'] = [];
            }
            unset($l);
            foreach (['pat', 'dig', 'avi'] as $b) {
                foreach ($S[$b] as &$l) {
                    unset($l['mk']);
                    if ($b === 'dig') {
                        unset($l['ini'], $l['fim']);
                    }
                }
                unset($l);
            }
            $S['parc'] = [];
            $f['ac_nome'] = $f['ac_data'] = $f['gs_nome'] = $f['gs_data'] = '';
            $f['res'] = $f['subst'] = '';
            unset($f);
        } elseif ($modo === 'substituicao') {
            if (!in_array($pi['situacao'], ['Enviada', 'Corrigida', 'Aprovada'], true)) {
                throw new BusinessException('Cancela/substitui só vale para PI Enviada, Corrigida ou Aprovada (RN-56).');
            }
            if (!Auth::can('pis.substituir') || !(self::podeAlterar($pi) || Auth::can('pis.cancelar'))) {
                throw new HttpException(403);
            }
            $f = &$S['f'];
            $f['origem'] = $pi['numero'];
            $f['num'] = '';
            $f['nums'] = [];
            $f['tipo'] = 'Cancela/substitui';
            $f['emissao'] = $hoje;
            $f['subst'] = $pi['numero'];
            $f['ac_nome'] = $f['ac_data'] = '';
            $f['res'] = '';
            unset($f);
        } else {
            throw new BusinessException('Modo inválido.');
        }
        $id = Database::insert('pi_rascunhos', [
            'usuario_id' => (int) Auth::id(), 'pi_id' => in_array($modo, ['edicao', 'correcao'], true) ? $piId : null, 'veiculo_id' => (int) ($S['f']['veic'] ?? 0) ?: null,
            'cliente_nome' => mb_substr((string) ($S['f']['cli_razao'] ?? ''), 0, 160) ?: null, 'titulo' => mb_substr((string) ($S['f']['titulo'] ?? ''), 0, 160) ?: null,
            'modo' => $modo, 'estado' => json_encode($S, JSON_UNESCAPED_UNICODE),
        ]);
        Auditoria::registrar('pi_aberta_' . $modo, 'pi', $piId, "PI {$pi['numero']} aberta para " . $modo);
        return $id;
    }
}
