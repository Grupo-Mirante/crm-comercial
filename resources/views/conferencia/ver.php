<?php
$f = $snap['f'] ?? [];
$T = $R['T'] ?? [];
$dif = function (string $rot, ?string $naPi, ?string $noCadastro): string {
    $ok = $noCadastro === null || normaliza((string) $naPi) === normaliza((string) $noCadastro);
    return '<tr><td class="small-muted">' . e($rot) . '</td><td>' . e($naPi ?: '—') . '</td><td class="' . ($ok ? '' : 'text-red fw-semibold') . '">' . e($noCadastro ?? '—') . ($ok ? '' : ' ≠') . '</td></tr>';
};
?>
<nav class="breadcrumb"><a href="<?= e(url('/conferencia')) ?>">Conferência</a>&nbsp;›&nbsp;<?= e($pi['numero']) ?></nav>
<div class="page-head">
    <div><h1>Conferir PI <?= e($pi['numero']) ?> <?= selo($pi['situacao']) ?></h1>
        <p><?= e($pi['tipo']) ?> · <?= e($pi['veiculo']) ?> · <?= e($pi['cliente_razao']) ?> · <?= e($pi['executivo']) ?> · versão <?= (int) $pi['versao_atual'] ?><?= (int) $pi['qtd_devolucoes'] ? ' · já devolvida ' . (int) $pi['qtd_devolucoes'] . '×' : '' ?></p></div>
    <div class="actions">
        <a class="btn btn-light" href="<?= e(url('/pis/' . $pi['id'] . '/imprimir')) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Ver a PI completa</a>
        <?php if (can('pis.exportar')): ?><a class="btn btn-light" href="<?= e(url('/pis/' . $pi['id'] . '/excel')) ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a><?php endif; ?>
        <a class="btn btn-light" href="<?= e(url('/pis/' . $pi['id'])) ?>">Ficha</a>
    </div>
</div>
<?php if (!$podeConferir): ?><div class="alert alert-info"><i class="bi bi-info-circle"></i><div>Esta PI não está aguardando conferência (situação: <?= e($pi['situacao']) ?>).</div></div><?php endif; ?>
<?php if ((int) $pi['fora_do_prazo']): ?><div class="alert alert-warning"><i class="bi bi-alarm"></i><div><b>Enviada fora do prazo</b> (menos antecedência que o SLA de envio). Não é motivo de devolução: conta no indicador do executivo, e a veiculação pode começar depois da data pedida.</div></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3"><div class="kpi"><div class="kpi-label">Tabela</div><div class="kpi-value" style="font-size:1.05rem"><?= e(moeda($pi['valor_tabela'])) ?></div></div></div>
            <div class="col-6 col-md-3"><div class="kpi"><div class="kpi-label">Desconto · bonif.</div><div class="kpi-value" style="font-size:1.05rem"><?= number_format((float) $pi['desconto_pct'] * 100, 1, ',', '.') ?>%</div><div class="kpi-sub">bonif. <?= e(moeda($pi['valor_bonificado'])) ?></div></div></div>
            <div class="col-6 col-md-3"><div class="kpi"><div class="kpi-label">Negociado</div><div class="kpi-value" style="font-size:1.05rem"><?= e(moeda($pi['valor_negociado'])) ?></div></div></div>
            <div class="col-6 col-md-3"><div class="kpi kpi-ok"><div class="kpi-label">Líquido</div><div class="kpi-value" style="font-size:1.05rem"><?= e(moeda($pi['valor_liquido'])) ?></div><div class="kpi-sub"><?= numero($pi['total_insercoes']) ?> inserções</div></div></div>
        </div>
        <div class="card mb-3"><div class="card-header"><i class="bi bi-person-vcard"></i> Cliente: PI × cadastro (D01)</div><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th style="width:24%"></th><th>Na PI</th><th>No cadastro</th></tr></thead>
            <tbody>
            <?= $dif('Razão social', $f['cli_razao'] ?? '', $cliente['razao_social'] ?? null) ?>
            <?= $dif('CNPJ / CPF', $f['cli_cnpj'] ?? '', $cliente['documento_formatado'] ?? null) ?>
            <?= $dif('Endereço', $f['cli_end'] ?? '', $cliente['endereco'] ?? null) ?>
            <?= $dif('Cidade / UF', trim(($f['cli_cid'] ?? '') . ' / ' . ($f['cli_uf'] ?? '')), $cliente ? trim(($cliente['cidade'] ?? '') . ' / ' . ($cliente['uf'] ?? '')) : null) ?>
            <?= $dif('E-mail de faturamento', $f['cli_email'] ?? '', $cliente['email_faturamento'] ?? null) ?>
            <tr><td class="small-muted">Situação cadastral</td><td colspan="2"><?= $cliente ? selo($cliente['situacao_cadastral']) : '—' ?></td></tr>
            </tbody></table></div></div>
        <div class="card mb-3"><div class="card-header"><i class="bi bi-briefcase"></i> Comercial, agência e aprovações</div><div class="card-body"><dl class="dl-grid mb-0">
            <div><dt>Executivo · matrícula</dt><dd><?= e(($f['exec'] ?? '') . ' · ' . ($f['mat'] ?? '')) ?></dd></div>
            <div><dt>Gestor</dt><dd><?= e($f['gestor'] ?? '—') ?></dd></div>
            <div><dt>Venda</dt><dd><?= e($pi['venda']) ?><?= $pi['venda'] === 'Agência' ? ' · ' . e($pi['agencia_razao']) . ' · ' . number_format((float) $pi['comissao_pct'], 2, ',', '.') . '%' : '' ?></dd></div>
            <?php if ($agencia): $venc = $agencia['carta_validade'] && $agencia['carta_validade'] < $pi['data_emissao']; ?><div><dt>Carta de credenciamento</dt><dd class="<?= $venc ? 'text-red fw-semibold' : '' ?>"><?= e(($agencia['carta_numero'] ?? '—') . ' · ' . (data_br($agencia['carta_validade']) ?: 'sem validade')) ?></dd></div><?php endif; ?>
            <div><dt>Período</dt><dd><?= e(data_br($pi['data_inicio'])) ?> a <?= e(data_br($pi['data_fim'])) ?></dd></div>
            <div><dt>Pagamento</dt><dd><?= e($pi['forma_pagamento'] ?? '—') ?></dd></div>
            <div><dt>Aceite do cliente</dt><dd><?= e(($f['ac_nome'] ?? '') . ' · ' . data_br($f['ac_data'] ?? '')) ?></dd></div>
            <div><dt>Aprovação do gestor</dt><dd><?= !empty($f['gs_nome']) ? e($f['gs_nome'] . ' · ' . data_br($f['gs_data'] ?? '')) : ((float) $pi['desconto_nominal'] > 0.005 ? '<span class="text-red">sem registro</span>' : 'não exigida') ?></dd></div>
        </dl></div></div>
        <div class="card mb-3"><div class="card-header"><i class="bi bi-list-columns"></i> Mídia e totais</div><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Bloco</th><th class="num">Linhas</th><th class="num">Tabela</th><th class="num">Total</th></tr></thead>
            <tbody>
            <?php foreach (['ins' => ['Inserções', 'insBruto', 'insTotal'], 'pat' => ['Patrocínios', 'patTab', 'patTotal'], 'dig' => ['Digital', 'digTab', 'digTotal'], 'avi' => ['Publicidade legal', 'aviTab', 'aviTotal'], 'prj' => ['Projetos especiais', 'prjTab', 'prjTotal']] as $k => [$rot, $kt, $kn]): if (empty($R[$k])) continue; ?>
                <tr><td><?= e($rot) ?></td><td class="num"><?= count($R[$k]) ?></td><td class="num"><?= e(moeda($T[$kt] ?? 0)) ?></td><td class="num"><?= e(moeda($T[$kn] ?? 0)) ?></td></tr>
            <?php endforeach; ?>
            <tr class="fw-semibold"><td>Soma das linhas + produção</td><td></td><td class="num"><?= e(moeda($T['tab'] ?? 0)) ?></td><td class="num"><?= e(moeda($T['neg'] ?? 0)) ?></td></tr>
            <tr><td>Parcelas</td><td class="num"><?= count($R['parcs'] ?? []) ?></td><td></td><td class="num"><?= e(moeda(array_sum(array_map(fn ($p) => (float) ($p['val'] ?? 0), $R['parcs'] ?? [])))) ?></td></tr>
            </tbody></table></div><div class="card-body small-muted py-2">Os totais são calculados pelo sistema a partir das linhas (D04/D05 conferidos automaticamente). Confira a grade, os programas e os formatos na <a href="<?= e(url('/pis/' . $pi['id'] . '/imprimir')) ?>" target="_blank" rel="noopener">PI completa</a>.</div></div>
        <?php if (!empty($R['ent'])): ?><div class="card mb-3"><div class="card-header">Entregas especiais (D09/D13)</div><ul class="list-group list-group-flush"><?php foreach ($R['ent'] as $en): ?><li class="list-group-item small"><b><?= e($en['tipo'] ?? '') ?></b> · <?= e($en['esp'] ?? '') ?> · área <?= e($en['area'] ?? '—') ?> · briefing <?= e($en['brief'] ?? '—') ?> · jurídico <?= e($en['jur'] ?? '—') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <div class="card mb-3"><div class="card-header"><i class="bi bi-paperclip"></i> Anexos (aceite, briefing)</div><ul class="list-group list-group-flush">
            <?php if (!$anexos): ?><li class="list-group-item small-muted">Nenhum anexo. O aceite pode estar no e-mail do executivo: confirme antes de aprovar (D10).</li><?php endif; ?>
            <?php foreach ($anexos as $a): ?><li class="list-group-item small"><a href="<?= e(url('/anexos/' . $a['id'])) ?>"><i class="bi bi-file-earmark"></i> <?= e($a['nome_original']) ?></a> <span class="small-muted">· <?= e($a['tipo']) ?> · <?= e(data_hora_br($a['criado_em'])) ?></span></li><?php endforeach; ?>
        </ul></div>
        <?php if ($devolucoes): ?><div class="card"><div class="card-header"><i class="bi bi-arrow-return-left"></i> Devoluções anteriores</div><ul class="list-group list-group-flush">
            <?php foreach ($devolucoes as $d): ?><li class="list-group-item small"><b><?= (int) $d['sequencia'] ?>ª · <?= e($d['codigos']) ?></b> — <?= e($d['descricao']) ?><div class="small-muted"><?= e(data_hora_br($d['devolvida_em'])) ?> por <?= e($d['por']) ?><?= $d['corrigida_em'] ? ' · corrigida em ' . e(data_hora_br($d['corrigida_em'])) : '' ?></div></li><?php endforeach; ?></ul></div><?php endif; ?>
    </div>

    <div class="col-lg-5">
        <?php if ($podeConferir): ?>
        <form method="post" action="<?= e(url('/conferencia/' . $pi['id'] . '/aprovar')) ?>" id="fConf" class="card mb-3" style="position:sticky;top:76px">
            <?= csrf_field() ?>
            <div class="card-header"><i class="bi bi-clipboard-check"></i> Checklist da Conferência <span class="ms-auto small-muted">a PI só é aprovada com todos os itens marcados</span></div>
            <div class="card-body">
                <?= erro('checklist') ?>
                <?php foreach ($checklist as $c): ?>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="checklist[]" value="<?= (int) $c['id'] ?>" id="ck<?= (int) $c['id'] ?>" data-codigos="<?= e($c['codigos']) ?>">
                        <label class="form-check-label" for="ck<?= (int) $c['id'] ?>"><?= (int) $c['ordem'] ?>. <?= e($c['descricao']) ?> <span class="small-muted">(<?= e($c['codigos']) ?>)</span></label></div>
                <?php endforeach; ?>
                <div class="mt-2"><input class="form-control form-control-sm" name="observacao" placeholder="Observação da aprovação (opcional)" maxlength="500"></div>
                <div class="d-grid mt-3"><button class="btn btn-success" type="submit" data-acao="aprovar" style="background:var(--gm-green);border-color:var(--gm-green)"><i class="bi bi-check2-circle"></i> Aprovar na conferência</button></div>
                <hr>
                <div class="fw-semibold mb-2 text-red"><i class="bi bi-arrow-return-left"></i> Devolver ao executivo</div>
                <div class="small-muted mb-2">Marque o(s) motivo(s). Itens do checklist desmarcados sugerem os códigos.</div>
                <?= erro('codigos') ?>
                <div class="row g-1 mb-2" id="codigos">
                    <?php foreach ($motivos as $m): ?><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="codigos[]" value="<?= e($m['codigo']) ?>" id="m<?= e($m['codigo']) ?>"><label class="form-check-label small" for="m<?= e($m['codigo']) ?>"><b><?= e($m['codigo']) ?></b> <?= e($m['descricao']) ?></label></div></div><?php endforeach; ?>
                </div>
                <textarea class="form-control form-control-sm<?= tem_erro('descricao') ?>" name="descricao" rows="2" placeholder="O que o executivo precisa corrigir (obrigatório na devolução)" maxlength="1000"></textarea><?= erro('descricao') ?>
                <div class="d-grid mt-2"><button class="btn btn-outline-danger" type="submit" data-acao="devolver" formaction="<?= e(url('/conferencia/' . $pi['id'] . '/devolver')) ?>"><i class="bi bi-arrow-return-left"></i> Devolver com código</button></div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
