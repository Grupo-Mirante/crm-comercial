/* Página de impressão da PI: botão Imprimir (sem script embutido, por causa da política de segurança). */
(function () {
    'use strict';
    const b = document.querySelector('[data-print]');
    if (b) b.addEventListener('click', () => window.print());
})();
