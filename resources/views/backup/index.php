<div class="page-head">
    <div><h1>Backup</h1><p>O backup do banco roda sozinho uma vez por dia (rotina agendada). Aqui você gera um backup na hora e baixa os arquivos para guardar fora do servidor.</p></div>
    <div class="actions">
        <form method="post" action="<?= e(url('/backup')) ?>" data-confirm="Gerar um backup do banco agora? Pode levar alguns segundos." data-confirm-danger="0" data-confirm-button="Gerar"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><i class="bi bi-database-down"></i> Backup do banco agora</button></form>
        <form method="post" action="<?= e(url('/backup')) ?>" data-confirm="Compactar os anexos (aceites, briefings, comprovantes)?" data-confirm-danger="0" data-confirm-button="Compactar"><?= csrf_field() ?><input type="hidden" name="tipo" value="anexos"><button class="btn btn-light" type="submit"><i class="bi bi-paperclip"></i> Backup dos anexos</button></form>
    </div>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="kpi<?= $ultimo && substr($ultimo, 0, 10) >= date('Y-m-d', strtotime('-1 day')) ? ' kpi-ok' : ' kpi-alert' ?>"><span class="kpi-icon"><i class="bi bi-clock-history"></i></span><div class="kpi-label">Último backup do banco</div><div class="kpi-value" style="font-size:1.1rem"><?= e($ultimo ? data_hora_br($ultimo) : 'nunca') ?></div><div class="kpi-sub">deve acontecer todo dia</div></div></div>
    <div class="col-md-8"><div class="kpi"><span class="kpi-icon"><i class="bi bi-folder2"></i></span><div class="kpi-label">Pasta dos backups no servidor</div><div class="kpi-value" style="font-size:.95rem;word-break:break-all"><code><?= e($pasta) ?></code></div><div class="kpi-sub">retenção: <?= (int) config('app.backup.retencao_dias', 30) ?> dias (BACKUP_RETENCAO_DIAS no .env)</div></div></div>
</div>
<div class="alert alert-info"><i class="bi bi-life-preserver"></i><div><b>Para restaurar</b> (substitui todo o banco pelo backup escolhido), o responsável técnico roda no terminal do servidor, dentro da pasta do CRM:
    <code>php bin/console.php restaurar NOME_DO_ARQUIVO.sql.gz</code>. O comando pede confirmação. Veja o Manual de instalação, seção “Backup e restauração”.</div></div>
<div class="table-card mb-4"><div class="table-responsive"><table class="table table-hover">
    <thead><tr><th>Arquivo</th><th>Tipo</th><th>Data</th><th class="num">Tamanho</th><th></th></tr></thead>
    <tbody>
    <?php if (!$lista): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-database"></i>Nenhum backup na pasta.</div></td></tr><?php endif; ?>
    <?php foreach ($lista as $b): ?><tr><td><code><?= e($b['nome']) ?></code></td><td><?= e($b['tipo']) ?></td><td><?= e(data_hora_br($b['data'])) ?></td><td class="num"><?= number_format($b['tamanho'] / 1048576, 2, ',', '.') ?> MB</td>
        <td><a class="btn btn-light btn-sm" href="<?= e(url('/backup/' . $b['nome'])) ?>"><i class="bi bi-download"></i> Baixar</a></td></tr><?php endforeach; ?>
    </tbody>
</table></div></div>
<h2 class="mb-2">Últimas execuções das rotinas automáticas</h2>
<div class="table-card"><table class="table table-sm">
    <thead><tr><th>Rotina</th><th>Início</th><th>Resultado</th></tr></thead>
    <tbody>
    <?php if (!$rotinas): ?><tr><td colspan="3" class="small-muted p-3">Nenhuma execução registrada: confira se o agendamento (cron) foi configurado no servidor.</td></tr><?php endif; ?>
    <?php foreach ($rotinas as $x): ?><tr><td><code><?= e($x['rotina']) ?></code></td><td class="small"><?= e(data_hora_br($x['iniciada_em'])) ?></td><td class="small <?= (int) $x['sucesso'] ? '' : 'text-red' ?>"><?= e($x['resultado']) ?></td></tr><?php endforeach; ?>
    </tbody>
</table></div>
