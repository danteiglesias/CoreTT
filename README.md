# CORETT - Plataforma de Guías y Meta Competitivo

> Una comunidad estratégica y academia colaborativa para jugadores de League of Legends, Counter-Strike 2 y Fortnite.

## Descripción
CORETT es una pagina web impulsada por la comunidad, diseñada para ir más allá de las estadísticas crudas. Funciona como un híbrido entre un foro estructurado y una base de datos del *meta* actual. Permite a los jugadores aprender, debatir y compartir estrategias (guías de macrojuego, lineups, análisis de parches) validadas mediante un sistema de reputación por votos.

## Características Principales

*   **Sistema de Reputación y Validación:** Las guías y estrategias suben o bajan de relevancia en base a los votos de la comunidad (Upvote/Downvote), garantizando contenido útil y actualizado.
*   **Enfoque Multi-Juego (Módulos Específicos):**
*   **League of Legends:** Análisis de *matchups*, control de oleadas y rutas de jungla por parche.
*   **Counter-Strike 2:** Directorio interactivo de *lineups* (humos, destellos, molotovs) y guías de economía.  *   **Fortnite:** Evolución del meta de armas por temporada, rotaciones y zonas de drop.
*   **Gestión de Usuarios Avanzada:** Perfiles personalizados con rango verificado, roles principales e historial de contribuciones al foro.
*   **Diseño Relacional Robusto:** Base de datos estructurada para manejar distintas entidades complejas (Juegos, Parches/Temporadas, Guías de Usuarios, Votos, Estadísticas Oficiales).

## Tecnologías Utilizadas

*   **Frontend:**
*   **Backend:**
*   **Base de Datos:** [MySQL / PHP]
*   **Herramientas Adicionales:**

## Estructura de la Base de Datos (Entidades Principales)

El proyecto se sostiene sobre un modelo Entidad-Relación que gestiona:
1.  `Usuarios`: Credenciales, perfil, rango, reputación.
2.  `Juegos`: Ecosistema (LoL, CS2, Fortnite) y subcategorías (Mapas, Armas, Campeones).
3.  `Guias_Foro`: Contenido generado por el usuario, vinculado a un juego y un parche específico.
4.  `Interacciones`: Sistema relacional de comentarios y votos (Upvotes/Downvotes).
5.  `Meta_Stats`: Tablas de datos (estadísticas base de armas/habilidades) actualizadas por administradores.
