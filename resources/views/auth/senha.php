<?php
/** Criação/alteração de senha. Variáveis: $obrigatoria (primeiro acesso ou link), $acao, $nomeUsuario. */
$min = $minimo ?? max(8, \App\Services\Configuracao::inteiro('senha_tamanho_minimo', 8));
$dentroDoApp = !$obrigatoria;
?>
<?php if ($dentroDoApp): ?>
<div class="page-head"><div><h1>Alterar senha</h1><p>Use uma senha só sua, que você não usa em outros sites.</p></div></div>
<div class="row"><div class="col-lg-6">
<div class="card"><div class="card-body">
<?php else: ?>
<h1><?= isset($nomeUsuario) ? 'Nova senha' : 'Crie sua senha pessoal' ?></h1>
<p class="lead-sm"><?= isset($nomeUsuario) ? 'Olá, ' . e($nomeUsuario) . '. Defina a nova senha de acesso.' : 'Olá, ' . e($usuario['nome'] ?? '') . '. Sua senha atual é provisória: crie uma senha pessoal para continuar.' ?></p>
<?php endif; ?>
<form method="post" action="<?= e(url($acao)) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($dentroDoApp): ?>
        <div class="mb-3">
            <label class="form-label" for="senha_atual">Senha atual <span class="req">*</span></label>
            <div class="pw-wrap"><input class="form-control<?= tem_erro('senha_atual') ?>" id="senha_atual" name="senha_atual" type="password" autocomplete="current-password" required data-autofocus>
                <button type="button" class="pw-toggle" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button></div>
            <?= erro('senha_atual') ?>
        </div>
    <?php endif; ?>
    <div class="mb-3">
        <label class="form-label" for="nova_senha">Nova senha <span class="req">*</span></label>
        <div class="pw-wrap"><input class="form-control<?= tem_erro('nova_senha') ?>" id="nova_senha" name="nova_senha" type="password" autocomplete="new-password" minlength="<?= $min ?>" required<?= $dentroDoApp ? '' : ' data-autofocus' ?>>
            <button type="button" class="pw-toggle" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button></div>
        <?= erro('nova_senha') ?>
        <div class="form-text">Mínimo de <?= $min ?> caracteres, com letras e números. Não use o seu nome de usuário.</div>
    </div>
    <div class="mb-4">
        <label class="form-label" for="confirmacao">Repita a nova senha <span class="req">*</span></label>
        <div class="pw-wrap"><input class="form-control<?= tem_erro('confirmacao') ?>" id="confirmacao" name="confirmacao" type="password" autocomplete="new-password" required>
            <button type="button" class="pw-toggle" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button></div>
        <?= erro('confirmacao') ?>
    </div>
    <button class="btn btn-primary<?= $dentroDoApp ? '' : ' btn-lg w-100' ?>" type="submit"><i class="bi bi-check2"></i> Salvar senha</button>
</form>
<?php if ($dentroDoApp): ?>
</div></div></div></div>
<?php elseif (!isset($nomeUsuario)): ?>
<form method="post" action="<?= e(url('/sair')) ?>" class="mt-3"><?= csrf_field() ?><button class="btn btn-link p-0 small" type="submit">Sair</button></form>
<?php endif; ?>
