/* =====================================================================
   Gerador de PI — tela em 8 etapas.
   O estado do formulário (S) tem a mesma estrutura do Gerador original. Todo cálculo, conferência,
   numeração e gravação acontecem no servidor (/api/pis/calcular e /api/pis/gerar); aqui só se monta a tela,
   a grade de dias e o salvamento automático do rascunho.
   ===================================================================== */
(function () {
    'use strict';
    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => [...el.querySelectorAll(s)];
    const { api, toast, confirmar, esc } = window.CRM;
    const B = JSON.parse($('#boot').textContent);

    let S = B.estado || {};
    ['ins', 'pat', 'dig', 'avi', 'prj', 'ent', 'parc'].forEach(k => { if (!Array.isArray(S[k])) S[k] = []; });
    S.f = S.f && typeof S.f === 'object' && !Array.isArray(S.f) ? S.f : {};
    S.ins.forEach(l => { if (!l.gm || Array.isArray(l.gm)) l.gm = {}; });
    let R = null, CAT = null, CUR = null, RID = B.rascunho || null, catKey = '';
    let tCalc = null, tSave = null, ctrl = null, salvando = false, gerada = false;

    /* ---------- utilidades de data (UTC, como no Gerador original) ---------- */
    const MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    const MES3 = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    const WD = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
    const brl = n => (+n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const num = (n, c = 0) => (+n || 0).toLocaleString('pt-BR', { minimumFractionDigits: c, maximumFractionDigits: c });
    const up = s => String(s || '').split('—')[0].trim().toUpperCase();
    const norm = s => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    const dParse = s => { if (!s) return null; const [y, m, d] = String(s).split('-').map(Number); return (y && m && d) ? new Date(Date.UTC(y, m - 1, d)) : null; };
    const dBR = s => { const d = dParse(s); return d ? d.toISOString().slice(0, 10).split('-').reverse().join('/') : ''; };
    function monthObj(key) { const [y, m] = key.split('-').map(Number); const days = new Date(Date.UTC(y, m, 0)).getUTCDate(); return { key, y, m: m - 1, days, label: MESES[m - 1] + ' / ' + y, short: MES3[m - 1] + '/' + String(y).slice(2) }; }
    function monthsOf() {
        const a = dParse(S.f.ini), b = dParse(S.f.fim) || a;
        if (!a) return [];
        const out = []; let y = a.getUTCFullYear(), m = a.getUTCMonth();
        while ((y < b.getUTCFullYear() || (y === b.getUTCFullYear() && m <= b.getUTCMonth())) && out.length < 12) { out.push(monthObj(`${y}-${String(m + 1).padStart(2, '0')}`)); if (++m > 11) { m = 0; y++; } }
        return out.length ? out : [monthObj(S.f.ini.slice(0, 7))];
    }
    function dayInfo(M, d) {
        if (!M || d > M.days) return { exists: false, ok: false };
        const dt = new Date(Date.UTC(M.y, M.m, d)), ini = dParse(S.f.ini), fim = dParse(S.f.fim), wd = dt.getUTCDay();
        return { exists: true, ok: (!ini || dt >= ini) && (!fim || dt <= fim), wd, letter: WD[wd], weekend: wd === 0 || wd === 6 };
    }
    function monthRange(M) { let a = 0, b = 0; for (let d = 1; d <= M.days; d++) if (dayInfo(M, d).ok) { if (!a) a = d; b = d; } return a ? `${a} a ${b}` : 'nenhum dia'; }

    /* ---------- catálogo do veículo ---------- */
    const V = () => B.veiculos.find(v => +v.id === +S.f.veic) || null;
    const prog = s => CAT && up(s) ? CAT.programas.find(p => p.s === up(s)) || null : null;
    const fmtD = (s, legal) => CAT && up(s) ? CAT.formatos.find(f => f.s === up(s) && f.legal === legal) || null : null;
    function resolveSig(bloco, raw) {
        const s = up(raw);
        const lista = bloco === 'dig' ? CAT.formatos.filter(f => !f.legal) : bloco === 'avi' ? CAT.formatos.filter(f => f.legal) : CAT.programas;
        if (lista.some(x => x.s === s)) return s;
        const n = norm(raw);
        const hit = lista.find(x => norm(x.n) === n) || (n.length >= 4 ? (h => h.length === 1 ? h[0] : null)(lista.filter(x => norm(x.n).includes(n))) : null);
        return hit ? hit.s : s;
    }
    async function loadCatalog(force) {
        if (!S.f.veic) { CAT = null; return; }
        const key = S.f.veic + '|' + (S.f.emissao || '').slice(0, 4) + '|' + (S.f.res || '');
        if (!force && key === catKey && CAT) return;
        catKey = key;
        CAT = await api(`/api/catalogo?veiculo=${encodeURIComponent(S.f.veic)}&emissao=${encodeURIComponent(S.f.emissao || '')}&reserva=${encodeURIComponent(S.f.res || '')}`);
    }

    /* ---------- campos fixos ---------- */
    function fillLists() {
        $$('select[data-list]').forEach(s => {
            const vals = B.listas[s.dataset.list] || [];
            s.innerHTML = '<option value="">—</option>' + vals.map(v => `<option>${esc(v)}</option>`).join('');
        });
        $('#veicBtns').innerHTML = B.veiculos.map(v => `<button type="button" role="radio" data-v="${v.id}" aria-checked="false">${esc(v.nome)}<span>${esc(v.tipo)}${+v.divide ? ' · 1 PI por mês' : ''}</span></button>`).join('');
        const sx = $('#selExec');
        if (sx) sx.innerHTML = B.executivos.map(u => `<option value="${u.id}">${esc(u.nome)}</option>`).join('');
    }
    function loadFields() {
        $$('#form [data-f]').forEach(el => { const v = S.f[el.dataset.f]; if (document.activeElement !== el) el.value = v ?? ''; });
        $$('#veicBtns button').forEach(b => { const on = +b.dataset.v === +S.f.veic; b.classList.toggle('on', on); b.setAttribute('aria-checked', String(on)); });
        const sx = $('#selExec');
        if (sx) sx.value = String(S.f.executivo_id || '');
        $('#agBox').hidden = S.f.venda !== 'Agência';
        $('#numIn').value = S.f.num || Object.values(S.f.nums || {}).filter(Boolean).join(' + ') || '';
        if (!$('#pD').value) $('#pD').value = S.f.fim || '';
    }
    function renderTab() {
        const sel = $('#tabSel');
        if (!CAT) { sel.innerHTML = '<option value="">Escolha o veículo</option>'; $('#emissora').value = ''; return; }
        const ts = CAT.tabelas;
        if (!ts.some(t => t.c === S.f.tab)) S.f.tab = ts.length === 1 ? ts[0].c : ((ts.find(t => t.c === 'LOC' || t.c === 'MA1') || {}).c || '');
        sel.innerHTML = '<option value="">Escolha</option>' + ts.map(t => `<option value="${esc(t.c)}">${esc(t.c)} · ${esc(t.d)}${t.sem ? ' (sem tabela: preço manual)' : ''}</option>`).join('');
        sel.value = S.f.tab || '';
        $('#emissora').value = CAT.veiculo.emissora;
    }

    /* ---------- mídia ---------- */
    const opts = (list, sel, labels) => '<option value="">—</option>' + list.map((v, i) => `<option value="${esc(v)}"${String(v) === String(sel ?? '') ? ' selected' : ''}>${esc(labels ? labels[i] : v)}</option>`).join('');
    const inp = (b, i, k, v, a = '') => `<input class="form-control form-control-sm" data-l="${b}|${i}|${k}" value="${esc(v ?? '')}" ${a}>`;
    const fld = (cls, lab, html) => `<div class="${cls}"><label class="form-label small mb-1">${lab}</label>${html}</div>`;
    const delBtn = (b, i) => `<div class="col-auto d-flex align-items-end"><button class="btn btn-light btn-sm text-danger" type="button" data-del="${b}|${i}" aria-label="Remover linha"><i class="bi bi-x-lg"></i></button></div>`;
    const sigDisp = (s, item) => s ? (item ? `${s} — ${item.n}` : s) : '';
    const sigInput = (b, i, l, list, item) => `<input class="form-control form-control-sm fw-semibold" data-l="${b}|${i}|s" list="${list}" value="${esc(sigDisp(l.s, item))}" placeholder="sigla ou nome">`;

    function renderMidia() {
        const v = V(), m = $('#midia');
        if (!v || !CAT) { m.innerHTML = '<div class="empty-state">Escolha o veículo na etapa 1 para ver os campos de mídia.</div>'; $('#perNote').innerHTML = ''; return; }
        const months = monthsOf(), isTV = v.tipo === 'TV', multi = months.length > 1;
        if (!months.some(M => M.key === CUR)) CUR = months[0] ? months[0].key : null;
        const M = CUR ? monthObj(CUR) : null;
        $('#perNote').innerHTML = !multi ? '' : (isTV
            ? `<div class="alert alert-info mb-0"><i class="bi bi-calendar3"></i><div>Período em ${months.length} meses: a TV gera <b>${months.length} PIs</b>, uma por mês (${months.map(x => esc(x.short) + ': dias ' + monthRange(x)).join(' · ')}), cada uma com sua grade, número e parcela (RN-17).</div></div>`
            : (+v.ins || +v.pat ? `<div class="alert alert-info mb-0"><i class="bi bi-calendar3"></i><div>Período em ${months.length} meses: sai <b>uma PI</b>. Na grade, troque o mês pelas abas; no patrocínio, escolha o mês de cada linha (RN-18).</div></div>`
                : `<div class="alert alert-info mb-0"><i class="bi bi-calendar3"></i><div>Período corrido em uma PI: o valor mensal é proporcional aos dias de cada mês (RN-19, RN-27).</div></div>`));
        let h = `<datalist id="dlP">${CAT.programas.map(p => `<option value="${esc(p.s + ' — ' + p.n)}">${esc([p.d, p.f].filter(Boolean).join(' '))}</option>`).join('')}</datalist>`;
        h += `<datalist id="dlD">${CAT.formatos.filter(f => !f.legal).map(f => `<option value="${esc(f.s + ' — ' + f.n)}">${esc(f.mod + (f.p != null ? ' · R$ ' + brl(f.p) : ' · sob consulta'))}</option>`).join('')}</datalist>`;
        h += `<datalist id="dlA">${CAT.formatos.filter(f => f.legal).map(f => `<option value="${esc(f.s + ' — ' + f.n)}">${esc('R$ ' + brl(f.p) + ' por cm/col')}</option>`).join('')}</datalist>`;
        const DURS = CAT.duracoes, COB = B.listas['Tipo de cobrança (rádio / TV)'] || [], COBD = B.listas['Cobrança (digital)'] || [];

        if (+v.ins) {
            h += `<div class="gm-sub">Compra de inserções <small>${esc(dBR(S.f.ini) || '?')} a ${esc(dBR(S.f.fim) || '?')} · uma linha por programa e duração</small></div>`;
            if (!months.length) h += `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i><div>Informe início e fim da campanha (etapa 4) para montar a grade.</div></div>`;
            if (months.length > 1) h += `<div class="gm-mtabs">${months.map(x => `<button type="button" data-mk="${x.key}" class="${x.key === CUR ? 'on' : ''}">${esc(x.label)} · dias ${monthRange(x)}</button>`).join('')}</div>`;
            if (months.length) h += `<div class="gm-legend"><span><i style="background:#F3F8FF"></i>disponível</span><span><i style="background:#FCEDE3"></i>fim de semana</span><span><i style="background:repeating-linear-gradient(135deg,#EEF0F3 0 4px,transparent 4px 8px)"></i>programa não vai ao ar</span><span><i style="background:#EEF0F3"></i>fora do período</span><span><i style="background:#FDECEC;border-color:#C00000"></i>inserção inválida</span></div>`;
            if (!S.ins.length) S.ins.push({ gm: {} });
            S.ins.forEach((l, i) => {
                const p = prog(l.s), air = p ? p.dias : null, g = (l.gm || {})[CUR] || {};
                h += `<div class="gm-line" id="li${i}"><div class="row g-2 align-items-end">
                    ${fld('col-md-5', 'Programa (sigla — nome)', sigInput('ins', i, l, 'dlP', p))}
                    ${fld('col-4 col-md-1', 'Dur (")', `<select class="form-select form-select-sm" data-l="ins|${i}|dur">${opts(DURS, l.dur)}</select>`)}
                    ${fld('col-8 col-md-2', 'Cobrança', `<select class="form-select form-select-sm" data-l="ins|${i}|cob">${opts(COB, l.cob || 'Avulsa')}</select>`)}
                    ${fld('col-4 col-md-1', 'Desc. %', inp('ins', i, 'desc', l.desc, 'type="number" min="0" max="100" step="0.5"'))}
                    ${fld('col-6 col-md-2', 'Preço manual', inp('ins', i, 'man', l.man, 'type="number" min="0" step="0.01" placeholder="tabela"'))}
                    ${delBtn('ins', i)}</div>
                    <div class="info" id="ii${i}"></div>`;
                if (M) {
                    h += `<div class="gm-days">${Array.from({ length: 31 }, (_, k) => { const d = k + 1, di = dayInfo(M, d);
                        const c = !di.exists ? 'no' : !di.ok ? 'out' : (air && !air.includes(di.wd)) ? 'nx' : di.weekend ? 'we' : '';
                        const bad = g[d] && (c === 'out' || c === 'nx') ? ' bad' : '';
                        const off = c === 'nx'; // RN-23: dia em que o programa não vai ao ar fica desabilitado
                        return `<label class="gm-day ${c}${bad}" id="d${i}_${d}"${off ? ` title="${esc(p.n)} não vai ao ar neste dia (${esc(p.d)})"` : ''}>${di.exists ? di.letter : '&nbsp;'}<input data-g="${i}|${d}" inputmode="numeric" maxlength="2" value="${esc(g[d] || '')}" aria-label="Dia ${d}${off ? ': programa não vai ao ar' : ''}"${di.exists ? '' : ' tabindex="-1"'}${off ? ' disabled' : ''}>${d}</label>`; }).join('')}</div>
                        <div class="gm-fill">Preencher <select class="form-select form-select-sm" data-pat="${i}">${air ? `<option value="${air.join('')}">dias do programa (${esc(p.d)})</option>` : ''}<option value="12345">seg a sex</option><option value="123456">seg a sáb</option><option value="0123456">todos os dias</option><option value="06">sáb e dom</option><option value="135">seg, qua, sex</option><option value="24">ter e qui</option></select>
                        com <input class="form-control form-control-sm" type="number" min="0" max="99" value="1" data-q="${i}" style="width:64px"> por dia
                        <button class="btn btn-light btn-sm" type="button" data-apply="${i}">${months.length > 1 ? 'Aplicar neste mês' : 'Aplicar no período'}</button>
                        ${months.length > 1 ? `<button class="btn btn-light btn-sm" type="button" data-applyall="${i}">Aplicar em todos os meses</button>` : ''}
                        <button class="btn btn-light btn-sm" type="button" data-clear="${i}">Limpar</button></div>`;
                }
                h += '</div>';
            });
            h += `<button class="btn btn-light btn-sm mb-2" type="button" data-add="ins"><i class="bi bi-plus"></i> Programa</button>`;
        }
        if (+v.pat) {
            h += `<div class="gm-sub">Compra de patrocínios <small>esquema de inserções e valor da cota (${S.f.tab === 'NAC' ? 'nacional' : 'local'}) vêm da tabela · RN-26</small></div>`;
            S.pat.forEach((l, i) => {
                const p = prog(l.s);
                h += `<div class="gm-line" id="lp${i}"><div class="row g-2 align-items-end">
                    ${fld('col-md-4', 'Patrocínio (sigla — nome)', sigInput('pat', i, l, 'dlP', p))}
                    ${multi ? fld('col-6 col-md-2', 'Mês', `<select class="form-select form-select-sm" data-l="pat|${i}|mk">${opts(months.map(x => x.key), l.mk, months.map(x => x.short))}</select>`) : ''}
                    ${fld('col-3 col-md-1', 'Cotas', inp('pat', i, 'cot', l.cot, 'type="number" min="1" placeholder="1"'))}
                    ${fld('col-5 col-md-2', 'Cobrança', `<select class="form-select form-select-sm" data-l="pat|${i}|cob">${opts(COB, l.cob || 'Avulsa')}</select>`)}
                    ${fld('col-4 col-md-1', 'Desc. %', inp('pat', i, 'desc', l.desc, 'type="number" min="0" max="100" step="0.5"'))}
                    ${fld('col-6 col-md-2', 'Preço manual', inp('pat', i, 'man', l.man, 'type="number" min="0" step="0.01" placeholder="tabela"'))}
                    ${fld('col-4 col-md-1', 'Outras ins.', inp('pat', i, 'out', l.out, 'type="number" min="0"'))}
                    ${fld('col-md-6', 'Observação', inp('pat', i, 'obs', l.obs))}
                    <div class="col-auto d-flex align-items-end gap-1"><button class="btn btn-light btn-sm" type="button" data-dup="pat|${i}" title="Duplicar para o mês seguinte"><i class="bi bi-copy"></i></button></div>${delBtn('pat', i)}</div>
                    <div class="info" id="pi${i}"></div></div>`;
            });
            h += `<button class="btn btn-light btn-sm mb-2" type="button" data-add="pat"><i class="bi bi-plus"></i> Patrocínio</button>`;
        }
        if (+v.dig) {
            h += `<div class="gm-sub">Patrocínio e mídia digital <small>mensal = proporcional aos dias de cada mês · CPM = por mil impressões</small></div>`;
            S.dig.forEach((l, i) => {
                const d = fmtD(l.s, false), cpm = d && d.mod === 'CPM';
                const qlab = !d ? 'Qtd' : cpm ? 'Impressões' : { Mensal: 'Cotas', 'Diária': 'Diárias', Unidade: 'Quantidade', Pacote: 'Pacotes' }[d.mod] || 'Qtd';
                h += `<div class="gm-line" id="lg${i}"><div class="row g-2 align-items-end">
                    ${fld('col-md-5', 'Formato (sigla — nome)', sigInput('dig', i, l, 'dlD', d))}
                    ${cpm ? fld('col-6 col-md-2', qlab, inp('dig', i, 'ent', l.ent, 'type="number" min="0" step="1000"')) : fld('col-4 col-md-1', qlab, inp('dig', i, 'qtd', l.qtd, 'type="number" min="1" placeholder="1"'))}
                    ${fld('col-6 col-md-2', 'Cobrança', `<select class="form-select form-select-sm" data-l="dig|${i}|cob">${opts(COBD, l.cob || 'Pago')}</select>`)}
                    ${fld('col-4 col-md-1', 'Desc. %', inp('dig', i, 'desc', l.desc, 'type="number" min="0" max="100"'))}
                    ${fld('col-6 col-md-2', cpm ? 'CPM manual' : 'Preço manual', inp('dig', i, 'man', l.man, 'type="number" min="0" step="0.01" placeholder="tabela"'))}
                    ${fld('col-6 col-md-2', 'Início', inp('dig', i, 'ini', l.ini || S.f.ini, 'type="date"'))}${fld('col-6 col-md-2', 'Fim', inp('dig', i, 'fim', l.fim || S.f.fim, 'type="date"'))}
                    ${fld('col-md-6', 'Observação', inp('dig', i, 'obs', l.obs))}${delBtn('dig', i)}</div>
                    <div class="info" id="gi${i}"></div></div>`;
            });
            h += `<button class="btn btn-light btn-sm mb-2" type="button" data-add="dig"><i class="bi bi-plus"></i> Formato digital</button>`;
        }
        if (+v.avi) {
            h += `<div class="gm-sub">Publicidade legal <small>valor = colunas × cm × publicações × R$ por cm/col · RN-31</small></div>`;
            S.avi.forEach((l, i) => {
                const d = fmtD(l.s, true);
                h += `<div class="gm-line" id="la${i}"><div class="row g-2 align-items-end">
                    ${fld('col-md-4', 'Tipo (sigla — nome)', sigInput('avi', i, l, 'dlA', d))}
                    ${fld('col-md-5', 'Material / título', inp('avi', i, 'mat', l.mat))}
                    ${fld('col-md-3', 'Código', inp('avi', i, 'cod', l.cod))}
                    ${fld('col-4 col-md-1', 'Colunas', inp('avi', i, 'col', l.col, 'type="number" min="1" placeholder="2"'))}
                    ${fld('col-4 col-md-1', 'Altura cm', inp('avi', i, 'cm', l.cm, 'type="number" min="0" step="0.5"'))}
                    ${fld('col-4 col-md-1', 'Public.', inp('avi', i, 'pub', l.pub, 'type="number" min="1" placeholder="1"'))}
                    ${fld('col-md-4', 'Datas de publicação', inp('avi', i, 'datas', l.datas))}
                    ${fld('col-4 col-md-1', 'Desc. %', inp('avi', i, 'desc', l.desc, 'type="number" min="0" max="100"'))}
                    ${fld('col-6 col-md-2', 'R$ cm/col manual', inp('avi', i, 'man', l.man, 'type="number" min="0" step="0.01" placeholder="tabela"'))}${delBtn('avi', i)}</div>
                    <div class="info" id="vi${i}"></div></div>`;
            });
            h += `<button class="btn btn-light btn-sm mb-2" type="button" data-add="avi"><i class="bi bi-plus"></i> Publicação legal</button>
                <div class="mt-2"><label class="form-label small" for="f_texto">Texto do anúncio (aprovado pelo cliente)</label><textarea class="form-control" data-f="texto" id="f_texto" rows="3">${esc(S.f.texto || '')}</textarea></div>`;
        }
        // projetos especiais
        const abertos = CAT.projetos;
        h += `<div class="gm-sub">Projetos especiais (cotas) <small>prazo encerrado, cotas esgotadas ou empresa errada bloqueiam a PI (RN-33 a RN-35)</small></div>`;
        if (!abertos.length) h += `<div class="small-muted mb-2">Nenhum projeto especial cadastrado para ${esc(v.nome)}.</div>`;
        else h += `<div class="gm-chips">${abertos.map(P => `<span class="gm-chip${P.aberto ? '' : ' off'}"><b>${esc(P.nome)}</b> · ${P.aberto ? P.cotas.map(c => `${esc(c.nome)} ${c.disponiveis}/${c.total}`).join(' · ') : esc(P.sit)} · até ${esc(dBR(P.ate))}</span>`).join('')}</div>`;
        S.prj.forEach((l, i) => {
            const P = abertos.find(x => +x.id === +l.id);
            const pOpts = '<option value="">— escolha —</option>' + abertos.map(Q => `<option value="${Q.id}"${+Q.id === +l.id ? ' selected' : ''}${!Q.aberto && +Q.id !== +l.id ? ' disabled' : ''}>${esc(Q.nome)} — ${Q.aberto ? Q.disponiveis + ' de ' + Q.total + ' cotas' : esc(Q.sit)}</option>`).join('')
                + (l.id && !P ? `<option value="${esc(l.id)}" selected>Projeto ${esc(l.id)} (indisponível)</option>` : '');
            const cOpts = '<option value="">—</option>' + (P ? P.cotas.map(c => `<option value="${c.id}"${+c.id === +l.cota ? ' selected' : ''}${!c.disponiveis && +c.id !== +l.cota ? ' disabled' : ''}>${esc(c.nome)} · R$ ${brl(c.valor)} · ${c.disponiveis ? c.disponiveis + ' disponível(is)' : 'esgotada'}${c.reservadas ? ' · ' + c.reservadas + ' reservada(s)' : ''}</option>`).join('') : '');
            h += `<div class="gm-line" id="lj${i}"><div class="row g-2 align-items-end">
                ${fld('col-md-4', 'Projeto', `<select class="form-select form-select-sm fw-semibold" data-l="prj|${i}|id">${pOpts}</select>`)}
                ${fld('col-md-3', 'Cota', `<select class="form-select form-select-sm" data-l="prj|${i}|cota">${cOpts}</select>`)}
                ${fld('col-3 col-md-1', 'Qtd', inp('prj', i, 'q', l.q, 'type="number" min="1" placeholder="1"'))}
                ${fld('col-5 col-md-2', 'Cobrança', `<select class="form-select form-select-sm" data-l="prj|${i}|cob">${opts(['Pago', 'Bonificado'], l.cob || 'Pago')}</select>`)}
                ${fld('col-4 col-md-1', 'Desc. %', inp('prj', i, 'desc', l.desc, 'type="number" min="0" max="100" step="0.5"'))}
                ${fld('col-md-6', 'Observação', inp('prj', i, 'obs', l.obs))}${delBtn('prj', i)}</div>
                <div class="info" id="ji${i}"></div></div>`;
        });
        if (abertos.length) h += `<button class="btn btn-light btn-sm" type="button" data-add="prj"><i class="bi bi-plus"></i> Cota de projeto</button>`;
        m.innerHTML = h;
        updateInfos();
    }

    function renderEnt() {
        const L = B.listas;
        $('#entregas').innerHTML = S.ent.length ? S.ent.map((l, i) => `<div class="gm-line"><div class="row g-2 align-items-end">
            ${fld('col-md-3', 'Entrega', `<select class="form-select form-select-sm" data-l="ent|${i}|tipo">${opts(L['Tipo de entrega especial'] || [], l.tipo)}</select>`)}
            ${fld('col-md-4', 'Especificação', inp('ent', i, 'esp', l.esp))}
            ${fld('col-3 col-md-1', 'Qtd', inp('ent', i, 'qtd', l.qtd, 'type="number" min="0"'))}
            ${fld('col-5 col-md-2', 'Área', `<select class="form-select form-select-sm" data-l="ent|${i}|area">${opts(L['Área responsável'] || [], l.area)}</select>`)}
            ${fld('col-4 col-md-2', 'Nº briefing', inp('ent', i, 'brief', l.brief))}
            ${fld('col-md-3', 'Aval do jurídico', `<select class="form-select form-select-sm" data-l="ent|${i}|jur">${opts(L['Aval do jurídico'] || [], l.jur || 'Não se aplica')}</select>`)}${delBtn('ent', i)}</div></div>`).join('')
            : '<div class="small-muted mb-2">Nenhuma entrega especial.</div>';
    }

    function renderParcs() {
        const tv = R && R.splitTV, ms = R ? R.months : [];
        let h = tv ? `<div class="alert alert-info"><i class="bi bi-info-circle"></i><div>TV em ${ms.length} meses: cada PI mensal leva uma parcela com o valor dela; sem data, vence no último dia útil do mês seguinte (RN-20). Para escolher as datas, crie ${ms.length} parcelas, uma por mês, na ordem.</div></div>` : '';
        h += S.parc.map((p, i) => `<div class="row g-2 mb-1 align-items-end"><div class="col-6 col-md-3">${i === 0 ? '<label class="form-label small mb-1">Vencimento</label>' : ''}<input class="form-control form-control-sm" type="date" data-l="parc|${i}|venc" value="${esc(p.venc || '')}" aria-label="Vencimento da parcela ${i + 1}"></div>
            <div class="col-5 col-md-3">${i === 0 ? '<label class="form-label small mb-1">Valor (R$)</label>' : ''}<input class="form-control form-control-sm text-end" type="number" min="0" step="0.01" data-l="parc|${i}|val" value="${esc(p.val ?? '')}" ${tv ? 'disabled placeholder="valor da PI do mês"' : ''} aria-label="Valor da parcela ${i + 1}"></div>
            <div class="col-1"><span class="small-muted">${i + 1}ª${tv && ms[i] ? ' · ' + esc(ms[i].short) : ''}</span></div>${delBtn('parc', i)}</div>`).join('');
        $('#parcs').innerHTML = h || '<div class="small-muted">Nenhuma parcela.</div>';
    }

    /* ---------- retorno do cálculo ---------- */
    function updateInfos() {
        if (!R) return;
        const linhas = {};
        (R.lines || []).forEach(x => linhas[x.li] = x);
        S.ins.forEach((l, i) => {
            const el = $('#ii' + i); if (!el) return;
            const x = linhas[i], box = $('#li' + i);
            if (!x || !l.s) { el.innerHTML = l.s ? '' : '<span>Escolha o programa.</span>'; box && box.classList.remove('erro'); return; }
            const erros = (x.out || []).length || (x.noAir || []).length || x.nome === 'Sigla não cadastrada' || x.semPreco;
            box && box.classList.toggle('erro', !!erros);
            el.innerHTML = [x.nome === 'Sigla não cadastrada' ? '<span class="bad">sigla não cadastrada</span>' : `<span><b>${esc(x.nome)}</b> · ${esc(x.dias || 'dias não informados')}</span>`,
                x.idx != null ? `<span>índice <b>${String(x.idx).replace('.', ',')}</b></span>` : '',
                `<span>unitário ${x.unit != null ? '<b>R$ ' + brl(x.unit) + '</b>' : '<span class="bad">sem preço' + (l.dur ? ' — informe o preço manual' : ' — escolha a duração') + '</span>'}</span>`,
                `<span><b>${num(x.n)}</b> inserção(ões)${R.multi ? ' (' + Object.entries(x.per || {}).filter(([, n]) => n).map(([k, n]) => monthObj(k).short + ': ' + n).join(' · ') + ')' : ''}</span>`,
                `<span>total <b>R$ ${brl(x.total)}</b></span>`,
                (x.out || []).length ? `<span class="bad">fora do período: ${esc(x.out.join(', '))}</span>` : '',
                (x.noAir || []).length ? `<span class="bad">dia sem exibição: ${esc(x.noAir.join(', '))}</span>` : ''].join('');
        });
        (R.pat || []).forEach(x => { const el = $('#pi' + x.li); if (!el) return;
            el.innerHTML = x.nome === 'Sigla não cadastrada' ? '<span class="bad">sigla não cadastrada</span>' : `<span><b>${esc(x.nome)}</b>${x.mes ? ' · ' + esc(x.mes) : ''}</span><span>esquema ${x.esq.map((n, k) => n ? n + ' ' + ['30"', '60"', 'vinh.', 'flash', 'test.', 'entrev.'][k] : '').filter(Boolean).join(' + ') || '—'}</span><span><b>${num(x.n)}</b> inserções</span><span>${x.valor != null ? 'cota R$ ' + brl(x.valor / x.cot) + ' × ' + x.cot : '<span class="bad">sem preço de cota</span>'}</span><span>total <b>R$ ${brl(x.total)}</b></span>`; });
        (R.dig || []).forEach(x => { const el = $('#gi' + x.li); if (!el) return;
            el.innerHTML = x.nome === 'Sigla não cadastrada' ? '<span class="bad">sigla não cadastrada</span>' : `<span><b>${esc(x.nome)}</b> · ${esc(x.mod)}</span>${x.mod === 'Mensal' ? `<span>${num(x.dias)} dia(s) · ${esc((x.mdet || []).join(' + '))} = ${num(x.meses, 4)} mês</span>` : ''}<span>${x.unit != null ? (x.mod === 'CPM' ? 'CPM R$ ' : 'R$ ') + brl(x.unit) : '<span class="bad">sob consulta — informe o preço manual</span>'}</span>${x.cpmSemEnt ? '<span class="bad">informe as impressões</span>' : ''}<span>tabela R$ ${brl(x.tab)} · total <b>R$ ${brl(x.total)}</b></span>`; });
        (R.avi || []).forEach(x => { const el = $('#vi' + x.li); if (!el) return;
            el.innerHTML = `<span><b>${esc(x.nome || 'escolha o tipo')}</b></span><span>${num(x.cmcol, 1)} cm/col × ${x.pub} publicação(ões) × ${x.unit != null ? 'R$ ' + brl(x.unit) : '<span class="bad">sem preço</span>'}</span><span>total <b>R$ ${brl(x.total)}</b></span>`; });
        (R.prj || []).forEach(x => { const el = $('#ji' + x.li); if (!el) return;
            const p = [x.venc ? 'prazo de venda encerrado' : '', x.excede ? 'quantidade maior que as cotas disponíveis (' + x.disp + ')' : '', x.outroVeic ? 'projeto não vale para esta empresa' : ''].filter(Boolean);
            el.innerHTML = `<span><b>${esc(x.nome)}</b>${x.cota ? ' · ' + esc(x.cota) : ''}</span>${x.unit != null ? `<span>${x.q} × R$ ${brl(x.unit)}</span>` : ''}<span>${x.disp} disponível(is)</span><span>total <b>R$ ${brl(x.total)}</b></span>${p.map(t => `<span class="bad">${esc(t)}</span>`).join('')}`;
            const box = $('#lj' + x.li); box && box.classList.toggle('erro', p.length > 0); });
        // grade: marca dias inválidos com inserção
        const M = CUR ? monthObj(CUR) : null;
        if (M) S.ins.forEach((l, i) => { const p = prog(l.s), air = p ? p.dias : null, g = (l.gm || {})[CUR] || {};
            for (let d = 1; d <= 31; d++) { const c = $(`#d${i}_${d}`); if (!c) continue; const di = dayInfo(M, d);
                c.classList.toggle('bad', !!(+g[d] && di.exists && (!di.ok || (air && !air.includes(di.wd))))); } });
        // avisos de reserva de outros vendedores (RN-68)
        const mine = S.f.res ? `<div class="gm-resbar"><i class="bi bi-hourglass-split"></i> Esta PI está <b>reservada</b>. Gere a PI antes de a reserva expirar para manter o espaço e as cotas.</div>` : '';
        $('#espWarn').innerHTML = mine + ((R.conflitos || []).length ? `<div class="gm-resbar"><b><i class="bi bi-exclamation-triangle"></i> Espaço reservado por outro vendedor:</b> ${R.conflitos.map(c => `${esc(c.sigla)} (${esc(c.onde)}) — ${esc(c.executivo)} para ${esc(c.cliente)}, até ${esc(c.ate.slice(8, 10) + '/' + c.ate.slice(5, 7) + ' ' + c.ate.slice(11, 16))}`).join(' · ')}. Combine com o colega ou com o gestor antes de vender.</div>` : '');
    }

    const PASSOS = [['s1', 'Identificação'], ['s2', 'Comercial'], ['s3', 'Cliente'], ['s4', 'Campanha'], ['s5', 'Mídia'], ['s6', 'Entregas'], ['s7', 'Financeiro'], ['s8', 'Aprovações']];
    const goOf = c => c.code === 'D06' || c.code === 'D10' ? 's8' : c.code === 'D07' ? 's7' : (c.code === 'D09' || c.code === 'D13') ? 's6' : c.go;

    function renderResumo() {
        const v = V();
        $('#rVeic').textContent = v ? v.nome + (S.f.tab ? ' · ' + S.f.tab : '') : 'Escolha o veículo';
        const nums = S.f.num || Object.values(S.f.nums || {}).filter(Boolean).join(' + ');
        $('#rNum').innerHTML = nums ? `<span class="badge text-bg-light border">${esc(nums)}</span>` : '<span class="small-muted">sem número</span>';
        if (!R) return;
        const T = R.T;
        $('#rNeg').textContent = 'R$ ' + brl(T.neg);
        const linhas = [['Valor de tabela', 'R$ ' + brl(T.tab)], ['Desconto', 'R$ ' + brl(T.desc) + ' (' + num(T.descPct * 100, 1) + '%)'], ['Bonificado', 'R$ ' + brl(T.bonif)]];
        if (T.comPct) linhas.push(['Comissão (' + num(T.comPct * 100, 2) + '%)', '− R$ ' + brl(T.com)]);
        linhas.push(['Líquido a faturar', 'R$ ' + brl(T.liq), 'liq']);
        if (T.nIns) linhas.push(['Inserções', num(T.nIns)]);
        if (R.isTV && T.imp) linhas.push(['Impactos · GRP', num(T.imp) + ' · ' + num(T.grp, 1)], ['CPM', 'R$ ' + brl(T.cpm)]);
        if (R.splitTV) linhas.push(['PIs que serão geradas', String(R.months.length) + ' (uma por mês)']);
        $('#rTot').innerHTML = linhas.map(([a, b, c]) => `<dt>${esc(a)}</dt><dd class="${c || ''}">${esc(b)}</dd>`).join('');
        $('#totais').innerHTML = [['Valor de tabela', T.tab], ['Desconto nominal', T.desc], ['Bonificado', T.bonif], ['Negociado', T.neg], ['Comissão agência', T.com], ['Líquido a faturar', T.liq]]
            .map(([a, b]) => `<div><span>${a}</span><b>R$ ${brl(b)}</b></div>`).join('');
        const pend = R.C.filter(c => c.st !== 'OK');
        $('#rPend').innerHTML = pend.length
            ? `<div class="small fw-semibold mb-1 ${R.projBlock ? 'text-red' : ''}">${R.projBlock ? '<i class="bi bi-slash-circle"></i> Venda bloqueada (projeto especial)' : `<i class="bi bi-exclamation-triangle"></i> ${pend.length} pendência(s): a PI sai como Rascunho`}</div>`
              + pend.map(c => `<div class="item ${c.st === 'ERRO' ? 'erro' : 'falta'}" data-go="${goOf(c)}" role="button" tabindex="0"><i class="bi bi-${c.st === 'ERRO' ? 'x-circle-fill' : 'circle'}"></i><span class="cod">${esc(c.code)}</span><span>${esc(c.txt)}<small>${esc(c.fix)}</small></span></div>`).join('')
            : '<div class="ok-all"><i class="bi bi-check-circle-fill"></i> Conferida automaticamente: sem pendências. Ao gerar, vai para a Conferência.</div>';
        $('#bGerar').disabled = R.projBlock || gerada;
        const porPasso = {};
        pend.forEach(c => { const g = goOf(c); porPasso[g] = (porPasso[g] || 0) + 1; });
        $('#passos').innerHTML = PASSOS.map(([id, t], k) => `<a href="#${id}" class="${porPasso[id] ? 'pend' : 'ok'}"><span class="n">${porPasso[id] ? '!' : k + 1}</span>${t}</a>`).join('');
        // aprovação do gestor (RN-41)
        const a = R.aprovacao, box = $('#aprovBox');
        const chave = [R.needGs, a && a.ok, a && a.situacao, a && a.nome, num(R.T.descPct * 100, 1), brl(R.T.bonif)].join('|');
        if (box.dataset.chave === chave) return;   // não redesenha (mantém a justificativa digitada)
        box.dataset.chave = chave;
        if (!R.needGs) box.innerHTML = '<div class="small-muted"><i class="bi bi-check2"></i> Sem desconto, bonificação ou preço manual: não precisa de aprovação do gestor.</div>';
        else if (a && a.ok) box.innerHTML = `<div class="alert alert-success mb-0"><i class="bi bi-check-circle"></i><div>Desconto/bonificação <b>aprovados por ${esc(a.nome)}</b> em ${esc(dBR(a.data))}. Se aumentar o desconto, a bonificação ou mudar um preço manual, será preciso pedir de novo.</div></div>`;
        else box.innerHTML = `<div class="alert ${a && a.situacao === 'recusada' ? 'alert-danger' : 'alert-warning'} mb-2"><i class="bi bi-person-check"></i><div>
            ${a && a.situacao === 'pendente' ? 'Aprovação solicitada: aguardando o gestor.' : a && a.situacao === 'recusada' ? 'O gestor recusou as condições. Revise e peça de novo.' : 'Esta PI tem desconto, bonificação ou preço manual: precisa da aprovação do gestor (RN-41).'}
            Desconto de ${num(R.T.descPct * 100, 1)}% · bonificado R$ ${brl(R.T.bonif)}.</div></div>
            ${a && a.situacao === 'pendente' ? '' : `<div class="input-group"><input class="form-control" id="justif" placeholder="Justificativa para o gestor (opcional)" maxlength="500"><button class="btn btn-outline-primary" type="button" id="bAprov"><i class="bi bi-send"></i> Solicitar aprovação</button></div>`}`;
    }

    /* ---------- servidor: cálculo e salvamento ---------- */
    function agendar() {
        clearTimeout(tCalc); tCalc = setTimeout(calcular, 300);
        clearTimeout(tSave); tSave = setTimeout(salvar, 1400);
        $('#salvoEm').textContent = '· alterações não salvas';
    }
    async function calcular() {
        if (ctrl) ctrl.abort();
        ctrl = new AbortController();
        try {
            const r = await api('/api/pis/calcular', { method: 'POST', body: { estado: S, rascunho: RID }, signal: ctrl.signal });
            R = r.r;
            if (R.executivo) Object.assign(S.f, R.executivo);
            ['exec', 'mat', 'gestor', 'ccom'].forEach(k => { const el = $(`[data-f="${k}"]`); if (el) el.value = S.f[k] || ''; });
            updateInfos(); renderResumo();
            if (R.splitTV !== (renderParcs.ultimo ?? null)) { renderParcs.ultimo = R.splitTV; renderParcs(); }
        } catch (e) { if (e.name !== 'AbortError') $('#rMsg').textContent = e.message; }
    }
    async function salvar() {
        if (salvando || gerada) return;
        salvando = true;
        try {
            if (!RID) {
                const r = await api('/api/rascunhos', { method: 'POST', body: { estado: S } });
                RID = r.id;
                history.replaceState(null, '', '?rascunho=' + RID);
            } else {
                await api('/api/rascunhos/' + RID, { method: 'POST', body: { estado: S } });
            }
            $('#salvoEm').textContent = '· rascunho salvo às ' + new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        } catch (e) { $('#salvoEm').textContent = '· não foi possível salvar: ' + e.message; }
        finally { salvando = false; }
    }

    async function aposVeiculo() {
        try { await loadCatalog(); } catch (e) { toast(e.message, 'error'); }
        renderTab(); renderMidia(); loadFields();
    }

    // RN-23: ao trocar o programa, tira da grade as inserções dos dias em que o novo programa não vai ao ar
    function limparDiasSemExibicao(l) {
        const p = prog(l.s), air = p ? p.dias : null;
        if (!air || !l.gm || Array.isArray(l.gm)) return;
        let n = 0;
        Object.keys(l.gm).forEach(k => { const g = l.gm[k], M = monthObj(k); if (!g || Array.isArray(g)) return;
            Object.keys(g).forEach(d => { const di = dayInfo(M, +d); if (di.exists && !air.includes(di.wd)) { delete g[d]; n++; } }); });
        if (n) toast(`${n} dia(s) da grade foram limpos: ${p.n} não vai ao ar nesses dias (${p.d}).`, 'warning', 7000);
    }

    /* ---------- eventos ---------- */
    const form = $('#form');
    form.addEventListener('submit', e => e.preventDefault());
    form.addEventListener('input', e => {
        const t = e.target;
        if (t.dataset.f) { S.f[t.dataset.f] = t.value; }
        else if (t.dataset.l) { const [b, i, k] = t.dataset.l.split('|'); if (!S[b][+i]) return; S[b][+i][k] = t.value; }
        else if (t.dataset.g) {
            const [i, d] = t.dataset.g.split('|'); const v = t.value.replace(/\D/g, ''); t.value = v;
            const l = S.ins[+i]; l.gm = l.gm && !Array.isArray(l.gm) ? l.gm : {}; const g = l.gm[CUR] = l.gm[CUR] && !Array.isArray(l.gm[CUR]) ? l.gm[CUR] : {};
            if (+v) g[d] = +v; else delete g[d];
        } else return;
        agendar();
    });
    form.addEventListener('change', async e => {
        const t = e.target, l = t.dataset.l || '', f = t.dataset.f || '';
        if (t.id === 'tabSel') { S.f.tab = t.value; renderMidia(); agendar(); return; }
        if (t.id === 'selExec') { S.f.executivo_id = +t.value; agendar(); return; }
        if (f === 'venda') $('#agBox').hidden = t.value !== 'Agência';
        if (f === 'emissao') { await aposVeiculo(); }
        if (f === 'ini' || f === 'fim') { if (!$('#pD').value) $('#pD').value = S.f.fim || ''; renderMidia(); }
        if (/\|s$/.test(l)) { const [b, i] = l.split('|'); S[b][+i].s = CAT ? resolveSig(b, t.value) : up(t.value); if (b === 'ins') limparDiasSemExibicao(S.ins[+i]); renderMidia(); }
        if (/\|mk$/.test(l) || /^prj\|\d+\|id$/.test(l)) { if (/^prj\|\d+\|id$/.test(l)) S.prj[+l.split('|')[1]].cota = ''; renderMidia(); }
        if (/^prj\|\d+\|cota$/.test(l)) renderMidia();
        agendar();
    });
    // preenchimento a partir da busca de cliente/agência
    document.addEventListener('ac:select', e => {
        const d = e.detail || {};
        Object.keys(d).forEach(k => { if (k in S.f || /^(cli|ag)_/.test(k)) { if (/^(cli|ag)_/.test(k)) S.f[k] = d[k] ?? ''; } });
        if (d.carta_vencida) toast('A carta de credenciamento desta agência está vencida: a PI ficará com pendência D08 até a carta ser atualizada.', 'warning', 9000);
        if (d.situacao && d.situacao !== 'Ativo') toast(`Situação cadastral do cliente: ${d.situacao}. Confirme com o Financeiro.`, 'warning', 9000);
        loadFields(); agendar();
    });
    document.addEventListener('click', async e => {
        const b = e.target.closest('button, [data-go]');
        if (!b) return;
        if (b.dataset.go) { const s = $('#' + b.dataset.go); s && s.scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
        if (b.dataset.v) {
            if (+S.f.veic !== +b.dataset.v) {
                const temMidia = S.ins.some(l => l.s) || S.pat.length || S.dig.length || S.avi.length || S.prj.length;
                if ((S.f.num || Object.keys(S.f.nums || {}).length) && !await confirmar('Esta PI já tem número de outro veículo. Para trocar de veículo, use Cancela/substitui. Continuar mesmo assim?', { perigo: true })) return;
                if (temMidia && !await confirmar('Trocar o veículo apaga as linhas de mídia já preenchidas (as siglas são de cada veículo). Continuar?', { botao: 'Trocar veículo' })) return;
                S.f.veic = +b.dataset.v; S.f.tab = ''; S.ins = [{ gm: {} }]; S.pat = []; S.dig = []; S.avi = []; S.prj = [];
                await aposVeiculo(); agendar();
            }
            return;
        }
        if (b.dataset.mk) { CUR = b.dataset.mk; renderMidia(); return; }
        if (b.dataset.add) {
            const k = b.dataset.add, ms = monthsOf();
            S[k].push(k === 'ins' ? { gm: {} } : (k === 'pat' && ms.length > 1 && V() && V().tipo !== 'TV' ? { mk: CUR || ms[0].key } : (k === 'ent' ? { jur: 'Não se aplica' } : {})));
            k === 'ent' ? renderEnt() : renderMidia(); agendar(); return;
        }
        if (b.dataset.del) {
            const [k, i] = b.dataset.del.split('|'); S[k].splice(+i, 1);
            k === 'parc' ? renderParcs() : k === 'ent' ? renderEnt() : renderMidia(); agendar(); return;
        }
        if (b.dataset.dup) {
            const [k, i] = b.dataset.dup.split('|'); const c = JSON.parse(JSON.stringify(S[k][+i])); const ms = monthsOf();
            const j = ms.findIndex(M => M.key === c.mk); if (j >= 0 && ms[j + 1]) c.mk = ms[j + 1].key;
            S[k].splice(+i + 1, 0, c); renderMidia(); agendar(); return;
        }
        if (b.dataset.apply != null || b.dataset.applyall != null || b.dataset.clear != null) {
            const i = +(b.dataset.apply ?? b.dataset.applyall ?? b.dataset.clear), l = S.ins[i];
            l.gm = l.gm && !Array.isArray(l.gm) ? l.gm : {};
            if (b.dataset.clear != null) l.gm[CUR] = {};
            else {
                const wd = $(`[data-pat="${i}"]`).value, q = +$(`[data-q="${i}"]`).value || 0, p = prog(l.s), air = p ? p.dias : null;
                const ms = b.dataset.applyall != null ? monthsOf() : [monthObj(CUR)];
                let n = 0;
                ms.forEach(M => { const g = {}; for (let d = 1; d <= M.days; d++) { const di = dayInfo(M, d); if (di.ok && wd.includes(String(di.wd)) && (!air || air.includes(di.wd)) && q) g[d] = q; } l.gm[M.key] = g; n += Object.keys(g).length; });
                if (!n) toast('Nenhum dia do período combina com esse padrão e com os dias do programa.', 'warning');
            }
            renderMidia(); agendar(); return;
        }
        if (b.id === 'pAdd') { S.parc.push({}); renderParcs(); agendar(); return; }
        if (b.id === 'pGo') {
            if (!R) return;
            try {
                const r = await api('/api/pis/parcelas', { method: 'POST', body: { liquido: R.T.liq, quantidade: +$('#pN').value || 1, primeiro: $('#pD').value || S.f.fim || B.hoje, tv: R.splitTV, meses: R.months.length } });
                S.parc = r.parcelas; renderParcs(); agendar();
            } catch (err) { toast(err.message, 'error'); }
            return;
        }
        if (b.id === 'bAprov') {
            await salvar();
            try { const r = await api('/api/pis/aprovacao', { method: 'POST', body: { estado: S, rascunho: RID, justificativa: ($('#justif') || {}).value || '' } }); toast(r.mensagem, 'success'); calcular(); }
            catch (err) { toast(err.message, 'error'); }
            return;
        }
        if (b.id === 'bRes') {
            await salvar();
            try {
                const r = await api('/api/pis/reservar', { method: 'POST', body: { estado: S, rascunho: RID } });
                S.f.res = r.reserva.id; toast(`Reservado até ${r.reserva.expira_em}. Gere a PI até lá (o prazo de 24 h não é renovado ao atualizar a reserva).`, 'success', 9000);
                await loadCatalog(true); renderMidia(); agendar();
            } catch (err) { toast(err.message, 'error', 10000); }
            return;
        }
        if (b.id === 'bGerar') { gerar(); }
    });
    document.addEventListener('keydown', e => { if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('.gm-pend .item')) { e.preventDefault(); e.target.click(); } });

    async function gerar() {
        clearTimeout(tCalc); await calcular();
        if (!R) return;
        if (R.projBlock) { toast('Venda bloqueada pelo projeto especial: corrija as pendências P02/P03/P04.', 'error'); return; }
        const pend = R.C.filter(c => c.st !== 'OK').length;
        const msg = pend
            ? `Há ${pend} pendência(s). A PI será gerada com número, mas sai como RASCUNHO (não vai para a Conferência). Gerar mesmo assim?`
            : (R.splitTV ? `Serão geradas ${R.months.length} PIs (uma por mês), cada uma com seu número. Confirmar?` : 'Gerar a PI agora? Ela recebe o número e vai para a Conferência.');
        if (!await confirmar(msg, { titulo: 'Gerar PI', botao: 'Gerar PI', perigo: false })) return;
        clearTimeout(tSave);
        const btn = $('#bGerar'); btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Gerando…';
        try {
            const r = await api('/api/pis/gerar', { method: 'POST', body: { estado: S, rascunho: RID } });
            gerada = true; RID = null;
            history.replaceState(null, '', location.pathname);
            $('#mGeradaTit').textContent = r.pis.length > 1 ? `${r.pis.length} PIs geradas` : `PI ${r.pis[0].numero} gerada`;
            $('#mGeradaBody').innerHTML = r.pis.map(p => `<div class="d-flex align-items-center gap-2 border rounded p-2 mb-2"><div class="flex-grow-1"><b>${esc(p.numero)}</b> <span class="st st-${esc(norm(p.situacao).replace(/[^a-z0-9]+/g, '-'))}">${esc(p.situacao)}</span>
                ${p.pendencias ? `<div class="small text-red">${p.pendencias} pendência(s): corrija e gere de novo (o número é mantido).</div>` : '<div class="small text-success">Enviada à Conferência.</div>'}</div>
                <a class="btn btn-outline-primary btn-sm" href="${esc(p.excel)}"><i class="bi bi-file-earmark-excel"></i> Baixar Excel</a><a class="btn btn-light btn-sm" href="${esc(p.link)}">Abrir</a></div>`).join('')
                + (r.grupo ? `<div class="small-muted">PIs mensais ligadas: ${esc(r.grupo)}</div>` : '');
            $('#mGeradaVer').href = r.pis[0].link;
            new bootstrap.Modal($('#mGerada'), { backdrop: 'static', keyboard: false }).show();
        } catch (err) {
            btn.disabled = false; btn.innerHTML = '<i class="bi bi-file-earmark-check"></i> Gerar PI';
            toast(err.message, 'error', 12000); calcular();
        }
    }

    /* ---------- início ---------- */
    (async function init() {
        fillLists();
        loadFields();
        if (S.f.veic) await aposVeiculo();
        renderEnt(); renderParcs();
        await calcular();
        if (B.devolucao) $('#avisoDevolucao').innerHTML = `<div class="alert alert-danger"><i class="bi bi-arrow-return-left"></i><div><b>Devolvida em ${esc(B.devolucao.em)} por ${esc(B.devolucao.por)} (${esc(B.devolucao.codigos)}):</b> ${esc(B.devolucao.descricao)}</div></div>`;
        window.addEventListener('beforeunload', e => { if (!gerada && $('#salvoEm').textContent.includes('não salvas')) { salvar(); e.preventDefault(); e.returnValue = ''; } });
    })();
})();
