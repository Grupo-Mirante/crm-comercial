<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\BusinessException;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Calendario;
use App\Services\AprovacaoService;
use App\Services\Auth;
use App\Services\Calculo\Catalogo;
use App\Services\Calculo\DiasExibicao;
use App\Services\ExcelPiService;
use App\Services\ParcelamentoService;
use App\Services\PiService;
use App\Services\PiWorkflow;
use App\Services\ProjetoService;
use App\Services\RascunhoService;
use App\Services\ReservaService;

/**
 * Módulo 2 (Nova PI / Gerador de PI) e Módulo 3 (Gestão de PIs).
 * A tela envia o estado do formulário; o servidor calcula, confere, numera e grava (o navegador nunca decide valores).
 */
final class PiController extends Controller
{
    // ================================================================ Gerador (tela)
    public function nova(Request $r): Response
    {
        $this->exigir('pis.criar');
        $rid = $r->int('rascunho');
        if ($rid) {
            $rasc = RascunhoService::obter($rid);
            $estado = $rasc['estado'];
            $modo = $rasc['modo'];
            $piNum = $rasc['pi_id'] ? (string) Database::value('SELECT numero FROM pis WHERE id = ?', [(int) $rasc['pi_id']]) : null;
        } else {
            $estado = RascunhoService::estadoInicial($r->int('cliente') ?: null);
            $modo = 'nova';
            $piNum = null;
        }
        $listas = [];
        foreach (Database::all('SELECT lista, valor FROM listas_valores WHERE ativo = 1 ORDER BY lista, ordem') as $l) {
            $listas[$l['lista']][] = $l['valor'];
        }
        $execs = [];
        if (Auth::can('pis.editar_outras')) {
            // Quem emite em nome de outro executivo (gerência) só escolhe entre os que pode ver (RN-73)
            $todos = Database::all("SELECT u.id, u.nome, u.gestor_id FROM usuarios u JOIN perfis p ON p.id = u.perfil_id
                                     WHERE u.ativo = 1 AND p.codigo IN ('exec','vend','gerente','diretor','admin') ORDER BY u.nome");
            $execs = array_values(array_map(fn ($u) => ['id' => (int) $u['id'], 'nome' => $u['nome']], array_filter($todos,
                fn ($u) => (int) $u['id'] === Auth::id() || Auth::podeVerPi(['executivo_id' => (int) $u['id'], 'gestor_id' => $u['gestor_id']]))));
        }
        $boot = [
            'rascunho' => $rid ?: null,
            'modo' => $modo,
            'piNumero' => $piNum,
            'estado' => $estado,
            'veiculos' => Database::all('SELECT id, nome, tipo, prefixo, bloco_insercoes AS ins, bloco_patrocinio AS pat, bloco_digital AS dig, bloco_publicidade_legal AS avi, divide_pi_por_mes AS divide FROM veiculos WHERE ativo = 1 ORDER BY ordem'),
            'listas' => $listas,
            'executivos' => $execs,
            'podeReservar' => Auth::can('reservas.criar'),
            'devolucao' => $rid && !empty($rasc['pi_id']) ? (function (int $piId) {
                $d = Database::first("SELECT d.descricao, d.devolvida_em, u.nome AS por, GROUP_CONCAT(c.motivo_codigo SEPARATOR ', ') AS codigos FROM pi_devolucoes d JOIN usuarios u ON u.id = d.devolvida_por
                                        LEFT JOIN pi_devolucao_codigos c ON c.devolucao_id = d.id WHERE d.pi_id = ? AND d.corrigida_em IS NULL GROUP BY d.id ORDER BY d.sequencia DESC LIMIT 1", [$piId]);
                return $d ? ['descricao' => $d['descricao'], 'em' => data_hora_br($d['devolvida_em']), 'por' => $d['por'], 'codigos' => $d['codigos']] : null;
            })((int) $rasc['pi_id']) : null,
            'hoje' => date('Y-m-d'),
        ];
        return $this->view('pis/gerador', [
            'titulo' => $modo === 'nova' ? 'Nova PI' : ($piNum ? 'PI ' . $piNum : 'PI em montagem'),
            'boot' => $boot,
            'meus' => RascunhoService::meus(),
            'js' => ['js/gerador.js'],
        ]);
    }

    // ================================================================ APIs do gerador
    /** Catálogo do veículo para o ano da emissão: tabelas, programas, formatos, durações e projetos. */
    public function catalogo(Request $r): Response
    {
        $this->exigir('pis.criar');
        $vid = $r->int('veiculo');
        $cat = new Catalogo();
        $v = $cat->veiculo($vid) ?? throw new HttpException(404, 'Veículo não encontrado.');
        $ano = $cat->anoTabela($vid, data_iso($r->str('emissao')) ?: date('Y-m-d'));
        $programas = array_values(array_map(fn ($p) => [
            's' => $p['sigla'], 'n' => $p['nome'], 'd' => $p['dias_exibicao'], 'f' => $p['faixa_horaria'], 'dias' => $p['dias'],
            'temPreco' => (bool) ($p['t'] || $p['r']), 'pat' => (bool) $p['e'], 'esq' => $p['e'], 'cota' => $p['p'],
        ], $cat->programas($vid, $ano)));
        $formatos = array_values(array_map(fn ($f) => [
            's' => $f['sigla'], 'n' => $f['nome'], 'mod' => $f['modelo'], 'p' => $f['preco'], 'cat' => $f['categoria'], 'legal' => (int) $f['publicidade_legal'] === 1,
            'disp' => $f['dispositivo'], 'local' => $f['local_pagina'], 'obs' => $f['observacao'], 'ent' => $f['entrega_estimada'],
        ], $cat->formatos($vid, $ano)));
        $vend = ProjetoService::vendidas(array_map('intval', (array) $r->input('excluir', [])));
        $res = ProjetoService::reservadas($r->int('reserva') ?: null);
        $projetos = [];
        foreach (ProjetoService::ativos() as $P) {
            if (!in_array($vid, $P['veiculos'], true)) {
                continue;
            }
            $st = ProjetoService::situacao($P, $vend, $res);
            $projetos[] = ['id' => (int) $P['id'], 'codigo' => $P['codigo'], 'nome' => $P['nome'], 'ate' => $P['data_limite_venda'], 'aberto' => $st['aberto'], 'sit' => $st['situacao'],
                'disponiveis' => $st['disponiveis'], 'total' => $st['total'], 'cotas' => $st['cotas']];
        }
        return $this->json([
            'ok' => true, 'ano' => $ano,
            'veiculo' => ['id' => (int) $v['id'], 'nome' => $v['nome'], 'tipo' => $v['tipo'], 'titulo' => $v['titulo_pi'],
                'emissora' => trim(implode(' · ', array_filter([$v['empresa_razao'], $v['empresa_cnpj']]))) ?: $v['empresa_nome'] . ' (dados da emissora a preencher)'],
            'tabelas' => array_map(fn ($t) => ['c' => $t['codigo'], 'd' => $t['descricao'], 'sem' => (int) $t['sem_tabela'] === 1], $cat->tabelas($vid, $ano)),
            'duracoes' => $cat->duracoes(),
            'programas' => $programas, 'formatos' => $formatos, 'projetos' => $projetos,
        ]);
    }

    /** Cálculo e conferência automática ao vivo (sem gravar). */
    public function calcular(Request $r): Response
    {
        $this->exigir('pis.criar');
        $S = RascunhoService::limpar((array) $r->input('estado', []));
        $rid = $r->int('rascunho') ?: null;
        if ($rid) {
            RascunhoService::obter($rid);
        }
        return $this->json(['ok' => true, 'r' => self::paraTela(PiService::previa($S, $rid))]);
    }

    public function criarRascunho(Request $r): Response
    {
        $id = RascunhoService::criar((array) $r->input('estado', []));
        return $this->json(['ok' => true, 'id' => $id]);
    }

    public function salvarRascunho(Request $r): Response
    {
        RascunhoService::salvar($this->id($r), (array) $r->input('estado', []));
        return $this->json(['ok' => true, 'salvo_em' => date('H:i')]);
    }

    public function excluirRascunho(Request $r): Response
    {
        RascunhoService::excluir($this->id($r));
        return $r->wantsJson() && str_starts_with($r->path, '/api/') ? $this->json(['ok' => true]) : $this->voltar('/pis/nova', 'PI em montagem descartada.', 'info');
    }

    /** RN-43 — atalho "Gerar parcelas". */
    public function parcelas(Request $r): Response
    {
        $p = ParcelamentoService::gerar(valor_decimal($r->input('liquido', 0)), $r->int('quantidade', 1), data_iso($r->str('primeiro')) ?: date('Y-m-d'),
            (bool) $r->input('tv', false), $r->int('meses'));
        return $this->json(['ok' => true, 'parcelas' => $p]);
    }

    public function gerar(Request $r): Response
    {
        $S = RascunhoService::formatarDocumentos(RascunhoService::limpar((array) $r->input('estado', [])));
        $rid = $r->int('rascunho') ?: null;
        if ($rid) {
            RascunhoService::salvar($rid, $S);
        }
        $res = PiService::gerar($S, $rid);
        $pis = array_map(fn ($g) => $g + ['excel' => url('/pis/' . $g['id'] . '/excel'), 'link' => url('/pis/' . $g['id'])], $res['pis']);
        return $this->json(['ok' => true, 'pis' => $pis, 'grupo' => $res['grupo']]);
    }

    public function reservar(Request $r): Response
    {
        $S = RascunhoService::limpar((array) $r->input('estado', []));
        $rid = $r->int('rascunho') ?: null;
        $res = ReservaService::reservar($S, $rid);
        return $this->json(['ok' => true, 'reserva' => ['id' => (int) $res['id'], 'codigo' => $res['codigo'], 'expira_em' => data_hora_br($res['expira_em'])]]);
    }

    public function solicitarAprovacao(Request $r): Response
    {
        $this->exigir('pis.criar');
        $S = RascunhoService::limpar((array) $r->input('estado', []));
        $rid = $r->int('rascunho') ?: null;
        if (!$rid) {
            throw new BusinessException('Aguarde o salvamento automático e tente de novo.');
        }
        RascunhoService::salvar($rid, $S);
        $R = PiService::previa($S, $rid);
        if (!$R['needGs']) {
            throw new BusinessException('Esta PI não tem desconto, bonificação nem preço manual: não precisa de aprovação.');
        }
        $resumo = trim(($R['V']['nome'] ?? '') . ' · ' . ($S['f']['cli_razao'] ?? 'cliente') . ' · ' . moeda($R['T']['neg']));
        [, $exec] = PiService::aplicarExecutivo($S);
        $id = AprovacaoService::solicitar($rid, null, $R['T'], $R['manuais'], $r->str('justificativa'), $resumo, (int) $exec['id']);
        $para = AprovacaoService::exigeDiretoria($R['T']['descPct'], $R['T']['bonif']) || !$exec['gestor_id'] ? 'à diretoria' : 'ao gestor ' . $exec['gestor_nome'];
        return $this->json(['ok' => true, 'id' => $id, 'mensagem' => 'Aprovação solicitada ' . $para . '. Você será avisado quando houver resposta.']);
    }

    /**
     * Reduz o resultado do cálculo ao que a tela precisa (sem repetir o formulário).
     * @param array<string,mixed> $R
     * @return array<string,mixed>
     */
    public static function paraTela(array $R): array
    {
        $V = $R['V'];
        return [
            'V' => $V ? ['id' => (int) $V['id'], 'nome' => $V['nome'], 'tipo' => $V['tipo']] : null,
            'isTV' => $R['isTV'], 'tab' => $R['tab'], 'ano' => $R['ano'], 'multi' => $R['multi'], 'splitTV' => $R['splitTV'], 'projBlock' => $R['projBlock'],
            'months' => array_map(fn ($M) => ['key' => $M['key'], 'label' => $M['label'], 'short' => $M['short'], 'days' => $M['days'], 'y' => $M['y'], 'm' => $M['m']], $R['months']),
            'lines' => array_map(fn ($x) => ['li' => $x['li'], 'n' => $x['n'], 'unit' => $x['unit'], 'idx' => $x['idx'], 'nome' => $x['nome'], 'dias' => $x['dias'], 'total' => $x['total'],
                'semPreco' => $x['semPreco'], 'out' => $x['out'], 'noAir' => $x['noAir'], 'per' => $x['per'], 'air' => $x['p']['dias'] ?? null], $R['lines']),
            'pat' => array_map(fn ($x) => ['li' => $x['li'], 'nome' => $x['nome'], 'mes' => $x['mes'], 'dias' => $x['dias'], 'esq' => $x['esq'], 'n' => $x['n'], 'cot' => $x['cot'], 'valor' => $x['valor'],
                'total' => $x['total'], 'semPreco' => $x['semPreco']], $R['pat']),
            'dig' => array_map(fn ($x) => ['li' => $x['li'], 'nome' => $x['nome'], 'mod' => $x['mod'], 'unit' => $x['unit'], 'qtd' => $x['qtd'], 'ent' => $x['ent'], 'dias' => $x['dias'], 'meses' => $x['meses'],
                'mdet' => $x['mdet'], 'tab' => $x['tab'], 'total' => $x['total'], 'semPreco' => $x['semPreco'], 'cpmSemEnt' => $x['cpmSemEnt'], 'formato' => $x['formato']], $R['dig']),
            'avi' => array_map(fn ($x) => ['li' => $x['li'], 'nome' => $x['nome'], 'unit' => $x['unit'], 'cmcol' => $x['cmcol'], 'pub' => $x['pub'], 'tab' => $x['tab'], 'total' => $x['total'], 'semPreco' => $x['semPreco']], $R['avi']),
            'prj' => array_map(fn ($x) => ['li' => $x['li'], 'nome' => $x['nome'], 'cota' => $x['cota'], 'q' => $x['q'], 'unit' => $x['unit'], 'tab' => $x['tab'], 'total' => $x['total'], 'disp' => $x['disp'],
                'venc' => $x['venc'], 'excede' => $x['excede'], 'outroVeic' => $x['outroVeic'], 'semCota' => $x['semCota'], 'ate' => $x['ate']], $R['prj']),
            'T' => $R['T'], 'C' => $R['C'], 'pend' => $R['pend'], 'needGs' => $R['needGs'], 'aprovacao' => $R['aprovacao'], 'conflitos' => $R['conflitos'] ?? [], 'executivo' => $R['executivo'] ?? null,
        ];
    }

    // ================================================================ Gestão de PIs (Módulo 3)
    public function index(Request $r): Response
    {
        if (!Auth::can('pis.ver_todas') && !Auth::can('pis.ver_equipe') && !Auth::can('pis.criar')) {
            throw new HttpException(403);
        }
        $f = [
            'numero' => $r->str('numero'), 'cliente' => $r->str('cliente'), 'cnpj' => $r->str('cnpj'), 'executivo' => $r->int('executivo'), 'veiculo' => $r->int('veiculo'),
            'empresa' => $r->int('empresa'), 'gestor' => $r->int('gestor'), 'situacao' => $r->str('situacao'), 'de' => data_iso($r->str('de')), 'ate' => data_iso($r->str('ate')),
            'minhas' => $r->str('minhas') === '1', 'sem_aprovacao' => $r->str('sem_aprovacao') === '1',
        ];
        [$esc, $p] = Auth::filtroPis('p');
        $w = [$esc];
        if ($f['numero'] !== '') {
            $w[] = '(p.numero LIKE :num OR p.pi_origem_numero LIKE :num2)';
            $p += ['num' => '%' . $f['numero'] . '%', 'num2' => '%' . $f['numero'] . '%'];
        }
        if ($f['cliente'] !== '') {
            $w[] = 'p.cliente_razao LIKE :cli';
            $p['cli'] = '%' . $f['cliente'] . '%';
        }
        if ($f['cnpj'] !== '') {
            $w[] = 'p.cliente_documento LIKE :doc';
            $p['doc'] = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $f['cnpj'])) . '%';
        }
        foreach (['executivo' => 'p.executivo_id', 'veiculo' => 'p.veiculo_id', 'empresa' => 'p.empresa_id', 'gestor' => 'p.gestor_id'] as $k => $col) {
            if ($f[$k]) {
                $w[] = "{$col} = :{$k}";
                $p[$k] = $f[$k];
            }
        }
        if (in_array($f['situacao'], \App\Services\DashboardService::SITUACOES, true)) {
            $w[] = 'p.situacao = :sit';
            $p['sit'] = $f['situacao'];
        }
        if ($f['de'] !== '') {
            $w[] = 'p.data_emissao >= :de';
            $p['de'] = $f['de'];
        }
        if ($f['ate'] !== '') {
            $w[] = 'p.data_emissao <= :ate';
            $p['ate'] = $f['ate'];
        }
        if ($f['minhas']) {
            $w[] = 'p.executivo_id = :eu';
            $p['eu'] = (int) Auth::id();
        }
        if ($f['sem_aprovacao']) {
            $w[] = "p.situacao IN ('Rascunho','Enviada','Devolvida','Corrigida') AND p.data_inicio BETWEEN CURDATE() AND CURDATE() + INTERVAL 5 DAY";
        }
        $sql = 'SELECT p.id, p.numero, p.data_emissao, p.situacao, p.cliente_razao, p.valor_tabela, p.valor_negociado, p.valor_liquido, p.data_inicio, p.data_fim, p.qtd_pendencias,
                       p.qtd_devolucoes, p.fora_do_prazo, p.parte, v.nome AS veiculo, e.nome_curto AS empresa, u.nome AS executivo,
                       (SELECT h.usuario_nome FROM pi_historico h WHERE h.pi_id = p.id ORDER BY h.id DESC LIMIT 1) AS ultimo_por,
                       (SELECT h.criado_em FROM pi_historico h WHERE h.pi_id = p.id ORDER BY h.id DESC LIMIT 1) AS ultimo_em
                  FROM pis p JOIN veiculos v ON v.id = p.veiculo_id JOIN empresas e ON e.id = p.empresa_id JOIN usuarios u ON u.id = p.executivo_id WHERE ' . implode(' AND ', $w);
        $pag = $this->paginar($sql, $p, 'p.data_emissao DESC, p.id DESC', $r, 30);
        $tot = Database::first("SELECT COUNT(*) n, SUM(CASE WHEN t.situacao NOT IN ('Cancelada','Substituída') THEN t.valor_negociado ELSE 0 END) neg,
                                       SUM(CASE WHEN t.situacao NOT IN ('Cancelada','Substituída') THEN t.valor_liquido ELSE 0 END) liq FROM ({$sql}) t", $p);
        return $this->view('pis/index', ['titulo' => 'PIs', 'p' => $pag, 'f' => $f, 'tot' => $tot, 'opcoes' => \App\Services\DashboardService::opcoesFiltros()]);
    }

    public function ver(Request $r): Response
    {
        $pi = PiService::obter($this->id($r));
        $id = (int) $pi['id'];
        $versao = Database::first('SELECT * FROM pi_versoes WHERE pi_id = ? ORDER BY versao DESC LIMIT 1', [$id]);
        $R = $versao ? json_decode((string) $versao['resultado'], true) : null;
        return $this->view('pis/ver', [
            'titulo' => 'PI ' . $pi['numero'], 'pi' => $pi, 'R' => $R, 'snap' => json_decode((string) $pi['snapshot'], true) ?: [],
            'itens' => Database::all('SELECT * FROM pi_itens WHERE pi_id = ? ORDER BY ordem', [$id]),
            'parcelas' => Database::all('SELECT * FROM pi_parcelas WHERE pi_id = ? ORDER BY numero', [$id]),
            'entregas' => Database::all('SELECT * FROM pi_entregas_especiais WHERE pi_id = ? ORDER BY ordem', [$id]),
            'historico' => Database::all('SELECT * FROM pi_historico WHERE pi_id = ? ORDER BY id DESC', [$id]),
            'devolucoes' => Database::all("SELECT d.*, u.nome AS por, GROUP_CONCAT(c.motivo_codigo ORDER BY c.motivo_codigo SEPARATOR ', ') AS codigos
                                             FROM pi_devolucoes d JOIN usuarios u ON u.id = d.devolvida_por LEFT JOIN pi_devolucao_codigos c ON c.devolucao_id = d.id
                                            WHERE d.pi_id = ? GROUP BY d.id ORDER BY d.sequencia", [$id]),
            'versoes' => Database::all('SELECT v.versao, v.situacao, v.gerado_em, u.nome AS por FROM pi_versoes v JOIN usuarios u ON u.id = v.gerado_por WHERE v.pi_id = ? ORDER BY v.versao DESC', [$id]),
            'aprovacoes' => Database::all('SELECT a.*, s.nome AS solicitante FROM pi_aprovacoes a JOIN usuarios s ON s.id = a.solicitante_id WHERE a.pi_id = ? ORDER BY a.id DESC', [$id]),
            'grupo' => $pi['grupo_id'] ? Database::all('SELECT id, numero, situacao, mes_referencia, valor_liquido FROM pis WHERE grupo_id = ? ORDER BY numero', [(int) $pi['grupo_id']]) : [],
            'derivadas' => Database::all('SELECT id, numero, tipo, situacao FROM pis WHERE pi_origem_id = ? ORDER BY id', [$id]),
            'acoes' => PiWorkflow::acoes($pi),
            'motivos' => Database::all('SELECT codigo, descricao FROM motivos_devolucao WHERE ativo = 1 ORDER BY ordem'),
        ]);
    }

    /** Abre a PI no formulário: edição/correção (mesmo número), renovação ou cancela/substitui (número novo). */
    public function abrir(Request $r): Response
    {
        $modo = $r->param('modo');
        $mapa = ['editar' => 'edicao', 'corrigir' => 'correcao', 'renovar' => 'renovacao', 'substituir' => 'substituicao'];
        if (!isset($mapa[$modo])) {
            throw new HttpException(404);
        }
        $rid = PiService::abrir($this->id($r), $mapa[$modo]);
        return Response::redirect('/pis/nova?rascunho=' . $rid);
    }

    /** Ações do fluxo que não são da Conferência (cancelar, programar, veicular, finalizar, faturar, encerrar). */
    public function acao(Request $r): Response
    {
        $id = $this->id($r);
        $acao = $r->param('acao');
        $obs = $r->str('observacao');
        $msg = match ($acao) {
            'cancelar' => (function () use ($id, $r) { PiWorkflow::cancelar($id, $r->str('motivo')); return 'PI cancelada. Ela saiu dos totais de venda e as cotas de projeto voltaram a ficar disponíveis.'; })(),
            'programar' => (function () use ($id, $obs) { PiWorkflow::programar($id, $obs); return 'PI liberada para programação.'; })(),
            'veicular' => (function () use ($id, $obs) { PiWorkflow::iniciarVeiculacao($id, $obs); return 'Veiculação iniciada.'; })(),
            'finalizar' => (function () use ($id, $r, $obs) { PiWorkflow::finalizarVeiculacao($id, $r->str('data_fim'), $obs); return 'Veiculação finalizada: PI pronta para faturamento.'; })(),
            'faturar' => (function () use ($id, $r, $obs) { PiWorkflow::faturar($id, $r->str('nf_numero'), $r->input('nf_valor'), $obs); return 'Faturamento registrado.'; })(),
            'encerrar' => (function () use ($id, $obs) { PiWorkflow::encerrar($id, $obs); return 'PI encerrada.'; })(),
            default => throw new HttpException(404),
        };
        return $this->voltar('/pis/' . $id, $msg);
    }

    /** RN-46 — Excel da PI (A4 paisagem, com fórmulas). */
    public function excel(Request $r): Response
    {
        $this->exigir('pis.exportar');
        $pi = PiService::obter($this->id($r));
        [$arquivo, $nome] = ExcelPiService::gerarArquivo((int) $pi['id']);
        \App\Services\Auditoria::registrar('pi_excel', 'pi', (int) $pi['id'], 'Excel baixado: ' . $nome);
        return Response::download($arquivo, $nome, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', true);
    }

    /** Versão para impressão (A4 paisagem). */
    public function imprimir(Request $r): Response
    {
        $pi = PiService::obter($this->id($r));
        $v = Database::first('SELECT * FROM pi_versoes WHERE pi_id = ? ORDER BY versao DESC LIMIT 1', [(int) $pi['id']]) ?? throw new HttpException(404);
        return $this->view('pis/imprimir', ['titulo' => 'PI ' . $pi['numero'], 'pi' => $pi, 'R' => json_decode((string) $v['resultado'], true), 'snap' => json_decode((string) $pi['snapshot'], true)], null);
    }

    /** Dias da semana e letras para a grade (usado também pela tela). @return array<int,string> */
    public static function letras(): array
    {
        return DiasExibicao::LETRAS;
    }

    /** Helper para a view: meses do período. @return list<array<string,mixed>> */
    public static function meses(?string $ini, ?string $fim): array
    {
        return Calendario::mesesDoPeriodo($ini, $fim);
    }
}
