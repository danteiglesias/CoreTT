/**
 * CoreTT - API Client.
 * Capa de comunicacion con el backend PHP.
 *
 * Modo real por defecto (requiere servidor PHP + base de datos).
 * Modo demo: agregar ?demo=1 a la URL (o window.API_CONFIG.demo = true).
 */
const API = (() => {
    const DEMO_MODE =
        /[?&]demo=/.test(location.search) ||
        (window.API_CONFIG && window.API_CONFIG.demo === true);

    const BASE_URL = (window.Core && Core.apiBase) || '/backend/api/';
    const API_AVAILABLE = !DEMO_MODE;

    /* ============================================
       Base de datos de demostracion (en memoria)
       ============================================ */

    const GAME_IMAGES = {
        'League of Legends': () => Core.asset('League of Legends.png'),
        'Counter-Strike 2': () => Core.asset('Counter Strike 2.png'),
        'Fortnite': () => Core.asset('Fortnite.png')
    };

    const GAME_CLASS = {
        'League of Legends': 'lol',
        'Counter-Strike 2': 'cs2',
        'Fortnite': 'fn'
    };

    const demoDB = {
        guides: [
            {
                id: 1, title: 'Como dominar el macro en League of Legends', game: 'League of Legends',
                author: 'JohnDoe', description: 'Control de oleadas, vision y toma de decisiones para subir de elo.',
                date: '2026-09-01', votes: 128, content: 'El macro es la habilidad de tomar las decisiones correctas...'
            },
            {
                id: 2, title: 'Lineups de humo en Mirage - CS2', game: 'Counter-Strike 2',
                author: 'SmokeMaster', description: 'Los mejores lineups de humo para Mirage.',
                date: '2026-08-28', votes: 96, content: 'En Mirage los humos en ventana (palace) y coctelera son esenciales...'
            },
            {
                id: 3, title: 'Meta de armas temporada 5 - Fortnite', game: 'Fortnite',
                author: 'VictoryRoyale', description: 'Ranking de armas segun el parche actual.',
                date: '2026-08-25', votes: 74, content: 'La temporada 5 trae cambios importantes en drops y rotaciones...'
            },
            {
                id: 4, title: 'Economia en CS2: cuando comprar y cuando ahorrar', game: 'Counter-Strike 2',
                author: 'EcoMaster', description: 'Ecos, force buys y full buys.',
                date: '2026-08-20', votes: 61, content: 'Entender la economia es clave para ganar rondas importantes...'
            },
            {
                id: 5, title: 'Matchup Zed vs control mages en mid lane', game: 'League of Legends',
                author: 'MidDiff', description: 'Como sobrevivir al early contra Zed.',
                date: '2026-08-15', votes: 45, content: 'Zed es un campeon de all-in temprano; la clave es posicionarse bien...'
            },
            {
                id: 6, title: 'Zonas de drop seguras en Fortnite', game: 'Fortnite',
                author: 'DropMaster', description: 'Mejores zonas de drop para el early game.',
                date: '2026-08-10', votes: 52, content: 'Las zonas de drop determinan tu early y tus rotaciones...'
            }
        ],
        posts: [
            {
                id: 1, title: 'Cual es el mejor campeon para empezar en LoL?', author: 'NewPlayer',
                date: '2026-09-03', excerpt: 'Soy nuevo y no se por quien empezar.', body: 'Recomienden campeones sencillos.',
                comments: [
                    { author: 'MrMid', date: '2026-09-03', text: 'Empieza con Garen o Annie.' },
                    { author: 'CoachTT', date: '2026-09-04', text: 'Juega todos los roles una vez.' }
                ],
                replies: 2
            },
            {
                id: 2, title: 'Economia en CS2: estrategias de ecos', author: 'TeamPilot',
                date: '2026-09-01', excerpt: 'Cuando conviene eco completo?', body: 'En partidas igualadas la economia decide.',
                comments: [
                    { author: 'EcoMaster', date: '2026-09-01', text: 'No compres de mas si perdiste la ronda de pistolas.' }
                ],
                replies: 1
            }
        ],
        users: [
            { id: 1, name: 'Admin', email: 'admin@corett.com', role: 'admin', rank: 'Diamante', guides: 8, votes: 342 },
            { id: 2, name: 'JohnDoe', email: 'john@corett.com', role: 'user', rank: 'Oro', guides: 4, votes: 128 }
        ]
    };

    const session = { votes: {}, createdPosts: [] };

    /* ============================================
       Helpers
       ============================================ */

    const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    async function request(endpoint, method, body, authenticated) {
        method = method || 'GET';
        const options = { method, headers: {} };

        if (method !== 'GET' && method !== 'HEAD') {
            // Solo en peticiones con cuerpo: mandar Content-Type en un GET
            // fuerza un preflight CORS innecesario al cruzar origenes.
            options.headers['Content-Type'] = 'application/json';
        }

        if (authenticated) {
            const token = (window.Auth && Auth.getToken()) || null;
            if (token) {
                options.headers['Authorization'] = 'Bearer ' + token;
            }
        }

        if (body) {
            options.body = JSON.stringify(body);
        }

        let response;
        try {
            response = await fetch(BASE_URL + endpoint, options);
        } catch (err) {
            throw withStatus(new Error('No se pudo conectar con el servidor. Verifica que el backend este corriendo.'), 0);
        }

        let data = null;
        try {
            data = await response.json();
        } catch (err) {
            data = null;
        }

        if (!response.ok) {
            // Se propaga el status: el cliente necesita distinguir un 401
            // (sesion caducada) de un 500.
            throw withStatus(
                new Error((data && data.error) || 'Error ' + response.status + ' en la peticion'),
                response.status
            );
        }
        return data;
    }

    function withStatus(error, status) {
        error.status = status;
        return error;
    }

    function enrichGuide(guide) {
        const extra = DEMO_MODE ? (session.votes[guide.id] || 0) : 0;
        return {
            ...guide,
            image: (GAME_IMAGES[guide.game] || (() => ''))(),
            gameClass: GAME_CLASS[guide.game] || '',
            votes: (guide.votes || 0) + extra
        };
    }

    /* ============================================
       Guias
       ============================================ */

    async function getGuides(options) {
        options = options || {};
        if (API_AVAILABLE) {
            const params = new URLSearchParams();
            if (options.game && options.game !== 'Todos') params.set('game', options.game);
            if (options.search) params.set('search', options.search);
            if (options.page) params.set('page', options.page);
            if (options.limit) params.set('limit', options.limit);
            if (options.sort) params.set('sort', options.sort);

            const qs = params.toString();
            const data = await request('guides.php' + (qs ? '?' + qs : ''));
            const guides = (data.guides || []).map(enrichGuide);
            guides.total = data.total || 0;
            return guides;
        }

        await delay(200);
        let guides = [...demoDB.guides];
        if (options.game && options.game !== 'Todos') {
            guides = guides.filter((g) => g.game === options.game);
        }
        if (options.search) {
            const q = options.search.toLowerCase();
            guides = guides.filter((g) =>
                g.title.toLowerCase().includes(q) ||
                g.author.toLowerCase().includes(q) ||
                g.description.toLowerCase().includes(q)
            );
        }
        const result = guides.map(enrichGuide);
        result.total = guides.length;
        return result;
    }

    async function getGuide(id) {
        if (API_AVAILABLE) {
            return enrichGuide(await request('guides.php?id=' + encodeURIComponent(id)));
        }
        await delay(150);
        const guide = demoDB.guides.find((g) => g.id === Number(id));
        if (!guide) throw new Error('Guia no encontrada');
        return enrichGuide(guide);
    }

    async function getMyGuides() {
        if (API_AVAILABLE) {
            const data = await request('guides.php?action=my', 'GET', null, true);
            const guides = (data || []).map(enrichGuide);
            guides.total = guides.length;
            return guides;
        }
        await delay(150);
        const user = window.Auth ? Auth.getCurrentUser() : null;
        const guides = (user ? demoDB.guides.filter((g) => g.author === user.name) : [])
            .map(enrichGuide);
        guides.total = guides.length;
        return guides;
    }

    async function voteGuide(id, vote) {
        if (API_AVAILABLE) {
            return request('guides.php?action=vote', 'POST', { id, vote }, true);
        }
        await delay(120);
        session.votes[id] = (session.votes[id] || 0) + vote;
        return { success: true, votes: demoDB.guides.find((g) => g.id === Number(id)).votes + session.votes[id] };
    }

    async function createGuide(data) {
        if (API_AVAILABLE) {
            return request('guides.php', 'POST', data, true);
        }
        await delay(200);
        const user = window.Auth ? Auth.getCurrentUser() : null;
        demoDB.guides.unshift({
            id: demoDB.guides.length + 100,
            title: data.title,
            game: data.game,
            description: data.description,
            content: data.content,
            author: user ? user.name : 'Anonimo',
            date: new Date().toISOString().slice(0, 10),
            votes: 0
        });
        return { success: true };
    }

    async function updateGuide(id, data) {
        if (API_AVAILABLE) {
            return request('guides.php?id=' + encodeURIComponent(id), 'PUT', data, true);
        }
        await delay(200);
        const guide = demoDB.guides.find((g) => g.id === Number(id));
        if (guide) Object.assign(guide, data);
        return { success: true };
    }

    async function deleteGuide(id) {
        if (API_AVAILABLE) {
            return request('guides.php?id=' + encodeURIComponent(id), 'DELETE', null, true);
        }
        await delay(120);
        demoDB.guides = demoDB.guides.filter((g) => g.id !== Number(id));
        return { success: true };
    }

    /* ============================================
       Foro
       ============================================ */

    async function getPosts(options) {
        options = options || {};
        if (API_AVAILABLE) {
            const params = new URLSearchParams();
            if (options.page) params.set('page', options.page);
            if (options.limit) params.set('limit', options.limit);
            if (options.search) params.set('search', options.search);
            const qs = params.toString();
            const data = await request('forum.php' + (qs ? '?' + qs : ''));
            const posts = data.posts || [];
            posts.total = data.total || 0;
            return posts;
        }
        await delay(200);
        const posts = [...demoDB.posts, ...session.createdPosts];
        posts.total = posts.length;
        return posts;
    }

    async function getPost(id) {
        if (API_AVAILABLE) {
            return request('forum.php?id=' + encodeURIComponent(id));
        }
        await delay(150);
        const post = [...demoDB.posts, ...session.createdPosts].find((p) => p.id === Number(id));
        if (!post) throw new Error('Post no encontrado');
        return post;
    }

    async function createPost(data) {
        if (API_AVAILABLE) {
            return request('forum.php', 'POST', data, true);
        }
        await delay(200);
        const user = window.Auth ? Auth.getCurrentUser() : null;
        session.createdPosts.unshift({
            id: demoDB.posts.length + session.createdPosts.length + 200,
            title: data.title,
            body: data.body,
            excerpt: data.body.slice(0, 120) + (data.body.length > 120 ? '...' : ''),
            author: user ? user.name : 'Anonimo',
            date: new Date().toISOString().slice(0, 10),
            comments: [],
            replies: 0
        });
        return { success: true };
    }

    async function addComment(postId, text) {
        if (API_AVAILABLE) {
            return request('forum.php?action=comment', 'POST', { postId, text }, true);
        }
        await delay(120);
        const user = window.Auth ? Auth.getCurrentUser() : null;
        const post = [...demoDB.posts, ...session.createdPosts].find((p) => p.id === Number(postId));
        if (post) {
            post.comments.push({
                author: user ? user.name : 'Anonimo',
                date: new Date().toISOString().slice(0, 10),
                text: text
            });
            post.replies = post.comments.length;
        }
        return { success: true, text };
    }

    async function deletePost(id) {
        if (API_AVAILABLE) {
            return request('forum.php?id=' + encodeURIComponent(id), 'DELETE', null, true);
        }
        await delay(120);
        demoDB.posts = demoDB.posts.filter((p) => p.id !== Number(id));
        session.createdPosts = session.createdPosts.filter((p) => p.id !== Number(id));
        return { success: true };
    }

    /* ============================================
       Juegos
       ============================================ */

    async function getGames() {
        if (API_AVAILABLE) {
            return request('games.php');
        }
        await delay(100);
        return Object.keys(GAME_IMAGES).map((name) => ({
            name,
            slug: GAME_CLASS[name],
            patch: null,
            stats_count: 0
        }));
    }

    /* ============================================
       Usuarios / Auth
       ============================================ */

    function normalizeUser(raw) {
        if (!raw) return raw;
        return {
            id: raw.id,
            name: raw.name || raw.nombre || '',
            email: raw.email || '',
            role: raw.role || raw.rol || 'user',
            rank: raw.rank || raw.rango || null,
            guides: raw.guides != null ? raw.guides : 0,
            votes: raw.votes != null ? raw.votes : 0,
            reputation: raw.reputation != null ? raw.reputation : 0
        };
    }

    async function login(email, password) {
        if (API_AVAILABLE) {
            const data = await request('users.php?action=login', 'POST', { email, password });
            return { ...data, user: normalizeUser(data.user) };
        }
        await delay(400);
        // El modo demo concedia rol admin solo con teclear el email, sin
        // comprobar la contrasena. Ahora exige una contrasena explicita.
        if (email.toLowerCase() === 'admin@corett.com' && password === 'demo') {
            return { success: true, token: 'demo-admin-token', user: demoDB.users[0] };
        }
        if (email && password.length >= 4) {
            return {
                success: true,
                token: 'demo-token-' + Date.now(),
                user: { id: 999, name: email.split('@')[0], email, role: 'user', rank: 'Oro', guides: 0, votes: 0 }
            };
        }
        throw new Error('Credenciales invalidas');
    }

    async function register(name, email, password) {
        if (API_AVAILABLE) {
            const data = await request('users.php?action=register', 'POST', { name, email, password });
            return { ...data, user: normalizeUser(data.user) };
        }
        await delay(400);
        if (name && email && password.length >= 4) {
            return { success: true, message: 'Cuenta creada correctamente. Ahora puedes iniciar sesion.' };
        }
        throw new Error('Datos incompletos o contrasena demasiado corta');
    }

    async function logout() {
        if (API_AVAILABLE) {
            try {
                return await request('users.php?action=logout', 'POST', {}, true);
            } catch (err) {
                // No bloquear el logout local si el servidor no responde
            }
        }
        return { success: true };
    }

    async function getUsers() {
        if (API_AVAILABLE) {
            const data = await request('users.php', 'GET', null, true);
            return data.users || [];
        }
        await delay(150);
        return demoDB.users;
    }

    async function changeRole(id, role) {
        if (API_AVAILABLE) {
            return request('users.php?action=role', 'POST', { id, rol: role }, true);
        }
        await delay(120);
        const user = demoDB.users.find((u) => u.id === Number(id));
        if (user) user.role = role;
        return { success: true };
    }

    async function deleteUser(id) {
        if (API_AVAILABLE) {
            return request('users.php?id=' + encodeURIComponent(id), 'DELETE', null, true);
        }
        await delay(120);
        demoDB.users = demoDB.users.filter((u) => u.id !== Number(id));
        return { success: true };
    }

    async function getProfile() {
        if (API_AVAILABLE) {
            return normalizeUser(await request('users.php?action=profile', 'GET', null, true));
        }
        await delay(150);
        const user = window.Auth ? Auth.getCurrentUser() : null;
        if (!user) throw new Error('No autenticado');
        return {
            name: user.name,
            email: user.email,
            rank: user.rank || 'Sin rango',
            guides: user.guides || demoDB.guides.filter((g) => g.author === user.name).length,
            votes: user.votes || demoDB.guides.filter((g) => g.author === user.name).reduce((sum, g) => sum + g.votes, 0),
            reputation: user.role === 'admin' ? 100 : demoDB.guides.filter((g) => g.author === user.name).length * 10
        };
    }

    async function updateProfile(data) {
        if (API_AVAILABLE) {
            return request('users.php?action=profile', 'PUT', data, true);
        }
        await delay(150);
        return { success: true };
    }

    /* ============================================
       Interfaz publica
       ============================================ */

    return {
        isAvailable: () => API_AVAILABLE,
        getGames,
        getGuides,
        getGuide,
        getMyGuides,
        voteGuide,
        createGuide,
        updateGuide,
        deleteGuide,
        getPosts,
        getPost,
        createPost,
        addComment,
        deletePost,
        getUsers,
        changeRole,
        deleteUser,
        getProfile,
        updateProfile,
        login,
        register,
        logout
    };
})();

// Expuesto en window para que el orden de carga de los scripts no importe
// (igual que Core).
window.API = API;