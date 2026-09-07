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

        bindModalEvents();
        updateUI();
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
        loginBtn.addEventListener('click', (e) => {
            e.preventDefault();
            openModal('loginModal');
        });

        // Cerrar sesión
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            logout();
        });

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
                alert(result.message || 'Cuenta creada correctamente. Ahora puedes iniciar sesión.');
            } else {
                showError(e.target, result.message || 'No se pudo crear la cuenta');
            }
        } catch (err) {
            showError(e.target, err.message);
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
        setSession,
        logout
    };
})();
