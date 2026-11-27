<?php
/**
 * PDF da PI (Dompdf): uma página A4 paisagem. Só tabelas e blocos (o Dompdf não tem flexbox).
 * $z é a escala aplicada a fontes e espaçamentos; o PdfPiService reduz $z até o documento caber em uma página.
 */
$f = $snap['f'] ?? [];
$E = $snap['emissora'] ?? [];
$T = $R['T'] ?? [];
$multi = !empty($R['multi']);
$pend = (int) ($R['pend'] ?? 0);
$ag = ($f['venda'] ?? '') === 'Agência';
$m = fn ($v) => number_format((float) $v, 2, ',', '.');
$pct = fn ($v) => (float) $v ? number_format((float) $v, 1, ',', '') . '%' : '';
$s = fn (float $pt) => round($pt * $z, 2) . 'pt';
$v = fn ($x) => ($x = trim((string) ($x ?? ''))) !== '' ? e($x) : '<span class="vz">—</span>';
$NAVY = '#1F3A5F';
$ACC = '#2E6DB4';
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><title>PI <?= e($pi['numero'] ?? '') ?></title>
<style>
@page { size: A4 landscape; margin: <?= $s(16) ?> <?= $s(18) ?>; }
* { box-sizing: border-box; }
body { font-family: Helvetica, Arial, sans-serif; font-size: <?= $s(7) ?>; color: #1E2732; margin: 0; line-height: 1.25; }
table { width: 100%; border-collapse: collapse; }
td, th { vertical-align: top; padding: 0; }
.vz { color: #A3ADBA; }
.r { text-align: right; white-space: nowrap; } .c { text-align: center; } .b { font-weight: bold; }

/* Cabeçalho */
.hd td { vertical-align: middle; }
.hd .logo img { width: <?= $s(118) ?>; }
.hd .tit { padding-left: <?= $s(12) ?>; border-left: <?= $s(2) ?> solid <?= $ACC ?>; }
.hd .tit h1 { font-size: <?= $s(11.5) ?>; color: <?= $NAVY ?>; margin: 0; letter-spacing: <?= $s(0.4) ?>; }
.hd .tit .emi { font-size: <?= $s(6.3) ?>; color: #6B7684; margin-top: <?= $s(2) ?>; }
.veic { display: inline-block; background: #E8F0FA; color: <?= $NAVY ?>; font-weight: bold; font-size: <?= $s(8.5) ?>; padding: <?= $s(3) ?> <?= $s(8) ?>; border-radius: <?= $s(10) ?>; }
.num { background: <?= $NAVY ?>; color: #fff; border-radius: <?= $s(5) ?>; padding: <?= $s(4) ?> <?= $s(10) ?>; text-align: right; }
.num .l { font-size: <?= $s(5.8) ?>; color: #BFD0E6; letter-spacing: <?= $s(0.6) ?>; }
.num .n { font-size: <?= $s(14) ?>; font-weight: bold; }
.st { margin-top: <?= $s(4) ?>; padding: <?= $s(2.5) ?> <?= $s(6) ?>; border-radius: <?= $s(3) ?>; font-size: <?= $s(6.5) ?>; font-weight: bold; }
.st.ok { background: #EAF4EC; color: #2F6B3A; }
.st.pend { background: #FBE9E9; color: #B00020; }

/* Cartões de campos (rótulo pequeno em cima, valor embaixo) */
.sec { margin-top: <?= $s(5) ?>; }
.grid { border: <?= $s(0.6) ?> solid #D5DCE5; border-radius: <?= $s(4) ?>; }
.grid td { padding: <?= $s(2.5) ?> <?= $s(5) ?>; border-left: <?= $s(0.6) ?> solid #E4E9EF; }
.grid td.first { border-left: 0; }
.k { display: block; font-size: <?= $s(5.3) ?>; color: #6B7684; text-transform: uppercase; letter-spacing: <?= $s(0.3) ?>; margin-bottom: <?= $s(1) ?>; }
.val { font-size: <?= $s(7.2) ?>; }
.card-h { font-size: <?= $s(6.3) ?>; font-weight: bold; color: <?= $NAVY ?>; letter-spacing: <?= $s(0.6) ?>; padding: <?= $s(2.5) ?> <?= $s(5) ?>; background: #EEF2F7; border-bottom: <?= $s(0.6) ?> solid #D5DCE5; }
.card-h span { color: #6B7684; font-weight: normal; letter-spacing: 0; }

/* Títulos de seção */
.band { margin-top: <?= $s(6) ?>; margin-bottom: <?= $s(2) ?>; font-size: <?= $s(6.8) ?>; font-weight: bold; color: <?= $NAVY ?>; letter-spacing: <?= $s(0.8) ?>; border-left: <?= $s(3) ?> solid <?= $ACC ?>; padding-left: <?= $s(4) ?>; }
.band span { color: #6B7684; font-weight: normal; letter-spacing: 0; }

/* Tabelas de itens */
.it { table-layout: fixed; }
.it th { background: <?= $NAVY ?>; color: #fff; font-size: <?= $s(5.6) ?>; font-weight: bold; padding: <?= $s(2) ?> <?= $s(2) ?>; text-align: center; vertical-align: middle; border-left: <?= $s(0.5) ?> solid #34557F; }
.it td { padding: <?= $s(1.8) ?> <?= $s(2.5) ?>; border-bottom: <?= $s(0.5) ?> solid #E1E6EC; vertical-align: middle; font-size: <?= $s(6.6) ?>; overflow: hidden; }
.it tr.zz td { background: #F7F9FB; }
.it td.d { padding: <?= $s(1.8) ?> 0; text-align: center; font-size: <?= $s(6) ?>; border-left: <?= $s(0.5) ?> solid #EDF0F4; }
.it th.d { padding: <?= $s(1.5) ?> 0; font-size: <?= $s(5) ?>; line-height: 1.1; }
.it th.d.we { background: #34557F; }
.it td.we { background: #FFF3E8; } .it td.out { background: #ECEEF1; } .it td.no { background: #C9CED6; }
.it td.q { font-weight: bold; color: <?= $NAVY ?>; }
.it tr.sub td { background: #E8F0FA; font-weight: bold; color: <?= $NAVY ?>; border-bottom: 0; }

/* Rodapé: valores, pagamento e assinaturas */
.tot td { padding: <?= $s(2.5) ?> <?= $s(5) ?>; border-left: <?= $s(0.6) ?> solid #E4E9EF; vertical-align: middle; }
.tot td.first { border-left: 0; }
.tot .val { font-size: <?= $s(7.6) ?>; }
.tot td.hi { background: #E8F0FA; }
.tot td.hi .val { font-size: <?= $s(9.5) ?>; font-weight: bold; color: <?= $NAVY ?>; }
.tot td.liq { background: <?= $NAVY ?>; }
.tot td.liq .k { color: #BFD0E6; }
.tot td.liq .val { font-size: <?= $s(10.5) ?>; font-weight: bold; color: #fff; }
.cl { font-size: <?= $s(5.4) ?>; color: #4A5562; margin: 0 0 <?= $s(1) ?>; text-align: justify; }
.aut { margin-top: <?= $s(5) ?>; font-size: <?= $s(6.6) ?>; }
.sig { margin-top: <?= $s(18) ?>; }
.sig td { padding: 0 <?= $s(8) ?>; }
.sig td div { border-top: <?= $s(0.8) ?> solid #1E2732; text-align: center; padding-top: <?= $s(2) ?>; font-size: <?= $s(6) ?>; color: #4A5562; }
.sig td div b { color: #1E2732; }
.foot { position: fixed; bottom: <?= $s(-10) ?>; left: 0; right: 0; font-size: <?= $s(5.2) ?>; color: #8A94A1; }
.wm { position: fixed; top: 38%; left: 18%; font-size: 72pt; font-weight: bold; color: #B00020; opacity: 0.07; transform: rotate(-18deg); letter-spacing: 6pt; }
</style></head>
<body>
<?php if ($pend): ?><div class="wm">RASCUNHO</div><?php endif; ?>
<div class="foot"><table><tr><td>Gerado pelo CRM Comercial em <?= e(date('d/m/Y H:i')) ?></td><td class="r">PI <?= e($f['num'] ?? ($pi['numero'] ?? '')) ?></td></tr></table></div>

<!-- Cabeçalho -->
<table class="hd"><tr>
    <td class="logo" style="width:<?= $s(128) ?>"><?php if (!empty($logo)): ?><img src="<?= e($logo) ?>" alt="Grupo Mirante"><?php else: ?><b>GRUPO MIRANTE</b><?php endif; ?></td>
    <td class="tit">
        <h1>PEDIDO DE INSERÇÃO E AUTORIZAÇÃO DE PUBLICIDADE</h1>
        <div class="emi"><?= e(implode('  ·  ', array_filter([$E['razao'] ?? null, !empty($E['cnpj']) ? 'CNPJ ' . $E['cnpj'] : null, $E['endereco'] ?? null, !empty($E['telefone']) ? 'Tel. ' . $E['telefone'] : null]))) ?: 'Grupo Mirante de Comunicação' ?></div>
    </td>
    <td class="r" style="width:<?= $s(150) ?>; padding-right:<?= $s(8) ?>"><?php if (!empty($E['titulo'])): ?><span class="veic"><?= e($E['titulo']) ?></span><?php endif; ?></td>
    <td style="width:<?= $s(112) ?>"><div class="num"><div class="l">PI Nº</div><div class="n"><?= e($f['num'] ?? ($pi['numero'] ?? '')) ?></div></div></td>
</tr></table>
<?php if ($pend): ?><div class="st pend">RASCUNHO · <?= $pend ?> pendência(s) na conferência — não enviar ao cliente</div><?php endif; ?>

<!-- Identificação -->
<table class="grid sec"><tr>
    <td class="first"><span class="k">Emissão</span><span class="val b"><?= $v(data_br($f['emissao'] ?? '')) ?></span></td>
    <td><span class="k">Nº do RP</span><span class="val"><?= $v($f['rp'] ?? '') ?></span></td>
    <td><span class="k">Tipo</span><span class="val"><?= $v(($f['tipo'] ?? '') . (!empty($f['parte']) ? ' (PI ' . $f['parte'] . ')' : '')) ?></span></td>
    <td><span class="k">PI de origem</span><span class="val"><?= $v($f['origem'] ?? '') ?></span></td>
    <td><span class="k">Tabela / praça</span><span class="val"><?= $v($R['tab'] ?? '') ?></span></td>
    <td><span class="k">Executivo de contas</span><span class="val"><?= $v(trim(($f['exec'] ?? '') . (!empty($f['mat']) ? ' (mat. ' . $f['mat'] . ')' : ''))) ?></span></td>
    <td><span class="k">Gestor comercial</span><span class="val"><?= $v($f['gestor'] ?? '') ?></span></td>
</tr></table>

<!-- Agência e cliente -->
<table class="sec"><tr>
    <td style="width:40%; padding-right:<?= $s(4) ?>">
        <table class="grid">
            <tr><td class="first card-h" colspan="3">AGÊNCIA<?= $ag ? '' : ' <span>· venda direta</span>' ?></td></tr>
            <tr><td class="first" colspan="2"><span class="k">Agência</span><span class="val b"><?= $v($ag ? ($f['ag_nome'] ?? '') : '') ?></span></td>
                <td><span class="k">CNPJ</span><span class="val"><?= $v($ag ? ($f['ag_cnpj'] ?? '') : '') ?></span></td></tr>
            <tr><td class="first"><span class="k">Comissão</span><span class="val"><?= $ag ? e(number_format((float) ($f['ag_com'] ?? 0), 2, ',', '.')) . '%' : '<span class="vz">—</span>' ?></span></td>
                <td><span class="k">Carta</span><span class="val"><?= $v($ag ? ($f['ag_carta'] ?? '') : '') ?></span></td>
                <td><span class="k">Validade</span><span class="val"><?= $v($ag ? data_br($f['ag_val'] ?? '') : '') ?></span></td></tr>
        </table>
    </td>
    <td style="padding-left:<?= $s(4) ?>">
        <table class="grid">
            <tr><td class="first card-h" colspan="3">CLIENTE <span>· anunciante</span></td></tr>
            <tr><td class="first" colspan="2"><span class="k">Razão social</span><span class="val b"><?= $v($f['cli_razao'] ?? '') ?></span></td>
                <td style="width:32%"><span class="k">CNPJ / CPF</span><span class="val"><?= $v($f['cli_cnpj'] ?? '') ?></span></td></tr>
            <tr><td class="first" colspan="2"><span class="k">Endereço · cidade / UF</span><span class="val"><?= $v(trim(($f['cli_end'] ?? '') . ' · ' . ($f['cli_cid'] ?? '') . ' - ' . ($f['cli_uf'] ?? ''), ' ·-')) ?></span></td>
                <td><span class="k">Contato · telefone · e-mail de faturamento</span><span class="val"><?= $v(implode(' · ', array_filter([$f['cli_contato'] ?? '', $f['cli_tel'] ?? '', $f['cli_email'] ?? '']))) ?></span></td></tr>
        </table>
    </td>
</tr></table>

<!-- Campanha -->
<table class="grid sec"><tr>
    <td class="first" style="width:24%"><span class="k">Campanha (título)</span><span class="val b"><?= $v($f['titulo'] ?? '') ?></span></td>
    <td style="width:18%"><span class="k">Produto / projeto</span><span class="val"><?= $v($f['produto'] ?? '') ?></span></td>
    <td><span class="k">Período</span><span class="val b"><?= $v(trim(data_br($f['ini'] ?? '') . ' a ' . data_br($f['fim'] ?? ''), ' a')) ?></span></td>
    <td><span class="k">Prazo</span><span class="val"><?= $v(!empty($f['prazo']) ? $f['prazo'] . ' dias' : '') ?></span></td>
    <td><span class="k">Praça de cobrança</span><span class="val"><?= $v($f['pcob'] ?? '') ?></span></td>
    <td><span class="k">Forma de pagamento</span><span class="val"><?= $v($f['forma'] ?? '') ?></span></td>
</tr></table>

<?php if (!empty($R['ins'])): $one = !$multi ? ($R['months'][0] ?? null) : null; ?>
<div class="band">COMPRA DE INSERÇÕES<?= $multi ? ' <span>· uma linha por mês</span>' : ($one ? ' <span>· ' . e($one['short'] ?? '') . '</span>' : '') ?></div>
<table class="it"><tr><th style="width:5%">SIGLA</th><th style="width:13.5%">PROGRAMA</th><th style="width:2.6%">DUR</th><th style="width:3%">ÍND.</th><th style="width:4.6%">COBR.</th>
<?php for ($d = 1; $d <= 31; $d++): $di = $one ? \App\Helpers\Calendario::diaInfo($one, $f['ini'] ?? null, $f['fim'] ?? null, $d) : ['exists' => false]; ?><th class="d<?= !empty($di['weekend']) ? ' we' : '' ?>" style="width:1.5%"><?= $di['exists'] ? e($di['letter']) . '<br>' : '' ?><?= $d ?></th><?php endfor; ?>
<th style="width:3%">Nº</th><th style="width:5.4%">UNIT.</th><th style="width:6%">BRUTO</th><th style="width:3.5%">DESC.</th><th style="width:6.9%">TOTAL</th></tr>
<?php foreach (array_values($R['ins']) as $i => $x): $M = $x['M'] ?? null; $g = (array) ($x['g'] ?? []); ?>
<tr<?= $i % 2 ? ' class="zz"' : '' ?>><td class="b"><?= e($x['sigla'] ?? '') ?></td><td><?= e(($x['nome'] ?? '') . ($multi && $M ? ' · ' . ($M['short'] ?? '') : '')) ?></td><td class="c"><?= (int) ($x['l']['dur'] ?? 0) ?></td><td class="c"><?= e(rtrim(rtrim(number_format((float) ($x['idx'] ?? 0), 3, ',', ''), '0'), ',')) ?></td><td class="c"><?= e($x['cob'] ?? '') ?></td>
<?php for ($d = 1; $d <= 31; $d++): $di = $M ? \App\Helpers\Calendario::diaInfo($M, $f['ini'] ?? null, $f['fim'] ?? null, $d) : ['exists' => false, 'ok' => false]; $q = (int) ($g[$d] ?? $g[(string) $d] ?? 0); ?>
<td class="d<?= !$di['exists'] ? ' no' : (!$di['ok'] ? ' out' : (!empty($di['weekend']) ? ' we' : '')) ?><?= $q ? ' q' : '' ?>"><?= $q ?: '' ?></td><?php endfor; ?>
<td class="c b"><?= (int) ($x['n'] ?? 0) ?></td><td class="r"><?= ($x['unit'] ?? null) !== null ? $m($x['unit']) : '—' ?></td><td class="r"><?= $m($x['bruto'] ?? 0) ?></td><td class="c"><?= $pct($x['desc'] ?? 0) ?></td><td class="r b"><?= $m($x['total'] ?? 0) ?></td></tr>
<?php endforeach; ?>
<tr class="sub"><td colspan="36">SUBTOTAL INSERÇÕES</td><td class="c"><?= numero(array_sum(array_column($R['ins'], 'n'))) ?></td><td></td><td class="r"><?= $m($T['insBruto'] ?? 0) ?></td><td></td><td class="r"><?= $m($T['insTotal'] ?? 0) ?></td></tr></table>
<?php endif; ?>

<?php foreach ([['pat', 'COMPRA DE PATROCÍNIOS', 'patTab', 'patTotal'], ['dig', 'PATROCÍNIO E MÍDIA DIGITAL', 'digTab', 'digTotal'], ['prj', 'PROJETOS ESPECIAIS', 'prjTab', 'prjTotal'], ['avi', 'PUBLICIDADE LEGAL', 'aviTab', 'aviTotal']] as [$k, $tit, $kt, $kn]): if (empty($R[$k])) continue; ?>
<div class="band"><?= e($tit) ?></div>
<table class="it"><tr><th style="width:7%">SIGLA</th><th style="width:24%">DESCRIÇÃO</th><th style="width:27%">DETALHE</th><th style="width:7%">COBRANÇA</th><th style="width:7%">QTD</th><th style="width:7.5%">UNITÁRIO</th><th style="width:8%">TABELA</th><th style="width:4.5%">DESC.</th><th style="width:8%">TOTAL</th></tr>
<?php foreach (array_values($R[$k]) as $i => $x):
    $det = match ($k) {
        'pat' => (!empty($x['mes']) ? $x['mes'] . ' · ' : '') . ($x['n'] ?? 0) . ' inserções',
        'dig' => ($x['mod'] ?? '') . ' · ' . data_br($x['ini'] ?? '') . ' a ' . data_br($x['fim'] ?? '') . (($x['mod'] ?? '') === 'Mensal' ? ' (' . implode(' + ', $x['mdet'] ?? []) . ')' : ''),
        'prj' => ($x['cota'] ?? '') . ' · venda até ' . data_br($x['ate'] ?? ''),
        default => ($x['l']['mat'] ?? '') . ' · ' . (float) ($x['l']['col'] ?? 0) . ' col × ' . (float) ($x['l']['cm'] ?? 0) . ' cm · ' . ($x['l']['datas'] ?? ''),
    };
    $qtd = match ($k) { 'pat' => $x['cot'] ?? '', 'dig' => ($x['mod'] ?? '') === 'CPM' ? numero($x['ent'] ?? 0) . ' impr.' : numero($x['qtd'] ?? 0), 'prj' => $x['q'] ?? '', default => $x['pub'] ?? '' };
    $unit = match ($k) { 'pat' => ($x['valor'] ?? null) !== null ? $x['valor'] / max(1, (int) ($x['cot'] ?? 1)) : null, default => $x['unit'] ?? null };
    $tab = match ($k) { 'pat' => $x['valor'] ?? 0, default => $x['tab'] ?? 0 }; ?>
<tr<?= $i % 2 ? ' class="zz"' : '' ?>><td class="b"><?= e($x['sigla'] ?? ($x['codigo'] ?? '')) ?></td><td><?= e($x['nome'] ?? '') ?></td><td><?= e($det) ?></td><td class="c"><?= e($x['cob'] ?? '') ?></td><td class="c"><?= e((string) $qtd) ?></td><td class="r"><?= $unit !== null ? $m($unit) : 'sob consulta' ?></td><td class="r"><?= $m($tab) ?></td><td class="c"><?= $pct($x['desc'] ?? 0) ?></td><td class="r b"><?= $m($x['total'] ?? 0) ?></td></tr>
<?php endforeach; ?>
<tr class="sub"><td colspan="6">SUBTOTAL</td><td class="r"><?= $m($T[$kt] ?? 0) ?></td><td></td><td class="r"><?= $m($T[$kn] ?? 0) ?></td></tr></table>
<?php endforeach; ?>
<?php if (!empty($f['texto']) && !empty($R['avi'])): ?><div class="cl" style="margin-top:<?= $s(2) ?>"><b>TEXTO DO ANÚNCIO:</b> <?= e($f['texto']) ?></div><?php endif; ?>

<?php if (!empty($R['ent'])): ?>
<div class="band">ENTREGAS ESPECIAIS</div>
<table class="it"><tr><th style="width:18%">ENTREGA</th><th style="width:34%">ESPECIFICAÇÃO</th><th style="width:6%">QTD</th><th style="width:18%">ÁREA RESPONSÁVEL</th><th style="width:12%">Nº DO BRIEFING</th><th style="width:12%">AVAL DO JURÍDICO</th></tr>
<?php foreach (array_values($R['ent']) as $i => $en): ?><tr<?= $i % 2 ? ' class="zz"' : '' ?>><td><?= e($en['tipo'] ?? '') ?></td><td><?= e($en['esp'] ?? '') ?></td><td class="c"><?= e((string) ($en['qtd'] ?? '')) ?></td><td><?= e($en['area'] ?? '') ?></td><td class="c"><?= e($en['brief'] ?? '') ?></td><td class="c"><?= e($en['jur'] ?? '') ?></td></tr><?php endforeach; ?></table>
<?php endif; ?>

<!-- Valores -->
<div class="band">VALORES<?php if (!empty($R['isTV']) && !empty($T['imp'])): ?> <span>· impacto total <?= numero($T['imp']) ?> · GRP <?= number_format((float) ($T['grp'] ?? 0), 1, ',', '.') ?> · CPM R$ <?= $m($T['cpm'] ?? 0) ?></span><?php endif; ?></div>
<table class="grid tot"><tr>
    <td class="first"><span class="k">Produção / taxas</span><span class="val"><?= $m($T['producao'] ?? 0) ?></span></td>
    <td><span class="k">Valor total de tabela</span><span class="val">R$ <?= $m($T['tab'] ?? 0) ?></span></td>
    <td><span class="k">Bonificado</span><span class="val"><?= $m($T['bonif'] ?? 0) ?></span></td>
    <td><span class="k">Desconto nominal</span><span class="val"><?= $m($T['desc'] ?? 0) ?></span></td>
    <td><span class="k">Desconto médio</span><span class="val"><?= number_format((float) ($T['descPct'] ?? 0) * 100, 1, ',', '') ?>%</span></td>
    <td class="hi"><span class="k">Valor negociado</span><span class="val">R$ <?= $m($T['neg'] ?? 0) ?></span></td>
    <td><span class="k">Comissão agência</span><span class="val"><?= $m($T['com'] ?? 0) ?></span></td>
    <td class="liq"><span class="k">Valor líquido</span><span class="val">R$ <?= $m($T['liq'] ?? 0) ?></span></td>
</tr></table>

<!-- Pagamento e observações -->
<table class="grid sec"><tr>
    <td class="first" style="width:50%"><span class="k">Parcelas · sem data definida, vence no último dia útil do mês seguinte a cada mês de veiculação</span><span class="val"><?= $v(implode('     ', array_map(fn ($p, $i) => ($i + 1) . 'ª: ' . data_br($p['venc'] ?? '') . ' — R$ ' . $m($p['val'] ?? 0), $R['parcs'] ?? [], array_keys($R['parcs'] ?? [])))) ?></span></td>
    <td><span class="k">Observações</span><span class="val"><?= $v(trim(($f['obs'] ?? '') . (!empty($f['gs_nome']) ? ' · Desconto/bonificação aprovados por ' . $f['gs_nome'] . ' em ' . data_br($f['gs_data'] ?? '') : ''), ' ·')) ?></span></td>
</tr></table>

<!-- Cláusulas -->
<?php $cls = array_merge(array_values($snap['clausulas'] ?? []), array_values(array_filter([$E['cl6'] ?? '', $E['cl7'] ?? '', $E['atendimento'] ?? '']))); if ($cls): ?>
<div class="band">CLÁUSULAS</div>
<?php foreach ($cls as $c): ?><div class="cl"><?= e($c) ?></div><?php endforeach; ?>
<?php endif; ?>

<div class="aut"><?= e($cidade ?? '') ?>, <?= e(data_br($f['emissao'] ?? '')) ?> · <b>AUTORIZAMOS O PRESENTE CONTRATO CONFORME ESPECIFICAÇÕES E CLÁUSULAS AQUI CONTIDAS.</b></div>
<table class="sig"><tr>
    <td style="width:25%"><div><b>CLIENTE</b><?= !empty($f['ac_nome']) ? ' — ' . e($f['ac_nome']) : '' ?></div></td>
    <td style="width:25%"><div><b>AGÊNCIA</b><?= $ag && !empty($f['ag_nome']) ? ' — ' . e($f['ag_nome']) : '' ?></div></td>
    <td style="width:25%"><div><b>EXECUTIVO DE CONTAS</b><?= !empty($f['exec']) ? ' — ' . e($f['exec']) : '' ?></div></td>
    <td style="width:25%"><div><b>GESTOR COMERCIAL</b><?= !empty($f['gestor']) ? ' — ' . e($f['gestor']) : '' ?></div></td>
</tr></table>
</body></html>
