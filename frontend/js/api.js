/**
 * CoreTT - API Client
 * Capa de comunicación con el backend PHP.
 * Si el backend no está disponible, los métodos usan datos de demo
 * para que el frontend siga funcionando de forma autónoma.
 */
const API = (() => {
    const BASE_URL = (() => {
        // Funciona desde frontend/index.html y desde frontend/pages/*.html
        return location.pathname.includes('/pages/') ? '../../backend/api/' : '../backend/api/';
    })();
    const API_AVAILABLE = false; // Cambiar a true cuando el backend esté listo

    /* ============================================
       Base de datos de demostración (en memoria)
       ============================================ */

    const ASSET_BASE = location.pathname.includes('/pages/') ? '../assets/' : 'assets/';
    const GAME_IMAGES = {
        'League of Legends': ASSET_BASE + 'League of Legends.png',
        'Counter-Strike 2': ASSET_BASE + 'Counter Strike 2.png',
        'Fortnite': ASSET_BASE + 'Fortnite.png'
    };

    const GAME_CLASS = {
        'League of Legends': 'lol',
        'Counter-Strike 2': 'cs2',
        'Fortnite': 'fn'
    };

    const demoDB = {
        guides: [
            {
                id: 1,
                title: 'Cómo dominar el macro en League of Legends',
                game: 'League of Legends',
                author: 'JohnDoe',
                description: 'Control de oleadas, visión y toma de decisiones para subir de elo.',
                date: '2026-09-01',
                votes: 128,
                content: 'El macrojuego es la habilidad de tomar las decisiones correctas en el momento correcto...'
            },
            {
                id: 2,
                title: 'Lineups de humo en Mirage - CS2',
                game: 'Counter-Strike 2',
                author: 'SmokeMaster',
                description: 'Los mejores lineups de humo para Mirage, con posición y referencia.',
                date: '2026-08-28',
                votes: 96,
                content: 'En Mirage los humos en ventana (palace) y coctelera (CT spawn) son esenciales...'
            },
            {
                id: 3,
                title: 'Meta de armas temporada 5 - Fortnite',
                game: 'Fortnite',
                author: 'VictoryRoyale',
                description: 'Ranking de armas según el parche actual y rotaciones óptimas.',
                date: '2026-08-25',
                votes: 74,
                content: 'La temporada 5 trae cambios importantes en drops y rotaciones de la tormenta...'
            },
            {
                id: 4,
                title: 'Economía en CS2: cuándo comprar y cuándo ahorrar',
                game: 'Counter-Strike 2',
                author: 'EcoMaster',
                description: 'Guía de la economía del equipo: ecos, force buys y full buys.',
                date: '2026-08-20',
                votes: 61,
                content: 'Entender la economía es clave para ganar rondas importantes...'
            },
            {
                id: 5,
                title: 'Matchup Zed vs control mages en mid lane',
                game: 'League of Legends',
                author: 'MidDiff',
                description: 'Cómo sobrevivir al early contra Zed y escalar al mid game.',
                date: '2026-08-15',
                votes: 45,
                content: 'Zed es un campeón de all-in temprano; la clave es el posicionamiento...'
            },
            {
                id: 6,
                title: 'Zonas de drop seguras en Fortnite',
                game: 'Fortnite',
                author: 'DropMaster',
                description: 'Mejores zonas de drop para farmear y sobrevivir al early game.',
                date: '2026-08-10',
                votes: 52,
                content: 'Las zonas de drop determinan tu early game y tus rotaciones...'
            }
        ],
        posts: [
            {
                id: 1,
                title: '¿Cuál es el mejor campeón para empezar en LoL?',
                author: 'NewPlayer',
                date: '2026-09-03',
                excerpt: 'Soy nuevo y no sé por quién empezar. ¿Recomendaciones de campeones sencillos?',
                body: 'Llevo unas semanas jugando y me pierdo con la cantidad de campeones. ¿Cuáles recomiendan para aprender las mecánicas base?',
                comments: [
                    { author: 'MrMid', date: '2026-09-03', text: 'Empieza con Garen o Annie. Son simples y te enseñan conceptos clave.' },
                    { author: 'CoachTT', date: '2026-09-04', text: 'También te recomiendo jugar todos los roles una vez para entender el juego.' }
                ],
                replies: 2
            },
            {
                id: 2,
                title: 'Economía en CS2: estrategias de ecos',
                author: 'TeamPilot',
                date: '2026-09-01',
                excerpt: '¿Cuándo conviene hacer eco completo y cuándo forzar compra?',
                body: 'En partidas igualadas la economía decide muchas rondas. Compartid vuestras reglas de oro.',
                comments: [
                    { author: 'EcoMaster', date: '2026-09-01', text: 'Regla simple: nunca compres de más si perdiste la ronda de pistolas.' },
                    { author: 'SmokeMaster', date: '2026-09-02', text: 'Los force buys funcionan en maps pequeños (Mirage) si ganáis el primer duelo.' }
                ],
                replies: 2
            },
            {
                id: 3,
                title: 'Rotaciones del final de partida en Fortnite',
                author: 'ZoneWatcher',
                date: '2026-08-29',
                excerpt: 'Tips para posicionarse mejor en las últimas zonas.',
                body: 'Siempre me pillan las rotaciones y pierdo por la tormenta. ¿Consejos para el late game?',
                comments: [
                    { author: 'VictoryRoyale', date: '2026-08-30', text: 'Mueve siempre con altura y nunca te quedes sin materiales en la cuarta zona.' }
                ],
                replies: 1
            }
        ],
        users: [
            { id: 1, name: 'Admin', email: 'admin@corett.com', role: 'admin', rank: 'Diamante', guides: 8, votes: 342 },
            { id: 2, name: 'JohnDoe', email: 'john@corett.com', role: 'user', rank: 'Oro', guides: 4, votes: 128 },
            { id: 3, name: 'SmokeMaster', email: 'smoke@corett.com', role: 'user', rank: 'Global Elite', guides: 6, votes: 215 },
            { id: 4, name: 'VictoryRoyale', email: 'victory@corett.com', role: 'user', rank: 'Champion', guides: 3, votes: 96 },
            { id: 5, name: 'NewPlayer', email: 'new@corett.com', role: 'user', rank: 'Bronce', guides: 0, votes: 12 }
        ]
    };

    // Estado de sesión demo de votos y posts creados (en memoria)
    const session = { votes: {}, createdPosts: [] };

    /* ============================================
       Helpers
       ============================================ */

    const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const clamp = (n, min, max) => Math.max(min, Math.min(max, n));

    async function request(endpoint, method = 'GET', body = null, authenticated = false) {
        const options = {
            method: method,
            headers: { 'Content-Type': 'application/json' }
        };

        if (authenticated) {
            const auth = (typeof Auth !== 'undefined') ? Auth : null;
            const token = auth ? auth.getToken() : null;
            if (token) {
                options.headers['Authorization'] = 'Bearer ' + token;
            }
        }

        if (body) {
            options.body = JSON.stringify(body);
        }

        const response = await fetch(BASE_URL + endpoint, options);
        let data = null;
        try {
            data = await response.json();
        } catch (e) {
            throw new Error('Respuesta no válida del servidor');
        }

        if (!response.ok) {
            throw new Error(data.error || 'Error en la petición');
        }
        return data;
    }

    /* ============================================
       Guías
       ============================================ */

    async function getGuides(options = {}) {
        if (API_AVAILABLE) {
            const query = options.game ? ('?game=' + encodeURIComponent(options.game)) : '';
            return request('guides.php' + query);
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

        // Añadir imágenes y clases de juego
        return guides.map((g) => enrichGuide(g));
    }

    function enrichGuide(guide) {
        return {
            ...guide,
            image: GAME_IMAGES[guide.game],
            gameClass: GAME_CLASS[guide.game] || '',
            votes: guide.votes + (session.votes[guide.id] || 0)
        };
    }

    async function getGuide(id) {
        if (API_AVAILABLE) {
            return request('guides.php?id=' + id);
        }
        await delay(150);
        const guide = demoDB.guides.find((g) => g.id === Number(id));
        if (!guide) throw new Error('Guía no encontrada');
        return enrichGuide(guide);
    }

    async function voteGuide(id, vote) {
        // vote: +1 (up) o -1 (down)
        if (API_AVAILABLE) {
            return request('guides.php?action=vote', 'POST', { id, vote }, true);
        }
        await delay(120);
        const nid = Number(id);
        if (vote !== 1 && vote !== -1) throw new Error('Voto inválido');
        const guide = demoDB.guides.find((g) => g.id === nid);
        if (!guide) throw new Error('Guía no encontrada');
        session.votes[nid] = (session.votes[nid] || 0) + vote;
        return { success: true, votes: guide.votes + session.votes[nid] };
    }

    async function createGuide(data) {
        if (API_AVAILABLE) {
            return request('guides.php', 'POST', data, true);
        }
        await delay(200);
        const auth = (typeof Auth !== 'undefined') ? Auth : null;
        const user = auth ? auth.getCurrentUser() : null;
        const newGuide = {
            id: Date.now(),
            title: data.title,
            game: data.game,
            description: data.description,
            content: data.content,
            author: user ? user.name : 'Anónimo',
            date: new Date().toISOString().slice(0, 10),
            votes: 0
        };
        demoDB.guides.unshift(newGuide);
        return { success: true, guide: enrichGuide(newGuide) };
    }

    async function deleteGuide(id) {
        if (API_AVAILABLE) {
            return request('guides.php?id=' + id, 'DELETE', null, true);
        }
        await delay(120);
        demoDB.guides = demoDB.guides.filter((g) => g.id !== Number(id));
        return { success: true };
    }

    /* ============================================
       Foro
       ============================================ */

    async function getPosts() {
        if (API_AVAILABLE) {
            return request('forum.php');
        }
        await delay(200);
        return [...session.createdPosts, ...demoDB.posts];
    }

    async function getPost(id) {
        if (API_AVAILABLE) {
            return request('forum.php?id=' + id);
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
        const auth = (typeof Auth !== 'undefined') ? Auth : null;
        const user = auth ? auth.getCurrentUser() : null;
        const body = String(data.body || '');
        const newPost = {
            id: Date.now(),
            title: data.title,
            body: body,
            excerpt: body.slice(0, 120) + (body.length > 120 ? '...' : ''),
            author: user ? user.name : 'Anónimo',
            date: new Date().toISOString().slice(0, 10),
            comments: [],
            replies: 0
        };
        session.createdPosts.push(newPost);
        return { success: true, post: newPost };
    }

    async function addComment(postId, text) {
        if (API_AVAILABLE) {
            return request('forum.php?action=comment', 'POST', { postId, text }, true);
        }
        await delay(120);
        const auth = (typeof Auth !== 'undefined') ? Auth : null;
        const user = auth ? auth.getCurrentUser() : null;
        const comment = {
            author: user ? user.name : 'Anónimo',
            date: new Date().toISOString().slice(0, 10),
            text: text
        };
        const post = [...demoDB.posts, ...session.createdPosts].find((p) => p.id === Number(postId));
        if (post) {
            post.comments.push(comment);
            post.replies = post.comments.length;
        }
        return { success: true, comment };
    }

    async function deletePost(id) {
        if (API_AVAILABLE) {
            return request('forum.php?id=' + id, 'DELETE', null, true);
        }
        await delay(120);
        demoDB.posts = demoDB.posts.filter((p) => p.id !== Number(id));
        session.createdPosts = session.createdPosts.filter((p) => p.id !== Number(id));
        return { success: true };
    }

    /* ============================================
       Juegos / Otros
       ============================================ */

    async function getGames() {
        if (API_AVAILABLE) {
            return request('games.php');
        }
        await delay(100);
        return Object.keys(GAME_IMAGES);
    }

    async function getUsers() {
        if (API_AVAILABLE) {
            return request('users.php');
        }
        await delay(150);
        return demoDB.users;
    }

    /* ============================================
       Autenticación
       ============================================ */

    async function login(email, password) {
        if (API_AVAILABLE) {
            return request('users.php?action=login', 'POST', { email, password });
        }

        await delay(400);

        // Cuenta admin de demo (exige password mínima, solo demo)
        if (email.toLowerCase() === 'admin@corett.com' && password.length >= 4) {
            return {
                success: true,
                token: 'demo-admin-token',
                user: demoDB.users[0]
            };
        }

        // Cuenta normal de demo (cualquier email con contraseña)
        if (email && password.length >= 4) {
            const name = email.split('@')[0];
            return {
                success: true,
                token: 'demo-token-' + Date.now(),
                user: {
                    id: 999,
                    name: name,
                    email: email,
                    role: 'user',
                    rank: 'Oro',
                    guides: 0,
                    votes: 0
                }
            };
        }
        throw new Error('Credenciales inválidas');
    }

    async function register(name, email, password) {
        if (API_AVAILABLE) {
            return request('users.php?action=register', 'POST', { name, email, password });
        }
        await delay(400);
        if (name && email && password.length >= 4) {
            return { success: true, message: 'Cuenta creada correctamente. Ahora puedes iniciar sesión.' };
        }
        throw new Error('Datos incompletos o contraseña demasiado corta');
    }

    /* ============================================
       Perfil (demo)
       ============================================ */

    async function getProfile() {
        if (API_AVAILABLE) {
            return request('users.php?action=profile', 'GET', null, true);
        }
        await delay(150);
        const auth = (typeof Auth !== 'undefined') ? Auth : null;
        const user = auth ? auth.getCurrentUser() : null;
        if (!user) throw new Error('No autenticado');

        const stats = {
            name: user.name,
            email: user.email,
            rank: user.rank || 'Sin rango',
            guides: user.guides ?? demoDB.guides.filter((g) => g.author === user.name).length,
            votes: user.votes ?? demoDB.guides.filter((g) => g.author === user.name).reduce((sum, g) => sum + g.votes, 0),
            reputation: user.role === 'admin' ? 100 : '--'
        };
        return stats;
    }

    /* ============================================
       Interfaz pública
       ============================================ */

    return {
        isAvailable: () => API_AVAILABLE,
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