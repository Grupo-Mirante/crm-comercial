<?php
/**
 * Botão "Redefinir senha" (RN-75): gera uma senha provisória nova, mostrada uma única vez na tela do usuário.
 * Espera $u (id, nome) e $semRedefinir (motivo pelo qual o usuário logado não pode redefinir, ou null).
 * Opcional: $compacto = true para a versão só com ícone (lista de usuários).
 */
$compacto ??= false;
?>
<?php if ($semRedefinir === null): ?>
<form class="d-inline" method="post" action="<?= e(url('/usuarios/' . $u['id'] . '/redefinir-senha')) ?>" data-confirm="Gerar uma senha provisória nova para <?= e($u['nome']) ?>? A senha atual deixa de valer, as sessões abertas são encerradas e a senha nova aparece só uma vez." data-confirm-title="Redefinir senha" data-confirm-button="Gerar senha" data-confirm-danger="0">
    <?= csrf_field() ?><button class="btn btn-light<?= $compacto ? ' btn-sm' : '' ?>" type="submit" title="Redefinir senha"><i class="bi bi-key"></i><?= $compacto ? '' : ' Redefinir senha' ?></button></form>
<?php elseif (!$compacto): ?>
<span class="d-inline-block" tabindex="0" title="<?= e($semRedefinir) ?>"><button class="btn btn-light" type="button" disabled><i class="bi bi-key"></i> Redefinir senha</button></span>
<?php endif; ?>
