/**
 * CoreTT - Main
 * Inicialización de la página: carga dinámica de contenido, menú móvil y handlers.
 */
document.addEventListener('DOMContentLoaded', () => {
    initNavbar();
    initGetStarted();
    loadGuides();
    loadPosts();
    Auth.init();
});

/* ---------- Navbar / Menú móvil ---------- */
function initNavbar() {
    const toggle = document.getElementById('navToggle');
    const navLinks = document.getElementById('navLinks');

    if (toggle && navLinks) {
        toggle.addEventListener('click', () => {
            navLinks.classList.toggle('active');
        });

        // Cerrar menú al hacer clic en un enlace
        navLinks.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('active');
            });
        });
    }
}

/* ---------- Botón "Comenzar" ---------- */
function initGetStarted() {
    const btn = document.getElementById('getStartedBtn');
    if (btn) {
        btn.addEventListener('click', () => {
            const guidesSection = document.querySelector('.featured-guides');
            if (guidesSection) {
                guidesSection.scrollIntoView({ behavior: 'smooth' });
            }
        });
    }
}

/* ---------- Carga dinámica de guías ---------- */
async function loadGuides() {
    const container = document.getElementById('guidesContainer');
    if (!container) return;

    // Solo renderizamos dinámicamente cuando el backend existe.
    // En modo demo (sin backend) se conserva el HTML estático con las portadas.
    if (!API.isAvailable()) return;

    try {
        const guides = await API.getGuides();

        if (Array.isArray(guides) && guides.length > 0) {
            renderGuides(guides);
        }
    } catch (error) {
        console.error('Error cargando guías:', error);
    }
}

function renderGuides(guides) {
    const container = document.getElementById('guidesContainer');
    if (!container) return;

    const firstGameImages = {
        'League of Legends': 'assets/League of Legends.png',
        'Counter-Strike 2': 'assets/Counter Strike 2.png',
        'Fortnite': 'assets/Fortnite.png'
    };

    const grid = document.createElement('div');
    grid.className = 'guides-grid';

    guides.forEach((guide) => {
        const card = document.createElement('article');
        card.className = 'guide-card';

        const image = firstGameImages[guide.game] || '';
        const imgHtml = image
            ? `<img src="${image}" alt="${guide.game}">`
            : '';

        card.innerHTML = `
            ${imgHtml}
            <h3>${escapeHtml(guide.title)}</h3>
            <p>${escapeHtml(guide.description || '')}</p>
            <div class="guide-meta">
                <span class="guide-author">${escapeHtml(guide.author)}</span>
                <span class="guide-date">${escapeHtml(guide.date || '')}</span>
                <span class="guide-votes">&#9650; ${guide.votes || 0}</span>
            </div>
        `;
        grid.appendChild(card);
    });

    container.innerHTML = '';
    container.appendChild(grid);
}

/* ---------- Carga dinámica de posts ---------- */
async function loadPosts() {
    const container = document.getElementById('postsContainer');
    if (!container) return;

    try {
        const posts = await API.getPosts();

        if (Array.isArray(posts) && posts.length > 0) {
            container.innerHTML = '';
            posts.forEach((post) => {
                const item = document.createElement('div');
                item.className = 'post-item';
                item.innerHTML = `
                    <h3>${escapeHtml(post.title)}</h3>
                    <p>${escapeHtml(post.excerpt || '')}</p>
                    <div class="post-meta">
                        ${escapeHtml(post.author)} &middot; ${escapeHtml(post.date || '')}
                    </div>
                `;
                container.appendChild(item);
            });
        }
    } catch (error) {
        console.error('Error cargando posts:', error);
    }
}

/* ---------- Utilidades ---------- */
function escapeHtml(text) {
    if (typeof text !== 'string') return text;
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
