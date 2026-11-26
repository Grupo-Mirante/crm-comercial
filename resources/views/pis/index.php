<?php $q = array_filter($f, fn ($v) => $v !== '' && $v !== 0 && $v !== false); ?>
<div class="page-head">
    <div><h1>PIs</h1><p>Registro único das PIs: número, situação, valores e quem fez a última movimentação. Você vê <?= ($e = \App\Services\Auth::escopoPis()) === 'todas' ? 'todas as PIs do grupo' : ($e === 'equipe' ? 'as suas e as da sua equipe' : 'as suas PIs') ?>.</p></div>
    <div class="actions"><?php if (can('pis.criar')): ?><a class="btn btn-danger" href="<?= e(url('/pis/nova')) ?>"><i class="bi bi-plus-lg"></i> Nova PI</a><?php endif; ?></div>
</div>
<form class="filters" method="get"><div class="row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label" for="numero">Nº da PI</label><input class="form-control form-control-sm" id="numero" name="numero" value="<?= e($f['numero']) ?>" placeholder="TV-2026-"></div>
    <div class="col-6 col-md-3"><label class="form-label" for="cliente">Cliente</label><input class="form-control form-control-sm" id="cliente" name="cliente" value="<?= e($f['cliente']) ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label" for="cnpj">CNPJ / CPF</label><input class="form-control form-control-sm" id="cnpj" name="cnpj" value="<?= e($f['cnpj']) ?>" data-mask="doc"></div>
    <div class="col-6 col-md-2"><label class="form-label" for="situacao">Situação</label><select class="form-select form-select-sm" id="situacao" name="situacao"><option value="">Todas</option>
        <?php foreach (\App\Services\DashboardService::SITUACOES as $s): ?><option<?= $f['situacao'] === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-3"><label class="form-label" for="veiculo">Veículo</label><select class="form-select form-select-sm" id="veiculo" name="veiculo"><option value="">Todos</option>
        <?php foreach ($opcoes['veiculos'] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $f['veiculo'] === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label" for="empresa">Empresa</label><select class="form-select form-select-sm" id="empresa" name="empresa"><option value="">Todas</option>
        <?php foreach ($opcoes['empresas'] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $f['empresa'] === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select></div>
    <?php if ($opcoes['executivos']): ?><div class="col-6 col-md-2"><label class="form-label" for="executivo">Executivo</label><select class="form-select form-select-sm" id="executivo" name="executivo"><option value="">Todos</option>
        <?php foreach ($opcoes['executivos'] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $f['executivo'] === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <?php if ($opcoes['gestores']): ?><div class="col-6 col-md-2"><label class="form-label" for="gestor">Gestor</label><select class="form-select form-select-sm" id="gestor" name="gestor"><option value="">Todos</option>
        <?php foreach ($opcoes['gestores'] as $o): ?><option value="<?= (int) $o['id'] ?>"<?= $f['gestor'] === (int) $o['id'] ? ' selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div class="col-6 col-md-2"><label class="form-label" for="de">Emissão de</label><input class="form-control form-control-sm" type="date" id="de" name="de" value="<?= e($f['de']) ?>"></div>
    <div class="col-6 col-md-2"><label class="form-label" for="ate">até</label><input class="form-control form-control-sm" type="date" id="ate" name="ate" value="<?= e($f['ate']) ?>"></div>
    <div class="col-12 col-md-auto ms-auto d-flex gap-2"><?php if ($f['minhas']): ?><input type="hidden" name="minhas" value="1"><?php endif; ?>
        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Filtrar</button><a class="btn btn-light btn-sm" href="<?= e(url('/pis')) ?>">Limpar</a></div>
</div></form>
<div class="d-flex flex-wrap gap-3 small mb-2"><span><b><?= numero($tot['n'] ?? 0) ?></b> PI(s)</span><span>Negociado <b><?= e(moeda($tot['neg'] ?? 0)) ?></b></span><span>Líquido <b><?= e(moeda($tot['liq'] ?? 0)) ?></b></span><span class="small-muted">(canceladas e substituídas não somam)</span></div>
<div class="table-card"><div class="table-responsive"><table class="table table-hover table-sm align-middle">
    <thead><tr><th>Nº da PI</th><th>Emissão</th><th>Veículo · empresa</th><th>Cliente</th><th>Executivo</th><th class="num">Bruto</th><th class="num">Negociado</th><th class="num">Líquido</th><th>Período</th><th>Situação</th><th>Última movimentação</th></tr></thead>
    <tbody>
    <?php if (!$p['itens']): ?><tr><td colspan="11"><div class="empty-state"><i class="bi bi-files"></i>Nenhuma PI encontrada<?= $q ? ' com esses filtros' : '' ?>.</div></td></tr><?php endif; ?>
    <?php foreach ($p['itens'] as $pi): ?>
        <tr>
            <td class="text-nowrap"><a class="fw-semibold" href="<?= e(url('/pis/' . $pi['id'])) ?>"><?= e($pi['numero']) ?></a><?= $pi['parte'] ? '<div class="small-muted">PI ' . e($pi['parte']) . '</div>' : '' ?></td>
            <td class="text-nowrap"><?= e(data_br($pi['data_emissao'])) ?></td>
            <td class="small"><?= e($pi['veiculo']) ?><div class="small-muted"><?= e($pi['empresa']) ?></div></td>
            <td class="small"><?= e($pi['cliente_razao']) ?></td>
            <td class="small"><?= e($pi['executivo']) ?></td>
            <td class="num"><?= e(moeda($pi['valor_tabela'], false)) ?></td><td class="num"><?= e(moeda($pi['valor_negociado'], false)) ?></td><td class="num fw-semibold"><?= e(moeda($pi['valor_liquido'], false)) ?></td>
            <td class="small text-nowrap"><?= e(data_br($pi['data_inicio'])) ?><br><?= e(data_br($pi['data_fim'])) ?></td>
            <td><?= selo($pi['situacao']) ?><?php if ((int) $pi['qtd_pendencias'] && $pi['situacao'] === 'Rascunho'): ?><div class="small text-red"><?= (int) $pi['qtd_pendencias'] ?> pendência(s)</div><?php endif; ?>
                <?php if ((int) $pi['qtd_devolucoes']): ?><div class="small-muted">devolvida <?= (int) $pi['qtd_devolucoes'] ?>×</div><?php endif; ?><?php if ((int) $pi['fora_do_prazo']): ?><div class="small" style="color:var(--gm-orange)">fora do prazo</div><?php endif; ?></td>
            <td class="small"><?= e($pi['ultimo_por'] ?? '—') ?><div class="small-muted"><?= e(data_hora_br($pi['ultimo_em'] ?? '')) ?></div></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php $query = $q; include __DIR__ . '/../partials/paginacao.php'; ?>
