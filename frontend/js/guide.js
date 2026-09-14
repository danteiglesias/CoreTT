/**
 * CoreTT - Detalle de guia
 * Muestra el contenido completo de una guia, votos y acciones (editar/borrar).
 */
document.addEventListener('DOMContentLoaded', () => {
    Auth.init();
    initEditModal();
    loadGuide();
});

async function loadGuide() {
    const content = document.getElementById('guideContent');
    if (!content) return;

    const id = Core.queryParam('id', '');
    if (!id) {
        content.innerHTML = '<div class="empty-state">Guia no especificada.</div>';
        return;
    }

    content.innerHTML = '<div class="empty-state">Cargando guia...</div>';

    try {
        const guide = await API.getGuide(id);
        renderGuide(guide, content);
    } catch (error) {
        content.innerHTML = `
            <div class="empty-state">No se pudo cargar la guia.</div>
            <div class="text-center mt-1">
                <a href="guides.html" class="btn btn-secondary">Volver a Guias</a>
            </div>
        `;
        Core.notify(error.message, 'error');
    }
}

function renderGuide(guide, container) {
    const paragraphs = String(guide.content || '')
        .split(/\n+/)
        .map((p) => p.trim())
        .filter(Boolean)
        .map((p) => `<p>${Core.escapeHtml(p)}</p>`)
        .join('');

    const authorLink = `profile.html?user=${encodeURIComponent(guide.author)}`;

    container.innerHTML = `
        <a href="guides.html" class="btn btn-secondary btn-sm">&larr; Volver a Guias</a>

        <article class="guide-detail">
            <span class="badge ${Core.escapeHtml(guide.gameClass)}">${Core.escapeHtml(guide.game)}</span>
            ${guide.patch ? `<span class="badge">${Core.escapeHtml(guide.patch)}</span>` : ''}
            <h1>${Core.escapeHtml(guide.title)}</h1>
            <div class="post-meta">
                <span><a href="${authorLink}">${Core.escapeHtml(guide.author)}</a></span>
                <span>&middot;</span>
                <span>${Core.escapeHtml(guide.date || '')}</span>
            </div>

            <div class="vote-group vote-group-lg">
                <button class="vote-btn up ${guide.myVote === 1 ? 'active' : ''}" data-id="${guide.id}" title="Votar arriba">&#9650;</button>
                <span class="guide-votes" id="votes-${guide.id}">${Core.compactNumber(guide.votes || 0)}</span>
                <button class="vote-btn down ${guide.myVote === -1 ? 'active' : ''}" data-id="${guide.id}" title="Votar abajo">&#9660;</button>
            </div>

            ${guide.description ? `<p class="guide-description">${Core.escapeHtml(guide.description)}</p>` : ''}

            <div class="guide-body">${paragraphs}</div>

            <div class="guide-actions">
                ${guide.can_edit ? '<button class="btn btn-secondary btn-sm" id="editGuideBtn">Editar</button>' : ''}
                ${guide.can_delete ? '<button class="btn btn-danger btn-sm" id="deleteGuideBtn">Eliminar</button>' : ''}
            </div>
        </article>
    `;

    container.querySelectorAll('.vote-btn').forEach((btn) => {
        btn.addEventListener('click', () => handleVote(btn, guide));
    });

    const editBtn = document.getElementById('editGuideBtn');
    if (editBtn) {
        editBtn.addEventListener('click', () => openEditModal(guide));
    }

    const deleteBtn = document.getElementById('deleteGuideBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', () => deleteGuide(guide));
    }
}

/* ---------- Votacion ---------- */
async function handleVote(button, guide) {
    const vote = button.classList.contains('up') ? 1 : -1;
    try {
        const result = await API.voteGuide(guide.id, vote);
        const votesEl = document.getElementById('votes-' + guide.id);
        if (votesEl && result.votes !== undefined) {
            votesEl.textContent = Core.compactNumber(result.votes);
        }
        button.closest('.vote-group').querySelectorAll('.vote-btn').forEach((b) => b.classList.remove('active'));
        if (result.myVote !== undefined) {
            if (result.myVote !== 0) {
                const dir = result.myVote === 1 ? '.up' : '.down';
                const activeBtn = button.closest('.vote-group').querySelector(dir);
                if (activeBtn) activeBtn.classList.add('active');
            }
        } else if (result.success) {
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

/* ---------- Edicion ---------- */
function openEditModal(guide) {
    document.getElementById('editGuideTitle').value = guide.title || '';
    document.getElementById('editGuideGame').value = guide.game || '';
    document.getElementById('editGuideDescription').value = guide.description || '';
    document.getElementById('editGuideContent').value = guide.content || '';
    var patchInput = document.getElementById('editGuidePatch');
    if (patchInput) patchInput.value = guide.patch || '';
    document.getElementById('guideEditError').textContent = '';

    window.__editingGuide = guide.id;
    Auth.openModal('editGuideModal');
}

function initEditModal() {
    const form = document.getElementById('editGuideForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errorEl = document.getElementById('guideEditError');

        const data = {
            title: document.getElementById('editGuideTitle').value.trim(),
            game: document.getElementById('editGuideGame').value,
            description: document.getElementById('editGuideDescription').value.trim(),
            content: document.getElementById('editGuideContent').value.trim(),
            patch: (document.getElementById('editGuidePatch') || {}).value
        };

        if (!data.title || !data.game || !data.content) {
            errorEl.textContent = 'Completa titulo, juego y contenido.';
            return;
        }

        try {
            await API.updateGuide(window.__editingGuide, data);
            Auth.closeModal('editGuideModal');
            Core.notify('Guia actualizada.', 'success');
            loadGuide();
        } catch (error) {
            errorEl.textContent = error.message;
        }
    });
}

/* ---------- Borrado ---------- */
async function deleteGuide(guide) {
    if (!confirm('Seguro que quieres eliminar esta guia?')) return;
    try {
        await API.deleteGuide(guide.id);
        Core.notify('Guia eliminada.', 'success');
        window.location.href = 'guides.html';
    } catch (error) {
        Core.notify(error.message, 'error');
    }
}