/**
 * CoreTT - Guias
 * Listado de guias con filtros, busqueda, votacion y paginacion.
 */
document.addEventListener('DOMContentLoaded', () => {
    Auth.init();
    initFilters();
    initSearch();
    initCreateGuide();
    initLoadMore();
    loadGuides(true);
});

let currentFilter = 'Todos';
let currentSearch = '';
let currentPage = 1;
let shownGuides = [];

const PER_PAGE = 12;

async function loadGuides(reset) {
    const container = document.getElementById('guidesContainer');
    if (!container) return;

    if (reset) {
        currentPage = 1;
        shownGuides = [];
    }

    try {
        const guides = await API.getGuides({
            game: currentFilter,
            search: currentSearch,
            page: currentPage,
            limit: PER_PAGE
        });

        shownGuides = reset ? guides : shownGuides.concat(guides);

        if (currentPage === 1) {
            container.innerHTML = '';
        }
        // Solo se pintan las guias recien recibidas al cargar mas: antes se
        // re-renderizaba la lista completa en cada pagina (O(n^2) en el DOM)
        // y se destruian los botones de voto, perdiendo clics en curso.
        renderGuides(reset ? shownGuides : guides, container);

        updateLoadMore(guides.total || shownGuides.length);
    } catch (error) {
        console.error('Error cargando guias:', error);
        container.innerHTML = '<div class="empty-state">No se pudieron cargar las guias.</div>';
    }
}

function renderGuides(guides, container) {
    if (!guides.length) {
        if (!container.children.length) {
            container.innerHTML = '<div class="empty-state">No hay guias para esta seleccion.</div>';
        }
        return;
    }

    const fragment = document.createDocumentFragment();

    guides.forEach((guide) => {
        const card = document.createElement('article');
        card.className = 'guide-card';

        card.innerHTML = `
            <a class="guide-thumb" href="guide.html?id=${encodeURIComponent(guide.id)}">
                <img src="${Core.escapeHtml(guide.image)}" alt="${Core.escapeHtml(guide.game)}" loading="lazy">
            </a>
            <span class="badge ${Core.escapeHtml(guide.gameClass)}">${Core.escapeHtml(guide.game)}</span>
            <h3><a href="guide.html?id=${encodeURIComponent(guide.id)}">${Core.escapeHtml(guide.title)}</a></h3>
            <p>${Core.escapeHtml(guide.description || '')}</p>
            <div class="guide-meta">
                <span class="guide-author">${Core.escapeHtml(guide.author)}</span>
                <span>${Core.escapeHtml(guide.date)}</span>
                <div class="vote-group" data-guide="${guide.id}">
                    <button class="vote-btn up ${guide.myVote === 1 ? 'active' : ''}" data-id="${guide.id}" title="Votar arriba">&#9650;</button>
                    <span class="guide-votes" id="votes-${guide.id}">${guide.votes}</span>
                    <button class="vote-btn down ${guide.myVote === -1 ? 'active' : ''}" data-id="${guide.id}" title="Votar abajo">&#9660;</button>
                </div>
            </div>
        `;
        fragment.appendChild(card);
    });

    container.appendChild(fragment);

    container.querySelectorAll('.vote-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            handleVote(e.currentTarget);
        });
    });
}

/* ---------- Votacion ---------- */
async function handleVote(button) {
    const id = Number(button.dataset.id);
    const vote = button.classList.contains('up') ? 1 : -1;

    try {
        const result = await API.voteGuide(id, vote);
        const votesEl = document.getElementById('votes-' + id);
        if (votesEl && result.votes !== undefined) {
            votesEl.textContent = result.votes;
        }
        // Estado activo segun el voto devuelto por el servidor
        const group = button.closest('.vote-group');
        group.querySelectorAll('.vote-btn').forEach((b) => b.classList.remove('active'));
        if (result.myVote !== undefined && result.myVote !== 0) {
            const direction = result.myVote === 1 ? '.up' : '.down';
            const activeBtn = group.querySelector(direction);
            if (activeBtn) activeBtn.classList.add('active');
        } else if (result.myVote === undefined && result.success) {
            button.classList.add('active'); // modo demo
        }
    } catch (error) {
        if (error.message && error.message.includes('Autenticacion')) {
            Core.notify('Debes iniciar sesion para votar.', 'error');
            Auth.openModal('loginModal');
        } else {
            Core.notify(error.message, 'error');
        }
    }
}

/* ---------- Filtros / Busqueda ---------- */
function initFilters() {
    document.querySelectorAll('.filter-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.game;
            loadGuides(true);
        });
    });
}

function initSearch() {
    const input = document.getElementById('searchInput');
    if (!input) return;

    let timeout;
    input.addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(async () => {
            currentSearch = input.value.trim();
            try {
                loadGuides(true);
            } catch (error) {
                console.error('Error en busqueda:', error);
            }
        }, 250);
    });
}

/* ---------- Paginacion ---------- */
function initLoadMore() {
    const btn = document.getElementById('loadMoreBtn');
    if (!btn) return;

    btn.addEventListener('click', () => {
        currentPage += 1;
        loadGuides(false);
    });
}

function updateLoadMore(total) {
    const btn = document.getElementById('loadMoreBtn');
    if (!btn) return;
    if (shownGuides.length >= total) {
        btn.style.display = 'none';
    } else {
        btn.style.display = '';
    }
}

/* ---------- Crear guia ---------- */
function initCreateGuide() {
    const openBtn = document.getElementById('createGuideBtn');
    const form = document.getElementById('newGuideForm');

    if (openBtn) {
        openBtn.addEventListener('click', () => {
            if (Auth.requireAuth()) {
                Auth.openModal('newGuideModal');
            }
        });
    }

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const errorEl = document.getElementById('guideError');

            try {
                errorEl.textContent = '';
                const data = {
                    title: document.getElementById('guideTitle').value.trim(),
                    game: document.getElementById('guideGame').value,
                    description: document.getElementById('guideDescription').value.trim(),
                    content: document.getElementById('guideContent').value.trim(),
                    patch: (document.getElementById('guidePatch') || {}).value
                };

                if (!data.title || !data.game || !data.description || !data.content) {
                    errorEl.textContent = 'Completa todos los campos.';
                    return;
                }

                await API.createGuide(data);
                Auth.closeModal('newGuideModal');
                form.reset();
                Core.notify('Guia publicada correctamente.', 'success');
                loadGuides(true);
            } catch (error) {
                errorEl.textContent = error.message;
            }
        });
    }
}