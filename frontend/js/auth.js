/**
 * CoreTT - Authentication
 * Manejo de sesión de usuario y modales de login/registro.
 */
const Auth = (() => {
    let currentUser = null;
    const TOKEN_KEY = 'corett_token';
    const USER_KEY = 'corett_user';

    function init() {
        // Cargar sesión guardada
        const savedUser = localStorage.getItem(USER_KEY);
        if (savedUser) {
            try {
                currentUser = JSON.parse(savedUser);
            } catch (e) {
                currentUser = null;
            }
        }

        bindNavbar();
        bindModalEvents();
        updateUI();
    }

    /* ---------- Menú móvil ---------- */
    function bindNavbar() {
        const toggle = document.getElementById('navToggle');
        const navLinks = document.getElementById('navLinks');

        if (toggle && navLinks) {
            toggle.addEventListener('click', () => {
                navLinks.classList.toggle('active');
            });

            navLinks.querySelectorAll('a').forEach((link) => {
                link.addEventListener('click', () => {
                    navLinks.classList.remove('active');
                });
            });
        }
    }

    /**
     * Comprueba si hay sesión; si no, abre el modal de login.
     * @returns {boolean} true si está autenticado
     */
    function requireAuth() {
        if (isLoggedIn()) return true;
        openModal('loginModal');
        return false;
    }

    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.style.display = 'block';
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.style.display = 'none';
            // Limpiar errores anteriores y resetear formulario
            const error = modal.querySelector('.form-error');
            if (error) error.textContent = '';
            const form = modal.querySelector('form');
            if (form) form.reset();
        }
    }

    function getToken() {
        return localStorage.getItem(TOKEN_KEY);
    }

    function getCurrentUser() {
        return currentUser;
    }

    function isLoggedIn() {
        return currentUser !== null;
    }

    function bindModalEvents() {
        const loginBtn = document.getElementById('loginBtn');
        const logoutBtn = document.getElementById('logoutBtn');
        const loginModal = document.getElementById('loginModal');
        const signupModal = document.getElementById('signupModal');
        const loginForm = document.getElementById('loginForm');
        const signupForm = document.getElementById('signupForm');

        // Abrir modal de login
        if (loginBtn) {
            loginBtn.addEventListener('click', (e) => {
                e.preventDefault();
                openModal('loginModal');
            });
        }

        // Cerrar sesión
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                logout();
            });
        }

        // Cambiar entre modales
        const signupLink = document.getElementById('signupLink');
        const loginLink = document.getElementById('loginLink');
        if (signupLink && loginLink) {
            signupLink.addEventListener('click', (e) => {
                e.preventDefault();
                closeModal('loginModal');
                openModal('signupModal');
            });
            loginLink.addEventListener('click', (e) => {
                e.preventDefault();
                closeModal('signupModal');
                openModal('loginModal');
            });
        }

        // Envío de formularios
        if (loginForm) {
            loginForm.addEventListener('submit', handleLogin);
        }
        if (signupForm) {
            signupForm.addEventListener('submit', handleSignup);
        }

        // Cerrar modales (X, o clic fuera) - una sola vez
        document.querySelectorAll('.close').forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-close') || 'loginModal';
                closeModal(target);
            });
        });
        if (!window.__corettModalOutsideBound) {
            window.addEventListener('click', (e) => {
                if (e.target.classList && e.target.classList.contains('modal')) {
                    closeModal(e.target.id);
                }
            });
            window.__corettModalOutsideBound = true;
        }
        // Cerrar con Escape
        if (!window.__corettModalEscBound) {
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal').forEach((m) => {
                        if (m.style.display === 'block') closeModal(m.id);
                    });
                }
            });
            window.__corettModalEscBound = true;
        }
    }

    function showError(form, message) {
        const errorEl = form.parentElement.querySelector('.form-error') || form.querySelector('.form-error');
        if (errorEl) {
            errorEl.textContent = message;
        }
    }

    async function handleLogin(e) {
        e.preventDefault();
        const email = document.getElementById('loginEmail').value.trim();
        const password = document.getElementById('loginPassword').value;
        const submitBtn = e.target.querySelector('button[type="submit"]');

        try {
            showError(e.target, '');
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showError(e.target, 'Email no válido');
                return;
            }
            if (submitBtn) submitBtn.disabled = true;
            const result = await API.login(email, password);

            if (result.success) {
                setSession(result.token, result.user);
                closeModal('loginModal');
                e.target.reset();
                // Refrescar perfil/admin si estamos en esas páginas
                if (typeof loadProfile === 'function' && document.getElementById('profileContent')) loadProfile();
                if (typeof guardAdmin === 'function' && document.getElementById('adminContent')) guardAdmin();
            } else {
                showError(e.target, result.message || 'No se pudo iniciar sesión');
            }
        } catch (err) {
            showError(e.target, err.message);
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    async function handleSignup(e) {
        e.preventDefault();
        const name = document.getElementById('signupName').value.trim();
        const email = document.getElementById('signupEmail').value.trim();
        const password = document.getElementById('signupPassword').value;
        const submitBtn = e.target.querySelector('button[type="submit"]');

        try {
            showError(e.target, '');
            if (name.length < 3) {
                showError(e.target, 'El nombre debe tener al menos 3 caracteres');
                return;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showError(e.target, 'Email no válido');
                return;
            }
            if (password.length < 4) {
                showError(e.target, 'La contraseña debe tener al menos 4 caracteres');
                return;
            }
            if (submitBtn) submitBtn.disabled = true;
            const result = await API.register(name, email, password);

            if (result.success) {
                closeModal('signupModal');
                e.target.reset();
                openModal('loginModal');
            } else {
                showError(e.target, result.message || 'No se pudo crear la cuenta');
            }
        } catch (err) {
            showError(e.target, err.message);
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    function setSession(token, user) {
        if (token) localStorage.setItem(TOKEN_KEY, token);
        localStorage.setItem(USER_KEY, JSON.stringify(user));
        currentUser = user;
        updateUI();
    }

    function logout() {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
        currentUser = null;
        updateUI();
        // Limpiar vistas protegidas sin recarga completa
        const profile = document.getElementById('profileContent');
        if (profile && typeof loadProfile === 'function') loadProfile();
        const admin = document.getElementById('adminContent');
        if (admin && typeof guardAdmin === 'function') guardAdmin();
    }

    function updateUI() {
        const loginBtn = document.getElementById('loginBtn');
        const logoutBtn = document.getElementById('logoutBtn');
        const profileLink = document.getElementById('profileLink');

        const loggedIn = isLoggedIn();
        if (loginBtn) loginBtn.style.display = loggedIn ? 'none' : '';
        if (logoutBtn) logoutBtn.style.display = loggedIn ? '' : 'none';
        if (profileLink) profileLink.style.display = loggedIn ? '' : 'none';
    }

    return {
        init,
        getToken,
        getCurrentUser,
        isLoggedIn,
        requireAuth,
        openModal,
        closeModal,
        setSession,
        logout
    };
})();
