<nav class="breadcrumb"><a href="<?= e(url('/cadastros')) ?>">Tabelas e veículos</a>&nbsp;›&nbsp;Listas de opções</nav>
<div class="page-head"><div><h1>Listas de opções</h1><p>Opções dos campos de escolha da PI (vindas do Gerador original). Desativar uma opção a tira dos formulários sem mudar as PIs antigas.</p></div></div>
<?php $fixas = ['Tipo de PI', 'Venda', 'Tipo de cobrança (rádio / TV)', 'Cobrança (digital)', 'Aval do jurídico', 'Sim / Não']; ?>
<div class="row g-3">
<?php foreach ($listas as $nome => $itens): $fixa = in_array($nome, $fixas, true); ?>
    <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-header"><?= e($nome) ?><?php if ($fixa): ?><span class="ms-auto small-muted" title="Usada nas regras de cálculo">regra fixa</span><?php endif; ?></div>
        <ul class="list-group list-group-flush">
            <?php foreach ($itens as $i): ?>
                <li class="list-group-item d-flex align-items-center<?= (int) $i['ativo'] ? '' : ' text-muted' ?>"><span class="<?= (int) $i['ativo'] ? '' : 'text-decoration-line-through' ?>"><?= e($i['valor']) ?></span>
                    <?php if (!$fixa): ?><form class="ms-auto" method="post" action="<?= e(url('/cadastros/listas')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $i['id'] ?>"><input type="hidden" name="ativo" value="<?= (int) $i['ativo'] ? '0' : '1' ?>">
                        <button class="btn btn-link btn-sm p-0" type="submit"><?= (int) $i['ativo'] ? 'desativar' : 'reativar' ?></button></form><?php endif; ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$fixa): ?><div class="card-body pt-2"><form method="post" action="<?= e(url('/cadastros/listas')) ?>" class="input-group input-group-sm"><?= csrf_field() ?><input type="hidden" name="lista" value="<?= e($nome) ?>">
            <input class="form-control" name="valor" maxlength="80" placeholder="nova opção" aria-label="Nova opção em <?= e($nome) ?>"><button class="btn btn-outline-primary" type="submit">Incluir</button></form></div><?php endif; ?>
    </div></div>
<?php endforeach; ?>
</div>
