/**
 * CoreTT - API Client
 * Capa de comunicación con el backend PHP.
 *
 * La URL del backend se detecta automáticamente a partir de la ubicación de
 * este script (frontend/js/api.js -> ../../backend/api/). Si el backend está
 * en otro dominio o ruta, define window.CORETT_API_BASE antes de cargar este
 * archivo, por ejemplo:
 *
 *   <script>window.CORETT_API_BASE = 'https://mi-servidor/CoreTT/backend/api/';</script>
 */
const API = (() => {
    const REQUEST_TIMEOUT = 15000;

    const GAME_IMAGES = {
        'League of Legends': 'League of Legends.png',
        'Counter-Strike 2': 'Counter Strike 2.png',
        'Fortnite': 'Fortnite.png'
    };

    const GAME_CLASS = {
        'League of Legends': 'lol',
        'Counter-Strike 2': 'cs2',
        'Fortnite': 'fn'
    };

    /* ============================================
       Detección de URLs
       ============================================ */

    function withTrailingSlash(url) {
        return url.endsWith('/') ? url : url + '/';
    }

    function resolveBaseUrl() {
        if (typeof window !== 'undefined'
            && typeof window.CORETT_API_BASE === 'string'
            && window.CORETT_API_BASE.trim() !== '') {
            return withTrailingSlash(window.CORETT_API_BASE.trim());
        }

        const script = typeof document !== 'undefined' ? document.currentScript : null;
        if (script && script.src) {
            try {
                return new URL('../../backend/api/', script.src).toString();
            } catch (error) {
                // Se usa el fallback de abajo
            }
        }

        const isInPages = typeof window !== 'undefined'
            && window.location
            && window.location.pathname
            && window.location.pathname.indexOf('/pages/') !== -1;
        return isInPages ? '../../backend/api/' : '../backend/api/';
    }

    function resolveAssetBaseUrl() {
        const script = typeof document !== 'undefined' ? document.currentScript : null;
        if (script && script.src) {
            try {
                return new URL('../assets/', script.src).toString();
            } catch (error) {
                // Se usa el fallback de abajo
            }
        }
        const isInPages = typeof window !== 'undefined'
            && window.location
            && window.location.pathname
            && window.location.pathname.indexOf('/pages/') !== -1;
        return isInPages ? '../assets/' : 'assets/';
    }

    const BASE_URL = resolveBaseUrl();
    const ASSET_BASE_URL = resolveAssetBaseUrl();

    function assetUrl(filename) {
        if (!filename) return '';
        try {
            return new URL(filename, ASSET_BASE_URL).toString();
        } catch (error) {
            return ASSET_BASE_URL + filename;
        }
    }

    /* ============================================
       Peticiones HTTP
       ============================================ */

    async function request(endpoint, method = 'GET', body = null, authenticated = false) {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };

        if (authenticated && typeof Auth !== 'undefined') {
            const token = Auth.getToken();
            if (token) {
                options.headers['Authorization'] = 'Bearer ' + token;
            }
        }

        if (body !== null && body !== undefined) {
            options.body = JSON.stringify(body);
        }

        let timer = null;
        if (typeof AbortController !== 'undefined') {
            const controller = new AbortController();
            options.signal = controller.signal;
            timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT);
        }

        let response;
        let rawBody = '';

        try {
            response = await fetch(BASE_URL + endpoint, options);
            rawBody = await response.text();
        } catch (error) {
            if (error && error.name === 'AbortError') {
                throw new Error('El servidor tardó demasiado en responder. Revisa que PHP y MySQL estén funcionando.');
            }
            throw new Error(
                'No se pudo conectar con el backend en ' + BASE_URL +
                '. Comprueba que el servidor web tenga PHP activo y que la ruta sea correcta.'
            );
        } finally {
            if (timer) clearTimeout(timer);
        }

        let data = null;
        if (rawBody) {
            try {
                data = JSON.parse(rawBody);
            } catch (error) {
                data = null;
            }
        }

        if (!response.ok) {
            if (response.status === 401 && typeof Auth !== 'undefined') {
                Auth.logout();
            }
            const message = data && (data.error || data.message)
                ? (data.error || data.message)
                : 'Error HTTP ' + response.status + (rawBody ? ': ' + rawBody.slice(0, 200) : '');
            throw new Error(message);
        }

        if (data === null) {
            throw new Error(
                'El backend devolvió una respuesta que no es JSON. ' +
                'Abre ' + BASE_URL + 'diagnostico.php?format=html para revisar la configuración.'
            );
        }

        return data;
    }

    /* ============================================
       Normalización de datos
       ============================================ */

    function enrichGuide(guide) {
        const game = guide.game || '';
        return {
            ...guide,
            votes: Number(guide.votes || 0),
            image: GAME_IMAGES[game] ? assetUrl(GAME_IMAGES[game]) : '',
            gameClass: GAME_CLASS[game] || ''
        };
    }

    function normalizePosts(posts) {
        if (!Array.isArray(posts)) return [];
        return posts.map((post) => ({
            ...post,
            replies: Number(post.replies || 0)
        }));
    }

    /* ============================================
       Guías
       ============================================ */

    async function getGuides(options = {}) {
        const params = new URLSearchParams();

        if (options.game && options.game !== 'Todos') {
            params.set('game', options.game);
        }
        if (options.search) {
            params.set('search', options.search);
        }

        const query = params.toString();
        const data = await request('guides.php' + (query ? '?' + query : ''));

        return Array.isArray(data) ? data.map(enrichGuide) : [];
    }

    async function getGuide(id) {
        const guide = await request('guides.php?id=' + encodeURIComponent(id));
        return enrichGuide(guide);
    }

    async function voteGuide(id, vote) {
        const result = await request('guides.php?action=vote', 'POST', { id, vote }, true);
        return {
            ...result,
            votes: Number(result.votes || 0)
        };
    }

    async function createGuide(data) {
        return request('guides.php', 'POST', data, true);
    }

    async function deleteGuide(id) {
        return request('guides.php?id=' + encodeURIComponent(id), 'DELETE', null, true);
    }

    /* ============================================
       Foro
       ============================================ */

    async function getPosts() {
        return normalizePosts(await request('forum.php'));
    }

    async function getPost(id) {
        const post = await request('forum.php?id=' + encodeURIComponent(id));
        return {
            ...post,
            comments: Array.isArray(post.comments) ? post.comments : [],
            replies: Number(post.replies || 0)
        };
    }

    async function createPost(data) {
        return request('forum.php', 'POST', data, true);
    }

    async function addComment(postId, text) {
        return request('forum.php?action=comment', 'POST', { postId, text }, true);
    }

    async function deletePost(id) {
        return request('forum.php?id=' + encodeURIComponent(id), 'DELETE', null, true);
    }

    /* ============================================
       Juegos / Usuarios
       ============================================ */

    async function getGames() {
        const games = await request('games.php');
        return Array.isArray(games) ? games : [];
    }

    async function getUsers() {
        const users = await request('users.php', 'GET', null, true);
        if (!Array.isArray(users)) return [];
        return users.map((user) => ({
            ...user,
            guides: Number(user.guides || 0),
            votes: Number(user.votes || 0)
        }));
    }

    /* ============================================
       Autenticación
       ============================================ */

    async function login(email, password) {
        const result = await request('users.php?action=login', 'POST', { email, password });

        if (!result || !result.token || !result.user) {
            throw new Error(result && result.message ? result.message : 'Credenciales inválidas');
        }

        return {
            ...result,
            success: true
        };
    }

    async function register(name, email, password) {
        const result = await request('users.php?action=register', 'POST', { name, email, password });
        return {
            ...result,
            success: result.success !== false
        };
    }

    async function getProfile() {
        const profile = await request('users.php?action=profile', 'GET', null, true);
        return {
            ...profile,
            reputation: profile.reputation !== undefined ? profile.reputation : profile.reputacion,
            guides: Number(profile.guides || 0),
            votes: Number(profile.votes || 0)
        };
    }

    /* ============================================
       Interfaz pública
       ============================================ */

    return {
        isAvailable: () => true,
        getBaseUrl: () => BASE_URL,
        getAssetBaseUrl: () => ASSET_BASE_URL,
        getGames,
        getGuides,
        getGuide,
        voteGuide,
        createGuide,
        deleteGuide,
        getPosts,
        getPost,
        createPost,
        addComment,
        deletePost,
        getUsers,
        getProfile,
        login,
        register
    };
})();
