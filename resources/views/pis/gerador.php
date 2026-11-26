<?php
/** Nova PI / Gerador de PI. O comportamento fica em public/assets/js/gerador.js; o servidor calcula e confere. */
$b = $boot;
$sel = fn (string $lista, string $campo, bool $req = false, string $extra = '') => '<select class="form-select" data-f="' . e($campo) . '" id="f_' . e($campo) . '" data-list="' . e($lista) . '"' . ($req ? ' required' : '') . $extra . '></select>';
$inp = fn (string $campo, string $type = 'text', string $extra = '') => '<input class="form-control" data-f="' . e($campo) . '" id="f_' . e($campo) . '" type="' . e($type) . '"' . $extra . '>';
$lab = fn (string $campo, string $txt, bool $req = false) => '<label class="form-label" for="f_' . e($campo) . '">' . e($txt) . ($req ? ' <span class="req">*</span>' : '') . '</label>';
$modoTxt = ['nova' => '', 'edicao' => 'Alterando a PI ' . ($b['piNumero'] ?? '') . ': ao gerar de novo, o número é mantido (RN-05).', 'correcao' => 'Corrigindo a PI devolvida ' . ($b['piNumero'] ?? '') . ': ao gerar, ela volta para a Conferência como Corrigida, com o mesmo número (RN-53).',
    'renovacao' => 'Renovação: PI nova, com número novo, período do mês seguinte e grade vazia (RN-49).', 'substituicao' => 'Cancela/substitui: a PI de origem só vira Substituída quando esta for gerada (RN-56).'][$b['modo']] ?? '';
?>
<script type="application/json" id="boot"><?= json_encode($b, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

<div class="page-head">
    <div>
        <h1 id="tituloPi"><?= e($titulo) ?></h1>
        <p>Preencha as etapas. O sistema calcula os valores e confere tudo na hora; o número da PI sai ao <b>Gerar PI</b>. <span id="salvoEm" class="small-muted"></span></p>
    </div>
    <div class="actions">
        <?php if ($meus): ?>
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-folder2-open"></i> Em montagem (<?= count($meus) ?>)</button>
            <div class="dropdown-menu dropdown-menu-end p-0" style="width:360px;max-height:60vh;overflow:auto">
                <?php foreach ($meus as $m): ?>
                    <a class="dropdown-item py-2 border-bottom<?= (int) ($b['rascunho'] ?? 0) === (int) $m['id'] ? ' active' : '' ?>" href="<?= e(url('/pis/nova', ['rascunho' => $m['id']])) ?>" style="white-space:normal">
                        <b><?= e($m['cliente_nome'] ?: 'Sem cliente') ?></b> <?= $m['numero'] ? '· ' . e($m['numero']) : '' ?><div class="small-muted"><?= e(trim(($m['veiculo'] ?? '—') . ' · ' . ($m['titulo'] ?? ''), ' ·')) ?> · <?= e(data_hora_br($m['updated_at'])) ?></div></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <a class="btn btn-light" href="<?= e(url('/pis/nova')) ?>"><i class="bi bi-file-earmark"></i> Em branco</a>
        <?php if ($b['rascunho']): ?>
            <form method="post" action="<?= e(url('/pis/rascunhos/' . $b['rascunho'] . '/excluir')) ?>" data-confirm="Descartar esta PI em montagem? O que foi preenchido será perdido (PIs já geradas não são afetadas)." data-confirm-button="Descartar"><?= csrf_field() ?>
                <button class="btn btn-light text-danger" type="submit"><i class="bi bi-trash"></i> Descartar</button></form>
        <?php endif; ?>
    </div>
</div>
<?php if ($modoTxt): ?><div class="alert alert-info"><i class="bi bi-info-circle"></i><div><?= e($modoTxt) ?></div></div><?php endif; ?>
<div id="avisoDevolucao"></div>

<div class="row g-3" id="gerador">
    <div class="col-xl-8 order-2 order-xl-1">
        <nav class="gm-steps mb-3" aria-label="Etapas da PI" id="passos"></nav>
        <form id="form" autocomplete="off" novalidate>

            <section class="form-section" id="s1"><div class="form-section-head"><span class="step">1</span><h2>Identificação</h2><p>O veículo define empresa, tabelas e blocos de mídia</p></div>
                <div class="form-section-body">
                    <label class="form-label">Veículo <span class="req">*</span></label>
                    <div class="gm-veiculos mb-3" id="veicBtns" role="radiogroup" aria-label="Veículo"></div>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label" for="tabSel">Tabela / praça <span class="req">*</span></label><select class="form-select" id="tabSel"></select></div>
                        <div class="col-md-4"><?= $lab('tipo', 'Tipo de PI', true) . $sel('Tipo de PI', 'tipo', true) ?></div>
                        <div class="col-md-4"><?= $lab('origem', 'PI de origem') . $inp('origem', 'text', ' placeholder="obrigatória em renovação, alteração…"') ?></div>
                        <div class="col-md-3"><?= $lab('emissao', 'Emissão', true) . $inp('emissao', 'date') ?></div>
                        <div class="col-md-3"><label class="form-label" for="numIn">Nº da PI</label><input class="form-control fw-bold" id="numIn" readonly placeholder="gerado ao gerar a PI"></div>
                        <div class="col-md-2"><?= $lab('rp', 'Nº do RP') . $inp('rp') ?></div>
                        <div class="col-md-4"><label class="form-label" for="emissora">Empresa emissora</label><input class="form-control" id="emissora" readonly></div>
                    </div>
                </div></section>

            <section class="form-section" id="s2"><div class="form-section-head"><span class="step">2</span><h2>Comercial</h2><p>Dados do executivo vêm do cadastro (RN-76)</p></div>
                <div class="form-section-body"><div class="row g-3">
                    <div class="col-md-5"><label class="form-label" for="f_exec">Executivo de contas <span class="req">*</span></label>
                        <?php if ($b['executivos']): ?><select class="form-select" id="selExec" aria-label="Executivo da PI"></select><?php else: ?><input class="form-control" id="f_exec" data-f="exec" readonly><?php endif; ?></div>
                    <div class="col-md-2"><?= $lab('mat', 'Matrícula', true) . $inp('mat', 'text', ' readonly') ?></div>
                    <div class="col-md-5"><?= $lab('gestor', 'Gestor comercial', true) . $inp('gestor', 'text', ' readonly') ?></div>
                    <div class="col-md-7"><?= $lab('ccom', 'Contato comercial') . $inp('ccom', 'text', ' readonly') ?></div>
                    <div class="col-md-5"><?= $lab('venda', 'Venda', true) . $sel('Venda', 'venda', true) ?></div>
                </div></div></section>

            <section class="form-section" id="s3"><div class="form-section-head"><span class="step">3</span><h2>Cliente e agência</h2><p>Busque no cadastro ou preencha: ao gerar, o cadastro é atualizado</p></div>
                <div class="form-section-body" id="blocoCliente">
                    <div class="mb-3"><label class="form-label" for="cliBusca"><i class="bi bi-search"></i> Cliente cadastrado</label>
                        <input class="form-control" id="cliBusca" data-autocomplete="/api/clientes" data-ac-fill="#blocoCliente" placeholder="Digite razão social, nome fantasia, CNPJ/CPF ou código"></div>
                    <div class="row g-3">
                        <div class="col-md-8"><?= $lab('cli_razao', 'Razão social', true) . $inp('cli_razao', 'text', ' data-fill="cli_razao" maxlength="160"') ?></div>
                        <div class="col-md-4"><?= $lab('cli_cnpj', 'CNPJ / CPF', true) . $inp('cli_cnpj', 'text', ' data-fill="cli_cnpj" data-mask="doc"') ?></div>
                        <div class="col-md-5"><?= $lab('cli_fant', 'Nome fantasia') . $inp('cli_fant', 'text', ' data-fill="cli_fant"') ?></div>
                        <div class="col-md-4"><?= $lab('cli_ie', 'Insc. estadual') . $inp('cli_ie', 'text', ' data-fill="cli_ie"') ?></div>
                        <div class="col-md-3"><?= $lab('cli_cod', 'Cód. cliente') . $inp('cli_cod', 'text', ' data-fill="cli_cod"') ?></div>
                        <div class="col-md-6"><?= $lab('cli_end', 'Endereço', true) . $inp('cli_end', 'text', ' data-fill="cli_end"') ?></div>
                        <div class="col-md-3"><?= $lab('cli_bairro', 'Bairro') . $inp('cli_bairro', 'text', ' data-fill="cli_bairro"') ?></div>
                        <div class="col-md-3"><?= $lab('cli_cep', 'CEP') . $inp('cli_cep', 'text', ' data-fill="cli_cep" data-mask="cep"') ?></div>
                        <div class="col-md-5"><?= $lab('cli_cid', 'Cidade', true) . $inp('cli_cid', 'text', ' data-fill="cli_cid"') ?></div>
                        <div class="col-md-2"><?= $lab('cli_uf', 'UF', true) ?><select class="form-select" data-f="cli_uf" id="f_cli_uf" data-fill="cli_uf"><?php foreach (\App\Helpers\Form::ufs() as $uf): ?><option><?= e($uf) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-5"><?= $lab('cli_contato', 'Contato no cliente', true) . $inp('cli_contato', 'text', ' data-fill="cli_contato"') ?></div>
                        <div class="col-md-5"><?= $lab('cli_tel', 'Telefones', true) . $inp('cli_tel', 'text', ' data-fill="cli_tel" data-mask="telefone"') ?></div>
                        <div class="col-md-7"><?= $lab('cli_email', 'E-mail de faturamento', true) . $inp('cli_email', 'email', ' data-fill="cli_email"') ?></div>
                    </div>
                    <div id="agBox" class="mt-4 pt-3 border-top" hidden>
                        <h3 class="mb-2"><i class="bi bi-briefcase"></i> Agência</h3>
                        <div class="mb-3" id="blocoAgencia"><label class="form-label" for="agBusca"><i class="bi bi-search"></i> Agência cadastrada</label>
                            <input class="form-control" id="agBusca" data-autocomplete="/api/agencias" data-ac-fill="#agBox" placeholder="Razão social ou CNPJ da agência"></div>
                        <div class="row g-3">
                            <div class="col-md-6"><?= $lab('ag_nome', 'Agência', true) . $inp('ag_nome', 'text', ' data-fill="ag_nome"') ?></div>
                            <div class="col-md-4"><?= $lab('ag_cnpj', 'CNPJ', true) . $inp('ag_cnpj', 'text', ' data-fill="ag_cnpj" data-mask="cnpj"') ?></div>
                            <div class="col-md-2"><?= $lab('ag_com', 'Comissão %', true) . $inp('ag_com', 'number', ' data-fill="ag_com" min="0" max="100" step="0.01"') ?></div>
                            <div class="col-md-4"><?= $lab('ag_carta', 'Carta de credenciamento', true) . $inp('ag_carta', 'text', ' data-fill="ag_carta"') ?></div>
                            <div class="col-md-3"><?= $lab('ag_val', 'Validade da carta') . $inp('ag_val', 'date', ' data-fill="ag_val"') ?></div>
                            <div class="col-md-5"><?= $lab('ag_end', 'Endereço') . $inp('ag_end', 'text', ' data-fill="ag_end"') ?></div>
                            <div class="col-md-4"><?= $lab('ag_cid', 'Cidade / UF') . $inp('ag_cid', 'text', ' data-fill="ag_cid"') ?></div>
                            <div class="col-md-3"><?= $lab('ag_tel', 'Telefone') . $inp('ag_tel', 'text', ' data-fill="ag_tel" data-mask="telefone"') ?></div>
                            <div class="col-md-5"><?= $lab('ag_contato', 'Contato e e-mail') . $inp('ag_contato', 'text', ' data-fill="ag_contato"') ?></div>
                        </div>
                    </div>
                </div></section>

            <section class="form-section" id="s4"><div class="form-section-head"><span class="step">4</span><h2>Campanha</h2><p>Na TV, período em mais de um mês gera uma PI por mês</p></div>
                <div class="form-section-body"><div class="row g-3">
                    <div class="col-md-6"><?= $lab('titulo', 'Título da campanha', true) . $inp('titulo', 'text', ' maxlength="160"') ?></div>
                    <div class="col-md-6"><?= $lab('produto', 'Produto / projeto', true) . $inp('produto', 'text', ' maxlength="160"') ?></div>
                    <div class="col-md-3"><?= $lab('ini', 'Início', true) . $inp('ini', 'date') ?></div>
                    <div class="col-md-3"><?= $lab('fim', 'Fim', true) . $inp('fim', 'date') ?></div>
                    <div class="col-md-2"><?= $lab('prazo', 'Prazo (dias)') . $inp('prazo', 'number', ' min="0"') ?></div>
                    <div class="col-md-4"><?= $lab('pcob', 'Praça de cobrança', true) . $sel('Praça de cobrança', 'pcob', true) ?></div>
                </div><div id="perNote" class="mt-3"></div></div></section>

            <section class="form-section" id="s5"><div class="form-section-head"><span class="step">5</span><h2>Mídia</h2><p>Digite a sigla ou o nome; preço e dias vêm da tabela</p></div>
                <div class="form-section-body"><div id="espWarn"></div><div id="midia"><div class="empty-state">Escolha o veículo na etapa 1 para ver os campos de mídia.</div></div></div></section>

            <section class="form-section" id="s6"><div class="form-section-head"><span class="step">6</span><h2>Entregas especiais</h2><p>Post, app, promoção, evento: briefing ao Marketing obrigatório</p></div>
                <div class="form-section-body"><div id="entregas"></div><button class="btn btn-light btn-sm" type="button" data-add="ent"><i class="bi bi-plus"></i> Entrega especial</button></div></section>

            <section class="form-section" id="s7"><div class="form-section-head"><span class="step">7</span><h2>Financeiro</h2><p>A soma das parcelas tem de ser igual ao líquido</p></div>
                <div class="form-section-body"><div class="row g-3 mb-3">
                    <div class="col-md-4"><?= $lab('forma', 'Forma de pagamento', true) . $sel('Forma de pagamento', 'forma', true) ?></div>
                    <div class="col-md-3"><?= $lab('producao', 'Produção / taxas (R$)') . $inp('producao', 'number', ' min="0" step="0.01"') ?></div>
                </div>
                    <div class="gm-totais mb-3" id="totais"></div>
                    <h3 class="mb-2">Parcelas</h3>
                    <div id="parcs"></div>
                    <div class="d-flex flex-wrap gap-2 align-items-end mt-2">
                        <button class="btn btn-light btn-sm" type="button" id="pAdd"><i class="bi bi-plus"></i> Parcela</button>
                        <span class="small-muted ms-2">ou dividir o líquido em</span>
                        <input class="form-control form-control-sm" style="width:70px" type="number" min="1" max="12" value="1" id="pN" aria-label="Número de parcelas">
                        <span class="small-muted">parcela(s) mensais a partir de</span>
                        <input class="form-control form-control-sm" style="width:160px" type="date" id="pD" aria-label="Primeiro vencimento">
                        <button class="btn btn-outline-primary btn-sm" type="button" id="pGo">Gerar parcelas</button>
                    </div>
                    <div class="mt-3"><?= $lab('obs', 'Observações (saem na PI)') ?><textarea class="form-control" data-f="obs" id="f_obs" rows="2" maxlength="1000"></textarea></div>
                </div></section>

            <section class="form-section" id="s8"><div class="form-section-head"><span class="step">8</span><h2>Aprovações</h2><p>Aceite do cliente e aprovação do gestor (desconto, bonificação, preço manual)</p></div>
                <div class="form-section-body"><div class="row g-3">
                    <div class="col-md-6"><?= $lab('ac_nome', 'Aceite do cliente: nome', true) . $inp('ac_nome', 'text', ' maxlength="120"') ?></div>
                    <div class="col-md-3"><?= $lab('ac_data', 'Data do aceite', true) . $inp('ac_data', 'date') ?></div>
                    <div class="col-12" id="aprovBox"></div>
                </div></div></section>
        </form>
    </div>

    <div class="col-xl-4 order-1 order-xl-2">
        <aside class="gm-resumo card" aria-label="Resumo da PI">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-1"><span class="small-muted" id="rVeic">Escolha o veículo</span><span class="ms-auto" id="rNum"></span></div>
                <div class="gm-valor" id="rNeg">R$ 0,00</div><div class="small-muted mb-2">valor negociado</div>
                <dl class="gm-linhas" id="rTot"></dl>
                <div class="gm-pend mt-3" id="rPend"></div>
                <div class="d-grid gap-2 mt-3">
                    <button class="btn btn-danger btn-lg" type="button" id="bGerar"><i class="bi bi-file-earmark-check"></i> Gerar PI</button>
                    <?php if ($b['podeReservar']): ?><button class="btn btn-outline-primary" type="button" id="bRes"><i class="bi bi-hourglass-split"></i> Reservar por 24h</button><?php endif; ?>
                </div>
                <div class="small-muted mt-2" id="rMsg"></div>
            </div>
        </aside>
    </div>
</div>

<div class="modal fade" id="mGerada" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="mGeradaTit">PI gerada</h5></div>
    <div class="modal-body" id="mGeradaBody"></div>
    <div class="modal-footer"><a class="btn btn-light" href="<?= e(url('/pis/nova')) ?>">Nova PI</a><a class="btn btn-primary" id="mGeradaVer" href="#">Ver a PI</a></div>
</div></div></div>
