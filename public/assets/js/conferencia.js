/* Conferência: itens do checklist desmarcados sugerem os códigos de devolução; confirmações antes de aprovar/devolver. */
(function () {
    'use strict';
    const form = document.getElementById('fConf');
    if (!form) return;
    const { confirmar, toast } = window.CRM;
    form.addEventListener('change', e => {
        const ck = e.target.closest('input[data-codigos]');
        if (!ck) return;
        ck.dataset.codigos.split(',').forEach(c => { const m = document.getElementById('m' + c.trim()); if (m && !ck.checked) m.checked = true; });
    });
    form.addEventListener('submit', async e => {
        if (form.dataset.ok) return;
        e.preventDefault();
        const acao = (e.submitter && e.submitter.dataset.acao) || 'aprovar';
        if (acao === 'aprovar') {
            const falta = [...form.querySelectorAll('input[data-codigos]')].filter(c => !c.checked).length;
            if (falta) { toast(`Faltam ${falta} item(ns) do checklist. Para aprovar, todos precisam estar marcados; senão, devolva com o código.`, 'warning', 8000); return; }
            if (!await confirmar('Aprovar a PI? Ela segue para Financeiro, OPEC e Marketing.', { titulo: 'Aprovar na conferência', botao: 'Aprovar', perigo: false })) return;
        } else {
            let cods = [...form.querySelectorAll('input[name="codigos[]"]:checked')].map(c => c.value);
            if (!cods.length) {
                // Sugere os códigos dos itens do checklist ainda não marcados; o conferente confere e clica de novo
                const sug = new Set();
                form.querySelectorAll('input[data-codigos]').forEach(ck => { if (!ck.checked) ck.dataset.codigos.split(',').forEach(c => sug.add(c.trim())); });
                sug.forEach(c => { const m = document.getElementById('m' + c); if (m) m.checked = true; });
                toast(sug.size
                    ? `Marquei os códigos sugeridos pelos itens pendentes do checklist (${[...sug].join(', ')}). Confira e clique em Devolver de novo.`
                    : 'Escolha ao menos um código de motivo (D01 a D15). Sem código, a devolução não vale.', 'warning', 9000);
                return;
            }
            if (form.querySelector('[name=descricao]').value.trim().length < 5) { toast('Explique em uma linha o que o executivo precisa corrigir.', 'warning'); return; }
            if (!await confirmar(`Devolver a PI com ${cods.join(', ')}? A devolução fica registrada no nome do executivo.`, { titulo: 'Devolver PI', botao: 'Devolver' })) return;
        }
        form.dataset.ok = '1';
        if (acao === 'devolver') form.action = e.submitter.getAttribute('formaction');
        form.submit();
    });
})();
