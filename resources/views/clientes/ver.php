<nav class="breadcrumb"><a href="<?= e(url('/clientes')) ?>">Clientes</a>&nbsp;›&nbsp;<?= e($c['razao_social']) ?></nav>
<div class="page-head">
    <div><h1><?= e($c['razao_social']) ?></h1><p><?= e($c['documento_formatado']) ?><?= $c['nome_fantasia'] ? ' · ' . e($c['nome_fantasia']) : '' ?> · <?= selo($c['situacao_cadastral']) ?></p></div>
    <div class="actions">
        <?php if (can('pis.criar') && in_array('/pis/nova', \App\Core\Router::$caminhos, true)): ?><a class="btn btn-danger" href="<?= e(url('/pis/nova', ['cliente' => $c['id']])) ?>"><i class="bi bi-file-earmark-plus"></i> Nova PI para este cliente</a><?php endif; ?>
        <?php if (can('clientes.editar')): ?><a class="btn btn-outline-primary" href="<?= e(url('/clientes/' . $c['id'] . '/editar')) ?>"><i class="bi bi-pencil"></i> Editar</a><?php endif; ?>
        <?php if (can('clientes.excluir')): ?>
            <form method="post" action="<?= e(url('/clientes/' . $c['id'] . '/excluir')) ?>" data-confirm="Excluir este cliente? Se ele tiver PIs, será apenas desativado e as PIs continuam com os dados originais." data-confirm-button="Excluir">
                <?= csrf_field() ?><button class="btn btn-light text-danger" type="submit"><i class="bi bi-trash"></i> Excluir</button></form>
        <?php endif; ?>
    </div>
</div>
<div class="card mb-3"><div class="card-body">
    <dl class="dl-grid mb-0">
        <div><dt>Endereço</dt><dd><?= e(trim(($c['endereco'] ?? '') . ($c['bairro'] ? ' · ' . $c['bairro'] : ''))) ?: '—' ?></dd></div>
        <div><dt>Cidade / UF</dt><dd><?= e(trim(($c['cidade'] ?? '') . ($c['uf'] ? ' / ' . $c['uf'] : ''))) ?: '—' ?></dd></div>
        <div><dt>CEP</dt><dd><?= e($c['cep'] ?: '—') ?></dd></div>
        <div><dt>Inscrição estadual</dt><dd><?= e($c['inscricao_estadual'] ?: '—') ?></dd></div>
        <div><dt>Contato</dt><dd><?= e($c['contato'] ?: '—') ?></dd></div>
        <div><dt>Telefones</dt><dd><?= e($c['telefone'] ?: '—') ?></dd></div>
        <div><dt>E-mail de faturamento</dt><dd><?= e($c['email_faturamento'] ?: '—') ?></dd></div>
        <div><dt>Código interno</dt><dd><?= e($c['codigo_interno'] ?: '—') ?></dd></div>
    </dl>
</div></div>
<h2 class="mb-2">PIs do cliente <span class="pill-count"><?= count($pis) ?></span></h2>
<div class="table-card"><div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>Nº da PI</th><th>Emissão</th><th>Veículo</th><th>Executivo</th><th>Período</th><th class="num">Negociado</th><th class="num">Líquido</th><th>Situação</th></tr></thead>
        <tbody>
        <?php if (!$pis): ?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-files"></i>Nenhuma PI deste cliente (entre as que você pode ver).</div></td></tr><?php endif; ?>
        <?php foreach ($pis as $p): ?>
            <tr>
                <td class="fw-semibold"><?= in_array('/pis', \App\Core\Router::$caminhos, true) ? '<a href="' . e(url('/pis/' . $p['id'])) . '">' . e($p['numero']) . '</a>' : e($p['numero']) ?></td>
                <td><?= e(data_br($p['data_emissao'])) ?></td><td><?= e($p['veiculo']) ?></td><td><?= e($p['executivo']) ?></td>
                <td class="text-nowrap"><?= e(data_br($p['data_inicio'])) ?> a <?= e(data_br($p['data_fim'])) ?></td>
                <td class="num"><?= e(moeda($p['valor_negociado'])) ?></td><td class="num"><?= e(moeda($p['valor_liquido'])) ?></td>
                <td><?= selo($p['situacao']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
