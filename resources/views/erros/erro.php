<?php
$icone = match ((int) $status) { 403 => 'shield-x', 404 => 'search', 405 => 'slash-circle', default => 'exclamation-triangle' };
$titulo = match ((int) $status) { 403 => 'Acesso não permitido', 404 => 'Página não encontrada', default => 'Algo deu errado' };
?>
<div class="empty-state py-5">
    <i class="bi bi-<?= e($icone) ?>" style="font-size:3rem;color:var(--gm-navy)"></i>
    <h1 class="h2 mt-2"><?= e($titulo) ?></h1>
    <p class="mb-4"><?= e($mensagem) ?></p>
    <a class="btn btn-primary" href="<?= e(url('/')) ?>"><i class="bi bi-house"></i> Ir para o início</a>
</div>
