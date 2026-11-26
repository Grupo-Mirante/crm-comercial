<?php
/** Versão de impressão da PI (A4 paisagem), a partir da versão gravada. */
$f = $snap['f'] ?? [];
$E = $snap['emissora'] ?? [];
$T = $R['T'] ?? [];
$multi = !empty($R['multi']);
$m = fn ($v) => number_format((float) $v, 2, ',', '.');
$ag = ($f['venda'] ?? '') === 'Agência';
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><title>PI <?= e($pi['numero']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
@page { size: A4 landscape; margin: 8mm; }
* { box-sizing: border-box; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 8px; color: #000; margin: 0; padding: 12px; }
.bar { display: flex; gap: 8px; margin-bottom: 10px; font-family: system-ui, sans-serif; font-size: 13px; }
.bar button, .bar a { padding: 6px 12px; border: 1px solid #1F3A5F; border-radius: 6px; background: #1F3A5F; color: #fff; text-decoration: none; cursor: pointer; }
.bar a { background: #fff; color: #1F3A5F; }
.top { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #1F3A5F; padding-bottom: 4px; }
.top img { height: 18px; }
.top h1 { font-size: 12px; color: #1F3A5F; margin: 0; flex: 1; }
.top .veic { color: #C00000; font-weight: bold; font-size: 12px; }
.emi { color: #595959; font-size: 7px; display: flex; justify-content: space-between; margin: 3px 0 6px; }
.emi b { color: <?= (int) ($R['pend'] ?? 0) ? '#C00000' : '#375623' ?>; }
table { width: 100%; border-collapse: collapse; margin-bottom: 4px; table-layout: fixed; }
td, th { border: 1px solid #B7C0CC; padding: 2px 3px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; }
th { background: #1F3A5F; color: #fff; font-size: 6.5px; text-align: center; }
.lab { background: #E7EBF1; font-weight: bold; font-size: 6.5px; color: #404040; }
.band { background: #1F3A5F; color: #fff; font-weight: bold; font-size: 8px; padding: 2px 4px; margin-top: 4px; }
.r { text-align: right; white-space: nowrap; } .c { text-align: center; }
.d { width: 14px; text-align: center; font-size: 7px; padding: 1px 0; }
.we { background: #FCE4D6; } .out { background: #D9D9D9; } .no { background: #8C8C8C; }
.sub td { background: #E7EBF1; font-weight: bold; }
.cl { font-size: 6px; margin: 1px 0; }
.sig { display: flex; gap: 24px; margin-top: 22px; }
.sig div { flex: 1; border-top: 1px solid #000; text-align: center; padding-top: 2px; font-size: 6.5px; }
@media print { .bar { display: none; } body { padding: 0; } }
</style></head>
<body>
<div class="bar"><button type="button" data-print>Imprimir</button><a href="<?= e(url('/pis/' . $pi['id'])) ?>">Voltar para a PI</a></div>
<div class="top"><img src="<?= e(asset('img/logo-grupo-mirante-azul.png')) ?>" alt="Grupo Mirante"><h1>GRUPO MIRANTE DE COMUNICAÇÃO · PEDIDO DE INSERÇÃO E AUTORIZAÇÃO DE PUBLICIDADE</h1><span class="veic"><?= e($E['titulo'] ?? '') ?></span></div>
<div class="emi"><span><?= e(implode('  ·  ', array_filter([$E['razao'] ?? null, !empty($E['cnpj']) ? 'CNPJ ' . $E['cnpj'] : null, $E['endereco'] ?? null, !empty($E['telefone']) ? 'Tel. ' . $E['telefone'] : null]))) ?></span>
    <b><?= (int) ($R['pend'] ?? 0) ? 'RASCUNHO: ' . (int) $R['pend'] . ' pendência(s) na conferência — não enviar ao cliente' : 'Conferida automaticamente: sem pendências' ?></b></div>

<table><tr class="lab"><td>Nº DA PI</td><td>Nº DO RP</td><td>TIPO</td><td>PI DE ORIGEM</td><td>EMISSÃO</td><td>TABELA / PRAÇA</td><td>EXECUTIVO DE CONTAS</td><td>MATRÍCULA</td><td>GESTOR COMERCIAL</td></tr>
<tr><td><b><?= e($f['num'] ?? '') ?></b></td><td><?= e($f['rp'] ?? '') ?></td><td><?= e(($f['tipo'] ?? '') . (!empty($f['parte']) ? ' (PI ' . $f['parte'] . ')' : '')) ?></td><td><?= e($f['origem'] ?? '') ?></td><td class="c"><?= e(data_br($f['emissao'] ?? '')) ?></td><td><?= e($R['tab'] ?? '') ?></td><td><?= e($f['exec'] ?? '') ?></td><td class="c"><?= e($f['mat'] ?? '') ?></td><td><?= e($f['gestor'] ?? '') ?></td></tr></table>

<table><tr><th colspan="4" style="text-align:left">AGÊNCIA<?= $ag ? '' : ' (venda direta)' ?></th><th colspan="4" style="text-align:left">CLIENTE (ANUNCIANTE)</th></tr>
<tr class="lab"><td>AGÊNCIA</td><td>CNPJ</td><td>COMISSÃO</td><td>CARTA · VALIDADE</td><td>RAZÃO SOCIAL</td><td>CNPJ / CPF</td><td>ENDEREÇO · CIDADE / UF</td><td>CONTATO · TELEFONE · E-MAIL DE FATURAMENTO</td></tr>
<tr><td><?= e($ag ? ($f['ag_nome'] ?? '') : '') ?></td><td><?= e($ag ? ($f['ag_cnpj'] ?? '') : '') ?></td><td class="c"><?= $ag ? e(number_format((float) ($f['ag_com'] ?? 0), 2, ',', '.')) . '%' : '' ?></td><td><?= e($ag ? trim(($f['ag_carta'] ?? '') . ' · ' . data_br($f['ag_val'] ?? ''), ' ·') : '') ?></td>
<td><b><?= e($f['cli_razao'] ?? '') ?></b></td><td><?= e($f['cli_cnpj'] ?? '') ?></td><td><?= e(trim(($f['cli_end'] ?? '') . ' · ' . ($f['cli_cid'] ?? '') . ' - ' . ($f['cli_uf'] ?? ''), ' ·-')) ?></td><td><?= e(implode(' · ', array_filter([$f['cli_contato'] ?? '', $f['cli_tel'] ?? '', $f['cli_email'] ?? '']))) ?></td></tr></table>

<div class="band">CAMPANHA</div>
<table><tr class="lab"><td>TÍTULO</td><td>PRODUTO / PROJETO</td><td>INÍCIO</td><td>FIM</td><td>PRAZO</td><td>PRAÇA DE COBRANÇA</td><td>FORMA DE PAGAMENTO</td></tr>
<tr><td><b><?= e($f['titulo'] ?? '') ?></b></td><td><?= e($f['produto'] ?? '') ?></td><td class="c"><?= e(data_br($f['ini'] ?? '')) ?></td><td class="c"><?= e(data_br($f['fim'] ?? '')) ?></td><td class="c"><?= e(!empty($f['prazo']) ? $f['prazo'] . ' dias' : '') ?></td><td><?= e($f['pcob'] ?? '') ?></td><td><?= e($f['forma'] ?? '') ?></td></tr></table>

<?php if (!empty($R['ins'])): $one = !$multi ? ($R['months'][0] ?? null) : null; ?>
<div class="band">COMPRA DE INSERÇÕES<?= $multi ? ' · uma linha por mês' : '' ?></div>
<table><tr><th style="width:42px">SIGLA</th><th style="width:110px">PROGRAMA</th><th style="width:22px">DUR</th><th style="width:26px">ÍND.</th><th style="width:42px">COBR.</th>
<?php for ($d = 1; $d <= 31; $d++): $di = $one ? \App\Helpers\Calendario::diaInfo($one, $f['ini'] ?? null, $f['fim'] ?? null, $d) : ['exists' => false]; ?><th class="d"><?= $di['exists'] ? e($di['letter']) . '<br>' : '' ?><?= $d ?></th><?php endfor; ?>
<th style="width:28px">Nº</th><th style="width:48px">UNIT.</th><th style="width:52px">BRUTO</th><th style="width:28px">DESC.</th><th style="width:56px">TOTAL</th></tr>
<?php foreach ($R['ins'] as $x): $M = $x['M'] ?? null; $g = (array) ($x['g'] ?? []); ?>
<tr><td><b><?= e($x['sigla'] ?? '') ?></b></td><td><?= e(($x['nome'] ?? '') . ($multi && $M ? ' · ' . $M['short'] : '')) ?></td><td class="c"><?= (int) ($x['l']['dur'] ?? 0) ?></td><td class="c"><?= e(rtrim(rtrim(number_format((float) $x['idx'], 3, ',', ''), '0'), ',')) ?></td><td class="c"><?= e($x['cob'] ?? '') ?></td>
<?php for ($d = 1; $d <= 31; $d++): $di = $M ? \App\Helpers\Calendario::diaInfo($M, $f['ini'] ?? null, $f['fim'] ?? null, $d) : ['exists' => false, 'ok' => false]; $q = (int) ($g[$d] ?? $g[(string) $d] ?? 0); ?>
<td class="d <?= !$di['exists'] ? 'no' : (!$di['ok'] ? 'out' : (!empty($di['weekend']) ? 'we' : '')) ?>"><?= $q ?: '' ?></td><?php endfor; ?>
<td class="c"><?= (int) $x['n'] ?></td><td class="r"><?= $x['unit'] !== null ? $m($x['unit']) : '—' ?></td><td class="r"><?= $m($x['bruto']) ?></td><td class="c"><?= (float) ($x['desc'] ?? 0) ? number_format((float) $x['desc'], 1, ',', '') . '%' : '' ?></td><td class="r"><?= $m($x['total']) ?></td></tr>
<?php endforeach; ?>
<tr class="sub"><td colspan="36">SUBTOTAL INSERÇÕES</td><td class="c"><?= numero(array_sum(array_column($R['ins'], 'n'))) ?></td><td></td><td class="r"><?= $m($T['insBruto'] ?? 0) ?></td><td></td><td class="r"><?= $m($T['insTotal'] ?? 0) ?></td></tr></table>
<?php endif; ?>

<?php foreach ([['pat', 'COMPRA DE PATROCÍNIOS', 'patTab', 'patTotal'], ['dig', 'PATROCÍNIO E MÍDIA DIGITAL', 'digTab', 'digTotal'], ['prj', 'PROJETOS ESPECIAIS', 'prjTab', 'prjTotal'], ['avi', 'PUBLICIDADE LEGAL', 'aviTab', 'aviTotal']] as [$k, $tit, $kt, $kn]): if (empty($R[$k])) continue; ?>
<div class="band"><?= e($tit) ?></div>
<table><tr><th style="width:50px">SIGLA</th><th>DESCRIÇÃO</th><th style="width:220px">DETALHE</th><th style="width:60px">COBRANÇA</th><th style="width:40px">QTD</th><th style="width:60px">UNITÁRIO</th><th style="width:66px">TABELA</th><th style="width:36px">DESC.</th><th style="width:66px">TOTAL</th></tr>
<?php foreach ($R[$k] as $x):
    $det = match ($k) {
        'pat' => (!empty($x['mes']) ? $x['mes'] . ' · ' : '') . $x['n'] . ' inserções',
        'dig' => $x['mod'] . ' · ' . data_br($x['ini']) . ' a ' . data_br($x['fim']) . ($x['mod'] === 'Mensal' ? ' (' . implode(' + ', $x['mdet'] ?? []) . ')' : ''),
        'prj' => ($x['cota'] ?? '') . ' · venda até ' . data_br($x['ate'] ?? ''),
        default => ($x['l']['mat'] ?? '') . ' · ' . (float) ($x['l']['col'] ?? 0) . ' col × ' . (float) ($x['l']['cm'] ?? 0) . ' cm · ' . ($x['l']['datas'] ?? ''),
    };
    $qtd = match ($k) { 'pat' => $x['cot'], 'dig' => $x['mod'] === 'CPM' ? numero($x['ent']) . ' impr.' : numero($x['qtd']), 'prj' => $x['q'], default => $x['pub'] };
    $unit = match ($k) { 'pat' => $x['valor'] !== null ? $x['valor'] / max(1, $x['cot']) : null, default => $x['unit'] };
    $tab = match ($k) { 'pat' => $x['valor'] ?? 0, default => $x['tab'] }; ?>
<tr><td><b><?= e($x['sigla'] ?? ($x['codigo'] ?? '')) ?></b></td><td><?= e($x['nome']) ?></td><td><?= e($det) ?></td><td class="c"><?= e($x['cob'] ?? '') ?></td><td class="c"><?= e((string) $qtd) ?></td><td class="r"><?= $unit !== null ? $m($unit) : 'sob consulta' ?></td><td class="r"><?= $m($tab) ?></td><td class="c"><?= (float) ($x['desc'] ?? 0) ? number_format((float) $x['desc'], 1, ',', '') . '%' : '' ?></td><td class="r"><?= $m($x['total']) ?></td></tr>
<?php endforeach; ?>
<tr class="sub"><td colspan="6">SUBTOTAL</td><td class="r"><?= $m($T[$kt] ?? 0) ?></td><td></td><td class="r"><?= $m($T[$kn] ?? 0) ?></td></tr></table>
<?php endforeach; ?>
<?php if (!empty($f['texto']) && !empty($R['avi'])): ?><div class="cl"><b>TEXTO DO ANÚNCIO:</b> <?= e($f['texto']) ?></div><?php endif; ?>

<?php if (!empty($R['ent'])): ?>
<div class="band">ENTREGAS ESPECIAIS</div>
<table><tr><th>ENTREGA</th><th>ESPECIFICAÇÃO</th><th>QTD</th><th>ÁREA RESPONSÁVEL</th><th>Nº DO BRIEFING</th><th>AVAL DO JURÍDICO</th></tr>
<?php foreach ($R['ent'] as $en): ?><tr><td><?= e($en['tipo'] ?? '') ?></td><td><?= e($en['esp'] ?? '') ?></td><td class="c"><?= e((string) ($en['qtd'] ?? '')) ?></td><td><?= e($en['area'] ?? '') ?></td><td class="c"><?= e($en['brief'] ?? '') ?></td><td class="c"><?= e($en['jur'] ?? '') ?></td></tr><?php endforeach; ?></table>
<?php endif; ?>

<div class="band">VALORES</div>
<table><tr class="lab"><td>PRODUÇÃO / TAXAS</td><td>VALOR TOTAL DE TABELA</td><td>BONIFICADO</td><td>DESCONTO NOMINAL</td><td>DESCONTO MÉDIO</td><td>VALOR NEGOCIADO</td><td>COMISSÃO AGÊNCIA</td><td>VALOR LÍQUIDO</td></tr>
<tr><td class="r"><?= $m($T['producao'] ?? 0) ?></td><td class="r">R$ <?= $m($T['tab'] ?? 0) ?></td><td class="r"><?= $m($T['bonif'] ?? 0) ?></td><td class="r"><?= $m($T['desc'] ?? 0) ?></td><td class="c"><?= number_format((float) ($T['descPct'] ?? 0) * 100, 1, ',', '') ?>%</td>
<td class="r"><b>R$ <?= $m($T['neg'] ?? 0) ?></b></td><td class="r"><?= $m($T['com'] ?? 0) ?></td><td class="r"><b>R$ <?= $m($T['liq'] ?? 0) ?></b></td></tr></table>
<?php if (!empty($R['isTV']) && !empty($T['imp'])): ?><div class="cl">Impacto total: <?= numero($T['imp']) ?> · GRP total: <?= number_format((float) $T['grp'], 1, ',', '.') ?> · CPM: R$ <?= $m($T['cpm']) ?></div><?php endif; ?>

<div class="band">PAGAMENTO E OBSERVAÇÕES</div>
<table><tr class="lab"><td style="width:50%">PARCELAS · sem data definida, vence no último dia útil do mês seguinte a cada mês de veiculação</td><td>OBSERVAÇÕES</td></tr>
<tr><td><?= e(implode('    ', array_map(fn ($p, $i) => ($i + 1) . 'ª: ' . data_br($p['venc'] ?? '') . ' — R$ ' . $m($p['val'] ?? 0), $R['parcs'] ?? [], array_keys($R['parcs'] ?? [])))) ?></td>
<td><?= e(trim(($f['obs'] ?? '') . (!empty($f['gs_nome']) ? ' · Desconto/bonificação aprovados por ' . $f['gs_nome'] . ' em ' . data_br($f['gs_data'] ?? '') : ''), ' ·')) ?></td></tr></table>

<div class="band">CLÁUSULAS</div>
<?php foreach (($snap['clausulas'] ?? []) as $c): ?><div class="cl"><?= e($c) ?></div><?php endforeach; ?>
<?php foreach (array_filter([$E['cl6'] ?? '', $E['cl7'] ?? '', $E['atendimento'] ?? '']) as $c): ?><div class="cl"><?= e($c) ?></div><?php endforeach; ?>
<p style="margin-top:10px"><?= e(\App\Services\Configuracao::texto('praca_padrao_cidade', 'São Luís - MA')) ?>, <?= e(data_br($f['emissao'] ?? '')) ?> · <b>AUTORIZAMOS O PRESENTE CONTRATO CONFORME ESPECIFICAÇÕES E CLÁUSULAS AQUI CONTIDAS.</b></p>
<div class="sig"><div>CLIENTE<?= !empty($f['ac_nome']) ? ' — ' . e($f['ac_nome']) : '' ?></div><div><?= $ag ? 'AGÊNCIA — ' . e($f['ag_nome'] ?? '') : 'AGÊNCIA' ?></div><div>EXECUTIVO DE CONTAS — <?= e($f['exec'] ?? '') ?></div><div>GESTOR COMERCIAL — <?= e($f['gestor'] ?? '') ?></div></div>
<script src="<?= e(asset('js/imprimir.js')) ?>"></script>
</body></html>
