<?php

use App\Helpers\Form;

$novo = empty($u['id']);
?>
<nav class="breadcrumb"><a href="<?= e(url('/usuarios')) ?>">Usuários</a>&nbsp;›&nbsp;<?= $novo ? 'Novo' : e($u['nome']) ?></nav>
<div class="page-head"><div><h1><?= $novo ? 'Novo usuário' : 'Editar usuário' ?></h1>
    <p><?= $novo ? 'Ao salvar, o sistema gera uma senha provisória que aparece uma única vez. No primeiro acesso a pessoa cria a própria senha.' : 'Nome, matrícula e gestor saem nas PIs do executivo e não podem ser trocados por ele (RN-76).' ?></p></div></div>
<form method="post" action="<?= e($novo ? url('/usuarios') : url('/usuarios/' . $u['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <div class="form-section-head"><span class="step">1</span><h2>Dados da pessoa</h2></div>
        <div class="form-section-body"><div class="row g-3">
            <?= Form::input('nome', 'Nome completo', $u['nome'] ?? '', ['req' => true, 'col' => 'col-md-6', 'maxlength' => 120, 'attrs' => ['data-autofocus' => true]]) ?>
            <?= Form::input('login', 'Usuário (para entrar)', $u['login'] ?? '', ['req' => true, 'col' => 'col-md-3', 'maxlength' => 60, 'help' => 'Ex.: maria.silva', 'autocomplete' => 'off']) ?>
            <?= Form::input('matricula', 'Matrícula', $u['matricula'] ?? '', ['col' => 'col-md-3', 'maxlength' => 30, 'help' => 'Obrigatória para executivo e vendedor']) ?>
            <?= Form::input('email', 'E-mail', $u['email'] ?? '', ['col' => 'col-md-6', 'type' => 'email', 'maxlength' => 160, 'help' => 'Usado para avisos e recuperação de senha']) ?>
            <?= Form::input('telefone', 'Telefone', $u['telefone'] ?? '', ['col' => 'col-md-3', 'mask' => 'telefone']) ?>
        </div></div>
    </section>
    <section class="form-section">
        <div class="form-section-head"><span class="step">2</span><h2>Perfil e equipe</h2><p>O gerente vê as PIs de quem tem ele como gestor</p></div>
        <div class="form-section-body"><div class="row g-3">
            <?= Form::select('perfil_id', 'Perfil', $perfis, $u['perfil_id'] ?? '', ['req' => true, 'col' => 'col-md-4']) ?>
            <?= Form::select('gestor_id', 'Gestor comercial', $gestores, $u['gestor_id'] ?? '', ['col' => 'col-md-5', 'vazio' => '— sem gestor —', 'help' => 'Obrigatório para executivo e vendedor']) ?>
        </div></div>
    </section>
    <div class="form-actions">
        <a class="btn btn-light" href="<?= e(url($novo ? '/usuarios' : '/usuarios/' . $u['id'])) ?>">Cancelar</a>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> <?= $novo ? 'Cadastrar e gerar senha provisória' : 'Salvar alterações' ?></button>
    </div>
</form>
