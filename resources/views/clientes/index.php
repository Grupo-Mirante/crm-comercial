<div class="page-head">
    <div><h1>Clientes</h1><p>Cadastro único por CNPJ/CPF. Ao gerar uma PI, o cliente entra aqui ou tem os dados atualizados.</p></div>
    <div class="actions">
        <?php if (can('clientes.editar')): ?><a class="btn btn-primary" href="<?= e(url('/clientes/novo')) ?>"><i class="bi bi-plus-lg"></i> Novo cliente</a><?php endif; ?>
    </div>
</div>
<form class="filters" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label" for="q">Buscar</label>
            <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Razão social, nome fantasia, CNPJ/CPF ou código" data-autofocus></div>
        <div class="col-md-3"><label class="form-label" for="situacao">Situação cadastral</label>
            <select class="form-select" id="situacao" name="situacao" data-autosubmit><option value="">Todas</option>
                <?php foreach (['Ativo', 'Em análise', 'Bloqueado', 'Inativo'] as $s): ?><option<?= $sit === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Buscar</button>
            <?php if ($q !== '' || $sit !== ''): ?><a class="btn btn-light" href="<?= e(url('/clientes')) ?>">Limpar</a><?php endif; ?></div>
    </div>
</form>
<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Razão social</th><th>CNPJ / CPF</th><th>Cidade / UF</th><th>Contato</th><th>E-mail de faturamento</th><th>Situação</th><th class="num">PIs</th><th></th></tr></thead>
            <tbody>
            <?php if (!$p['itens']): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-building"></i><?= $q !== '' ? 'Nenhum cliente encontrado com essa busca.' : 'Nenhum cliente cadastrado ainda.' ?></div></td></tr>
            <?php endif; ?>
            <?php foreach ($p['itens'] as $c): ?>
                <tr>
                    <td><a class="fw-semibold text-decoration-none" href="<?= e(url('/clientes/' . $c['id'])) ?>"><?= e($c['razao_social']) ?></a><?php if ($c['nome_fantasia']): ?><div class="small-muted"><?= e($c['nome_fantasia']) ?></div><?php endif; ?></td>
                    <td class="tabular text-nowrap"><?= e($c['documento_formatado']) ?></td>
                    <td><?= e(trim(($c['cidade'] ?? '') . ($c['uf'] ? ' / ' . $c['uf'] : ''))) ?></td>
                    <td><?= e($c['contato']) ?><div class="small-muted"><?= e($c['telefone']) ?></div></td>
                    <td class="small"><?= e($c['email_faturamento']) ?></td>
                    <td><?= selo($c['situacao_cadastral']) ?></td>
                    <td class="num"><?= (int) $c['qtd_pis'] ?></td>
                    <td><div class="table-actions">
                        <a class="btn btn-light btn-sm" href="<?= e(url('/clientes/' . $c['id'])) ?>" title="Ver"><i class="bi bi-eye"></i></a>
                        <?php if (can('clientes.editar')): ?><a class="btn btn-light btn-sm" href="<?= e(url('/clientes/' . $c['id'] . '/editar')) ?>" title="Editar"><i class="bi bi-pencil"></i></a><?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $query = array_filter(['q' => $q, 'situacao' => $sit]); include __DIR__ . '/../partials/paginacao.php'; ?>
