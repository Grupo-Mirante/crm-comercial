<?php
use App\Helpers\Form;

$novo = empty($f['id']);
?>
<nav class="breadcrumb"><a href="<?= e(url('/cadastros')) ?>">Tabelas e veículos</a>&nbsp;›&nbsp;<a href="<?= e(url('/cadastros/formatos')) ?>">Formatos digitais</a>&nbsp;›&nbsp;<?= $novo ? 'Novo' : e($f['sigla']) ?></nav>
<div class="page-head"><div><h1><?= $novo ? 'Novo formato' : e($f['sigla']) ?></h1><p>O preço fica em Tabelas de preços. Sem preço, o formato é "sob consulta" e exige preço manual aprovado pelo gestor (RN-14, RN-15).</p></div></div>
<form method="post" action="<?= e($novo ? url('/cadastros/formatos') : url('/cadastros/formatos/' . $f['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section"><div class="form-section-head"><span class="step">1</span><h2>Formato</h2></div><div class="form-section-body"><div class="row g-3">
        <?= Form::select('veiculo_id', 'Veículo', $veiculos, $f['veiculo_id'] ?? '', ['req' => true, 'col' => 'col-md-3']) ?>
        <?= Form::input('sigla', 'Sigla', $f['sigla'] ?? '', ['req' => true, 'col' => 'col-md-2', 'maxlength' => 12]) ?>
        <?= Form::input('nome', 'Nome', $f['nome'] ?? '', ['req' => true, 'col' => 'col-md-7', 'maxlength' => 200]) ?>
        <?= Form::select('modelo', 'Modelo de cobrança', ['Mensal' => 'Mensal (proporcional aos dias de cada mês)', 'Diária' => 'Diária', 'Unidade' => 'Unidade', 'Pacote' => 'Pacote', 'CPM' => 'CPM (por mil impressões)', 'cm/col' => 'Publicidade legal (cm/col · só Imirante)'], $f['modelo'] ?? '', ['req' => true, 'col' => 'col-md-5']) ?>
        <?= Form::input('categoria', 'Categoria', $f['categoria'] ?? '', ['col' => 'col-md-4', 'maxlength' => 60]) ?>
        <?= Form::check('ativo', 'Ativo', (bool) ($f['ativo'] ?? 1), ['col' => 'col-md-3']) ?>
    </div></div></section>
    <section class="form-section"><div class="form-section-head"><span class="step">2</span><h2>Detalhes que saem na PI</h2></div><div class="form-section-body"><div class="row g-3">
        <?= Form::select('dispositivo', 'Dispositivo', ['Desktop', 'Mobile', 'Desktop e Mobile', 'App'], $f['dispositivo'] ?? '', ['col' => 'col-md-3']) ?>
        <?= Form::input('local_pagina', 'Local / página', $f['local_pagina'] ?? '', ['col' => 'col-md-5', 'maxlength' => 120]) ?>
        <?= Form::input('visibilidade_pct', 'Visibilidade', isset($f['visibilidade_pct']) && $f['visibilidade_pct'] !== null ? number_format((float) $f['visibilidade_pct'], 0, ',', '') : '', ['col' => 'col-md-2', 'sufixo' => '%', 'mask' => 'percentual']) ?>
        <?= Form::input('entrega_estimada', 'Entrega estimada', $f['entrega_estimada'] ?? '', ['col' => 'col-md-2', 'mask' => 'inteiro', 'help' => 'impressões']) ?>
        <?= Form::input('observacao', 'Observação', $f['observacao'] ?? '', ['col' => 'col-12', 'maxlength' => 255]) ?>
    </div></div></section>
    <div class="form-actions"><a class="btn btn-light" href="<?= e(url('/cadastros/formatos')) ?>">Cancelar</a><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Salvar formato</button></div>
</form>
