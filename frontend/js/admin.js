/**
 * CoreTT - Panel de Administracion
 * Modera usuarios, guias y posts. Solo visible para administradores.
 */
document.addEventListener('DOMContentLoaded', () => {
    Auth.init();
    guardAdmin();
});

function showAccessDenied() {
    const content = document.getElementById('adminContent');
    if (!content) return;
    const user = Auth.getCurrentUser();
    content.innerHTML = `
        <h2 class="section-title">Acceso Restringido</h2>
        <div class="alert alert-danger">
            Esta area es solo para administradores.
            ${user ? '<a href="profile.html" style="color:inherit;">Volver a mi perfil</a>' : ''}
        </div>
    `;
}

async function guardAdmin() {
    const content = document.getElementById('adminContent');
    if (!content) return;

    if (!Auth.isLoggedIn()) {
        showAccessDenied();
        return;
    }

    // El rol se pide al servidor, no se lee del localStorage: ese valor es
    // editable desde el navegador, asi que usarlo como decision de acceso
    // permitiria abrir el panel admin escribiendo role:"admin" a mano.
    // (El backend igual valida require_admin, pero la UI no debe mentir.)
    try {
        const profile = await API.getProfile();
        if (!profile || profile.role !== 'admin') {
            showAccessDenied();
            return;
        }
    } catch (error) {
        showAccessDenied();
        return;
    }

    renderAdminPanel();
}

function renderAdminPanel() {
    const content = document.getElementById('adminContent');
    if (!content) return;

    content.innerHTML = `
        <h2 class="section-title">Panel de Administracion</h2>

        <div class="admin-tabs">
            <button class="filter-btn active" data-tab="users">Usuarios</button>
            <button class="filter-btn" data-tab="guides">Guias</button>
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
            renderUsers(await API.getUsers());
        } else if (tab === 'guides') {
            renderGuides(await API.getGuides({ game: 'Todos' }));
        } else if (tab === 'posts') {
            renderPosts(await API.getPosts());
        }
    } catch (error) {
        console.error('Error en panel admin:', error);
        panel.innerHTML = '<div class="alert alert-danger">No se pudieron cargar los datos.</div>';
    }
}

function renderUsers(users) {
    const panel = document.getElementById('adminPanel');
    if (!panel) return;

    if (!users || !users.length) {
        panel.innerHTML = '<div class="empty-state">Sin usuarios.</div>';
        return;
    }

    panel.innerHTML = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Rango</th>
                    <th>Guias</th>
                    <th>Reputacion</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                ${users.map((u) => `
                    <tr>
                        <td><strong>${Core.escapeHtml(u.name)}</strong><div class="user-email">${Core.escapeHtml(u.email)}</div></td>
                        <td>${u.role === 'admin' ? '<span class="badge">Admin</span>' : 'Usuario'}</td>
                        <td>${Core.escapeHtml(u.rank || '-')}</td>
                        <td>${u.guides}</td>
                        <td>${u.reputation != null ? u.reputation : u.votes}</td>
                        <td>
                            <button class="btn btn-secondary btn-sm" data-role="${u.id}" data-rol="${u.role}">${u.role === 'admin' ? 'Hacer usuario' : 'Hacer admin'}</button>
                            <button class="btn btn-danger btn-sm" data-del-user="${u.id}">Eliminar</button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    panel.querySelectorAll('[data-role]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const nextRole = btn.dataset.rol === 'admin' ? 'user' : 'admin';
            if (!confirm('Cambiar el rol a ' + (nextRole === 'admin' ? 'administrador' : 'usuario') + '?')) return;
            try {
                await API.changeRole(Number(btn.dataset.role), nextRole);
                Core.notify('Rol actualizado.', 'success');
                loadTab('users');
            } catch (error) {
                Core.notify(error.message, 'error');
            }
        });
    });

    panel.querySelectorAll('[data-del-user]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('Seguro que quieres eliminar este usuario y todo su contenido?')) return;
            try {
                await API.deleteUser(Number(btn.dataset.delUser));
                Core.notify('Usuario eliminado.', 'success');
                loadTab('users');
            } catch (error) {
                Core.notify(error.message, 'error');
            }
        });
    });
}

function renderGuides(guides) {
    const panel = document.getElementById('adminPanel');
    if (!panel) return;

    if (!guides || !guides.length) {
        panel.innerHTML = '<div class="empty-state">Sin guias.</div>';
        return;
    }

    panel.innerHTML = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Titulo</th>
                    <th>Juego</th>
                    <th>Autor</th>
                    <th>Votos</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                ${guides.map((g) => `
                    <tr>
                        <td>${Core.escapeHtml(g.title)}</td>
                        <td><span class="badge ${Core.escapeHtml(g.gameClass)}">${Core.escapeHtml(g.game)}</span></td>
                        <td>${Core.escapeHtml(g.author)}</td>
                        <td>${g.votes}</td>
                        <td><button class="btn btn-danger btn-sm" data-del-guide="${g.id}">Eliminar</button></td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    panel.querySelectorAll('[data-del-guide]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('Eliminar esta guia?')) return;
            try {
                await API.deleteGuide(Number(btn.dataset.delGuide));
                Core.notify('Guia eliminada.', 'success');
                loadTab('guides');
            } catch (error) {
                Core.notify(error.message, 'error');
            }
        });
    });
}

function renderPosts(posts) {
    const panel = document.getElementById('adminPanel');
    if (!panel) return;

    if (!posts || !posts.length) {
        panel.innerHTML = '<div class="empty-state">Sin posts.</div>';
        return;
    }

    panel.innerHTML = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Titulo</th>
                    <th>Autor</th>
                    <th>Fecha</th>
                    <th>Respuestas</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                ${posts.map((p) => `
                    <tr>
                        <td>${Core.escapeHtml(p.title)}</td>
                        <td>${Core.escapeHtml(p.author)}</td>
                        <td>${Core.escapeHtml(p.date)}</td>
                        <td>${p.replies || 0}</td>
                        <td><button class="btn btn-danger btn-sm" data-del-post="${p.id}">Eliminar</button></td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;

    panel.querySelectorAll('[data-del-post]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('Eliminar este post?')) return;
            try {
                await API.deletePost(Number(btn.dataset.delPost));
                Core.notify('Post eliminado.', 'success');
                loadTab('posts');
            } catch (error) {
                Core.notify(error.message, 'error');
            }
        });
    });
}