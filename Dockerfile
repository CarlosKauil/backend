FROM php:8.2-apache

# Habilitar módulos Apache
RUN a2enmod rewrite headers proxy_http

# Instalar extensiones PHP necesarias
RUN apt-get update && apt-get install -y \
    libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libxml2-dev libonig-dev libcurl4-openssl-dev unzip zip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo pdo_mysql xml curl opcache

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

# Copiar el código fuente de Laravel dentro del contenedor
COPY . /var/www/html

WORKDIR /var/www/html

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader

# Permisos necesarios
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 755 /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \;

# Copiar configuración de Apache para Laravel y habilitar sitio
COPY laravel.conf /etc/apache2/sites-available/laravel.conf

RUN a2dissite 000-default.conf \
    && a2ensite laravel.conf \
    && a2enmod rewrite

# Exponer puerto 80
EXPOSE 80

# Comando para iniciar Apache en primer plano
CMD ["apache2-foreground"]