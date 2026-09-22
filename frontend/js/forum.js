/**
 * CoreTT - Foro
 * Listado de posts, detalle con comentarios y creación de posts.
 */
document.addEventListener('DOMContentLoaded', () => {
    Auth.init();
    initCreatePost();
    loadPosts();
});

let currentPostId = null;

async function loadPosts() {
    const container = document.getElementById('postsContainer');
    if (!container) return;

    try {
        const posts = await API.getPosts();

        if (!posts.length) {
            container.innerHTML = '<div class="empty-state">Aún no hay posts. ¡Sé el primero en publicar!</div>';
            return;
        }

        container.innerHTML = '';
        posts.forEach((post) => {
            const item = document.createElement('div');
            item.className = 'post-item';
            item.dataset.id = post.id;
            item.innerHTML = `
                <h3>${escapeHtml(post.title)}</h3>
                <p class="post-excerpt">${escapeHtml(post.excerpt || '')}</p>
                <div class="post-meta">
                    <span>${escapeHtml(post.author)}</span>
                    <span>&middot;</span>
                    <span>${escapeHtml(post.date)}</span>
                    <span class="post-stats">&#128172; ${Number(post.replies) || 0} respuestas</span>
                </div>
            `;
            item.addEventListener('click', () => showPostDetail(post.id));
            container.appendChild(item);
        });
    } catch (error) {
        console.error('Error cargando posts:', error);
        container.innerHTML = '<div class="empty-state">No se pudieron cargar los posts.</div>';
    }
}

async function showPostDetail(id) {
    currentPostId = id;
    const container = document.getElementById('postsContainer');
    const title = document.getElementById('forumTitle');
    if (!container) return;

    try {
        const post = await API.getPost(id);

        title.textContent = 'Post';
        container.innerHTML = '';

        const detail = document.createElement('div');
        detail.className = 'post-detail';
        detail.innerHTML = `
            <button class="btn btn-secondary btn-sm" id="backBtn">&#8592; Volver al foro</button>
            <h2>${escapeHtml(post.title)}</h2>
            <div class="post-meta">
                <span>${escapeHtml(post.author)}</span>
                <span>&middot;</span>
                <span>${escapeHtml(post.date)}</span>
            </div>
            <div class="post-body">${escapeHtml(post.body || '')}</div>
            <button class="btn btn-primary" id="commentBtn">Responder</button>

            <div class="comments-section">
                <h3>Comentarios (${(post.comments || []).length})</h3>
                <div id="commentsContainer"></div>
            </div>
        `;
        container.appendChild(detail);

        document.getElementById('backBtn').addEventListener('click', () => {
            backToList();
        });
        document.getElementById('commentBtn').addEventListener('click', () => {
            if (Auth.requireAuth()) {
                openCommentEditor();
            }
        });

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
        container.innerHTML = '<div class="empty-state">Sin comentarios todavía. ¡Anímate a responder!</div>';
        return;
    }

    container.innerHTML = '';
    comments.forEach((comment) => {
        const item = document.createElement('div');
        item.className = 'comment';
        item.innerHTML = `
            <div class="post-meta">
                <span>${escapeHtml(comment.author)}</span>
                <span>&middot;</span>
                <span>${escapeHtml(comment.date)}</span>
            </div>
            <p class="comment-body">${escapeHtml(comment.text)}</p>
        `;
        container.appendChild(item);
    });
}

function openCommentEditor() {
    const container = document.getElementById('commentsContainer');
    if (!container) return;
    // Evita editores duplicados
    if (document.getElementById('submitCommentBtn')) return;

    const editor = document.createElement('div');
    editor.className = 'comment';
    editor.innerHTML = `
        <textarea placeholder="Escribe tu comentario..." class="comment-input" maxlength="2000"></textarea>
        <div class="mt-1">
            <button class="btn btn-primary btn-sm" id="submitCommentBtn">Enviar</button>
            <button class="btn btn-secondary btn-sm" id="cancelCommentBtn">Cancelar</button>
        </div>
    `;
    container.prepend(editor);

    document.getElementById('submitCommentBtn').addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const text = editor.querySelector('textarea').value.trim();
        if (!text) return;

        btn.disabled = true;
        try {
            await API.addComment(currentPostId, text);
            const post = await API.getPost(currentPostId);
            renderComments(post.comments || []);
        } catch (error) {
            console.error('Error al comentar:', error);
        } finally {
            btn.disabled = false;
        }
    });
    document.getElementById('cancelCommentBtn').addEventListener('click', () => {
        editor.remove();
    });
}

function backToList() {
    currentPostId = null;
    const title = document.getElementById('forumTitle');
    if (title) title.textContent = 'Foro de Discusión';
    loadPosts();
}

function initCreatePost() {
    const openBtn = document.getElementById('createPostBtn');
    const form = document.getElementById('newPostForm');

    if (openBtn) {
        openBtn.addEventListener('click', () => {
            if (Auth.requireAuth()) {
                Auth.openModal('newPostModal');
            }
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
                loadPosts();
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