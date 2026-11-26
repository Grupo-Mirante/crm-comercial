<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Indicadores do painel, calculados direto do MySQL e sempre limitados ao que o usuário pode ver (RN-73).
 * Definições do documento de processos:
 *  - Vendas no período: PIs Nova e Renovação aprovadas (ou adiante no fluxo); valor bruto (tabela), negociado e líquido
 *  - Taxa de devolução: PIs devolvidas ao menos uma vez ÷ PIs conferidas
 *  - PI certa de primeira: PIs aprovadas sem nenhuma devolução ÷ PIs conferidas
 * Canceladas e substituídas não entram nos totais de venda (RN-55, RN-60).
 */
final class DashboardService
{
    public const APROVADAS = ['Aprovada', 'Em programação', 'Em veiculação', 'Pronta para faturamento', 'Faturada', 'Encerrada'];
    public const MORTAS = ['Cancelada', 'Substituída'];
    public const SITUACOES = ['Rascunho', 'Enviada', 'Devolvida', 'Corrigida', 'Aprovada', 'Em programação', 'Em veiculação', 'Pronta para faturamento', 'Faturada', 'Encerrada', 'Cancelada', 'Substituída'];

    /**
     * Filtros normalizados: período (de/até, padrão mês atual), empresa, veículo, executivo, gestor, situação.
     * @param array<string,mixed> $in
     * @return array<string,mixed>
     */
    public static function filtros(array $in): array
    {
        $de = data_iso((string) ($in['de'] ?? '')) ?: date('Y-m-01');
        $ate = data_iso((string) ($in['ate'] ?? '')) ?: date('Y-m-t');
        if ($ate < $de) {
            [$de, $ate] = [$ate, $de];
        }
        $sit = (string) ($in['situacao'] ?? '');
        return [
            'de' => $de, 'ate' => $ate,
            'empresa' => (int) ($in['empresa'] ?? 0) ?: null,
            'veiculo' => (int) ($in['veiculo'] ?? 0) ?: null,
            'executivo' => (int) ($in['executivo'] ?? 0) ?: null,
            'gestor' => (int) ($in['gestor'] ?? 0) ?: null,
            'situacao' => in_array($sit, self::SITUACOES, true) ? $sit : null,
        ];
    }

    /** @param array<string,mixed> $f @return array{0:string,1:array<string,mixed>} */
    private static function where(array $f, bool $periodo = true, bool $situacao = true): array
    {
        [$esc, $p] = Auth::filtroPis('p');
        $w = [$esc];
        if ($periodo) {
            $w[] = 'p.data_emissao BETWEEN :de AND :ate';
            $p['de'] = $f['de'];
            $p['ate'] = $f['ate'];
        }
        foreach (['empresa' => 'p.empresa_id', 'veiculo' => 'p.veiculo_id', 'executivo' => 'p.executivo_id', 'gestor' => 'p.gestor_id'] as $k => $col) {
            if ($f[$k]) {
                $w[] = "{$col} = :f_{$k}";
                $p['f_' . $k] = $f[$k];
            }
        }
        if ($situacao && $f['situacao']) {
            $w[] = 'p.situacao = :f_sit';
            $p['f_sit'] = $f['situacao'];
        }
        return [implode(' AND ', $w), $p];
    }

    /** @param array<string,mixed> $f @return array<string,mixed> */
    public static function indicadores(array $f): array
    {
        [$w, $p] = self::where($f);
        $inA = Database::lista(self::APROVADAS);
        $inM = Database::lista(self::MORTAS);
        $k = Database::first(
            "SELECT
                COUNT(*) AS geradas,
                SUM(p.situacao NOT IN {$inM}) AS ativas,
                SUM(CASE WHEN p.situacao NOT IN {$inM} THEN p.valor_tabela ELSE 0 END) AS bruto,
                SUM(CASE WHEN p.situacao NOT IN {$inM} THEN p.valor_negociado ELSE 0 END) AS negociado,
                SUM(CASE WHEN p.situacao NOT IN {$inM} THEN p.valor_liquido ELSE 0 END) AS liquido,
                SUM(p.situacao IN {$inA} AND p.tipo IN ('Nova','Renovação')) AS vendas,
                SUM(CASE WHEN p.situacao IN {$inA} AND p.tipo IN ('Nova','Renovação') THEN p.valor_negociado ELSE 0 END) AS vendas_valor,
                SUM(p.situacao = 'Cancelada') AS canceladas,
                SUM(p.situacao = 'Rascunho') AS rascunhos,
                SUM(p.fora_do_prazo = 1) AS fora_prazo
             FROM pis p WHERE {$w}",
            $p
        ) ?? [];
        // Situação atual (não depende do período): filas de trabalho
        [$w2, $p2] = self::where($f, false, false);
        $filas = Database::first(
            "SELECT SUM(p.situacao IN ('Enviada','Corrigida')) AS aguardando, SUM(p.situacao = 'Devolvida') AS devolvidas,
                    SUM(p.situacao = 'Pronta para faturamento') AS prontas, SUM(CASE WHEN p.situacao = 'Pronta para faturamento' THEN p.valor_liquido ELSE 0 END) AS prontas_valor
               FROM pis p WHERE {$w2}",
            $p2
        ) ?? [];
        $aprovadasPeriodo = (int) Database::value("SELECT COUNT(*) FROM pis p WHERE {$w2} AND p.aprovada_em BETWEEN :d1 AND :d2", $p2 + ['d1' => $f['de'] . ' 00:00:00', 'd2' => $f['ate'] . ' 23:59:59']);
        // Conferidas no período: PIs com ao menos uma conferência concluída no período
        $conf = Database::first(
            "SELECT COUNT(DISTINCT p.id) AS conferidas, COUNT(DISTINCT CASE WHEN p.qtd_devolucoes > 0 THEN p.id END) AS devolvidas_alguma,
                    COUNT(DISTINCT CASE WHEN p.aprovada_primeira = 1 THEN p.id END) AS primeira
               FROM pis p JOIN pi_conferencias c ON c.pi_id = p.id
              WHERE {$w2} AND c.concluida_em BETWEEN :d1 AND :d2",
            $p2 + ['d1' => $f['de'] . ' 00:00:00', 'd2' => $f['ate'] . ' 23:59:59']
        ) ?? [];
        $conferidas = (int) ($conf['conferidas'] ?? 0);
        $devolucoesPeriodo = (int) Database::value(
            "SELECT COUNT(*) FROM pi_devolucoes d JOIN pis p ON p.id = d.pi_id WHERE {$w2} AND d.devolvida_em BETWEEN :d1 AND :d2",
            $p2 + ['d1' => $f['de'] . ' 00:00:00', 'd2' => $f['ate'] . ' 23:59:59']
        );
        $ativas = (int) ($k['ativas'] ?? 0);
        // Projetos e reservas (visão geral, sem dados individuais)
        $projVendido = (float) Database::value(
            "SELECT COALESCE(SUM(i.valor_total), 0) FROM pi_itens i JOIN pis p ON p.id = i.pi_id WHERE i.bloco = 'projeto' AND p.situacao NOT IN {$inM} AND {$w}",
            $p
        );
        $projQtd = (int) Database::value(
            "SELECT COALESCE(SUM(i.quantidade), 0) FROM pi_itens i JOIN pis p ON p.id = i.pi_id WHERE i.bloco = 'projeto' AND p.situacao NOT IN {$inM} AND {$w}",
            $p
        );
        $vend = ProjetoService::vendidas();
        $res = ProjetoService::reservadas();
        $cotasDisp = 0;
        foreach (ProjetoService::ativos() as $P) {
            $st = ProjetoService::situacao($P, $vend, $res);
            if (!$st['vencido']) {
                $cotasDisp += $st['disponiveis'];
            }
        }
        $reservas = (int) Database::value("SELECT COUNT(*) FROM reservas WHERE situacao = 'ativa' AND expira_em > NOW()" . (Auth::escopoPis() === 'proprias' ? ' AND usuario_id = ' . (int) Auth::id() : ''));
        return [
            'geradas' => (int) ($k['geradas'] ?? 0),
            'ativas' => $ativas,
            'bruto' => (float) ($k['bruto'] ?? 0),
            'negociado' => (float) ($k['negociado'] ?? 0),
            'liquido' => (float) ($k['liquido'] ?? 0),
            'vendas' => (int) ($k['vendas'] ?? 0),
            'vendas_valor' => (float) ($k['vendas_valor'] ?? 0),
            'ticket' => $ativas > 0 ? (float) ($k['negociado'] ?? 0) / $ativas : 0.0,
            'canceladas' => (int) ($k['canceladas'] ?? 0),
            'rascunhos' => (int) ($k['rascunhos'] ?? 0),
            'fora_prazo' => (int) ($k['fora_prazo'] ?? 0),
            'aguardando' => (int) ($filas['aguardando'] ?? 0),
            'devolvidas' => (int) ($filas['devolvidas'] ?? 0),
            'prontas' => (int) ($filas['prontas'] ?? 0),
            'prontas_valor' => (float) ($filas['prontas_valor'] ?? 0),
            'aprovadas' => $aprovadasPeriodo,
            'conferidas' => $conferidas,
            'devolucoes_periodo' => $devolucoesPeriodo,
            'taxa_devolucao' => $conferidas ? (int) $conf['devolvidas_alguma'] / $conferidas : null,
            'primeira' => $conferidas ? (int) $conf['primeira'] / $conferidas : null,
            'projetos_valor' => $projVendido,
            'projetos_cotas' => $projQtd,
            'cotas_disponiveis' => $cotasDisp,
            'reservas_ativas' => $reservas,
        ];
    }

    /** Dados dos gráficos. @param array<string,mixed> $f @return array<string,mixed> */
    public static function graficos(array $f): array
    {
        [$w, $p] = self::where($f);
        $inM = Database::lista(self::MORTAS);
        $porVeiculo = Database::all(
            "SELECT v.nome AS rotulo, COUNT(p.id) AS qtd, COALESCE(SUM(p.valor_negociado), 0) AS valor
               FROM veiculos v LEFT JOIN pis p ON p.veiculo_id = v.id AND p.situacao NOT IN {$inM} AND {$w}
              GROUP BY v.id, v.nome, v.ordem ORDER BY v.ordem",
            $p
        );
        $mostrarNomes = Auth::can('relatorios.ranking_nominal') || Auth::escopoPis() !== 'proprias';
        $porExecutivo = $mostrarNomes ? Database::all(
            "SELECT u.nome AS rotulo, COUNT(p.id) AS qtd, SUM(p.valor_negociado) AS valor FROM pis p JOIN usuarios u ON u.id = p.executivo_id
              WHERE p.situacao NOT IN {$inM} AND {$w} GROUP BY u.id, u.nome ORDER BY valor DESC LIMIT 12",
            $p
        ) : [];
        $porSituacao = Database::all("SELECT p.situacao AS rotulo, COUNT(*) AS qtd FROM pis p WHERE {$w} GROUP BY p.situacao", $p);
        $ordem = array_flip(self::SITUACOES);
        usort($porSituacao, fn ($a, $b) => ($ordem[$a['rotulo']] ?? 99) <=> ($ordem[$b['rotulo']] ?? 99));
        // Evolução: 6 meses até o fim do período
        [$w3, $p3] = self::where($f, false);
        $inicio = date('Y-m-01', strtotime(substr($f['ate'], 0, 7) . '-01 -5 months'));
        $evol = Database::all(
            "SELECT DATE_FORMAT(p.data_emissao, '%Y-%m') AS mes, SUM(p.valor_negociado) AS negociado, SUM(p.valor_liquido) AS liquido, COUNT(*) AS qtd
               FROM pis p WHERE {$w3} AND p.situacao NOT IN {$inM} AND p.data_emissao BETWEEN :ini AND :fim GROUP BY mes ORDER BY mes",
            $p3 + ['ini' => $inicio, 'fim' => $f['ate']]
        );
        $meses = [];
        for ($i = 0; $i < 6; $i++) {
            $m = date('Y-m', strtotime($inicio . " +{$i} months"));
            $meses[$m] = ['mes' => $m, 'negociado' => 0.0, 'liquido' => 0.0, 'qtd' => 0];
        }
        foreach ($evol as $e) {
            if (isset($meses[$e['mes']])) {
                $meses[$e['mes']] = ['mes' => $e['mes'], 'negociado' => (float) $e['negociado'], 'liquido' => (float) $e['liquido'], 'qtd' => (int) $e['qtd']];
            }
        }
        [$w2, $p2] = self::where($f, false, false);
        $motivos = Database::all(
            "SELECT c.motivo_codigo AS rotulo, m.descricao, COUNT(*) AS qtd FROM pi_devolucao_codigos c JOIN pi_devolucoes d ON d.id = c.devolucao_id
               JOIN pis p ON p.id = d.pi_id JOIN motivos_devolucao m ON m.codigo = c.motivo_codigo
              WHERE {$w2} AND d.devolvida_em BETWEEN :d1 AND :d2 GROUP BY c.motivo_codigo, m.descricao ORDER BY qtd DESC LIMIT 8",
            $p2 + ['d1' => $f['de'] . ' 00:00:00', 'd2' => $f['ate'] . ' 23:59:59']
        );
        return [
            'veiculos' => array_map(fn ($r) => ['rotulo' => $r['rotulo'], 'qtd' => (int) $r['qtd'], 'valor' => (float) $r['valor']], $porVeiculo),
            'executivos' => array_map(fn ($r) => ['rotulo' => $r['rotulo'], 'qtd' => (int) $r['qtd'], 'valor' => (float) $r['valor']], $porExecutivo),
            'situacoes' => array_map(fn ($r) => ['rotulo' => $r['rotulo'], 'qtd' => (int) $r['qtd']], $porSituacao),
            'evolucao' => array_values($meses),
            'motivos' => array_map(fn ($r) => ['rotulo' => $r['rotulo'], 'descricao' => $r['descricao'], 'qtd' => (int) $r['qtd']], $motivos),
            'mostrarNomes' => $mostrarNomes,
        ];
    }

    /**
     * "O que fazer hoje" conforme o perfil.
     * @return list<array{icone:string,texto:string,n:int,link:string,alerta:bool}>
     */
    public static function tarefas(): array
    {
        $uid = (int) Auth::id();
        $t = [];
        if (Auth::can('pis.criar')) {
            $dev = (int) Database::value("SELECT COUNT(*) FROM pis WHERE executivo_id = ? AND situacao = 'Devolvida'", [$uid]);
            $t[] = ['icone' => 'arrow-return-left', 'texto' => 'PIs devolvidas para você corrigir (prazo: 1 dia útil)', 'n' => $dev, 'link' => '/pis?situacao=Devolvida&minhas=1', 'alerta' => $dev > 0];
            $ras = (int) Database::value("SELECT COUNT(*) FROM pis WHERE executivo_id = ? AND situacao = 'Rascunho'", [$uid]);
            $t[] = ['icone' => 'pencil-square', 'texto' => 'Rascunhos com pendência', 'n' => $ras, 'link' => '/pis?situacao=Rascunho&minhas=1', 'alerta' => false];
            $em = (int) Database::value('SELECT COUNT(*) FROM pi_rascunhos WHERE usuario_id = ? AND pi_id IS NULL', [$uid]);
            $t[] = ['icone' => 'file-earmark-text', 'texto' => 'PIs em montagem (sem número)', 'n' => $em, 'link' => '/pis/nova', 'alerta' => false];
            $res = (int) Database::value("SELECT COUNT(*) FROM reservas WHERE usuario_id = ? AND situacao = 'ativa' AND expira_em > NOW()", [$uid]);
            $t[] = ['icone' => 'hourglass-split', 'texto' => 'Suas reservas de 24h ativas', 'n' => $res, 'link' => '/reservas', 'alerta' => false];
        }
        if (Auth::can('conferencia.conferir')) {
            [$w, $p] = Auth::filtroPis('p');
            $fila = (int) Database::value("SELECT COUNT(*) FROM pis p WHERE {$w} AND p.situacao IN ('Enviada','Corrigida')", $p);
            $t[] = ['icone' => 'clipboard-check', 'texto' => 'PIs aguardando conferência', 'n' => $fila, 'link' => '/conferencia', 'alerta' => $fila > 0];
            $proximas = (int) Database::value(
                "SELECT COUNT(*) FROM pis p WHERE {$w} AND p.situacao IN ('Rascunho','Enviada','Devolvida','Corrigida') AND p.data_inicio BETWEEN CURDATE() AND CURDATE() + INTERVAL :d DAY",
                $p + ['d' => Configuracao::inteiro('sla_alerta_inicio_dias', 5)]
            );
            $t[] = ['icone' => 'alarm', 'texto' => 'PIs com início nos próximos ' . Configuracao::inteiro('sla_alerta_inicio_dias', 5) . ' dias ainda sem aprovação', 'n' => $proximas, 'link' => '/pis?sem_aprovacao=1', 'alerta' => $proximas > 0];
        }
        if (Auth::can('aprovacoes.aprovar') || Auth::can('aprovacoes.aprovar_diretoria')) {
            $n = \App\Helpers\Menu::aprovacoesPendentes();
            $t[] = ['icone' => 'check2-square', 'texto' => 'Descontos e bonificações aguardando sua aprovação', 'n' => $n, 'link' => '/aprovacoes', 'alerta' => $n > 0];
        }
        if (Auth::can('operacao.programar')) {
            $n = (int) Database::value("SELECT COUNT(*) FROM pis WHERE situacao = 'Aprovada'");
            $t[] = ['icone' => 'calendar2-week', 'texto' => 'PIs aprovadas aguardando programação (OPEC)', 'n' => $n, 'link' => '/pis?situacao=Aprovada', 'alerta' => false];
        }
        if (Auth::can('financeiro.faturar')) {
            $n = (int) Database::value("SELECT COUNT(*) FROM pis WHERE situacao = 'Pronta para faturamento'");
            $t[] = ['icone' => 'receipt', 'texto' => 'PIs prontas para faturamento', 'n' => $n, 'link' => '/faturamento', 'alerta' => $n > 0];
        }
        return $t;
    }

    /** @return array<string,list<array<string,mixed>>> listas para os filtros */
    public static function opcoesFiltros(): array
    {
        $escopo = Auth::escopoPis();
        $execs = match ($escopo) {
            'todas' => Database::all("SELECT u.id, u.nome FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE p.codigo IN ('exec','vend','gerente','diretor','admin') ORDER BY u.nome"),
            'equipe' => Database::all('SELECT id, nome FROM usuarios WHERE gestor_id = ? OR id = ? ORDER BY nome', [Auth::id(), Auth::id()]),
            default => [],
        };
        return [
            'empresas' => Database::all('SELECT id, nome_curto AS nome FROM empresas WHERE ativo = 1 ORDER BY nome_curto'),
            'veiculos' => Database::all('SELECT id, nome FROM veiculos WHERE ativo = 1 ORDER BY ordem'),
            'executivos' => $execs,
            'gestores' => $escopo === 'todas' ? Database::all("SELECT u.id, u.nome FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE p.codigo IN ('gerente','diretor') AND u.ativo = 1 ORDER BY u.nome") : [],
        ];
    }
}
