<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\Calendario;

/**
 * Módulo 10 — Relatórios comerciais com dados do MySQL, respeitando o que cada usuário pode ver (RN-73).
 * Visões: diária, semanal e mensal (agrupamento e período padrão).
 * Privacidade (documento de processos): o ranking nominal completo fica com gestores, diretoria e Conferência
 * (permissão relatorios.ranking_nominal); os demais veem só os próprios números.
 */
final class RelatorioService
{
    public const MORTAS = "('Cancelada','Substituída')";
    public const APROVADAS = "('Aprovada','Em programação','Em veiculação','Pronta para faturamento','Faturada','Encerrada')";

    /** @return array<string,array{titulo:string,grupo:string,descricao:string,nominal?:bool,periodo?:bool}> */
    public static function catalogo(): array
    {
        return [
            'vendas_periodo' => ['titulo' => 'Vendas por período', 'grupo' => 'Vendas', 'descricao' => 'PIs, vendas aprovadas (novas e renovações), valor bruto, negociado e líquido por dia, semana ou mês.'],
            'vendas_veiculo' => ['titulo' => 'Vendas por veículo', 'grupo' => 'Vendas', 'descricao' => 'Quantidade, bruto, negociado, líquido e ticket médio por veículo.'],
            'vendas_empresa' => ['titulo' => 'Vendas por empresa', 'grupo' => 'Vendas', 'descricao' => 'Totais por empresa emissora.'],
            'vendas_executivo' => ['titulo' => 'Vendas por executivo', 'grupo' => 'Vendas', 'descricao' => 'Totais por executivo de contas.', 'nominal' => true],
            'vendas_gestor' => ['titulo' => 'Vendas por gestor', 'grupo' => 'Vendas', 'descricao' => 'Totais por gestor comercial (equipe).', 'nominal' => true],
            'valores_negociados' => ['titulo' => 'Valores negociados', 'grupo' => 'Valores', 'descricao' => 'Tabela, desconto, bonificado e negociado por período.'],
            'valores_liquidos' => ['titulo' => 'Valores líquidos', 'grupo' => 'Valores', 'descricao' => 'Negociado, comissão de agência e líquido a faturar por período.'],
            'comissoes_agencias' => ['titulo' => 'Comissões de agências', 'grupo' => 'Valores', 'descricao' => 'PIs, negociado e comissão por agência.'],
            'pis_geradas' => ['titulo' => 'PIs geradas', 'grupo' => 'PIs', 'descricao' => 'Lista das PIs emitidas no período, com situação e valores.'],
            'pis_aprovadas' => ['titulo' => 'PIs aprovadas', 'grupo' => 'PIs', 'descricao' => 'PIs aprovadas na conferência no período, com tempo de ciclo e se foram certas de primeira.'],
            'pis_devolvidas' => ['titulo' => 'PIs devolvidas', 'grupo' => 'Conferência', 'descricao' => 'Cada devolução com código, descrição, quem devolveu e quando foi corrigida.'],
            'motivos_devolucao' => ['titulo' => 'Motivos de devolução', 'grupo' => 'Conferência', 'descricao' => 'Contagem por código D01 a D15, do mais para o menos frequente (os 3 primeiros viram tema de treinamento).'],
            'ranking_devolucoes' => ['titulo' => 'Ranking de devoluções', 'grupo' => 'Conferência', 'descricao' => 'Por executivo: PIs conferidas, devolvidas, taxa de devolução e certa de primeira (meta: nenhum acima de 20%).', 'nominal' => true],
            'pis_fora_prazo' => ['titulo' => 'PIs fora do prazo', 'grupo' => 'Conferência', 'descricao' => 'PIs enviadas com menos antecedência que o SLA (meta: abaixo de 10%).'],
            'tempo_conferencia' => ['titulo' => 'Tempo médio de conferência', 'grupo' => 'Conferência', 'descricao' => 'Tempo entre o recebimento e a decisão da Conferência, e ciclo até a aprovação (meta: até 2 dias úteis).'],
            'prontas_faturamento' => ['titulo' => 'PIs prontas para faturamento', 'grupo' => 'Financeiro', 'descricao' => 'PIs com veiculação finalizada aguardando nota (situação atual).', 'periodo' => false],
            'projetos' => ['titulo' => 'Projetos especiais', 'grupo' => 'Projetos e reservas', 'descricao' => 'Situação, cotas e valores vendidos por projeto.', 'periodo' => false],
            'cotas' => ['titulo' => 'Cotas vendidas e disponíveis', 'grupo' => 'Projetos e reservas', 'descricao' => 'Por cota: total, vendidas, reservadas e disponíveis.', 'periodo' => false],
            'reservas_ativas' => ['titulo' => 'Reservas ativas', 'grupo' => 'Projetos e reservas', 'descricao' => 'Reservas de 24 horas em vigor, com tempo restante.', 'periodo' => false],
        ];
    }

    /**
     * Filtros normalizados. Período padrão conforme a visão: diária = hoje; semanal = semana atual; mensal = mês atual.
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    public static function filtros(array $in): array
    {
        $visao = in_array($in['visao'] ?? '', ['diaria', 'semanal', 'mensal'], true) ? $in['visao'] : 'mensal';
        $padrao = match ($visao) {
            'diaria' => [date('Y-m-d'), date('Y-m-d')],
            'semanal' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))],
            default => [date('Y-m-01'), date('Y-m-t')],
        };
        $de = data_iso((string) ($in['de'] ?? '')) ?: $padrao[0];
        $ate = data_iso((string) ($in['ate'] ?? '')) ?: $padrao[1];
        if ($ate < $de) {
            [$de, $ate] = [$ate, $de];
        }
        return ['visao' => $visao, 'de' => $de, 'ate' => $ate, 'empresa' => (int) ($in['empresa'] ?? 0) ?: null, 'veiculo' => (int) ($in['veiculo'] ?? 0) ?: null,
            'executivo' => (int) ($in['executivo'] ?? 0) ?: null, 'gestor' => (int) ($in['gestor'] ?? 0) ?: null];
    }

    /** @param array<string,mixed> $f @return array{0:string,1:array<string,mixed>} */
    private static function where(array $f, bool $nominal = false): array
    {
        [$w, $p] = Auth::filtroPis('p');
        $cond = [$w];
        foreach (['empresa' => 'p.empresa_id', 'veiculo' => 'p.veiculo_id', 'executivo' => 'p.executivo_id', 'gestor' => 'p.gestor_id'] as $k => $c) {
            if ($f[$k]) {
                $cond[] = "{$c} = :r_{$k}";
                $p['r_' . $k] = $f[$k];
            }
        }
        if ($nominal && !Auth::can('relatorios.ranking_nominal')) {
            $cond[] = 'p.executivo_id = :r_eu';
            $p['r_eu'] = (int) Auth::id();
        }
        return [implode(' AND ', $cond), $p];
    }

    private static function grupoData(string $col, string $visao): string
    {
        return match ($visao) {
            'diaria' => "DATE({$col})",
            'semanal' => "DATE_SUB(DATE({$col}), INTERVAL WEEKDAY({$col}) DAY)",
            default => "DATE_FORMAT({$col}, '%Y-%m')",
        };
    }

    private static function rotuloPeriodo(string $v, string $visao): string
    {
        return match ($visao) {
            'diaria' => data_br($v),
            'semanal' => 'Semana de ' . data_br($v),
            default => (fn ($m) => Calendario::MESES[(int) substr($m, 5, 2) - 1] . '/' . substr($m, 0, 4))($v),
        };
    }

    /**
     * Executa o relatório.
     * @param array<string,mixed> $f
     * @return array{colunas:list<array{0:string,1:string,2:string}>,linhas:list<array<string,mixed>>,totais:?array<string,mixed>,nota:string}
     */
    public static function executar(string $chave, array $f): array
    {
        $cat = self::catalogo();
        if (!isset($cat[$chave])) {
            throw new \App\Core\HttpException(404, 'Relatório não encontrado.');
        }
        $nominal = !empty($cat[$chave]['nominal']);
        [$w, $p] = self::where($f, $nominal);
        $pp = $p + ['de' => $f['de'], 'ate' => $f['ate']];
        $pt = $p + ['d1' => $f['de'] . ' 00:00:00', 'd2' => $f['ate'] . ' 23:59:59'];
        $nota = $nominal && !Auth::can('relatorios.ranking_nominal') ? 'Você vê só os próprios números: o ranking nominal completo é restrito aos gestores e à Conferência.' : '';
        $M = self::MORTAS;
        $A = self::APROVADAS;
        $somaVendas = "COUNT(*) AS pis, SUM(p.tipo IN ('Nova','Renovação') AND p.situacao IN {$A}) AS vendas, SUM(p.valor_tabela) AS bruto, SUM(p.valor_negociado) AS negociado, SUM(p.valor_liquido) AS liquido, SUM(p.valor_negociado) / NULLIF(COUNT(*), 0) AS ticket";
        $colVendas = [['pis', 'PIs ativas', 'int'], ['vendas', 'Vendas aprovadas', 'int'], ['bruto', 'Bruto (tabela)', 'money'], ['negociado', 'Negociado', 'money'], ['liquido', 'Líquido', 'money'], ['ticket', 'Ticket médio', 'money']];
        $porGrupo = function (string $sel, string $join, string $grp, string $rot, string $ordem = 'negociado DESC') use ($w, $pp, $somaVendas, $colVendas, $M): array {
            $l = Database::all("SELECT {$sel} AS grupo, {$somaVendas} FROM pis p {$join} WHERE {$w} AND p.situacao NOT IN {$M} AND p.data_emissao BETWEEN :de AND :ate GROUP BY {$grp} ORDER BY {$ordem}", $pp);
            return ['colunas' => array_merge([['grupo', $rot, 'text']], $colVendas), 'linhas' => $l];
        };
        $out = match ($chave) {
            'vendas_periodo' => (function () use ($w, $pp, $f, $somaVendas, $colVendas, $M) {
                $g = self::grupoData('p.data_emissao', $f['visao']);
                $l = Database::all("SELECT {$g} AS grupo, {$somaVendas} FROM pis p WHERE {$w} AND p.situacao NOT IN {$M} AND p.data_emissao BETWEEN :de AND :ate GROUP BY grupo ORDER BY grupo", $pp);
                foreach ($l as &$x) {
                    $x['grupo'] = self::rotuloPeriodo((string) $x['grupo'], $f['visao']);
                }
                return ['colunas' => array_merge([['grupo', 'Período', 'text']], $colVendas), 'linhas' => $l];
            })(),
            'vendas_veiculo' => $porGrupo('v.nome', 'JOIN veiculos v ON v.id = p.veiculo_id', 'v.id, v.nome', 'Veículo'),
            'vendas_empresa' => $porGrupo('e.nome_curto', 'JOIN empresas e ON e.id = p.empresa_id', 'e.id, e.nome_curto', 'Empresa'),
            'vendas_executivo' => $porGrupo('u.nome', 'JOIN usuarios u ON u.id = p.executivo_id', 'u.id, u.nome', 'Executivo'),
            'vendas_gestor' => $porGrupo("COALESCE(g.nome, '(sem gestor)')", 'LEFT JOIN usuarios g ON g.id = p.gestor_id', 'g.id, g.nome', 'Gestor'),
            'valores_negociados' => (function () use ($w, $pp, $f, $M) {
                $g = self::grupoData('p.data_emissao', $f['visao']);
                $l = Database::all("SELECT {$g} AS grupo, COUNT(*) AS pis, SUM(p.valor_tabela) AS tabela, SUM(p.desconto_nominal) AS desconto, SUM(p.valor_bonificado) AS bonificado, SUM(p.valor_negociado) AS negociado,
                                           SUM(p.desconto_nominal) / NULLIF(SUM(p.valor_tabela), 0) AS desc_pct FROM pis p WHERE {$w} AND p.situacao NOT IN {$M} AND p.data_emissao BETWEEN :de AND :ate GROUP BY grupo ORDER BY grupo", $pp);
                foreach ($l as &$x) {
                    $x['grupo'] = self::rotuloPeriodo((string) $x['grupo'], $f['visao']);
                }
                return ['colunas' => [['grupo', 'Período', 'text'], ['pis', 'PIs', 'int'], ['tabela', 'Valor de tabela', 'money'], ['desconto', 'Desconto nominal', 'money'], ['bonificado', 'Bonificado', 'money'], ['negociado', 'Negociado', 'money'], ['desc_pct', 'Desconto médio', 'pct']], 'linhas' => $l];
            })(),
            'valores_liquidos' => (function () use ($w, $pp, $f, $M) {
                $g = self::grupoData('p.data_emissao', $f['visao']);
                $l = Database::all("SELECT {$g} AS grupo, COUNT(*) AS pis, SUM(p.venda = 'Agência') AS por_agencia, SUM(p.valor_negociado) AS negociado, SUM(p.valor_comissao) AS comissao, SUM(p.valor_liquido) AS liquido
                                      FROM pis p WHERE {$w} AND p.situacao NOT IN {$M} AND p.data_emissao BETWEEN :de AND :ate GROUP BY grupo ORDER BY grupo", $pp);
                foreach ($l as &$x) {
                    $x['grupo'] = self::rotuloPeriodo((string) $x['grupo'], $f['visao']);
                }
                return ['colunas' => [['grupo', 'Período', 'text'], ['pis', 'PIs', 'int'], ['por_agencia', 'Por agência', 'int'], ['negociado', 'Negociado', 'money'], ['comissao', 'Comissão', 'money'], ['liquido', 'Líquido a faturar', 'money']], 'linhas' => $l];
            })(),
            'comissoes_agencias' => ['colunas' => [['grupo', 'Agência', 'text'], ['cnpj', 'CNPJ', 'text'], ['pis', 'PIs', 'int'], ['negociado', 'Negociado', 'money'], ['comissao', 'Comissão', 'money'], ['pct', 'Comissão média', 'pct']],
                'linhas' => Database::all("SELECT COALESCE(a.razao_social, p.agencia_razao) AS grupo, a.cnpj_formatado AS cnpj, COUNT(*) AS pis, SUM(p.valor_negociado) AS negociado, SUM(p.valor_comissao) AS comissao,
                                                  SUM(p.valor_comissao) / NULLIF(SUM(p.valor_negociado), 0) AS pct FROM pis p LEFT JOIN agencias a ON a.id = p.agencia_id
                                             WHERE {$w} AND p.venda = 'Agência' AND p.situacao NOT IN {$M} AND p.data_emissao BETWEEN :de AND :ate GROUP BY grupo, cnpj ORDER BY comissao DESC", $pp)],
            'pis_geradas' => ['colunas' => [['numero', 'Nº da PI', 'text'], ['data_emissao', 'Emissão', 'date'], ['veiculo', 'Veículo', 'text'], ['cliente_razao', 'Cliente', 'text'], ['executivo', 'Executivo', 'text'],
                    ['tipo', 'Tipo', 'text'], ['situacao', 'Situação', 'text'], ['valor_negociado', 'Negociado', 'money'], ['valor_liquido', 'Líquido', 'money']],
                'linhas' => Database::all("SELECT p.numero, p.data_emissao, v.nome AS veiculo, p.cliente_razao, u.nome AS executivo, p.tipo, p.situacao, p.valor_negociado, p.valor_liquido
                                             FROM pis p JOIN veiculos v ON v.id = p.veiculo_id JOIN usuarios u ON u.id = p.executivo_id WHERE {$w} AND p.data_emissao BETWEEN :de AND :ate ORDER BY p.data_emissao, p.numero", $pp)],
            'pis_aprovadas' => (function () use ($w, $pt) {
                $l = Database::all("SELECT p.numero, p.aprovada_em, v.nome AS veiculo, p.cliente_razao, u.nome AS executivo, p.enviada_em, p.qtd_devolucoes, p.aprovada_primeira, p.valor_liquido
                                      FROM pis p JOIN veiculos v ON v.id = p.veiculo_id JOIN usuarios u ON u.id = p.executivo_id WHERE {$w} AND p.aprovada_em BETWEEN :d1 AND :d2 ORDER BY p.aprovada_em", $pt);
                foreach ($l as &$x) {
                    $x['ciclo'] = $x['enviada_em'] ? Calendario::diasUteisEntre((string) $x['enviada_em'], (string) $x['aprovada_em']) : null;
                    $x['primeira'] = (int) $x['aprovada_primeira'] ? 'Sim' : 'Não';
                }
                return ['colunas' => [['numero', 'Nº da PI', 'text'], ['aprovada_em', 'Aprovada em', 'datetime'], ['veiculo', 'Veículo', 'text'], ['cliente_razao', 'Cliente', 'text'], ['executivo', 'Executivo', 'text'],
                    ['qtd_devolucoes', 'Devoluções', 'int'], ['primeira', 'Certa de primeira', 'text'], ['ciclo', 'Ciclo (dias úteis)', 'int'], ['valor_liquido', 'Líquido', 'money']], 'linhas' => $l];
            })(),
            'pis_devolvidas' => ['colunas' => [['devolvida_em', 'Devolvida em', 'datetime'], ['numero', 'Nº da PI', 'text'], ['executivo', 'Executivo', 'text'], ['sequencia', 'Nº da devolução', 'int'], ['codigos', 'Códigos', 'text'],
                    ['descricao', 'O que corrigir', 'text'], ['por', 'Devolvida por', 'text'], ['corrigida_em', 'Corrigida em', 'datetime']],
                'linhas' => Database::all("SELECT d.devolvida_em, p.numero, u.nome AS executivo, d.sequencia, (SELECT GROUP_CONCAT(c.motivo_codigo ORDER BY c.motivo_codigo SEPARATOR ', ') FROM pi_devolucao_codigos c WHERE c.devolucao_id = d.id) AS codigos,
                                                  d.descricao, x.nome AS por, d.corrigida_em FROM pi_devolucoes d JOIN pis p ON p.id = d.pi_id JOIN usuarios u ON u.id = d.executivo_id JOIN usuarios x ON x.id = d.devolvida_por
                                             WHERE {$w} AND d.devolvida_em BETWEEN :d1 AND :d2 ORDER BY d.devolvida_em", $pt)],
            'motivos_devolucao' => (function () use ($w, $pt) {
                $l = Database::all("SELECT m.codigo, m.descricao, COUNT(c.devolucao_id) AS qtd FROM motivos_devolucao m LEFT JOIN (pi_devolucao_codigos c JOIN pi_devolucoes d ON d.id = c.devolucao_id JOIN pis p ON p.id = d.pi_id
                                      AND {$w} AND d.devolvida_em BETWEEN :d1 AND :d2) ON c.motivo_codigo = m.codigo GROUP BY m.codigo, m.descricao ORDER BY qtd DESC, m.ordem", $pt);
                $tot = array_sum(array_column($l, 'qtd'));
                foreach ($l as $i => &$x) {
                    $x['pct'] = $tot ? (int) $x['qtd'] / $tot : null;
                    $x['treino'] = $i < 3 && (int) $x['qtd'] > 0 ? 'Tema de treinamento' : '';
                }
                return ['colunas' => [['codigo', 'Código', 'text'], ['descricao', 'Motivo', 'text'], ['qtd', 'Devoluções', 'int'], ['pct', '% do total', 'pct'], ['treino', '', 'text']], 'linhas' => $l];
            })(),
            'ranking_devolucoes' => (function () use ($w, $pt) {
                $l = Database::all("SELECT u.nome AS executivo, COUNT(DISTINCT p.id) AS conferidas, COUNT(DISTINCT CASE WHEN p.qtd_devolucoes > 0 THEN p.id END) AS devolvidas,
                                           (SELECT COUNT(*) FROM pi_devolucoes d2 WHERE d2.executivo_id = u.id AND d2.devolvida_em BETWEEN :d1b AND :d2b) AS devolucoes,
                                           COUNT(DISTINCT CASE WHEN p.aprovada_primeira = 1 THEN p.id END) AS primeira
                                      FROM pis p JOIN pi_conferencias c ON c.pi_id = p.id JOIN usuarios u ON u.id = p.executivo_id
                                     WHERE {$w} AND c.concluida_em BETWEEN :d1 AND :d2 GROUP BY u.id, u.nome", $pt + ['d1b' => $pt['d1'], 'd2b' => $pt['d2']]);
                foreach ($l as &$x) {
                    $x['taxa'] = $x['conferidas'] ? (int) $x['devolvidas'] / (int) $x['conferidas'] : null;
                    $x['taxa_primeira'] = $x['conferidas'] ? (int) $x['primeira'] / (int) $x['conferidas'] : null;
                    $x['meta'] = $x['taxa'] !== null && $x['taxa'] > 0.20 ? 'Acima de 20%' : 'Dentro da meta';
                }
                usort($l, fn ($a, $b) => ($b['taxa'] ?? 0) <=> ($a['taxa'] ?? 0));
                return ['colunas' => [['executivo', 'Executivo', 'text'], ['conferidas', 'PIs conferidas', 'int'], ['devolvidas', 'Devolvidas ao menos 1×', 'int'], ['devolucoes', 'Devoluções', 'int'],
                    ['taxa', 'Taxa de devolução', 'pct'], ['taxa_primeira', 'Certa de primeira', 'pct'], ['meta', 'Meta (≤ 20%)', 'text']], 'linhas' => $l];
            })(),
            'pis_fora_prazo' => (function () use ($w, $pt) {
                $l = Database::all("SELECT p.numero, p.enviada_em, p.data_inicio, v.nome AS veiculo, p.cliente_razao, u.nome AS executivo, p.situacao FROM pis p JOIN veiculos v ON v.id = p.veiculo_id JOIN usuarios u ON u.id = p.executivo_id
                                     WHERE {$w} AND p.fora_do_prazo = 1 AND p.enviada_em BETWEEN :d1 AND :d2 ORDER BY p.enviada_em", $pt);
                foreach ($l as &$x) {
                    $x['antecedencia'] = Calendario::diasUteisEntre((string) $x['enviada_em'], (string) $x['data_inicio']);
                }
                $enviadas = (int) Database::value("SELECT COUNT(*) FROM pis p WHERE {$w} AND p.enviada_em BETWEEN :d1 AND :d2", $pt);
                return ['colunas' => [['numero', 'Nº da PI', 'text'], ['enviada_em', 'Enviada em', 'datetime'], ['data_inicio', '1ª veiculação', 'date'], ['antecedencia', 'Antecedência (dias úteis)', 'int'],
                    ['veiculo', 'Veículo', 'text'], ['cliente_razao', 'Cliente', 'text'], ['executivo', 'Executivo', 'text'], ['situacao', 'Situação', 'text']], 'linhas' => $l,
                    'nota' => $enviadas ? sprintf('%d de %d PIs enviadas no período (%s) saíram fora do prazo. Meta: abaixo de 10%%.', count($l), $enviadas, number_format(count($l) / $enviadas * 100, 1, ',', '.') . '%') : ''];
            })(),
            'tempo_conferencia' => (function () use ($w, $pt, $f) {
                $rows = Database::all("SELECT c.concluida_em, c.resultado, p.numero, (SELECT MAX(h.criado_em) FROM pi_historico h WHERE h.pi_id = p.id AND h.situacao_nova IN ('Enviada','Corrigida') AND h.criado_em <= c.concluida_em) AS recebida, p.enviada_em, p.aprovada_em
                                         FROM pi_conferencias c JOIN pis p ON p.id = c.pi_id WHERE {$w} AND c.concluida_em BETWEEN :d1 AND :d2 ORDER BY c.concluida_em", $pt);
                $grupos = [];
                foreach ($rows as $x) {
                    $g = match ($f['visao']) { 'diaria' => substr($x['concluida_em'], 0, 10), 'semanal' => date('Y-m-d', strtotime(substr($x['concluida_em'], 0, 10) . ' -' . ((int) date('N', strtotime($x['concluida_em'])) - 1) . ' days')), default => substr($x['concluida_em'], 0, 7) };
                    $grupos[$g] ??= ['conferencias' => 0, 'aprovadas' => 0, 'devolvidas' => 0, 'horas' => 0.0, 'ciclos' => []];
                    $grupos[$g]['conferencias']++;
                    $grupos[$g][$x['resultado'] === 'aprovada' ? 'aprovadas' : 'devolvidas']++;
                    $grupos[$g]['horas'] += $x['recebida'] ? max(0, (strtotime($x['concluida_em']) - strtotime($x['recebida'])) / 3600) : 0;
                    if ($x['resultado'] === 'aprovada' && $x['enviada_em']) {
                        $grupos[$g]['ciclos'][] = Calendario::diasUteisEntre((string) $x['enviada_em'], (string) $x['concluida_em']);
                    }
                }
                ksort($grupos);
                $l = [];
                foreach ($grupos as $g => $v) {
                    $l[] = ['grupo' => self::rotuloPeriodo($g, $f['visao']), 'conferencias' => $v['conferencias'], 'aprovadas' => $v['aprovadas'], 'devolvidas' => $v['devolvidas'],
                        'horas' => $v['conferencias'] ? $v['horas'] / $v['conferencias'] : null, 'ciclo' => $v['ciclos'] ? array_sum($v['ciclos']) / count($v['ciclos']) : null];
                }
                return ['colunas' => [['grupo', 'Período', 'text'], ['conferencias', 'Conferências', 'int'], ['aprovadas', 'Aprovadas', 'int'], ['devolvidas', 'Devolvidas', 'int'],
                    ['horas', 'Tempo médio até a decisão (horas)', 'dec'], ['ciclo', 'Ciclo envio → aprovação (dias úteis)', 'dec']], 'linhas' => $l];
            })(),
            'prontas_faturamento' => ['colunas' => [['numero', 'Nº da PI', 'text'], ['veiculo', 'Veículo', 'text'], ['cliente_razao', 'Cliente', 'text'], ['executivo', 'Executivo', 'text'], ['data_fim_veiculacao', 'Fim da veiculação', 'date'],
                    ['venc', '1º vencimento', 'date'], ['valor_liquido', 'Líquido a faturar', 'money']],
                'linhas' => Database::all("SELECT p.numero, v.nome AS veiculo, p.cliente_razao, u.nome AS executivo, p.data_fim_veiculacao, (SELECT MIN(pa.vencimento) FROM pi_parcelas pa WHERE pa.pi_id = p.id) AS venc, p.valor_liquido
                                             FROM pis p JOIN veiculos v ON v.id = p.veiculo_id JOIN usuarios u ON u.id = p.executivo_id WHERE {$w} AND p.situacao = 'Pronta para faturamento' ORDER BY p.data_fim_veiculacao", $p)],
            'projetos' => (function () {
                $vend = ProjetoService::vendidas();
                $res = ProjetoService::reservadas();
                $l = [];
                foreach (Database::all('SELECT * FROM projetos_especiais WHERE excluido_em IS NULL ORDER BY data_limite_venda DESC') as $P) {
                    $P['cotas'] = Database::all('SELECT * FROM projetos_cotas WHERE projeto_id = ?', [(int) $P['id']]);
                    $st = ProjetoService::situacao($P, $vend, $res);
                    $valor = (float) Database::value("SELECT COALESCE(SUM(i.valor_total), 0) FROM pi_itens i JOIN pis p ON p.id = i.pi_id JOIN projetos_cotas c ON c.id = i.projeto_cota_id WHERE c.projeto_id = ? AND p.situacao NOT IN " . self::MORTAS, [(int) $P['id']]);
                    $l[] = ['codigo' => $P['codigo'], 'nome' => $P['nome'], 'ate' => $P['data_limite_venda'], 'situacao' => $st['situacao'], 'total' => $st['total'], 'vendidas' => $st['vendidas'],
                        'reservadas' => $st['reservadas'], 'disponiveis' => $st['disponiveis'], 'vendido' => $valor, 'falta' => $st['falta_vender']];
                }
                return ['colunas' => [['codigo', 'Código', 'text'], ['nome', 'Projeto', 'text'], ['ate', 'Venda até', 'date'], ['situacao', 'Situação', 'text'], ['total', 'Cotas', 'int'], ['vendidas', 'Vendidas', 'int'],
                    ['reservadas', 'Reservadas', 'int'], ['disponiveis', 'Disponíveis', 'int'], ['vendido', 'Vendido (R$)', 'money'], ['falta', 'Falta vender (R$)', 'money']], 'linhas' => $l];
            })(),
            'cotas' => (function () {
                $vend = ProjetoService::vendidas();
                $res = ProjetoService::reservadas();
                $l = Database::all('SELECT c.id, p.codigo, p.nome AS projeto, p.data_limite_venda, c.nome AS cota, c.valor, c.quantidade_total FROM projetos_cotas c JOIN projetos_especiais p ON p.id = c.projeto_id WHERE p.excluido_em IS NULL ORDER BY p.nome, c.ordem');
                foreach ($l as &$x) {
                    $x['vendidas'] = $vend[(int) $x['id']] ?? 0;
                    $x['reservadas'] = $res[(int) $x['id']] ?? 0;
                    $x['disponiveis'] = max(0, (int) $x['quantidade_total'] - $x['vendidas'] - $x['reservadas']);
                }
                return ['colunas' => [['projeto', 'Projeto', 'text'], ['cota', 'Cota', 'text'], ['valor', 'Valor da cota', 'money'], ['data_limite_venda', 'Venda até', 'date'], ['quantidade_total', 'Total', 'int'],
                    ['vendidas', 'Vendidas', 'int'], ['reservadas', 'Reservadas', 'int'], ['disponiveis', 'Disponíveis', 'int']], 'linhas' => $l];
            })(),
            'reservas_ativas' => (function () {
                $l = ReservaService::ativas();
                if (Auth::escopoPis() === 'proprias') {
                    $l = array_values(array_filter($l, fn ($r) => $r['meu']));
                }
                return ['colunas' => [['cliente_nome', 'Cliente', 'text'], ['veiculo', 'Veículo', 'text'], ['executivo', 'Executivo', 'text'], ['resumo_itens', 'Itens reservados', 'text'],
                    ['reservada_em', 'Reservada em', 'datetime'], ['expira_em', 'Expira', 'datetime'], ['tempo_restante', 'Tempo restante', 'text']], 'linhas' => $l];
            })(),
        };
        $out['nota'] = trim(($out['nota'] ?? '') . ' ' . $nota);
        $out['totais'] = self::totais($out['colunas'], $out['linhas']);
        return $out;
    }

    /** Soma colunas numéricas (exceto percentuais e médias). @param list<array{0:string,1:string,2:string}> $cols @param list<array<string,mixed>> $linhas @return array<string,mixed>|null */
    private static function totais(array $cols, array $linhas): ?array
    {
        if (!$linhas) {
            return null;
        }
        $t = [];
        $tem = false;
        foreach ($cols as [$k, , $tipo]) {
            if (in_array($tipo, ['int', 'money'], true) && !in_array($k, ['ticket', 'sequencia', 'ciclo', 'antecedencia', 'valor'], true)) {
                $t[$k] = array_sum(array_map(fn ($l) => (float) ($l[$k] ?? 0), $linhas));
                $tem = true;
            }
        }
        if (isset($t['negociado'], $t['pis']) && in_array('ticket', array_column($cols, 0), true)) {
            $t['ticket'] = $t['pis'] ? $t['negociado'] / $t['pis'] : null;
        }
        return $tem ? $t : null;
    }
}
