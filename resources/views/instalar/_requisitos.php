<?php /** @var list<array{item:string,ok:bool,detalhe:string,obrigatorio:bool}> $requisitos */ ?>
<ul class="inst-req">
    <?php foreach ($requisitos as $x):
        $cls = $x['ok'] ? 'ok' : ($x['obrigatorio'] ? 'erro' : 'aviso');
        $ic = $x['ok'] ? 'check-circle-fill' : ($x['obrigatorio'] ? 'x-circle-fill' : 'exclamation-triangle-fill'); ?>
        <li class="<?= $cls ?>"><i class="bi bi-<?= $ic ?>"></i>
            <span><b><?= e($x['item']) ?></b> <small><?= e($x['detalhe']) ?><?= !$x['ok'] && !$x['obrigatorio'] ? ' (recomendado)' : '' ?></small></span></li>
    <?php endforeach; ?>
</ul>
