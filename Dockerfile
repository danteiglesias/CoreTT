FROM php:8.2-apache

# Extension para la conexion con MySQL/MariaDB
RUN docker-php-ext-install pdo_mysql

# Permite configurar rutas amigables (opcional)
RUN a2enmod rewrite headers

WORKDIR /var/www/html

# La imagen oficial trae "AllowOverride None" para /var/www/, lo que hacia que
# el .htaccess del proyecto se ignorase por completo: sin el, /.git/config y
# /.env se servian como texto plano y el listado de directorios estaba activo.
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Bloquea el acceso a los archivos sensibles directamente en la config del
# servidor, no solo en .htaccess (defensa en profundidad: si el override falla,
# estas reglas siguen aplicando).
RUN printf '%s\n' \
    '<Directory /var/www/html>' \
    '    Options -Indexes -FollowSymLinks' \
    '    AllowOverride All' \
    '    Require all granted' \
    '</Directory>' \
    \
    '# Nunca servir el historial de Git ni la configuracion.' \
    '<DirectoryMatch "/var/www/html/\.git">' \
    '    Require all denied' \
    '</DirectoryMatch>' \
    '<FilesMatch "^\.env(\..*)?$">' \
    '    Require all denied' \
    '</FilesMatch>' \
    '<FilesMatch "\.(sql|md|lock|ini|log|bak|json)$">' \
    '    Require all denied' \
    '</FilesMatch>' \
    \
    '# No anunciar la version de PHP.' \
    '<IfModule mod_php.c>' \
    '    php_admin_flag expose_php Off' \
    '    php_admin_flag display_errors Off' \
    '    php_admin_value error_log /dev/stderr' \
    '    php_admin_value upload_max_filesize 2M' \
    '    php_admin_value post_max_size 2M' \
    '</IfModule>' \
    > /etc/apache2/conf-available/corett-security.conf \
    && a2enconf corett-security

# El contenedor no necesita root.
RUN useradd --system --create-home --home-dir /var/www --shell /usr/sbin/nologin corett \
    && chown -R corett:corett /var/www/html
USER corett
