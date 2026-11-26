<div class="page-head">
    <div><h1>Avisos</h1><p>Aprovações, devoluções, PIs na fila e faturamento.</p></div>
    <div class="actions">
        <form method="post" action="<?= e(url('/notificacoes/todas-lidas')) ?>"><?= csrf_field() ?><button class="btn btn-light" type="submit"><i class="bi bi-check2-all"></i> Marcar todos como lidos</button></form>
    </div>
</div>
<div class="table-card">
    <?php if (!$p['itens']): ?>
        <div class="empty-state"><i class="bi bi-bell-slash"></i>Você não tem avisos.</div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($p['itens'] as $n): ?>
                <a class="list-group-item list-group-item-action py-3<?= $n['lida_em'] ? '' : ' bg-soft' ?>" data-notif="<?= (int) $n['id'] ?>" href="<?= e(url($n['link'] ?: '/notificacoes')) ?>">
                    <div class="d-flex gap-2 align-items-start">
                        <?php if (!$n['lida_em']): ?><span class="badge text-bg-danger mt-1">novo</span><?php endif; ?>
                        <div class="flex-grow-1">
                            <div class="fw-semibold text-navy"><?= e($n['titulo']) ?></div>
                            <?php if ($n['mensagem']): ?><div class="small"><?= e($n['mensagem']) ?></div><?php endif; ?>
                        </div>
                        <small class="small-muted text-nowrap"><?= e(data_hora_br($n['criado_em'])) ?></small>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../partials/paginacao.php'; ?>
