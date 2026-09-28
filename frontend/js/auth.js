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
                currentUser = normalizeUser(JSON.parse(savedUser));
            } catch (e) {
                currentUser = null;
            }
        }

        bindNavbar();
        bindModalEvents();
        updateUI();

        // El localStorage es editable por el usuario, así que la sesión se
        // confirma contra el servidor: si el token caducó o fue revocado, la
        // interfaz no debe seguir mostrando un panel de administrador.
        refreshSession();
    }

    /**
     * Normaliza la forma del usuario a un unico esquema (name/role/rank).
     *
     * El login devuelve campos en español (rol, rango) y el endpoint de
     * perfil en inglés (role, rank). API ya normaliza, pero setSession
     * tambien se llama desde otros puntos, asi que se normaliza aqui para
     * que el resto del frontend dependa siempre de la misma forma.
     */
    function normalizeUser(user) {
        if (!user || typeof user !== 'object') return null;
        return {
            id: user.id,
            name: user.name || user.nombre || '',
            email: user.email || '',
            role: user.role || user.rol || 'user',
            rank: user.rank !== undefined ? user.rank : (user.rango !== undefined ? user.rango : null),
            reputation: user.reputation !== undefined ? user.reputation : (user.reputacion || 0)
        };
    }

    /**
     * Confirma la sesion contra el servidor y actualiza el rol real.
     * Si el token ya no es valido, limpia el estado local.
     */
    async function refreshSession() {
        if (!getToken()) {
            // Sin token no puede haber sesion valida: se limpia tambien el
            // objeto de usuario que hubiera quedado en localStorage.
            if (currentUser !== null) {
                clearSession();
            }
            return null;
        }
        try {
            const profile = await API.getProfile();
            currentUser = normalizeUser(profile);
            localStorage.setItem(USER_KEY, JSON.stringify(currentUser));
            updateUI();
            return currentUser;
        } catch (error) {
            // 401: la sesion ya no existe en el servidor.
            if (error && error.status === 401) {
                clearSession();
            }
            return null;
        }
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
            // Limpiar errores anteriores
            const error = modal.querySelector('.form-error');
            if (error) error.textContent = '';
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

    function isAdmin() {
        return currentUser !== null && currentUser.role === 'admin';
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

        // Cerrar modales (X, o clic fuera)
        document.querySelectorAll('.close').forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = btn.getAttribute('data-close') || 'loginModal';
                closeModal(target);
            });
        });
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                closeModal(e.target.id);
            }
        });
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

        try {
            showError(e.target, '');
            const result = await API.login(email, password);

            if (result.success) {
                setSession(result.token, result.user);
                closeModal('loginModal');
                e.target.reset();
            } else {
                showError(e.target, result.message || 'No se pudo iniciar sesión');
            }
        } catch (err) {
            showError(e.target, err.message);
        }
    }

    async function handleSignup(e) {
        e.preventDefault();
        const name = document.getElementById('signupName').value.trim();
        const email = document.getElementById('signupEmail').value.trim();
        const password = document.getElementById('signupPassword').value;

        try {
            showError(e.target, '');
            const result = await API.register(name, email, password);

            if (result.success) {
                closeModal('signupModal');
                e.target.reset();
                const message = result.message || 'Cuenta creada correctamente. Ahora puedes iniciar sesion.';
                if (window.Core) {
                    Core.notify(message, 'success');
                } else {
                    alert(message);
                }
            } else {
                showError(e.target, result.message || 'No se pudo crear la cuenta');
            }
        } catch (err) {
            showError(e.target, err.message);
        }
    }

    function setSession(token, user) {
        if (token) localStorage.setItem(TOKEN_KEY, token);
        currentUser = normalizeUser(user);
        localStorage.setItem(USER_KEY, JSON.stringify(currentUser));
        updateUI();
    }

    function clearSession() {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
        currentUser = null;
        updateUI();
    }

    async function logout() {
        // Se espera a que el servidor invalide la sesion antes de limpiar el
        // estado local: hacerlo al reves dejaba tokens de 7 dias vivos si la
        // peticion se cancelaba al navegar.
        if (window.API && API.logout) {
            try {
                await API.logout();
            } catch (error) {
                // Best-effort: la sesion local se limpia igual.
            }
        }
        clearSession();
        if (window.Core) Core.notify('Sesion cerrada');
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
        clearSession,
        refreshSession,
        isAdmin,
        logout
    };
})();

// "const Auth = ..." crea un binding global lexical, no una propiedad de
// window. Sin esta linea, cualquier comprobacion "window.Auth && ..." desde
// otro script daria undefined y se perderia el token en cada peticion.
window.Auth = Auth;
