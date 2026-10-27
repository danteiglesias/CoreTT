# CORETT - Plataforma de Guías y Meta Competitivo

> Una comunidad estratégica y academia colaborativa para jugadores de League of Legends, Counter-Strike 2 y Fortnite.

## Descripción

CORETT es una pagina web impulsada por la comunidad, diseñada para ir más allá de las estadísticas crudas. Funciona como un híbrido entre un foro estructurado y una base de datos del *meta* actual. Permite a los jugadores aprender, debatir y compartir estrategias (guías de macrojuego, lineups, análisis de parches) validadas mediante un sistema de reputación por votos.

## Características Principales

- **Sistema de Reputación y Validación:** Las guías y estrategias suben o bajan de relevancia en base a los votos de la comunidad (Upvote/Downvote), garantizando contenido útil y actualizado.
- **Enfoque Multi-Juego (Módulos Específicos):**
  - **League of Legends:** Análisis de *matchups*, control de oleadas y rutas de jungla por parche.
  - **Counter-Strike 2:** Directorio interactivo de *lineups* (humos, destellos, molotovs) y guías de economía.
  - **Fortnite:** Evolución del meta de armas por temporada, rotaciones y zonas de drop.
- **Gestión de Usuarios Avanzada:** Perfiles personalizados con rango verificado, roles principales e historial de contribuciones al foro.
- **Diseño Relacional Robusto:** Base de datos estructurada para manejar distintas entidades complejas (Juegos, Parches/Temporadas, Guías de Usuarios, Votos, Estadísticas Oficiales).
- **API RESTful en PHP:** Backend completo con endpoints para todas las funcionalidades.
- **Frontend Responsive:** Interfaz adaptable a móviles y escritorio.

## Tecnologías Utilizadas

- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Backend:** PHP 8.x con PDO
- **Base de Datos:** MySQL/MariaDB
- **Servidor:** Compatible con Apache/Nginx en Linux/Windows

## Instalación y Despliegue

### Requisitos
- Servidor web con PHP 7.4+ o PHP 8.x
- MySQL 5.7+ o MariaDB 10.2+
- Acceso a phpMyAdmin o línea de comandos MySQL

### Pasos para despliegue

1. **Clonar o subir archivos**
   ```bash
   git clone [repositorio]
   ```

2. **Crear base de datos**
   - Abrir phpMyAdmin
   - Crear base de datos llamada `corett_db` (utf8mb4_unicode_ci)
   - Importar `database/schema.sql`
   - Importar `database/datos_iniciales.sql` (opcional, para datos de prueba)

3. **Configurar conexión**
   - Copiar `.env.example` a `.env`
   - Editar `.env` con los datos de tu servidor:
   ```env
   DB_HOST=localhost
   DB_NAME=corett_db
   DB_USER=tu_usuario
   DB_PASS=tu_contraseña
   ```

4. **Configurar permisos**
   - Asegurar permisos de lectura/escritura en el servidor
   - Para producción, cambiar `corett_secret_key` en `backend/includes/functions.php`

5. **Probar instalación**
   - Acceder a `http://tu-dominio/backend/` para verificar la API
   - Acceder a `http://tu-dominio/` para ver el frontend

### Cuentas de prueba
- **Admin:** admin@corett.com / password
- **Usuario:** john@corett.com / password

## Estructura del Proyecto

```
CoreTT/
├── backend/           # API REST en PHP
│   ├── api/          # Endpoints (users, guides, forum, games, votes)
│   ├── config/       # Configuración de BD
│   └── includes/     # Funciones, auth, validaciones
├── database/         # Scripts SQL
│   ├── schema.sql    # Estructura de la BD
│   └── datos_iniciales.sql # Datos de prueba
├── frontend/         # Aplicación web
│   ├── assets/       # Imágenes
│   ├── css/          # Estilos
│   ├── js/           # JavaScript
│   └── pages/        # Páginas HTML
└── docs/             # Documentación adicional
```

## Estructura de la Base de Datos (Entidades Principales)

El proyecto se sostiene sobre un modelo Entidad-Relación que gestiona:
1. `usuarios` - Credenciales, perfil, rango, reputación, roles
2. `juegos` - Ecosistema (LoL, CS2, Fortnite)
3. `parches_temporadas` - Control de parches por juego
4. `guias` - Contenido generado por el usuario, vinculado a juego y parche
5. `foro_posts` - Publicaciones del foro
6. `foro_comentarios` - Comentarios en posts
7. `votos_guias` - Sistema de votos upvote/downvote
8. `meta_stats` - Estadísticas meta por juego/categoría

## Seguridad

- Contraseñas hasheadas con bcrypt
- Autenticación basada en tokens
- Validación de datos en servidor
- Protección contra SQL injection con prepared statements
- CORS configurado
- Sanitización de inputs

## Licencia

Ver archivo LICENSE para más detalles.
