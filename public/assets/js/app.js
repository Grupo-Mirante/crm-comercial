/* =====================================================================
   CRM Comercial · Grupo Mirante — comportamento comum das telas
   (máscaras, confirmações, busca rápida, avisos e chamadas à API)
   Toda autorização e validação é refeita no servidor; aqui é só ajuda ao usuário.
   ===================================================================== */
(function () {
    'use strict';

    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => [...el.querySelectorAll(s)];
    const csrf = () => ($('meta[name="csrf-token"]') || {}).content || '';
    const base = () => ($('meta[name="app-url"]') || {}).content || '';
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /* ---------- API ---------- */
    async function api(url, opts = {}) {
        const o = Object.assign({ method: 'GET', headers: {} }, opts);
        o.headers = Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, o.headers);
        if (o.method !== 'GET') o.headers['X-CSRF-Token'] = csrf();
        if (o.body && !(o.body instanceof FormData) && typeof o.body !== 'string') {
            o.headers['Content-Type'] = 'application/json';
            o.body = JSON.stringify(o.body);
        }
        const full = /^https?:/.test(url) ? url : base() + url;
        const r = await fetch(full, Object.assign(o, { credentials: 'same-origin' }));
        let data = null;
        try { data = await r.json(); } catch (e) { data = null; }
        if (r.status === 401) { window.location.href = base() + '/login'; throw new Error('Sessão encerrada'); }
        if (!r.ok) {
            const err = new Error((data && data.mensagem) || 'Não foi possível concluir. Tente de novo.');
            err.data = data; err.status = r.status;
            throw err;
        }
        return data;
    }

    /* ---------- avisos (toasts) ---------- */
    function toast(msg, tipo = 'info', ms = 6000) {
        let zone = $('.toast-zone');
        if (!zone) { zone = document.createElement('div'); zone.className = 'toast-zone'; document.body.appendChild(zone); }
        const icon = { success: 'check-circle-fill text-success', error: 'exclamation-octagon-fill text-danger', warning: 'exclamation-triangle-fill text-warning', info: 'info-circle-fill text-primary' }[tipo] || 'info-circle-fill';
        const el = document.createElement('div');
        el.className = 'toast show t-' + tipo;
        el.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
        el.innerHTML = `<div class="toast-body d-flex gap-2 align-items-start"><i class="bi bi-${icon} fs-5"></i><div class="flex-grow-1">${esc(msg)}</div><button type="button" class="btn-close btn-sm" aria-label="Fechar"></button></div>`;
        el.querySelector('.btn-close').addEventListener('click', () => el.remove());
        zone.appendChild(el);
        if (ms) setTimeout(() => el.remove(), ms);
    }

    /* ---------- confirmação antes de ações irreversíveis ---------- */
    function confirmar(mensagem, { titulo = 'Confirmar', botao = 'Confirmar', perigo = true } = {}) {
        return new Promise(resolve => {
            const m = document.createElement('div');
            m.className = 'modal fade';
            m.tabIndex = -1;
            m.innerHTML = `<div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">${esc(titulo)}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                <div class="modal-body">${esc(mensagem)}</div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Voltar</button>
                <button type="button" class="btn ${perigo ? 'btn-danger' : 'btn-primary'}" data-ok>${esc(botao)}</button></div></div></div>`;
            document.body.appendChild(m);
            const modal = new bootstrap.Modal(m);
            let ok = false;
            m.querySelector('[data-ok]').addEventListener('click', () => { ok = true; modal.hide(); });
            m.addEventListener('hidden.bs.modal', () => { m.remove(); resolve(ok); });
            modal.show();
        });
    }

    document.addEventListener('submit', async e => {
        const f = e.target;
        if (!(f instanceof HTMLFormElement) || !f.dataset.confirm || f.dataset.confirmed) return;
        e.preventDefault();
        if (await confirmar(f.dataset.confirm, { titulo: f.dataset.confirmTitle || 'Confirmar', botao: f.dataset.confirmButton || 'Confirmar', perigo: f.dataset.confirmDanger !== '0' })) {
            f.dataset.confirmed = '1';
            f.requestSubmit ? f.requestSubmit(e.submitter || undefined) : f.submit();
        }
    }, true);

    /* Evita duplo envio */
    document.addEventListener('submit', e => {
        const f = e.target;
        // Envio cancelado por uma validação da página (ex.: checklist incompleto): os botões continuam ativos
        if (!(f instanceof HTMLFormElement) || e.defaultPrevented || (f.dataset.confirm && !f.dataset.confirmed)) return;
        setTimeout(() => $$('button[type=submit]', f).forEach(b => { b.disabled = true; b.dataset.label = b.innerHTML; b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Aguarde…'; }), 0);
    });

    /* ---------- CPF / CNPJ (inclusive alfanumérico) ---------- */
    function docCheck(s) {
        const v = String(s || '').toUpperCase().replace(/[^0-9A-Z]/g, '');
        if (!v) return { ok: false, vazio: true };
        const dv = (str, w) => { const t = [...str].reduce((a, ch, i) => a + (ch.charCodeAt(0) - 48) * w[i], 0) % 11; return t < 2 ? 0 : 11 - t; };
        if (v.length === 11 && /^\d{11}$/.test(v)) {
            if (/^(\d)\1{10}$/.test(v)) return { ok: false, tipo: 'CPF' };
            const w1 = [10, 9, 8, 7, 6, 5, 4, 3, 2], w2 = [11, ...w1];
            return { ok: dv(v.slice(0, 9), w1) === +v[9] && dv(v.slice(0, 10), w2) === +v[10], tipo: 'CPF', fmt: `${v.slice(0, 3)}.${v.slice(3, 6)}.${v.slice(6, 9)}-${v.slice(9)}` };
        }
        if (v.length === 14 && /^[0-9A-Z]{12}\d{2}$/.test(v)) {
            if (/^(.)\1{13}$/.test(v)) return { ok: false, tipo: 'CNPJ' };
            const w1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], w2 = [6, ...w1];
            return { ok: dv(v.slice(0, 12), w1) === +v[12] && dv(v.slice(0, 13), w2) === +v[13], tipo: 'CNPJ', fmt: `${v.slice(0, 2)}.${v.slice(2, 5)}.${v.slice(5, 8)}/${v.slice(8, 12)}-${v.slice(12)}` };
        }
        return { ok: false, tipo: v.length > 11 ? 'CNPJ' : 'CPF' };
    }

    /* ---------- máscaras ---------- */
    const masks = {
        doc(v) {
            v = v.toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 14);
            if (v.length <= 11 && /^\d*$/.test(v)) return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
            return v.replace(/^(\w{2})(\w)/, '$1.$2').replace(/^(\w{2})\.(\w{3})(\w)/, '$1.$2.$3').replace(/^(\w{2})\.(\w{3})\.(\w{3})(\w)/, '$1.$2.$3/$4').replace(/^(\w{2})\.(\w{3})\.(\w{3})\/(\w{4})(\w)/, '$1.$2.$3/$4-$5');
        },
        cnpj(v) { return masks.doc(v); },
        cep(v) { v = v.replace(/\D/g, '').slice(0, 8); return v.length > 5 ? v.slice(0, 5) + '-' + v.slice(5) : v; },
        telefone(v) {
            const parts = v.split(/\s*[\/;·]\s*/);
            return parts.map(p => {
                const d = p.replace(/\D/g, '').slice(0, 11);
                if (d.length <= 2) return d ? '(' + d : '';
                if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
                if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
                return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
            }).join(' / ');
        },
        moeda(v) {
            const d = v.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
            if (!d) return '';
            const n = (parseInt(d, 10) / 100).toFixed(2);
            const [i, c] = n.split('.');
            return i.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + c;
        },
        percentual(v) { return v.replace(/[^\d,]/g, '').replace(/(,.*?),/g, '$1').slice(0, 6); },
        data(v) { v = v.replace(/\D/g, '').slice(0, 8); return v.replace(/(\d{2})(\d)/, '$1/$2').replace(/(\d{2})\/(\d{2})(\d)/, '$1/$2/$3'); },
        uf(v) { return v.replace(/[^A-Za-z]/g, '').slice(0, 2).toUpperCase(); },
        inteiro(v) { return v.replace(/\D/g, ''); },
    };

    function applyMask(el) {
        const fn = masks[el.dataset.mask];
        if (!fn) return;
        const before = el.value;
        const after = fn(before);
        if (before !== after) el.value = after;
    }

    function docFeedback(el) {
        let box = el.parentElement.querySelector('.doc-status');
        if (!box) { box = document.createElement('div'); box.className = 'doc-status'; el.insertAdjacentElement('afterend', box); }
        const d = docCheck(el.value);
        if (d.vazio) { box.textContent = ''; box.className = 'doc-status'; el.classList.remove('is-invalid', 'is-valid'); return; }
        const completo = el.value.replace(/[^0-9A-Za-z]/g, '').length >= 11;
        if (!completo) { box.textContent = ''; return; }
        box.className = 'doc-status ' + (d.ok ? 'ok' : 'bad');
        box.innerHTML = d.ok ? `<i class="bi bi-check-circle"></i> ${esc(d.tipo)} válido` : `<i class="bi bi-x-circle"></i> ${esc(d.tipo || 'Documento')} inválido: confira os dígitos`;
        if (d.ok && d.fmt) el.value = d.fmt;
    }

    document.addEventListener('input', e => {
        const el = e.target;
        if (el.matches && el.matches('[data-mask]')) applyMask(el);
    });
    document.addEventListener('change', e => {
        const el = e.target;
        if (el.matches && el.matches('[data-mask="doc"],[data-mask="cnpj"]')) docFeedback(el);
        if (el.matches && el.matches('[data-autosubmit]')) el.form && el.form.requestSubmit();
    });

    /* ---------- busca rápida (autocomplete) ---------- */
    function autocomplete(input) {
        const wrap = document.createElement('div');
        wrap.className = 'ac-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        const list = document.createElement('div');
        list.className = 'ac-list';
        list.setAttribute('role', 'listbox');
        wrap.appendChild(list);
        input.setAttribute('autocomplete', 'off');
        let timer = null, items = [], idx = -1;
        const render = () => {
            list.innerHTML = items.length ? items.map((it, i) => `<div class="ac-item${i === idx ? ' active' : ''}" data-i="${i}" role="option"><b>${esc(it.titulo)}</b><small>${esc(it.sub || '')}</small></div>`).join('')
                : `<div class="ac-item text-muted">Nada encontrado.${input.dataset.acVazio ? ' ' + esc(input.dataset.acVazio) : ''}</div>`;
            list.classList.add('open');
        };
        const pick = i => {
            const it = items[i];
            if (!it) return;
            list.classList.remove('open');
            input.dispatchEvent(new CustomEvent('ac:select', { detail: it.dados, bubbles: true }));
            if (input.dataset.acFill) fill(input.dataset.acFill, it.dados);
            if (input.dataset.acGo) window.location.href = base() + input.dataset.acGo.replace('{id}', it.dados.id);
            input.value = input.dataset.acKeep ? it.titulo : '';
        };
        input.addEventListener('input', () => {
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) { list.classList.remove('open'); return; }
            timer = setTimeout(async () => {
                try {
                    const r = await api(input.dataset.autocomplete + (input.dataset.autocomplete.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q));
                    items = r.itens || [];
                    idx = -1;
                    render();
                } catch (err) { list.classList.remove('open'); }
            }, 250);
        });
        input.addEventListener('keydown', e => {
            if (!list.classList.contains('open')) return;
            if (e.key === 'ArrowDown') { idx = Math.min(items.length - 1, idx + 1); render(); e.preventDefault(); }
            else if (e.key === 'ArrowUp') { idx = Math.max(0, idx - 1); render(); e.preventDefault(); }
            else if (e.key === 'Enter' && idx >= 0) { pick(idx); e.preventDefault(); }
            else if (e.key === 'Escape') list.classList.remove('open');
        });
        list.addEventListener('mousedown', e => { const it = e.target.closest('[data-i]'); if (it) { e.preventDefault(); pick(+it.dataset.i); } });
        input.addEventListener('blur', () => setTimeout(() => list.classList.remove('open'), 150));
    }

    /* Preenche campos a partir de um objeto: data-ac-fill="prefixo" → campos com name="prefixo[chave]" ou data-fill="chave" */
    function fill(scope, dados) {
        const root = document.querySelector(scope) || document;
        Object.entries(dados || {}).forEach(([k, v]) => {
            $$(`[data-fill="${k}"]`, root).forEach(el => {
                if (el.type === 'checkbox') el.checked = !!v; else el.value = v ?? '';
                el.dispatchEvent(new Event('change', { bubbles: true }));
                el.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    }

    /* ---------- menu lateral (celular) ---------- */
    function sidebar() {
        const sb = $('.sidebar'), bd = $('.sidebar-backdrop'), bt = $('.menu-toggle');
        if (!sb || !bt) return;
        const close = () => { sb.classList.remove('open'); bd && bd.classList.remove('open'); bt.setAttribute('aria-expanded', 'false'); };
        bt.addEventListener('click', () => { const o = !sb.classList.contains('open'); sb.classList.toggle('open', o); bd && bd.classList.toggle('open', o); bt.setAttribute('aria-expanded', String(o)); });
        bd && bd.addEventListener('click', close);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    }

    /* ---------- mostrar/ocultar senha ---------- */
    function passwords() {
        $$('.pw-wrap').forEach(w => {
            const i = $('input', w), b = $('.pw-toggle', w);
            if (!i || !b) return;
            b.addEventListener('click', () => {
                const show = i.type === 'password';
                i.type = show ? 'text' : 'password';
                b.innerHTML = `<i class="bi bi-eye${show ? '-slash' : ''}"></i>`;
                b.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
            });
        });
    }

    /* ---------- linhas dinâmicas (cotas de projeto, etc.) ---------- */
    function repeaters() {
        $$('[data-repeater]').forEach(rep => {
            const tpl = $('template', rep), body = $('[data-repeater-body]', rep);
            if (!tpl || !body) return;
            let n = body.children.length;
            rep.addEventListener('click', e => {
                if (e.target.closest('[data-repeater-add]')) {
                    const html = tpl.innerHTML.replace(/__i__/g, String(n++));
                    body.insertAdjacentHTML('beforeend', html);
                }
                const del = e.target.closest('[data-repeater-del]');
                if (del) del.closest('[data-repeater-row]').remove();
            });
        });
    }

    /* ---------- filtro rápido de linhas de tabela (data-filter-target + tr[data-busca]) ---------- */
    document.addEventListener('input', e => {
        const el = e.target;
        if (!el.matches || !el.matches('[data-filter-target]')) return;
        const q = el.value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
        $$(el.dataset.filterTarget + ' tr[data-busca]').forEach(tr => { tr.hidden = q !== '' && !tr.dataset.busca.includes(q); });
    });

    /* ---------- imprimir ---------- */
    document.addEventListener('click', e => { if (e.target.closest('[data-print]') && !document.querySelector('script[src*="imprimir.js"]')) window.print(); });

    /* ---------- copiar texto ---------- */
    document.addEventListener('click', e => {
        const b = e.target.closest('[data-copy]');
        if (!b) return;
        const t = document.querySelector(b.dataset.copy);
        if (t && navigator.clipboard) navigator.clipboard.writeText(t.textContent.trim()).then(() => toast('Copiado.', 'success', 2000));
    });

    /* ---------- notificações (marcar como lida) ---------- */
    document.addEventListener('click', async e => {
        const a = e.target.closest('[data-notif]');
        if (!a) return;
        try { await api('/notificacoes/' + a.dataset.notif + '/lida', { method: 'POST' }); } catch (err) { /* segue o link mesmo assim */ }
    });

    /* ---------- inicialização ---------- */
    document.addEventListener('DOMContentLoaded', () => {
        sidebar();
        passwords();
        repeaters();
        $$('[data-mask]').forEach(applyMask);
        $$('[data-autocomplete]').forEach(autocomplete);
        $$('[data-flash]').forEach(el => toast(el.dataset.flash, el.dataset.tipo || 'info', el.dataset.tipo === 'error' ? 10000 : 6000));
        $$('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
        const first = $('[data-autofocus]');
        if (first) first.focus();
    });

    window.CRM = { api, toast, confirmar, docCheck, masks, fill, esc };
})();
