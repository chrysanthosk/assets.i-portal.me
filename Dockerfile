# syntax=docker/dockerfile:1

###############################################
# Stage 1 — Build frontend assets (Vite)
###############################################
FROM node:20-alpine AS frontend

WORKDIR /app

# Install JS dependencies first (better layer caching)
COPY package.json package-lock.json ./
RUN npm ci

# Copy the sources Vite needs and build the production bundle
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build


###############################################
# Stage 2 — Install PHP dependencies (Composer)
###############################################
FROM composer:2 AS vendor

WORKDIR /app

# Install vendor packages without running artisan scripts (no app key / DB yet)
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

# Bring in the full source and build an optimized autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction


###############################################
# Stage 3 — Runtime image (PHP-FPM)
###############################################
FROM php:8.4-fpm AS app

# System libraries required by the PHP extensions below, plus the web server
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        nginx \
        supervisor \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        gd \
        zip \
        exif \
        pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy Composer binary from the official image for runtime use (artisan, updates)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Custom PHP runtime settings + web server / process manager configs
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

# Application source + dependencies + built assets
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

# Ensure writable runtime directories and drop the stock Nginx vhost
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && rm -f /etc/nginx/sites-enabled/default

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Nginx serves the app on port 80; PHP-FPM runs alongside via Supervisor
EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
