<div class="page-head">
    <div><h1>Aprovações de desconto e bonificação</h1><p>RN-41: desconto, bonificação ou preço manual só valem com a aprovação registrada. Prazo do gestor: <?= \App\Services\Configuracao::inteiro('sla_aprovacao_gestor_dias_uteis', 1) ?> dia útil.
        Alçada do gestor: <?= $alcada !== null ? 'até ' . number_format($alcada, 1, ',', '.') . '% de desconto' : 'sem limite definido' ?><?= $alcadaB !== null ? ' e ' . moeda($alcadaB) . ' bonificados' : '' ?>; acima disso, a diretoria aprova.</p></div>
</div>
<h2 class="mb-2">Aguardando sua decisão <span class="pill-count"><?= count($pendentes) ?></span></h2>
<?php if (!$pendentes): ?><div class="table-card mb-4"><div class="empty-state"><i class="bi bi-check2-all"></i>Nenhuma aprovação pendente para você.</div></div><?php endif; ?>
<?php foreach ($pendentes as $a): $S = $a['rascunho_estado'] ? json_decode((string) $a['rascunho_estado'], true) : null; ?>
<div class="card mb-3"><div class="card-body">
    <div class="d-flex flex-wrap gap-3 align-items-start">
        <div class="flex-grow-1">
            <div class="fw-semibold text-navy"><?= e($a['resumo']) ?> <?= $a['tipo'] === 'diretoria' ? '<span class="st st-devolvida">acima da alçada · diretoria</span>' : '' ?></div>
            <div class="small-muted">Pedido de <?= e($a['solicitante']) ?> em <?= e(data_hora_br($a['solicitada_em'])) ?><?= $a['numero'] ? ' · PI ' . e($a['numero']) : ' · PI em montagem' ?></div>
            <div class="d-flex flex-wrap gap-4 mt-2">
                <div><div class="small-muted">Desconto</div><b><?= number_format((float) $a['desconto_pct'] * 100, 1, ',', '.') ?>%</b> · <?= e(moeda($a['desconto_nominal'])) ?></div>
                <div><div class="small-muted">Bonificado</div><b><?= e(moeda($a['valor_bonificado'])) ?></b></div>
                <?php if ($a['manuais']): ?><div><div class="small-muted">Preços manuais</div><?= e(implode(' · ', array_map(fn ($m) => $m['sigla'] . ' R$ ' . number_format((float) $m['preco'], 2, ',', '.'), $a['manuais']))) ?></div><?php endif; ?>
                <?php if ($S): ?><div><div class="small-muted">Cliente · período</div><?= e(($S['f']['cli_razao'] ?? '—') . ' · ' . data_br($S['f']['ini'] ?? '') . ' a ' . data_br($S['f']['fim'] ?? '')) ?></div><?php endif; ?>
            </div>
            <?php if ($a['justificativa']): ?><div class="small mt-2"><b>Justificativa:</b> <?= e($a['justificativa']) ?></div><?php endif; ?>
        </div>
        <form method="post" action="<?= e(url('/aprovacoes/' . $a['id'] . '/responder')) ?>" class="d-flex flex-column gap-2" style="min-width:280px">
            <?= csrf_field() ?>
            <input class="form-control form-control-sm" name="resposta" placeholder="Comentário (obrigatório para recusar)" maxlength="500">
            <div class="d-flex gap-2">
                <button class="btn btn-sm flex-grow-1 btn-success" style="background:var(--gm-green);border-color:var(--gm-green)" name="decisao" value="aprovar" type="submit"><i class="bi bi-check2"></i> Aprovar</button>
                <button class="btn btn-sm flex-grow-1 btn-outline-danger" name="decisao" value="recusar" type="submit"><i class="bi bi-x"></i> Recusar</button>
            </div>
        </form>
    </div>
</div></div>
<?php endforeach; ?>

<div class="row g-3 mt-1">
    <div class="col-lg-6"><h2 class="mb-2">Meus pedidos</h2><div class="table-card"><table class="table table-sm mb-0">
        <thead><tr><th>Pedido</th><th>Situação</th><th>Resposta</th></tr></thead><tbody>
        <?php if (!$minhas): ?><tr><td colspan="3" class="small-muted p-3">Você não fez pedidos de aprovação.</td></tr><?php endif; ?>
        <?php foreach ($minhas as $a): ?><tr><td class="small"><?= e($a['resumo']) ?><div class="small-muted"><?= e(data_hora_br($a['solicitada_em'])) ?></div></td><td><?php [$cls, $txt] = ['pendente' => ['rascunho', 'Pendente'], 'aprovada' => ['aprovada', 'Aprovada'], 'recusada' => ['devolvida', 'Recusada'], 'cancelada' => ['cancelada', 'Substituída por novo pedido']][$a['situacao']]; ?><span class="st st-<?= $cls ?>"><?= e($txt) ?></span></td>
            <td class="small"><?= e($a['aprovador_nome'] ? $a['aprovador_nome'] . ($a['resposta'] ? ': ' . $a['resposta'] : '') : '—') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <div class="col-lg-6"><h2 class="mb-2">Respondidas por mim</h2><div class="table-card"><table class="table table-sm mb-0">
        <thead><tr><th>Pedido</th><th>Decisão</th></tr></thead><tbody>
        <?php if (!$respondidas): ?><tr><td colspan="2" class="small-muted p-3">Nenhuma.</td></tr><?php endif; ?>
        <?php foreach ($respondidas as $a): ?><tr><td class="small"><?= e($a['resumo']) ?><div class="small-muted"><?= e($a['solicitante']) ?> · <?= e(data_hora_br($a['respondida_em'])) ?></div></td><td class="small"><?= e(ucfirst($a['situacao'])) ?><?= $a['resposta'] ? ' · ' . e($a['resposta']) : '' ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>
