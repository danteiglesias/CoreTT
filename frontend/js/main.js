/**
 * CoreTT - Main (portada).
 * Inicializacion de la pagina de inicio: carga dinamica de contenido.
 */
document.addEventListener('DOMContentLoaded', () => {
    initGetStarted();
    Auth.init();
    loadGuides();
    loadPosts();
});

/* ---------- Boton "Comenzar" ---------- */
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

/* ---------- Carga dinamica de guias ---------- */
async function loadGuides() {
    const container = document.getElementById('guidesContainer');
    if (!container) return;

    // Sin backend (modo demo) se conserva el HTML estatico con las portadas.
    if (!API.isAvailable()) return;

    try {
        const guides = await API.getGuides();
        if (Array.isArray(guides) && guides.length > 0) {
            container.innerHTML = '';
            renderGuides(guides, container);
        }
    } catch (error) {
        Core.notify(error.message, 'error');
    }
}

function renderGuides(guides, container) {
    // El contenedor (#guidesContainer) ya es .guides-grid: crear otro grid
    // dentro anidaba dos rejillas y rompia el reparto de columnas.
    const fragment = document.createDocumentFragment();

    guides.forEach((guide) => {
        const card = document.createElement('article');
        card.className = 'guide-card';

        const imgHtml = guide.image
            ? `<img src="${Core.escapeHtml(guide.image)}" alt="${Core.escapeHtml(guide.game)}" loading="lazy">`
            : '';

        card.innerHTML = `
            ${imgHtml}
            <h3><a href="pages/guide.html?id=${encodeURIComponent(guide.id)}">${Core.escapeHtml(guide.title)}</a></h3>
            <p>${Core.escapeHtml(guide.description || '')}</p>
            <div class="guide-meta">
                <span class="guide-author">${Core.escapeHtml(guide.author)}</span>
                <span class="guide-date">${Core.escapeHtml(guide.date || '')}</span>
                <span class="guide-votes">&#9650; ${Core.compactNumber(guide.votes || 0)}</span>
            </div>
        `;
        fragment.appendChild(card);
    });

    container.appendChild(fragment);
}

/* ---------- Carga dinamica de posts ---------- */
async function loadPosts() {
    const container = document.getElementById('postsContainer');
    if (!container) return;

    try {
        const posts = await API.getPosts();

        if (Array.isArray(posts) && posts.length > 0) {
            container.innerHTML = '';
            posts.slice(0, 5).forEach((post) => {
                const item = document.createElement('div');
                item.className = 'post-item';
                item.innerHTML = `
                    <h3>${Core.escapeHtml(post.title)}</h3>
                    <p>${Core.escapeHtml(post.excerpt || '')}</p>
                    <div class="post-meta">
                        ${Core.escapeHtml(post.author)} &middot; ${Core.escapeHtml(post.date || '')}
                    </div>
                `;
                item.addEventListener('click', () => {
                    window.location.href = 'pages/forum.html';
                });
                container.appendChild(item);
            });
        }
    } catch (error) {
        Core.notify(error.message, 'error');
    }
}