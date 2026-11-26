<div class="page-head">
    <div><h1>Reservas de 24 horas</h1><p>Reserve pela Nova PI (botão “Reservar por 24h”). Cotas de projeto reservadas saem da disponibilidade dos outros vendedores; espaço reservado (programas, patrocínios e formatos) aparece como aviso para quem tentar vender o mesmo espaço. Sem PI gerada em 24 horas, a reserva cai sozinha.</p></div>
    <?php if (can('reservas.criar')): ?><div class="actions"><a class="btn btn-primary" href="<?= e(url('/pis/nova')) ?>"><i class="bi bi-plus-lg"></i> Montar PI e reservar</a></div><?php endif; ?>
</div>
<div class="table-card mb-4"><div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th>Cliente</th><th>Veículo</th><th>Executivo</th><th>Itens reservados</th><th>Reservada em</th><th>Expira</th><th>Tempo restante</th><th></th></tr></thead>
    <tbody>
    <?php if (!$ativas): ?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-hourglass"></i>Nenhuma reserva ativa.</div></td></tr><?php endif; ?>
    <?php foreach ($ativas as $a): ?>
        <tr><td class="fw-semibold"><?= e($a['cliente_nome']) ?><div class="small-muted"><?= e($a['codigo']) ?></div></td><td class="small"><?= e($a['veiculo']) ?></td><td class="small"><?= e($a['executivo']) ?></td>
            <td class="small"><?php foreach ($a['itens'] as $it): ?><div><span class="pill-count"><?= e(['cota' => 'cota', 'insercao' => 'inserções', 'patrocinio' => 'patrocínio', 'digital' => 'digital'][$it['tipo']]) ?></span> <?= e($it['descricao']) ?><?= $it['data_inicio'] ? ' <span class="small-muted">' . e(data_br($it['data_inicio']) . ' a ' . data_br($it['data_fim'])) . '</span>' : '' ?></div><?php endforeach; ?></td>
            <td class="small text-nowrap"><?= e(data_hora_br($a['reservada_em'])) ?></td><td class="small text-nowrap"><?= e(data_hora_br($a['expira_em'])) ?></td>
            <td class="<?= (int) $a['minutos_restantes'] < 120 ? 'text-red fw-semibold' : '' ?>"><?= e($a['tempo_restante']) ?></td>
            <td><div class="table-actions">
                <?php if ($a['meu']): ?>
                    <form method="post" action="<?= e(url('/reservas/' . $a['id'] . '/abrir')) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit">Abrir e gerar PI</button></form>
                <?php endif; ?>
                <?php if ($a['meu'] || \App\Services\Auth::isAdmin()): ?>
                    <form method="post" action="<?= e(url('/reservas/' . $a['id'] . '/liberar')) ?>" data-confirm="Liberar esta reserva antes do prazo? As cotas e o espaço voltam a ficar disponíveis para os outros vendedores." data-confirm-button="Liberar"><?= csrf_field() ?><button class="btn btn-light btn-sm text-danger" type="submit">Liberar</button></form>
                <?php endif; ?>
            </div></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<h2 class="mb-2">Encerradas recentemente</h2>
<div class="table-card"><div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Cliente</th><th>Veículo</th><th>Executivo</th><th>Itens</th><th>Situação</th><th>Quando</th></tr></thead>
    <tbody>
    <?php if (!$encerradas): ?><tr><td colspan="6" class="small-muted p-3">Nenhuma.</td></tr><?php endif; ?>
    <?php foreach ($encerradas as $a): ?><tr><td class="small"><?= e($a['cliente_nome']) ?></td><td class="small"><?= e($a['veiculo']) ?></td><td class="small"><?= e($a['executivo']) ?></td><td class="small"><?= e($a['resumo_itens']) ?></td>
        <td class="small"><?= e(['convertida' => 'Virou venda', 'liberada' => 'Liberada', 'expirada' => 'Expirou (24 h)'][$a['situacao']] ?? $a['situacao']) ?><?= $a['numero'] ? ' · <a href="' . e(url('/pis/' . $a['pi_id'])) . '">' . e($a['numero']) . '</a>' : '' ?></td>
        <td class="small text-nowrap"><?= e(data_hora_br($a['encerrada_em'])) ?></td></tr><?php endforeach; ?>
    </tbody>
</table></div></div>
