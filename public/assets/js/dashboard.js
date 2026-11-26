/* Painel: gráficos (Chart.js) a partir dos dados do servidor. */
(function () {
    'use strict';
    const el = document.getElementById('dados-graficos');
    if (!el || typeof Chart === 'undefined') return;
    const g = JSON.parse(el.textContent || '{}');
    const NAVY = '#1F3A5F', RED = '#C00000';
    const PALETA = ['#1F3A5F', '#C00000', '#4F7CAC', '#D98A2B', '#2E8B6E', '#7A5AA6', '#8C96A3', '#B0413E', '#5DA9C9', '#3D4652', '#A3B8D1', '#E0A458'];
    const COR_SIT = {
        'Rascunho': '#E5B94D', 'Enviada': '#4F7CAC', 'Devolvida': '#C00000', 'Corrigida': '#7A5AA6', 'Aprovada': '#2E8B6E', 'Em programação': '#1F8A8A',
        'Em veiculação': '#3657B8', 'Pronta para faturamento': '#D98A2B', 'Faturada': '#1E6B42', 'Encerrada': '#6B7480', 'Cancelada': '#C7CCD3', 'Substituída': '#A3AAB3'
    };
    const brl = v => 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const brlCurto = v => { v = Number(v || 0); return v >= 1e6 ? 'R$ ' + (v / 1e6).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi' : v >= 1e3 ? 'R$ ' + (v / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : brl(v); };
    const mesLabel = k => { const [y, m] = k.split('-'); return ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'][+m - 1] + '/' + y.slice(2); };

    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#3D4652';
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.maintainAspectRatio = false;

    const vazio = (canvas, msg) => {
        const box = canvas.parentElement;
        box.innerHTML = `<div class="empty-state h-100 d-flex flex-column justify-content-center"><i class="bi bi-bar-chart"></i>${msg}</div>`;
    };
    const mk = (id, cfg, temDados, msg) => {
        const c = document.getElementById(id);
        if (!c) return;
        if (!temDados) { vazio(c, msg || 'Sem dados no período.'); return; }
        new Chart(c, cfg);
    };

    mk('gVeiculos', {
        type: 'bar',
        data: { labels: g.veiculos.map(x => x.rotulo), datasets: [{ label: 'Negociado', data: g.veiculos.map(x => x.valor), backgroundColor: NAVY, borderRadius: 6, maxBarThickness: 48 }] },
        options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => brl(c.raw) + ' · ' + g.veiculos[c.dataIndex].qtd + ' PI(s)' } } },
            scales: { y: { ticks: { callback: brlCurto }, grid: { color: '#EEF0F3' } }, x: { grid: { display: false } } } }
    }, g.veiculos.some(x => x.valor > 0));

    mk('gSituacoes', {
        type: 'doughnut',
        data: { labels: g.situacoes.map(x => x.rotulo), datasets: [{ data: g.situacoes.map(x => x.qtd), backgroundColor: g.situacoes.map(x => COR_SIT[x.rotulo] || '#8C96A3'), borderWidth: 2, borderColor: '#fff' }] },
        options: { cutout: '62%', plugins: { legend: { position: 'right' } } }
    }, g.situacoes.length > 0);

    mk('gEvolucao', {
        type: 'line',
        data: { labels: g.evolucao.map(x => mesLabel(x.mes)), datasets: [
            { label: 'Negociado', data: g.evolucao.map(x => x.negociado), borderColor: NAVY, backgroundColor: 'rgba(31,58,95,.08)', fill: true, tension: .3, pointRadius: 3 },
            { label: 'Líquido', data: g.evolucao.map(x => x.liquido), borderColor: RED, backgroundColor: 'transparent', tension: .3, pointRadius: 3, borderDash: [5, 4] }
        ] },
        options: { interaction: { mode: 'index', intersect: false }, plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + brl(c.raw) } } },
            scales: { y: { ticks: { callback: brlCurto }, grid: { color: '#EEF0F3' } }, x: { grid: { display: false } } } }
    }, g.evolucao.some(x => x.negociado > 0));

    mk('gMotivos', {
        type: 'bar',
        data: { labels: g.motivos.map(x => x.rotulo), datasets: [{ label: 'Devoluções', data: g.motivos.map(x => x.qtd), backgroundColor: RED, borderRadius: 6, maxBarThickness: 28 }] },
        options: { indexAxis: 'y', plugins: { legend: { display: false }, tooltip: { callbacks: { title: c => g.motivos[c[0].dataIndex].rotulo + ' · ' + g.motivos[c[0].dataIndex].descricao } } },
            scales: { x: { ticks: { precision: 0 }, grid: { color: '#EEF0F3' } }, y: { grid: { display: false } } } }
    }, g.motivos.length > 0, 'Nenhuma devolução no período.');

    mk('gExecutivos', {
        type: 'bar',
        data: { labels: g.executivos.map(x => x.rotulo), datasets: [{ label: 'Negociado', data: g.executivos.map(x => x.valor), backgroundColor: g.executivos.map((x, i) => PALETA[i % PALETA.length]), borderRadius: 6, maxBarThickness: 40 }] },
        options: { plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => brl(c.raw) + ' · ' + g.executivos[c.dataIndex].qtd + ' PI(s)' } } },
            scales: { y: { ticks: { callback: brlCurto }, grid: { color: '#EEF0F3' } }, x: { grid: { display: false } } } }
    }, g.executivos.length > 0);
})();
