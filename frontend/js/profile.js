/**
 * CoreTT - Perfil
 * Muestra la información del usuario autenticado.
 */
document.addEventListener('DOMContentLoaded', () => {
    Auth.init();
    loadProfile();
});

async function loadProfile() {
    const content = document.getElementById('profileContent');
    if (!content) return;

    // Si no hay sesión, pedir autenticación
    if (!Auth.isLoggedIn()) {
        content.innerHTML = `
            <div class="alert alert-warning">
                Necesitas <a href="#" id="needLoginLink" style="color:inherit;font-weight:bold;">iniciar sesión</a> para ver tu perfil.
            </div>
            <div class="empty-state">Tu perfil aparece aquí cuando inicias sesión. Con tu cuenta podrás publicar guías, participar en el foro y ganar reputación.</div>
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
    } catch (error) {
        console.error('Error cargando perfil:', error);
        content.innerHTML = '<div class="alert alert-danger">No se pudo cargar el perfil.</div>';
    }
}

function renderProfile(profile) {
    const content = document.getElementById('profileContent');
    if (!content) return;

    const initial = ((profile.name || '?').trim().charAt(0) || '?').toUpperCase();
    const currentUser = (typeof Auth !== 'undefined' && Auth.getCurrentUser) ? Auth.getCurrentUser() : null;
    const isAdmin = currentUser && currentUser.role === 'admin';

    content.innerHTML = `
        <h2 class="section-title">Mi Perfil</h2>

        <div class="profile-card">
            <div class="avatar">${escapeHtml(initial)}</div>
            <h2>${escapeHtml(profile.name)}</h2>
            <p class="profile-email">${escapeHtml(profile.email)}</p>
            <span class="badge">${escapeHtml(profile.rank || 'Sin rango')}</span>
            ${isAdmin ? '<span class="badge mt-1">Admin</span>' : ''}
        </div>

        <div class="profile-stats">
            <div class="stat-item">
                <span class="stat-number">${escapeHtml(String(profile.guides ?? 0))}</span>
                <span class="stat-label">Guías publicadas</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">${escapeHtml(String(profile.votes ?? 0))}</span>
                <span class="stat-label">Votos recibidos</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">${escapeHtml(String(profile.reputation ?? '--'))}</span>
                <span class="stat-label">Reputación</span>
            </div>
        </div>

        ${isAdmin ? `
            <div class="text-center">
                <a href="admin.html" class="btn btn-accent">Ir al Panel de Administración</a>
            </div>
        ` : ''}
    `;
}

function escapeHtml(text) {
    if (typeof text !== 'string') return text;
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}