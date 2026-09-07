/**
 * CoreTT - Guías
 * Listado de guías con filtros por juego, búsqueda y votación.
 */
document.addEventListener('DOMContentLoaded', () => {
    Auth.init();
    initFilters();
    initSearch();
    initCreateGuide();
    loadGuides();
});

let currentFilter = 'Todos';
let allGuides = [];

async function loadGuides() {
    const container = document.getElementById('guidesContainer');
    if (!container) return;

    try {
        allGuides = await API.getGuides({ game: currentFilter });
        renderGuides(allGuides);
    } catch (error) {
        console.error('Error cargando guías:', error);
        container.innerHTML = '<div class="empty-state">No se pudieron cargar las guías.</div>';
    }
}

function renderGuides(guides) {
    const container = document.getElementById('guidesContainer');
    if (!container) return;

    if (!guides.length) {
        container.innerHTML = '<div class="empty-state">No hay guías para esta selección.</div>';
        return;
    }

    container.innerHTML = '';
    guides.forEach((guide) => {
        const card = document.createElement('article');
        card.className = 'guide-card';

        card.innerHTML = `
            <img src="${escapeHtml(guide.image)}" alt="${escapeHtml(guide.game)}">
            <span class="badge ${escapeHtml(guide.gameClass)}">${escapeHtml(guide.game)}</span>
            <h3>${escapeHtml(guide.title)}</h3>
            <p>${escapeHtml(guide.description || '')}</p>
            <div class="guide-meta">
                <span class="guide-author">${escapeHtml(guide.author)}</span>
                <span>${escapeHtml(guide.date)}</span>
                <div class="vote-group">
                    <button class="vote-btn up" data-id="${guide.id}" title="Votar arriba">&#9650;</button>
                    <span class="guide-votes" id="votes-${guide.id}">${guide.votes}</span>
                    <button class="vote-btn down" data-id="${guide.id}" title="Votar abajo">&#9660;</button>
                </div>
            </div>
        `;
        container.appendChild(card);
    });

    // Eventos de votación
    container.querySelectorAll('.vote-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => handleVote(e.currentTarget));
    });
}

async function handleVote(button) {
    const id = Number(button.dataset.id);
    const vote = button.classList.contains('up') ? 1 : -1;

    try {
        const result = await API.voteGuide(id, vote);
        const votesEl = document.getElementById('votes-' + id);
        if (votesEl && result.votes !== undefined) {
            votesEl.textContent = result.votes;
        }
        button.classList.add('active');
    } catch (error) {
        console.error('Error al votar:', error);
    }
}

function initFilters() {
    document.querySelectorAll('.filter-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.game;
            loadGuides();
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
            try {
                const guides = await API.getGuides({ game: currentFilter, search: input.value.trim() });
                renderGuides(guides);
            } catch (error) {
                console.error('Error en búsqueda:', error);
            }
        }, 250);
    });
}

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
                    content: document.getElementById('guideContent').value.trim()
                };

                if (!data.title || !data.game || !data.description || !data.content) {
                    errorEl.textContent = 'Completa todos los campos.';
                    return;
                }

                await API.createGuide(data);
                Auth.closeModal('newGuideModal');
                form.reset();
                loadGuides();
            } catch (error) {
                errorEl.textContent = error.message;
            }
        });
    }
}

function escapeHtml(text) {
    if (typeof text !== 'string') return text;
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}