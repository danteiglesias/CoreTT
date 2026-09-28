/**
 * CoreTT - Foro
 * Listado de posts, detalle con comentarios, creacion de posts y busqueda.
 */
document.addEventListener('DOMContentLoaded', () => {
    Auth.init();
    initCreatePost();
    initSearch();
    initLoadMore();
    loadPosts(true);
});

let currentSearch = '';
let currentPage = 1;
let shownPosts = [];
let currentPostId = null;

const PER_PAGE = 15;

async function loadPosts(reset) {
    const container = document.getElementById('postsContainer');
    if (!container) return;

    if (reset) {
        currentPage = 1;
        shownPosts = [];
    }

    try {
        const posts = await API.getPosts({
            page: currentPage,
            limit: PER_PAGE,
            search: currentSearch
        });

        shownPosts = reset ? posts : shownPosts.concat(posts);
        container.innerHTML = '';

        if (!shownPosts.length) {
            container.innerHTML = '<div class="empty-state">Aun no hay posts. Se el primero en publicar!</div>';
            return;
        }

        shownPosts.forEach((post) => {
            container.appendChild(renderPostItem(post));
        });

        const total = posts.total || shownPosts.length;
        const loadMoreBtn = document.getElementById('loadMorePostsBtn');
        if (loadMoreBtn) {
            loadMoreBtn.style.display = shownPosts.length >= total ? 'none' : '';
        }
    } catch (error) {
        console.error('Error cargando posts:', error);
        container.innerHTML = '<div class="empty-state">No se pudieron cargar los posts.</div>';
    }
}

function renderPostItem(post) {
    const item = document.createElement('div');
    item.className = 'post-item';
    item.dataset.id = post.id;
    item.innerHTML = `
        <h3>${Core.escapeHtml(post.title)}</h3>
        <p class="post-excerpt">${Core.escapeHtml(post.excerpt || '')}</p>
        <div class="post-meta">
            <span>${Core.escapeHtml(post.author)}</span>
            <span>&middot;</span>
            <span>${Core.escapeHtml(post.date)}</span>
            <span class="post-stats">&#128172; ${post.replies || 0} respuestas</span>
        </div>
    `;
    item.addEventListener('click', () => showPostDetail(post.id));
    return item;
}

async function showPostDetail(id) {
    currentPostId = id;
    const container = document.getElementById('postsContainer');
    const title = document.getElementById('forumTitle');
    if (!container) return;

    container.innerHTML = '<div class="empty-state">Cargando post...</div>';

    try {
        const post = await API.getPost(id);

        if (title) title.textContent = 'Post';

        const detail = document.createElement('div');
        detail.className = 'post-detail';
        detail.innerHTML = `
            <button class="btn btn-secondary btn-sm" id="backBtn">&#8592; Volver al foro</button>
            <h2>${Core.escapeHtml(post.title)}</h2>
            <div class="post-meta">
                <span>${Core.escapeHtml(post.author)}</span>
                <span>&middot;</span>
                <span>${Core.escapeHtml(post.date)}</span>
            </div>
            <div class="post-body">${Core.escapeHtml(post.body || '')}</div>
            <div class="post-actions">
                <button class="btn btn-primary" id="commentBtn">Responder</button>
                ${post.can_delete ? '<button class="btn btn-danger" id="deletePostBtn">Eliminar post</button>' : ''}
            </div>

            <div class="comments-section">
                <h3>Comentarios (${(post.comments || []).length})</h3>
                <div id="commentsContainer"></div>
            </div>
        `;
        container.innerHTML = '';
        container.appendChild(detail);

        document.getElementById('backBtn').addEventListener('click', backToList);
        document.getElementById('commentBtn').addEventListener('click', () => {
            if (Auth.requireAuth()) openCommentEditor();
        });

        if (post.can_delete) {
            document.getElementById('deletePostBtn').addEventListener('click', async () => {
                if (!confirm('Seguro que quieres eliminar este post?')) return;
                try {
                    await API.deletePost(post.id);
                    Core.notify('Post eliminado.', 'success');
                    backToList();
                } catch (error) {
                    Core.notify(error.message, 'error');
                }
            });
        }

        renderComments(post.comments || []);
    } catch (error) {
        console.error('Error cargando post:', error);
        backToList();
    }
}

function renderComments(comments) {
    const container = document.getElementById('commentsContainer');
    if (!container) return;

    if (!comments.length) {
        container.innerHTML = '<div class="empty-state">Sin comentarios todavia. Animate a responder!</div>';
        return;
    }

    container.innerHTML = '';
    comments.forEach((comment) => {
        const item = document.createElement('div');
        item.className = 'comment';
        item.innerHTML = `
            <div class="post-meta">
                <span>${Core.escapeHtml(comment.author)}</span>
                <span>&middot;</span>
                <span>${Core.escapeHtml(comment.date)}</span>
            </div>
            <p class="comment-body">${Core.escapeHtml(comment.text)}</p>
        `;
        container.appendChild(item);
    });
}

function openCommentEditor() {
    const container = document.getElementById('commentsContainer');
    if (!container) return;

    const editor = document.createElement('div');
    editor.className = 'comment';
    editor.innerHTML = `
        <textarea placeholder="Escribe tu comentario..." class="comment-input"></textarea>
        <div class="mt-1">
            <button class="btn btn-primary btn-sm" id="submitCommentBtn">Enviar</button>
            <button class="btn btn-secondary btn-sm" id="cancelCommentBtn">Cancelar</button>
        </div>
    `;
    container.prepend(editor);

    document.getElementById('submitCommentBtn').addEventListener('click', async () => {
        const text = editor.querySelector('textarea').value.trim();
        if (!text) return;

        try {
            await API.addComment(currentPostId, text);
            const post = await API.getPost(currentPostId);
            renderComments(post.comments || []);
        } catch (error) {
            Core.notify(error.message, 'error');
        }
    });
    document.getElementById('cancelCommentBtn').addEventListener('click', () => {
        editor.remove();
    });
}

function backToList() {
    currentPostId = null;
    const title = document.getElementById('forumTitle');
    if (title) title.textContent = 'Foro de Discusion';
    loadPosts(true);
}

function initCreatePost() {
    const openBtn = document.getElementById('createPostBtn');
    const form = document.getElementById('newPostForm');

    if (openBtn) {
        openBtn.addEventListener('click', () => {
            if (Auth.requireAuth()) Auth.openModal('newPostModal');
        });
    }

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const errorEl = document.getElementById('postError');

            try {
                errorEl.textContent = '';
                const data = {
                    title: document.getElementById('postTitle').value.trim(),
                    body: document.getElementById('postBody').value.trim()
                };

                if (!data.title || !data.body) {
                    errorEl.textContent = 'Completa los campos del post.';
                    return;
                }

                await API.createPost(data);
                Auth.closeModal('newPostModal');
                form.reset();
                Core.notify('Post publicado.', 'success');
                loadPosts(true);
            } catch (error) {
                errorEl.textContent = error.message;
            }
        });
    }
}

function initSearch() {
    const input = document.getElementById('forumSearch');
    if (!input) return;

    let timeout;
    input.addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            currentSearch = input.value.trim();
            loadPosts(true);
        }, 250);
    });
}

function initLoadMore() {
    const btn = document.getElementById('loadMorePostsBtn');
    if (!btn) return;
    btn.addEventListener('click', () => {
        currentPage += 1;
        loadPosts(false);
    });
}