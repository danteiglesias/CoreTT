/**
 * CoreTT - Layout compartido.
 * Inyecta la barra de navegacion, el footer y los modales de sesion
 * (login/signup) en todas las paginas para evitar codigo duplicado.
 * Debe cargarse despues de core.js y antes de auth.js.
 */
(function () {
    const inPages = /\/pages\//.test(location.pathname);
    const href = (rel) => (inPages ? rel : 'pages/' + rel);
    const home = inPages ? '../index.html' : 'index.html';

    function navbarHTML() {
        return `
        <nav class="navbar">
            <div class="container">
                <div class="logo">CoreTT</div>
                <button class="nav-toggle" id="navToggle" aria-label="Abrir menu">&#9776;</button>
                <ul class="nav-links" id="navLinks">
                    <li><a href="${home}">Inicio</a></li>
                    <li><a href="${href('guides.html')}">Guias</a></li>
                    <li><a href="${href('forum.html')}">Foro</a></li>
                    <li><a href="${href('profile.html')}" id="profileLink" style="display:none;">Perfil</a></li>
                    <li><a href="#" id="loginBtn">Iniciar Sesion</a></li>
                    <li><a href="#" id="logoutBtn" style="display:none;">Cerrar Sesion</a></li>
                </ul>
            </div>
        </nav>`;
    }

    function footerHTML() {
        return `<footer class="footer"><p>&copy; 2026 CoreTT. Todos los derechos reservados.</p></footer>`;
    }

    function modalsHTML() {
        return `
        <!-- Modal de Login -->
        <div id="loginModal" class="modal">
            <div class="modal-content">
                <span class="close" data-close="loginModal">&times;</span>
                <h2>Iniciar Sesion</h2>
                <form id="loginForm">
                    <input type="email" id="loginEmail" placeholder="Email" required>
                    <input type="password" id="loginPassword" placeholder="Contrasena" required>
                    <div id="loginError" class="form-error"></div>
                    <button type="submit" class="btn btn-primary">Entrar</button>
                </form>
                <p>No tienes cuenta? <a href="#" id="signupLink">Registrate</a></p>
            </div>
        </div>

        <!-- Modal de Registro -->
        <div id="signupModal" class="modal">
            <div class="modal-content">
                <span class="close" data-close="signupModal">&times;</span>
                <h2>Registrarse</h2>
                <form id="signupForm">
                    <input type="text" id="signupName" placeholder="Nombre de usuario" required>
                    <input type="email" id="signupEmail" placeholder="Email" required>
                    <input type="password" id="signupPassword" placeholder="Contrasena (min. 8 caracteres)" required>
                    <div id="signupError" class="form-error"></div>
                    <button type="submit" class="btn btn-primary">Crear cuenta</button>
                </form>
                <p>Ya tienes cuenta? <a href="#" id="loginLink">Inicia sesion</a></p>
            </div>
        </div>`;
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!document.getElementById('navbarStub')) {
            const nav = document.createElement('div');
            nav.id = 'navbarStub';
            nav.innerHTML = navbarHTML();
            document.body.prepend(nav);
        }

        if (!document.querySelector('.footer')) {
            document.body.insertAdjacentHTML('beforeend', footerHTML());
        }

        if (!document.getElementById('loginModal')) {
            document.body.insertAdjacentHTML('beforeend', modalsHTML());
        }
    });
})();