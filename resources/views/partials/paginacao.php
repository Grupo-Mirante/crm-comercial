<?php
/** Paginação. Variáveis: $p (resultado de Controller::paginar), $query (filtros atuais). */
if (($p['paginas'] ?? 1) <= 1) {
    echo '<div class="small-muted mt-2">' . numero($p['total'] ?? 0) . ' registro(s)</div>';
    return;
}
$query = $query ?? [];
$link = fn (int $n) => '?' . http_build_query(array_merge($query, ['pagina' => $n]));
$ini = max(1, $p['pagina'] - 2);
$fim = min($p['paginas'], $p['pagina'] + 2);
?>
<div class="d-flex flex-wrap align-items-center gap-2 mt-3">
    <span class="small-muted"><?= numero($p['total']) ?> registro(s) · página <?= (int) $p['pagina'] ?> de <?= (int) $p['paginas'] ?></span>
    <nav class="ms-auto" aria-label="Páginas">
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item<?= $p['pagina'] <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($link($p['pagina'] - 1)) ?>" aria-label="Anterior">‹</a></li>
            <?php for ($i = $ini; $i <= $fim; $i++): ?>
                <li class="page-item<?= $i === $p['pagina'] ? ' active' : '' ?>"><a class="page-link" href="<?= e($link($i)) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <li class="page-item<?= $p['pagina'] >= $p['paginas'] ? ' disabled' : '' ?>"><a class="page-link" href="<?= e($link($p['pagina'] + 1)) ?>" aria-label="Próxima">›</a></li>
        </ul>
    </nav>
</div>
