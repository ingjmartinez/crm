FROM node:20-alpine AS node-build

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/ resources/
COPY vite.config.js ./
RUN npm run build

FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git unzip \
    libzip-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libxml2-dev \
    libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql mbstring exif pcntl bcmath zip gd intl soap \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .
COPY --from=node-build /app/public/build /var/www/public/build
COPY --from=node-build /app/public/build /opt/crm-build
COPY docker/entrypoint.sh /usr/local/bin/crm-entrypoint

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress \
    || (sleep 5 && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress) \
    || (sleep 10 && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress)

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

ENTRYPOINT ["/bin/sh", "/usr/local/bin/crm-entrypoint"]
CMD ["php-fpm"]
