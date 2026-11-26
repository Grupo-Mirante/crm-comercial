<?php
use App\Helpers\Form;

$novo = empty($P['id']);
$oldCotas = old('cotas', null);
$cotas = is_array($oldCotas) ? $oldCotas : $P['cotas'];
$oldVeic = old('veiculos', null);
$marcados = array_map('intval', is_array($oldVeic) ? $oldVeic : $P['veiculos']);
?>
<nav class="breadcrumb"><a href="<?= e(url('/projetos')) ?>">Projetos especiais</a>&nbsp;›&nbsp;<?= $novo ? 'Novo' : e($P['nome']) ?></nav>
<div class="page-head"><div><h1><?= $novo ? 'Novo projeto especial' : 'Editar ' . e($P['nome']) ?></h1><p>RN-32: nome, data limite de venda, empresas em que vale (ao menos uma) e cotas (nome, valor e quantidade). O código (PROJ-ano-sequência) é gerado automaticamente ao salvar. Não é possível reduzir uma cota abaixo do que já foi vendido ou reservado (RN-39).</p></div></div>
<form method="post" action="<?= e($novo ? url('/projetos') : url('/projetos/' . $P['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section"><div class="form-section-head"><span class="step">1</span><h2>Projeto</h2></div><div class="form-section-body"><div class="row g-3">
        <?= Form::input('codigo_exibicao', 'Código', $novo ? '' : $P['codigo'], ['col' => 'col-md-2', 'readonly' => true, 'placeholder' => 'PROJ-' . date('Y') . '-…', 'help' => $novo ? 'Gerado ao salvar' : 'Gerado pelo CRM' . (!empty($P['codigo_anterior']) ? ' (antes: ' . $P['codigo_anterior'] . ')' : ''), 'attrs' => ['tabindex' => '-1']]) ?>
        <?= Form::input('nome', 'Nome', $P['nome'] ?? '', ['req' => true, 'col' => 'col-md-6', 'maxlength' => 120]) ?>
        <?= Form::input('data_limite_venda', 'Venda até', $P['data_limite_venda'] ?? '', ['req' => true, 'col' => 'col-md-2', 'type' => 'date', 'help' => 'Comparada com a data de hoje (RN-33)']) ?>
        <?= Form::check('ativo', 'Ativo', (bool) ($P['ativo'] ?? 1), ['col' => 'col-md-2']) ?>
        <?= Form::textarea('descricao', 'Descrição', $P['descricao'] ?? '', ['col' => 'col-12', 'rows' => 2]) ?>
    </div></div></section>
    <section class="form-section"><div class="form-section-head"><span class="step">2</span><h2>Empresas em que vale <span class="req">*</span></h2><p>O projeto só aparece nas PIs dessas empresas (RN-35)</p></div><div class="form-section-body">
        <?= erro('veiculos') ?>
        <div class="d-flex flex-wrap gap-3"><?php foreach ($todos as $v): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="veiculos[]" value="<?= (int) $v['id'] ?>" id="pv<?= (int) $v['id'] ?>"<?= in_array((int) $v['id'], $marcados, true) ? ' checked' : '' ?>><label class="form-check-label" for="pv<?= (int) $v['id'] ?>"><?= e($v['nome']) ?></label></div><?php endforeach; ?></div>
    </div></section>
    <section class="form-section" data-repeater><div class="form-section-head"><span class="step">3</span><h2>Cotas <span class="req">*</span></h2><p>Valor = valor da cota × quantidade vendida (RN-37)</p></div><div class="form-section-body">
        <?= erro('cotas') ?><?php foreach ($GLOBALS['__errors'] ?? [] as $k => $m): if (str_starts_with((string) $k, 'cotas.')): ?><div class="invalid-feedback d-block"><?= e($m) ?></div><?php endif; endforeach; ?>
        <div class="row g-2 small-muted fw-semibold mb-1"><div class="col-4">Nome da cota</div><div class="col-3">Valor (R$)</div><div class="col-3">Quantidade</div></div>
        <div data-repeater-body>
            <?php foreach ($cotas as $i => $c): ?>
                <div class="row g-2 mb-2" data-repeater-row><input type="hidden" name="cotas[<?= $i ?>][id]" value="<?= (int) ($c['id'] ?? 0) ?>">
                    <div class="col-4"><input class="form-control" name="cotas[<?= $i ?>][nome]" value="<?= e($c['nome'] ?? '') ?>" maxlength="40" aria-label="Nome da cota"></div>
                    <div class="col-3"><input class="form-control text-end" name="cotas[<?= $i ?>][valor]" value="<?= e(isset($c['valor']) && $c['valor'] !== '' ? (is_numeric($c['valor']) ? number_format((float) $c['valor'], 2, ',', '.') : $c['valor']) : '') ?>" data-mask="moeda" aria-label="Valor da cota"></div>
                    <div class="col-3"><input class="form-control" type="number" min="1" name="cotas[<?= $i ?>][quantidade_total]" value="<?= e((string) ($c['quantidade_total'] ?? '')) ?>" aria-label="Quantidade"></div>
                    <div class="col-2"><button class="btn btn-light text-danger" type="button" data-repeater-del aria-label="Remover cota"><i class="bi bi-x-lg"></i></button></div></div>
            <?php endforeach; ?>
        </div>
        <template><div class="row g-2 mb-2" data-repeater-row><input type="hidden" name="cotas[__i__][id]" value="0">
            <div class="col-4"><input class="form-control" name="cotas[__i__][nome]" maxlength="40" aria-label="Nome da cota"></div>
            <div class="col-3"><input class="form-control text-end" name="cotas[__i__][valor]" data-mask="moeda" aria-label="Valor da cota"></div>
            <div class="col-3"><input class="form-control" type="number" min="1" name="cotas[__i__][quantidade_total]" aria-label="Quantidade"></div>
            <div class="col-2"><button class="btn btn-light text-danger" type="button" data-repeater-del aria-label="Remover cota"><i class="bi bi-x-lg"></i></button></div></div></template>
        <button class="btn btn-light btn-sm" type="button" data-repeater-add><i class="bi bi-plus"></i> Cota</button>
    </div></section>
    <div class="form-actions"><a class="btn btn-light" href="<?= e(url($novo ? '/projetos' : '/projetos/' . $P['id'])) ?>">Cancelar</a><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Salvar projeto</button></div>
</form>
