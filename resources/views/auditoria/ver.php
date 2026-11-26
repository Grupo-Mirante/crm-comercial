<?php
$antes = $l['dados_antes'] ? json_decode((string) $l['dados_antes'], true) : null;
$depois = $l['dados_depois'] ? json_decode((string) $l['dados_depois'], true) : null;
$fmt = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : (string) ($v ?? '—');
$chaves = array_unique(array_merge(array_keys((array) $antes), array_keys((array) $depois)));
?>
<nav class="breadcrumb"><a href="<?= e(url('/auditoria')) ?>">Auditoria</a>&nbsp;›&nbsp;#<?= (int) $l['id'] ?></nav>
<div class="page-head"><div><h1><code><?= e($l['acao']) ?></code></h1><p><?= e($l['descricao']) ?></p></div></div>
<div class="card mb-3"><div class="card-body"><dl class="dl-grid mb-0">
    <div><dt>Quando</dt><dd><?= e(data_hora_br($l['criado_em'])) ?></dd></div>
    <div><dt>Usuário</dt><dd><?= e($l['usuario_nome'] ?: '—') ?></dd></div>
    <div><dt>Registro</dt><dd><?= e(trim(($l['entidade'] ?? '') . ' ' . ($l['entidade_id'] ?? '')) ?: '—') ?></dd></div>
    <div><dt>IP</dt><dd><?= e($l['ip'] ?: '—') ?></dd></div>
    <div style="grid-column:1/-1"><dt>Navegador</dt><dd class="small"><?= e($l['user_agent'] ?: '—') ?></dd></div>
</dl></div></div>
<?php if ($chaves): ?>
<div class="table-card"><div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Campo</th><th>Antes</th><th>Depois</th></tr></thead>
    <tbody><?php foreach ($chaves as $k): ?>
        <tr><td><code><?= e($k) ?></code></td><td><pre class="mb-0 small" style="white-space:pre-wrap"><?= e($fmt($antes[$k] ?? null)) ?></pre></td><td><pre class="mb-0 small" style="white-space:pre-wrap"><?= e($fmt($depois[$k] ?? null)) ?></pre></td></tr>
    <?php endforeach; ?></tbody>
</table></div></div>
<?php endif; ?>
