/**
 * CoreTT - API Client
 * Capa de comunicación con el backend PHP.
 * Si el backend no está disponible, los métodos usan datos de demo
 * para que el frontend siga funcionando de forma autónoma.
 */
const API = (() => {
    const BASE_URL = '../backend/api/';
    const API_AVAILABLE = false; // Cambiar a true cuando el backend esté listo

    // Datos de demostración (fallback mientras no exista backend)
    const demoGuides = [
        {
            id: 1,
            title: 'Cómo dominar el macro en League of Legends',
            game: 'League of Legends',
            author: 'JohnDoe',
            description: 'Guía completa de control de oleadas y visión para subir de elo.',
            date: '2026-09-01',
            votes: 128
        },
        {
            id: 2,
            title: 'Lineups de humo en Mirage - CS2',
            game: 'Counter-Strike 2',
            author: 'SmokeMaster',
            description: 'Los mejores lineups de humo para Mirage con screenshots.',
            date: '2026-08-28',
            votes: 96
        },
        {
            id: 3,
            title: 'Meta de armas temporada 5 - Fortnite',
            game: 'Fortnite',
            author: 'VictoryRoyale',
            description: 'Ranking de las mejores armas según el último parche.',
            date: '2026-08-25',
            votes: 74
        }
    ];

    const demoTitles = [
        '¿Cuál es el mejor campeón para empezar?',
        'Economía en CS2: cuándo comprar',
        'Zonas de drop seguras en Fortnite',
        'Cómo contrarrestar a Zed en mid lane'
    ];

    const demoPosts = demoTitles.map((title, i) => ({
        id: i + 1,
        title: title,
        author: 'Comunidad',
        date: '2026-09-0' + (i + 1),
        excerpt: 'Discusión abierta de la comunidad sobre estrategias y dudas del meta actual.'
    }));

    // Helper genérico para peticiones fetch
    async function request(endpoint, method = 'GET', body = null, authenticated = false) {
        const options = {
            method: method,
            headers: { 'Content-Type': 'application/json' }
        };

        if (authenticated) {
            const token = Auth ? Auth.getToken() : null;
            if (token) {
                options.headers['Authorization'] = 'Bearer ' + token;
            }
        }

        if (body) {
            options.body = JSON.stringify(body);
        }

        try {
            const response = await fetch(BASE_URL + endpoint, options);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Error en la petición');
            }
            return data;
        } catch (error) {
            console.error('API error:', error);
            throw error;
        }
    }

    return {
        // Cuando el backend no está disponible, devolvemos datos de demo
        isAvailable: () => API_AVAILABLE,

        getGuides: async () => {
            if (API_AVAILABLE) {
                return request('guides.php');
            }
            return demoGuides;
        },

        getPosts: async () => {
            if (API_AVAILABLE) {
                return request('forum.php');
            }
            return demoPosts;
        },

        getGames: async () => {
            if (API_AVAILABLE) {
                return request('games.php');
            }
            return ['League of Legends', 'Counter-Strike 2', 'Fortnite'];
        },

        login: async (email, password) => {
            if (API_AVAILABLE) {
                return request('users.php?action=login', 'POST', { email, password });
            }
            // Simulación de login para demo
            if (email && password) {
                return {
                    success: true,
                    token: 'demo-token-' + Date.now(),
                    user: { name: email.split('@')[0], email: email }
                };
            }
            throw new Error('Credenciales inválidas');
        },

        register: async (name, email, password) => {
            if (API_AVAILABLE) {
                return request('users.php?action=register', 'POST', { name, email, password });
            }
            if (name && email && password) {
                return { success: true, message: 'Cuenta creada correctamente' };
            }
            throw new Error('Datos incompletos');
        },

        // Métodos para cuando el backend esté listo
        getGuide: (id) => request('guides.php?id=' + id),
        voteGuide: (id, vote) => request('guides.php?action=vote', 'POST', { id, vote }, true),
        createGuide: (data) => request('guides.php', 'POST', data, true),
        createPost: (data) => request('forum.php', 'POST', data, true)
    };
})();
