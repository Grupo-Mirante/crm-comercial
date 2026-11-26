<?php

use App\Helpers\Form;

$novo = empty($a['id']);
?>
<nav class="breadcrumb"><a href="<?= e(url('/agencias')) ?>">Agências</a>&nbsp;›&nbsp;<?= $novo ? 'Nova' : e($a['razao_social']) ?></nav>
<div class="page-head"><div><h1><?= $novo ? 'Nova agência' : 'Editar agência' ?></h1><p>Na PI por agência são obrigatórios: nome, CNPJ, comissão e carta de credenciamento (RN-42).</p></div></div>
<form method="post" action="<?= e($novo ? url('/agencias') : url('/agencias/' . $a['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <div class="form-section-head"><span class="step">1</span><h2>Agência e comissão</h2></div>
        <div class="form-section-body"><div class="row g-3">
            <?= Form::input('razao_social', 'Razão social', $a['razao_social'] ?? '', ['req' => true, 'col' => 'col-md-6', 'maxlength' => 160, 'attrs' => ['data-autofocus' => true]]) ?>
            <?= Form::input('cnpj', 'CNPJ', $a['cnpj_formatado'] ?? '', ['req' => true, 'col' => 'col-md-3', 'mask' => 'cnpj', 'placeholder' => '00.000.000/0000-00']) ?>
            <?= Form::input('comissao_padrao', 'Comissão padrão', isset($a['comissao_padrao']) ? number_format((float) $a['comissao_padrao'], 2, ',', '') : '', ['req' => true, 'col' => 'col-md-3', 'mask' => 'percentual', 'sufixo' => '%', 'help' => 'Comissão = negociado × %']) ?>
            <?= Form::input('carta_numero', 'Nº da carta de credenciamento', $a['carta_numero'] ?? '', ['col' => 'col-md-4', 'maxlength' => 40]) ?>
            <?= Form::input('carta_validade', 'Validade da carta', $a['carta_validade'] ?? '', ['col' => 'col-md-3', 'type' => 'date', 'help' => 'Carta vencida na emissão gera pendência D08']) ?>
        </div></div>
    </section>
    <section class="form-section">
        <div class="form-section-head"><span class="step">2</span><h2>Endereço e contato</h2></div>
        <div class="form-section-body"><div class="row g-3">
            <?= Form::input('endereco', 'Endereço', $a['endereco'] ?? '', ['col' => 'col-md-6', 'maxlength' => 200]) ?>
            <?= Form::input('cidade', 'Cidade', $a['cidade'] ?? '', ['col' => 'col-md-4', 'maxlength' => 80]) ?>
            <?= Form::select('uf', 'UF', Form::ufs(), $a['uf'] ?? 'MA', ['col' => 'col-md-2']) ?>
            <?= Form::input('contato', 'Contato', $a['contato'] ?? '', ['col' => 'col-md-4', 'maxlength' => 120]) ?>
            <?= Form::input('telefone', 'Telefone', $a['telefone'] ?? '', ['col' => 'col-md-4', 'mask' => 'telefone']) ?>
            <?= Form::input('email', 'E-mail', $a['email'] ?? '', ['col' => 'col-md-4', 'type' => 'email', 'maxlength' => 160]) ?>
        </div></div>
    </section>
    <div class="form-actions">
        <a class="btn btn-light" href="<?= e(url($novo ? '/agencias' : '/agencias/' . $a['id'])) ?>">Cancelar</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> <?= $novo ? 'Cadastrar agência' : 'Salvar alterações' ?></button>
    </div>
</form>
