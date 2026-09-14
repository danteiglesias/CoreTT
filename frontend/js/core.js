/**
 * CoreTT - Utilidades compartidas.
 * Debe cargarse antes que cualquier otro script (core.js, layout.js, api.js, auth.js...).
 */
window.Core = (() => {
    // Ruta base de la API. Si el docroot es la raiz del proyecto, esta es correcta.
    // Se puede sobreescribir antes con: window.API_CONFIG = { apiBase: '...' }
    const config = window.API_CONFIG || {};
    const apiBase = config.apiBase || '/backend/api/';

    /** Escapa texto para insertarlo en HTML de forma segura. */
    function escapeHtml(text) {
        if (typeof text !== 'string') return text;
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /** Devuelve la ruta de un asset dentro de /frontend/assets, relativa a la pagina actual. */
    function asset(name) {
        const inPages = /\/pages\//.test(location.pathname);
        return (inPages ? '../' : '') + 'assets/' + name;
    }

    /** Query param o default. */
    function queryParam(key, fallback) {
        const params = new URLSearchParams(location.search);
        const value = params.get(key);
        return value === null ? fallback : value;
    }

    /** Muestra una notificacion tipo toast. */
    function notify(message, type) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + (type || 'info');
        toast.textContent = message;
        container.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    /** Formatea un numero grande de votos (1.2k). */
    function compactNumber(n) {
        const value = Number(n) || 0;
        if (Math.abs(value) >= 1000) {
            return (value / 1000).toFixed(1).replace('.0', '') + 'k';
        }
        return String(value);
    }

    return {
        apiBase,
        escapeHtml,
        asset,
        queryParam,
        notify,
        compactNumber
    };
})();