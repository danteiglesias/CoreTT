/**
 * CoreTT - Panel de Administración
 * Modera usuarios, guías y posts. Solo visible para administradores.
 */
document.addEventListener('DOMContentLoaded', () => {
    Auth.init();
    guardAdmin();
});

function guardAdmin() {
    const content = document.getElementById('adminContent');
    if (!content) return;

    const user = Auth.getCurrentUser();

    if (!user || user.role !== 'admin') {
        content.innerHTML = `
            <h2 class="section-title">Acceso Restringido</h2>
            <div class="alert alert-danger">
                Esta área es solo para administradores.
                ${user ? '<a href="profile.html" style="color:inherit;">Volver a mi perfil</a>' : ''}
            </div>
        `;
        return;
    }

    renderAdminPanel();
}

function renderAdminPanel() {
    const content = document.getElementById('adminContent');
    if (!content) return;

    content.innerHTML = `
        <h2 class="section-title">Panel de Administración</h2>

        <div class="admin-tabs">
            <button class="filter-btn active" data-tab="users">Usuarios</button>
            <button class="filter-btn" data-tab="guides">Guías</button>
            <button class="filter-btn" data-tab="posts">Posts</button>
        </div>

        <div class="admin-panel" id="adminPanel"></div>
    `;

    document.querySelectorAll('.admin-tabs .filter-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.admin-tabs .filter-btn').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            loadTab(btn.dataset.tab);
        });
    });

    loadTab('users');
}

async function loadTab(tab) {
    const panel = document.getElementById('adminPanel');
    if (!panel) return;

    panel.innerHTML = '<div class="empty-state">Cargando...</div>';

    try {
        if (tab === 'users') {
            const users = await API.getUsers();
            renderUsers(users);
        } else if (tab === 'guides') {
            const guides = await API.getGuides({ game: 'Todos' });
            renderGuides(guides);
        } else if (tab === 'posts') {
            const posts = await API.getPosts();
            renderPosts(posts);
        }
    } catch (error) {
        console.error('Error en panel admin:', error);
        panel.innerHTML = '<div class="alert alert-danger">No se pudieron cargar los datos.</div>';
    }
}

function renderUsers(users) {
    const panel = document.getElementById('adminPanel');
    if (!panel) return;

    panel.innerHTML = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Rango</th>
                    <th>Guías</th>
                    <th>Reputación</th>
                </tr>
            </thead>
            <tbody>
                ${users.map((u) => `
                    <tr>
                        <td><strong>${escapeHtml(u.name)}</strong><div class="user-email">${escapeHtml(u.email)}</div></td>
                        <td>${u.role === 'admin' ? '<span class="badge">Admin</span>' : 'Usuario'}</td>
                        <td>${escapeHtml(u.rank)}</td>
                        <td>${u.guides}</td>
                        <td>${u.reputation ?? u.reputacion ?? 0}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function renderGuides(guides) {
    const panel = document.getElementById('adminPanel');
    if (!panel) return;

    panel.innerHTML = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Juego</th>
                    <th>Autor</th>
                    <th>Votos</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                ${guides.map((g) => `
                    <tr>
                        <td>${escapeHtml(g.title)}</td>
                        <td><span class="badge ${escapeHtml(g.gameClass)}">${escapeHtml(g.game)}</span></td>
                        <td>${escapeHtml(g.author)}</td>
                        <td>${g.votes}</td>
                        <td><button class="btn btn-danger btn-sm" data-del-guide="${g.id}">Eliminar</button></td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    panel.querySelectorAll('[data-del-guide]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('¿Eliminar esta guía?')) return;
            await API.deleteGuide(Number(btn.dataset.delGuide));
            loadTab('guides');
        });
    });
}

function renderPosts(posts) {
    const panel = document.getElementById('adminPanel');
    if (!panel) return;

    panel.innerHTML = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Autor</th>
                    <th>Fecha</th>
                    <th>Respuestas</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                ${posts.map((p) => `
                    <tr>
                        <td>${escapeHtml(p.title)}</td>
                        <td>${escapeHtml(p.author)}</td>
                        <td>${escapeHtml(p.date)}</td>
                        <td>${p.replies || 0}</td>
                        <td><button class="btn btn-danger btn-sm" data-del-post="${p.id}">Eliminar</button></td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    panel.querySelectorAll('[data-del-post]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('¿Eliminar este post?')) return;
            await API.deletePost(Number(btn.dataset.delPost));
            loadTab('posts');
        });
    });
}

function escapeHtml(text) {
    if (typeof text !== 'string') return text;
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}