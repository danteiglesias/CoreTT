/**
 * CoreTT - Perfil
 * Muestra la informacion del usuario autenticado, sus guias y permite editar perfil.
 */
document.addEventListener('DOMContentLoaded', () => {
    Auth.init();
    initEditProfile();
    loadProfile();
});

async function loadProfile() {
    const content = document.getElementById('profileContent');
    if (!content) return;

    if (!Auth.isLoggedIn()) {
        content.innerHTML = `
            <h2 class="section-title">Mi Perfil</h2>
            <div class="alert alert-warning">
                Necesitas <a href="#" id="needLoginLink" style="color:inherit;font-weight:bold;">iniciar sesion</a> para ver tu perfil.
            </div>
            <div class="empty-state">Tu perfil aparece aqui cuando inicias sesion. Con tu cuenta podras publicar guias,
                participar en el foro y ganar reputacion.</div>
        `;

        const link = document.getElementById('needLoginLink');
        if (link) {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                Auth.openModal('loginModal');
            });
        }
        return;
    }

    try {
        const profile = await API.getProfile();
        renderProfile(profile);
        loadMyGuides();
    } catch (error) {
        console.error('Error cargando perfil:', error);
        content.innerHTML = '<div class="alert alert-danger">No se pudo cargar el perfil.</div>';
    }
}

function renderProfile(profile) {
    const content = document.getElementById('profileContent');
    if (!content) return;

    const initial = (profile.name || '?').charAt(0).toUpperCase();
    const isAdmin = Auth.getCurrentUser() && Auth.getCurrentUser().role === 'admin';

    content.innerHTML = `
        <h2 class="section-title">Mi Perfil</h2>

        <div class="profile-card">
            <div class="avatar">${Core.escapeHtml(initial)}</div>
            <h2>${Core.escapeHtml(profile.name)}</h2>
            <p class="profile-email">${Core.escapeHtml(profile.email)}</p>
            <span class="badge">${Core.escapeHtml(profile.rank || 'Sin rango')}</span>
            <button class="btn btn-secondary btn-sm" id="editProfileBtn">Editar perfil</button>
            ${isAdmin ? '<a href="admin.html" class="btn btn-accent btn-sm">Panel Admin</a>' : ''}
        </div>

        <div class="profile-stats">
            <div class="stat-item">
                <span class="stat-number">${Core.escapeHtml(profile.guides ?? 0)}</span>
                <span class="stat-label">Guias publicadas</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">${Core.escapeHtml(profile.votes ?? 0)}</span>
                <span class="stat-label">Votos recibidos</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">${Core.escapeHtml(profile.reputation ?? '--')}</span>
                <span class="stat-label">Reputacion</span>
            </div>
        </div>

        <h3 class="section-title">Mis Guias</h3>
        <div id="myGuidesContainer"><div class="empty-state">Cargando tus guias...</div></div>
    `;

    document.getElementById('editProfileBtn').addEventListener('click', () => {
        document.getElementById('editName').value = profile.name || '';
        document.getElementById('editRank').value = profile.rank || '';
        Auth.openModal('editProfileModal');
    });
}

async function loadMyGuides() {
    const container = document.getElementById('myGuidesContainer');
    if (!container) return;

    try {
        const guides = await API.getMyGuides();
        if (!guides.length) {
            container.innerHTML = '<div class="empty-state">Aun no has publicado guias.</div>';
            return;
        }

        container.innerHTML = '';
        guides.forEach((guide) => {
            const item = document.createElement('div');
            item.className = 'post-item';
            item.innerHTML = `
                <h3><a href="guide.html?id=${encodeURIComponent(guide.id)}">${Core.escapeHtml(guide.title)}</a></h3>
                <div class="post-meta">
                    <span>${Core.escapeHtml(guide.game)}</span>
                    <span>&middot;</span>
                    <span>${Core.escapeHtml(guide.date)}</span>
                    <span class="post-stats">&#9650; ${guide.votes || 0}</span>
                    ${guide.can_delete ? '<button class="btn btn-danger btn-sm" data-del-guide="' + guide.id + '">Eliminar</button>' : ''}
                </div>
            `;
            container.appendChild(item);
        });

        container.querySelectorAll('[data-del-guide]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (!confirm('Seguro que quieres eliminar esta guia?')) return;
                try {
                    await API.deleteGuide(Number(btn.dataset.delGuide));
                    Core.notify('Guia eliminada.', 'success');
                    loadMyGuides();
                } catch (error) {
                    Core.notify(error.message, 'error');
                }
            });
        });
    } catch (error) {
        container.innerHTML = '<div class="empty-state">No se pudieron cargar tus guias.</div>';
    }
}

function initEditProfile() {
    const form = document.getElementById('editProfileForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errorEl = document.getElementById('profileEditError');
        errorEl.textContent = '';

        const data = {
            name: document.getElementById('editName').value.trim(),
            rank: document.getElementById('editRank').value.trim()
        };

        if (!data.name) {
            errorEl.textContent = 'El nombre es obligatorio.';
            return;
        }

        try {
            await API.updateProfile(data);
            Auth.closeModal('editProfileModal');
            Core.notify('Perfil actualizado.', 'success');
            loadProfile();
        } catch (error) {
            errorEl.textContent = error.message;
        }
    });
}