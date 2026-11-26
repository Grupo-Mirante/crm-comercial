<?php
$cards = [
    ['/cadastros/empresas', 'building', 'Empresas emissoras', $c['empresas'] . ' empresas' . ($c['pendentes'] ? ' · ' . $c['pendentes'] . ' com dados a preencher' : ''), $c['pendentes'] > 0],
    ['/cadastros/veiculos', 'broadcast', 'Veículos', $c['veiculos'] . ' veículos · blocos de mídia, cláusulas 6 e 7', false],
    ['/cadastros/tabelas', 'table', 'Tabelas de preços', $c['tabelas'] . ' tabelas · ' . numero($c['precos']) . ' preços · anos ' . implode(', ', $c['anos']), false],
    ['/cadastros/programas', 'tv', 'Programas (TV e rádio)', $c['programas'] . ' programas · dias, faixa, índice do 15", esquema de patrocínio', false],
    ['/cadastros/formatos', 'display', 'Formatos digitais', $c['formatos'] . ' formatos · G1 Maranhão e Imirante, inclusive publicidade legal', false],
    ['/cadastros/indices', 'percent', 'Índices de duração (TV)', 'Preço = 30" da praça × índice da duração', false],
    ['/cadastros/clausulas', 'file-text', 'Cláusulas da PI', 'Textos fixos do rodapé e multa por rescisão', false],
    ['/cadastros/feriados', 'calendar-event', 'Feriados', $c['feriados'] . ' feriados futuros · usados nos prazos em dias úteis', false],
    ['/cadastros/listas', 'list-ul', 'Listas de opções', 'Formas de pagamento, tipos de entrega, áreas, praças de cobrança…', false],
];
?>
<div class="page-head"><div><h1>Tabelas e veículos</h1><p>Catálogo comercial usado pelo Gerador de PI. Alterações valem para as próximas PIs; as já geradas mantêm os valores da época.</p></div></div>
<?php if ($c['pendentes']): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i><div><b>Dados das empresas a preencher.</b> O documento de processos deixou razão social e CNPJ de algumas empresas “a preencher”. Complete em <a href="<?= e(url('/cadastros/empresas')) ?>">Empresas emissoras</a> para que saiam no cabeçalho da PI.</div></div>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($cards as [$link, $icone, $tit, $sub, $alerta]): ?>
        <div class="col-md-6 col-xl-4"><a class="kpi<?= $alerta ? ' kpi-alert' : '' ?>" href="<?= e(url($link)) ?>"><span class="kpi-icon"><i class="bi bi-<?= e($icone) ?>"></i></span>
            <div class="kpi-value" style="font-size:1.05rem"><?= e($tit) ?></div><div class="kpi-sub"><?= e($sub) ?></div></a></div>
    <?php endforeach; ?>
</div>
