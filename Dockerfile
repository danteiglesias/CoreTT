FROM php:8.2-apache

# Extension para la conexion con MySQL/MariaDB
RUN docker-php-ext-install pdo_mysql

# Permite configurar rutas amigables (opcional)
RUN a2enmod rewrite

WORKDIR /var/www/html