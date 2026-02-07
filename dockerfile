FROM php:8.3-fpm

# Dependencias del sistema
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql zip mbstring exif pcntl bcmath

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copiamos el proyecto
COPY . .

# Instala dependencias (prod)
RUN composer install --no-dev --optimize-autoloader

# Permisos (en runtime igual se refuerza)
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

CMD ["php-fpm"]
