<?php $venc = $a['carta_validade'] && $a['carta_validade'] < date('Y-m-d'); ?>
<nav class="breadcrumb"><a href="<?= e(url('/agencias')) ?>">Agências</a>&nbsp;›&nbsp;<?= e($a['razao_social']) ?></nav>
<div class="page-head">
    <div><h1><?= e($a['razao_social']) ?></h1><p><?= e($a['cnpj_formatado']) ?> · comissão padrão <?= e(number_format((float) $a['comissao_padrao'], 2, ',', '.')) ?>%<?= (int) $a['ativo'] ? '' : ' · <span class="st st-inativo">Inativa</span>' ?></p></div>
    <div class="actions">
        <?php if (can('agencias.editar')): ?><a class="btn btn-outline-primary" href="<?= e(url('/agencias/' . $a['id'] . '/editar')) ?>"><i class="bi bi-pencil"></i> Editar</a><?php endif; ?>
        <?php if (can('agencias.excluir')): ?>
            <form method="post" action="<?= e(url('/agencias/' . $a['id'] . '/excluir')) ?>" data-confirm="Excluir esta agência? Se ela tiver PIs, será apenas desativada." data-confirm-button="Excluir">
                <?= csrf_field() ?><button class="btn btn-light text-danger" type="submit"><i class="bi bi-trash"></i> Excluir</button></form>
        <?php endif; ?>
    </div>
</div>
<?php if ($venc): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div><b>Carta de credenciamento vencida em <?= e(data_br($a['carta_validade'])) ?>.</b> PIs desta agência sairão como Rascunho (D08) até a carta ser atualizada.</div></div><?php endif; ?>
<div class="card mb-3"><div class="card-body"><dl class="dl-grid mb-0">
    <div><dt>Carta de credenciamento</dt><dd><?= e($a['carta_numero'] ?: '—') ?></dd></div>
    <div><dt>Validade</dt><dd class="<?= $venc ? 'text-red fw-semibold' : '' ?>"><?= e(data_br($a['carta_validade']) ?: '—') ?></dd></div>
    <div><dt>Endereço</dt><dd><?= e($a['endereco'] ?: '—') ?></dd></div>
    <div><dt>Cidade / UF</dt><dd><?= e(trim(($a['cidade'] ?? '') . ($a['uf'] ? ' / ' . $a['uf'] : '')) ?: '—') ?></dd></div>
    <div><dt>Contato</dt><dd><?= e($a['contato'] ?: '—') ?></dd></div>
    <div><dt>Telefone</dt><dd><?= e($a['telefone'] ?: '—') ?></dd></div>
    <div><dt>E-mail</dt><dd><?= e($a['email'] ?: '—') ?></dd></div>
</dl></div></div>
<h2 class="mb-2">PIs da agência <span class="pill-count"><?= count($pis) ?></span></h2>
<div class="table-card"><div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Nº da PI</th><th>Emissão</th><th>Veículo</th><th>Cliente</th><th class="num">Negociado</th><th class="num">Comissão</th><th>Situação</th></tr></thead>
        <tbody>
        <?php if (!$pis): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-files"></i>Nenhuma PI desta agência (entre as que você pode ver).</div></td></tr><?php endif; ?>
        <?php foreach ($pis as $p): ?>
            <tr><td class="fw-semibold"><?= e($p['numero']) ?></td><td><?= e(data_br($p['data_emissao'])) ?></td><td><?= e($p['veiculo']) ?></td><td><?= e($p['cliente_razao']) ?></td>
                <td class="num"><?= e(moeda($p['valor_negociado'])) ?></td><td class="num"><?= e(moeda($p['valor_comissao'])) ?> <span class="small-muted">(<?= e(number_format((float) $p['comissao_pct'], 2, ',', '.')) ?>%)</span></td><td><?= selo($p['situacao']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
