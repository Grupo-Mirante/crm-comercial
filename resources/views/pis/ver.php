<?php
$f = $snap['f'] ?? [];
$T = $R['T'] ?? [];
$pend = json_decode((string) ($pi['pendencias'] ?? '[]'), true) ?: [];
$blocos = ['insercao' => 'Inserções', 'patrocinio' => 'Patrocínios', 'digital' => 'Digital', 'publicidade_legal' => 'Publicidade legal', 'projeto' => 'Projetos especiais'];
$porBloco = [];
foreach ($itens as $it) {
    $porBloco[$it['bloco']][] = $it;
}
$form = fn (string $acao, string $conteudo, string $confirm = '', string $botao = 'Confirmar', bool $perigo = false) =>
    '<form method="post" action="' . e(url('/pis/' . $pi['id'] . '/acao/' . $acao)) . '" class="row g-2 align-items-end"' . ($confirm ? ' data-confirm="' . e($confirm) . '" data-confirm-button="' . e($botao) . '" data-confirm-danger="' . ($perigo ? '1' : '0') . '"' : '') . '>' . csrf_field() . $conteudo . '</form>';
$rota = fn (string $p) => in_array($p, \App\Core\Router::$caminhos, true);
?>
<nav class="breadcrumb"><a href="<?= e(url('/pis')) ?>">PIs</a>&nbsp;›&nbsp;<?= e($pi['numero']) ?></nav>
<div class="page-head">
    <div>
        <h1>PI <?= e($pi['numero']) ?> <?= selo($pi['situacao']) ?></h1>
        <p><?= e($pi['tipo']) ?> · <?= e($pi['veiculo']) ?> (<?= e($pi['empresa']) ?>) · <?= e($pi['cliente_razao']) ?> · <?= e($pi['executivo']) ?><?= $pi['parte'] ? ' · PI ' . e($pi['parte']) . ' da campanha' : '' ?></p>
    </div>
    <div class="actions">
        <?php if (isset($acoes['excel'])): ?><a class="btn btn-outline-primary" href="<?= e(url('/pis/' . $pi['id'] . '/excel')) ?>"><i class="bi bi-file-earmark-excel"></i> Baixar Excel</a><?php endif; ?>
        <?php if (isset($acoes['excel'])): ?><a class="btn btn-outline-primary" href="<?= e(url('/pis/' . $pi['id'] . '/pdf')) ?>"><i class="bi bi-file-earmark-pdf"></i> Baixar PDF</a><?php endif; ?>
        <a class="btn btn-light" href="<?= e(url('/pis/' . $pi['id'] . '/imprimir')) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Imprimir</a>
        <?php foreach (['editar' => ['pencil', 'btn-primary'], 'corrigir' => ['wrench', 'btn-danger']] as $k => [$ic, $cls]): if (isset($acoes[$k])): ?>
            <form method="post" action="<?= e(url('/pis/' . $pi['id'] . '/abrir/' . $k)) ?>"><?= csrf_field() ?><button class="btn <?= $cls ?>" type="submit"><i class="bi bi-<?= $ic ?>"></i> <?= e($acoes[$k]) ?></button></form>
        <?php endif; endforeach; ?>
        <?php if (isset($acoes['conferir']) && $rota('/conferencia')): ?><a class="btn btn-primary" href="<?= e(url('/conferencia/' . $pi['id'])) ?>"><i class="bi bi-clipboard-check"></i> Conferir</a><?php endif; ?>
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">Mais ações</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if (isset($acoes['renovar'])): ?><li><form method="post" action="<?= e(url('/pis/' . $pi['id'] . '/abrir/renovar')) ?>"><?= csrf_field() ?><button class="dropdown-item" type="submit"><i class="bi bi-arrow-repeat me-2"></i>Renovar (PI nova, mês seguinte)</button></form></li><?php endif; ?>
                <?php if (isset($acoes['substituir'])): ?><li><form method="post" action="<?= e(url('/pis/' . $pi['id'] . '/abrir/substituir')) ?>"><?= csrf_field() ?><button class="dropdown-item" type="submit"><i class="bi bi-arrow-left-right me-2"></i>Cancela/substitui</button></form></li><?php endif; ?>
                <?php if (!isset($acoes['renovar']) && !isset($acoes['substituir'])): ?><li><span class="dropdown-item-text small-muted">Nenhuma outra ação para esta situação.</span></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php if ($pi['situacao'] === 'Rascunho' && $pend): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i><div><b>Rascunho com <?= count($pend) ?> pendência(s).</b> A PI não vai para a Conferência até ser corrigida e gerada de novo (o número é mantido):
    <ul class="mb-0 mt-1"><?php foreach ($pend as $c): ?><li><b><?= e($c['code']) ?></b> <?= e($c['txt']) ?> — <?= e($c['fix']) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>
<?php if ($pi['situacao'] === 'Devolvida' && $devolucoes): $d = end($devolucoes); ?>
<div class="alert alert-danger"><i class="bi bi-arrow-return-left"></i><div><b>Devolvida em <?= e(data_hora_br($d['devolvida_em'])) ?> por <?= e($d['por']) ?> (<?= e($d['codigos']) ?>):</b> <?= e($d['descricao']) ?><div class="small mt-1">Corrija em até 1 dia útil e gere de novo: a PI volta para a Conferência como Corrigida, com o mesmo número.</div></div></div>
<?php endif; ?>
<?php if ($pi['situacao'] === 'Cancelada'): ?><div class="alert alert-secondary"><i class="bi bi-x-octagon"></i><div><b>Cancelada em <?= e(data_hora_br($pi['cancelada_em'])) ?>:</b> <?= e($pi['motivo_cancelamento']) ?></div></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3"><div class="kpi"><div class="kpi-label">Valor de tabela</div><div class="kpi-value" style="font-size:1.15rem"><?= e(moeda($pi['valor_tabela'])) ?></div><div class="kpi-sub">desconto <?= e(moeda($pi['desconto_nominal'])) ?> (<?= number_format((float) $pi['desconto_pct'] * 100, 1, ',', '.') ?>%)</div></div></div>
            <div class="col-6 col-md-3"><div class="kpi"><div class="kpi-label">Negociado</div><div class="kpi-value" style="font-size:1.15rem"><?= e(moeda($pi['valor_negociado'])) ?></div><div class="kpi-sub">bonificado <?= e(moeda($pi['valor_bonificado'])) ?></div></div></div>
            <div class="col-6 col-md-3"><div class="kpi"><div class="kpi-label">Comissão agência</div><div class="kpi-value" style="font-size:1.15rem"><?= e(moeda($pi['valor_comissao'])) ?></div><div class="kpi-sub"><?= e($pi['venda'] === 'Agência' ? $pi['agencia_razao'] . ' · ' . number_format((float) $pi['comissao_pct'], 2, ',', '.') . '%' : 'venda direta') ?></div></div></div>
            <div class="col-6 col-md-3"><div class="kpi kpi-ok"><div class="kpi-label">Líquido a faturar</div><div class="kpi-value" style="font-size:1.15rem"><?= e(moeda($pi['valor_liquido'])) ?></div><div class="kpi-sub"><?= numero($pi['total_insercoes']) ?> inserção(ões)</div></div></div>
        </div>

        <div class="card mb-3"><div class="card-header"><i class="bi bi-info-circle"></i> Dados da PI</div><div class="card-body"><dl class="dl-grid mb-0">
            <div><dt>Emissão</dt><dd><?= e(data_br($pi['data_emissao'])) ?></dd></div>
            <div><dt>Período</dt><dd><?= e(data_br($pi['data_inicio'])) ?> a <?= e(data_br($pi['data_fim'])) ?></dd></div>
            <div><dt>Tabela / praça</dt><dd><?= e($R['tab'] ?? '—') ?></dd></div>
            <div><dt>PI de origem</dt><dd><?= $pi['pi_origem_id'] ? '<a href="' . e(url('/pis/' . $pi['pi_origem_id'])) . '">' . e($pi['pi_origem_numero']) . '</a>' : e($pi['pi_origem_numero'] ?: '—') ?></dd></div>
            <div><dt>Campanha</dt><dd><?= e($pi['titulo'] ?: '—') ?></dd></div>
            <div><dt>Produto</dt><dd><?= e($pi['produto'] ?: '—') ?></dd></div>
            <div><dt>Gestor</dt><dd><?= e($pi['gestor'] ?: '—') ?></dd></div>
            <div><dt>Pagamento</dt><dd><?= e(trim(($pi['forma_pagamento'] ?? '') . ($pi['prazo_pagamento_dias'] ? ' · ' . $pi['prazo_pagamento_dias'] . ' dias' : ''))) ?: '—' ?></dd></div>
            <div><dt>Cliente</dt><dd><?= $pi['cliente_id'] && can('clientes.ver') ? '<a href="' . e(url('/clientes/' . $pi['cliente_id'])) . '">' . e($pi['cliente_razao']) . '</a>' : e($pi['cliente_razao']) ?><div class="small-muted"><?= e($f['cli_cnpj'] ?? '') ?></div></dd></div>
            <div><dt>Contato · faturamento</dt><dd class="small"><?= e($f['cli_contato'] ?? '') ?><br><?= e($f['cli_email'] ?? '') ?></dd></div>
            <div><dt>Aceite do cliente</dt><dd><?= e(($f['ac_nome'] ?? '') ?: '—') ?><?= !empty($f['ac_data']) ? ' · ' . e(data_br($f['ac_data'])) : '' ?></dd></div>
            <div><dt>Aprovação do gestor</dt><dd><?= !empty($f['gs_nome']) ? e($f['gs_nome'] . ' · ' . data_br($f['gs_data'] ?? '')) : 'não exigida' ?></dd></div>
            <?php if ((int) $pi['fora_do_prazo']): ?><div><dt>Prazo de envio</dt><dd style="color:var(--gm-orange)">Fora do prazo (SLA)</dd></div><?php endif; ?>
            <?php if ($pi['data_fim_veiculacao']): ?><div><dt>Fim da veiculação</dt><dd><?= e(data_br($pi['data_fim_veiculacao'])) ?></dd></div><?php endif; ?>
            <?php if ($pi['nf_numero']): ?><div><dt>Nota fiscal</dt><dd><?= e($pi['nf_numero']) ?> · <?= e(moeda($pi['nf_valor'])) ?><?= abs((float) $pi['nf_valor'] - (float) $pi['valor_liquido']) >= 0.01 ? ' <span class="text-red small">(diferente da PI)</span>' : '' ?></dd></div><?php endif; ?>
        </dl><?php if (!empty($f['obs'])): ?><div class="mt-2 small"><b>Observações:</b> <?= e($f['obs']) ?></div><?php endif; ?></div></div>

        <?php foreach ($blocos as $bk => $bt): if (empty($porBloco[$bk])) continue; ?>
        <div class="card mb-3"><div class="card-header"><?= e($bt) ?></div><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Sigla</th><th>Descrição</th><?= $bk === 'insercao' ? '<th>Mês</th><th class="num">Dur.</th>' : '' ?><th>Cobrança</th><th class="num">Qtd</th><th class="num">Unitário</th><th class="num">Tabela</th><th class="num">Desc.</th><th class="num">Total</th></tr></thead>
            <tbody><?php foreach ($porBloco[$bk] as $it): $dt = json_decode((string) $it['detalhes'], true) ?: []; ?>
                <tr><td><code><?= e($it['sigla']) ?></code></td><td class="small"><?= e($it['descricao']) ?><?php if (!empty($dt['dias'])): ?><div class="small-muted"><?= e(is_array($dt['dias']) ? '' : $dt['dias']) ?></div><?php endif; ?>
                    <?php if ($bk === 'digital' && !empty($dt['proporcao'])): ?><div class="small-muted"><?= e(data_br($it['data_inicio'])) ?> a <?= e(data_br($it['data_fim'])) ?> · <?= e(implode(' + ', $dt['proporcao'])) ?></div><?php endif; ?>
                    <?php if ($bk === 'publicidade_legal'): ?><div class="small-muted"><?= e(($dt['material'] ?? '') . ' · ' . (float) $it['colunas'] . ' col × ' . (float) $it['centimetros'] . ' cm') ?></div><?php endif; ?></td>
                    <?= $bk === 'insercao' ? '<td class="small">' . e($it['mes_ref']) . '</td><td class="num">' . (int) $it['duracao_seg'] . '"</td>' : '' ?>
                    <td class="small"><?= e($it['cobranca']) ?><?= (int) $it['preco_manual'] ? ' <span class="small" style="color:var(--gm-orange)">preço manual</span>' : '' ?></td>
                    <td class="num"><?= $it['impressoes'] ? numero($it['impressoes']) . ' impr.' : numero($it['quantidade'], fmod((float) $it['quantidade'], 1) ? 2 : 0) ?></td>
                    <td class="num"><?= $it['preco_unitario'] !== null ? e(moeda($it['preco_unitario'], false)) : '—' ?></td><td class="num"><?= e(moeda($it['valor_tabela'], false)) ?></td>
                    <td class="num"><?= (float) $it['desconto_pct'] ? number_format((float) $it['desconto_pct'], 1, ',', '.') . '%' : '—' ?></td><td class="num fw-semibold"><?= e(moeda($it['valor_total'], false)) ?></td></tr>
            <?php endforeach; ?></tbody></table></div></div>
        <?php endforeach; ?>

        <?php if ($entregas): ?>
        <div class="card mb-3"><div class="card-header">Entregas especiais</div><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Entrega</th><th>Especificação</th><th class="num">Qtd</th><th>Área</th><th>Briefing</th><th>Jurídico</th></tr></thead>
            <tbody><?php foreach ($entregas as $en): ?><tr><td><?= e($en['tipo']) ?></td><td class="small"><?= e($en['especificacao']) ?></td><td class="num"><?= e((string) $en['quantidade']) ?></td><td><?= e($en['area_responsavel']) ?></td><td><?= e($en['numero_briefing']) ?></td><td><?= e($en['aval_juridico']) ?></td></tr><?php endforeach; ?></tbody>
        </table></div></div>
        <?php endif; ?>

        <div class="card mb-3"><div class="card-header">Parcelas</div><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Parcela</th><th>Vencimento</th><th class="num">Valor</th></tr></thead>
            <tbody><?php if (!$parcelas): ?><tr><td colspan="3" class="small-muted">Sem parcelas.</td></tr><?php endif; ?>
            <?php foreach ($parcelas as $pa): ?><tr><td><?= (int) $pa['numero'] ?>ª</td><td><?= e(data_br($pa['vencimento'])) ?></td><td class="num"><?= e(moeda($pa['valor'])) ?></td></tr><?php endforeach; ?></tbody>
        </table></div></div>
    </div>

    <div class="col-lg-4">
        <?php
        $opFormularios = [];
        if (isset($acoes['programar'])) {
            $opFormularios[] = ['Liberar para programação (OPEC)', $form('programar', '<div class="col-12"><input class="form-control form-control-sm" name="observacao" placeholder="Observação (opcional)"></div><div class="col-12"><button class="btn btn-primary btn-sm" type="submit">Liberar</button></div>', 'Liberar a PI para programação? Financeiro, OPEC e Marketing passam a trabalhar nela.', 'Liberar')];
        }
        if (isset($acoes['veicular'])) {
            $opFormularios[] = ['Início da veiculação', $form('veicular', '<div class="col-12"><button class="btn btn-primary btn-sm" type="submit">Marcar veiculação iniciada</button></div>')];
        }
        if (isset($acoes['finalizar'])) {
            $opFormularios[] = ['Veiculação finalizada (RN-57)', $form('finalizar', '<div class="col-6"><label class="form-label small">Fim da veiculação *</label><input class="form-control form-control-sm" type="date" name="data_fim" value="' . e($pi['data_fim'] && $pi['data_fim'] < date('Y-m-d') ? $pi['data_fim'] : date('Y-m-d')) . '" required></div><div class="col-12"><input class="form-control form-control-sm" name="observacao" placeholder="ex.: comprovante de veiculação emitido"></div><div class="col-12"><button class="btn btn-primary btn-sm" type="submit">Finalizar</button></div>', 'Marcar a veiculação como finalizada? A PI fica pronta para faturamento e não pode mais ser alterada (só renovada).', 'Finalizar')];
        }
        if (isset($acoes['faturar'])) {
            $opFormularios[] = ['Faturamento', $form('faturar', '<div class="col-6"><label class="form-label small">Nº da nota *</label><input class="form-control form-control-sm" name="nf_numero" required></div><div class="col-6"><label class="form-label small">Valor da nota *</label><input class="form-control form-control-sm text-end" name="nf_valor" value="' . e(number_format((float) $pi['valor_liquido'], 2, ',', '.')) . '" data-mask="moeda" required></div><div class="col-12"><button class="btn btn-primary btn-sm" type="submit">Registrar faturamento</button></div>', 'Registrar o faturamento desta PI?', 'Registrar')];
        }
        if (isset($acoes['encerrar'])) {
            $opFormularios[] = ['Pagamento recebido', $form('encerrar', '<div class="col-12"><button class="btn btn-primary btn-sm" type="submit">Encerrar PI</button></div>', 'Confirmar o recebimento e encerrar a PI?', 'Encerrar')];
        }
        if (isset($acoes['cancelar'])) {
            $opFormularios[] = ['Cancelar PI (RN-55)', $form('cancelar', '<div class="col-12"><textarea class="form-control form-control-sm" name="motivo" rows="2" placeholder="Motivo do cancelamento (obrigatório)" required></textarea></div><div class="col-12"><button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-octagon"></i> Cancelar PI</button></div>',
                'Cancelar a PI ' . $pi['numero'] . '? Não dá para desfazer: ela sai dos totais de venda e as cotas de projeto voltam a ficar disponíveis.' . ($grupo ? ' Esta PI faz parte de um grupo mensal: só ela será cancelada.' : ''), 'Cancelar PI', true)];
        }
        ?>
        <?php if ($opFormularios): ?>
        <div class="card mb-3"><div class="card-header"><i class="bi bi-lightning"></i> Ações</div><div class="card-body">
            <?php foreach ($opFormularios as $k => [$t, $html]): ?><div class="<?= $k ? 'pt-3 mt-3 border-top' : '' ?>"><div class="fw-semibold small mb-2"><?= e($t) ?></div><?= $html ?></div><?php endforeach; ?>
        </div></div>
        <?php endif; ?>

        <?php if ($grupo): ?><div class="card mb-3"><div class="card-header"><i class="bi bi-collection"></i> PIs mensais da campanha (RN-17)</div><ul class="list-group list-group-flush">
            <?php foreach ($grupo as $g): ?><li class="list-group-item d-flex align-items-center gap-2<?= (int) $g['id'] === (int) $pi['id'] ? ' bg-soft' : '' ?>"><a href="<?= e(url('/pis/' . $g['id'])) ?>"><?= e($g['numero']) ?></a><span class="small-muted"><?= e($g['mes_referencia']) ?></span><span class="ms-auto"><?= selo($g['situacao']) ?></span></li><?php endforeach; ?>
        </ul></div><?php endif; ?>
        <?php if ($derivadas): ?><div class="card mb-3"><div class="card-header"><i class="bi bi-diagram-2"></i> PIs derivadas desta</div><ul class="list-group list-group-flush">
            <?php foreach ($derivadas as $g): ?><li class="list-group-item d-flex gap-2"><a href="<?= e(url('/pis/' . $g['id'])) ?>"><?= e($g['numero']) ?></a><span class="small-muted"><?= e($g['tipo']) ?></span><span class="ms-auto"><?= selo($g['situacao']) ?></span></li><?php endforeach; ?>
        </ul></div><?php endif; ?>

        <?php if ($devolucoes): ?><div class="card mb-3"><div class="card-header"><i class="bi bi-arrow-return-left"></i> Devoluções (<?= count($devolucoes) ?>)</div><ul class="list-group list-group-flush">
            <?php foreach ($devolucoes as $d): ?><li class="list-group-item small"><b><?= (int) $d['sequencia'] ?>ª · <?= e($d['codigos']) ?></b> — <?= e($d['descricao']) ?><div class="small-muted"><?= e(data_hora_br($d['devolvida_em'])) ?> por <?= e($d['por']) ?><?= $d['corrigida_em'] ? ' · corrigida em ' . e(data_hora_br($d['corrigida_em'])) : ' · aguardando correção' ?><?= (int) $d['gestor_notificado'] ? ' · gestor avisado' : '' ?></div></li><?php endforeach; ?>
        </ul></div><?php endif; ?>

        <?php if ($aprovacoes): ?><div class="card mb-3"><div class="card-header"><i class="bi bi-check2-square"></i> Aprovações do gestor</div><ul class="list-group list-group-flush">
            <?php foreach ($aprovacoes as $a): ?><li class="list-group-item small"><b><?= e(ucfirst($a['situacao'])) ?></b> · desconto <?= number_format((float) $a['desconto_pct'] * 100, 1, ',', '.') ?>% · bonificado <?= e(moeda($a['valor_bonificado'])) ?>
                <div class="small-muted">pedida por <?= e($a['solicitante']) ?> em <?= e(data_hora_br($a['solicitada_em'])) ?><?= $a['aprovador_nome'] ? ' · ' . e($a['aprovador_nome']) . ' em ' . e(data_hora_br($a['respondida_em'])) : '' ?></div></li><?php endforeach; ?>
        </ul></div><?php endif; ?>

        <?php $anexos = \App\Core\Database::all("SELECT a.id, a.tipo, a.nome_original, a.criado_em, a.enviado_por, u.nome AS por FROM anexos a JOIN usuarios u ON u.id = a.enviado_por WHERE a.entidade = 'pi' AND a.entidade_id = ? AND a.excluido_em IS NULL ORDER BY a.id", [(int) $pi['id']]); ?>
        <div class="card mb-3"><div class="card-header"><i class="bi bi-paperclip"></i> Anexos <span class="ms-auto small-muted">aceite, briefing, jurídico, comprovante</span></div>
            <ul class="list-group list-group-flush">
                <?php if (!$anexos): ?><li class="list-group-item small-muted small">Nenhum anexo. Anexe o aceite do cliente (assinatura ou e-mail) antes da conferência (D10).</li><?php endif; ?>
                <?php foreach ($anexos as $an): ?><li class="list-group-item small d-flex gap-2 align-items-center"><a href="<?= e(url('/anexos/' . $an['id'])) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark"></i> <?= e($an['nome_original']) ?></a>
                    <span class="small-muted"><?= e(\App\Services\AnexoService::TIPOS[$an['tipo']] ?? $an['tipo']) ?> · <?= e($an['por']) ?></span>
                    <?php if ((int) $an['enviado_por'] === \App\Services\Auth::id() || \App\Services\Auth::isAdmin()): ?><form class="ms-auto" method="post" action="<?= e(url('/anexos/' . $an['id'] . '/excluir')) ?>" data-confirm="Excluir o anexo <?= e($an['nome_original']) ?>?" data-confirm-button="Excluir"><?= csrf_field() ?><button class="btn btn-link btn-sm text-danger p-0" type="submit" aria-label="Excluir anexo"><i class="bi bi-trash"></i></button></form><?php endif; ?></li><?php endforeach; ?>
            </ul>
            <?php if (\App\Services\AnexoService::podeAnexar($pi)): ?>
            <form class="card-body border-top row g-2" method="post" action="<?= e(url('/pis/' . $pi['id'] . '/anexos')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="col-5"><select class="form-select form-select-sm" name="tipo" aria-label="Tipo do anexo"><?php foreach (\App\Services\AnexoService::TIPOS as $k => $t): ?><option value="<?= e($k) ?>"><?= e($t) ?></option><?php endforeach; ?></select></div>
                <div class="col-7"><input class="form-control form-control-sm" type="file" name="arquivo" required accept=".pdf,.jpg,.jpeg,.png,.eml,.msg,.docx,.xlsx,.txt" aria-label="Arquivo"></div>
                <div class="col-12"><button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-upload"></i> Anexar</button> <span class="small-muted">até <?= (int) config('app.uploads.max_mb', 10) ?> MB · PDF, imagem, e-mail, Word ou Excel</span></div>
            </form>
            <?php endif; ?>
        </div>

        <div class="card mb-3"><div class="card-header"><i class="bi bi-clock-history"></i> Histórico (RN-59)</div><ul class="list-group list-group-flush" style="max-height:420px;overflow:auto">
            <?php foreach ($historico as $h): ?><li class="list-group-item small"><div class="d-flex gap-2"><span class="fw-semibold"><?= e($h['evento']) ?></span></div>
                <div class="small-muted"><?= e(data_hora_br($h['criado_em'])) ?> · <?= e($h['usuario_nome']) ?><?= $h['situacao_nova'] && $h['situacao_nova'] !== $h['situacao_anterior'] ? ' · ' . e(($h['situacao_anterior'] ?: 'nova') . ' → ' . $h['situacao_nova']) : '' ?></div></li><?php endforeach; ?>
        </ul></div>

        <div class="card"><div class="card-header"><i class="bi bi-layers"></i> Versões geradas</div><ul class="list-group list-group-flush">
            <?php foreach ($versoes as $v): ?><li class="list-group-item small d-flex"><span>Versão <?= (int) $v['versao'] ?> · <?= e($v['situacao']) ?></span><span class="ms-auto small-muted"><?= e(data_hora_br($v['gerado_em'])) ?> · <?= e($v['por']) ?></span></li><?php endforeach; ?>
        </ul></div>
    </div>
</div>
