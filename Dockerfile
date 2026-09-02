# Deliberately a Dockerfile rather than Railway's Nixpacks autodetection: the
# tiling job shells out to the `vips` CLI, which no PHP buildpack installs, and
# the same image has to run somewhere else later without being rebuilt.

# --- Frontend assets -------------------------------------------------------
# Only welcome.blade.php uses @vite; Filament ships its own assets. Built
# anyway so the manifest exists and / doesn't 500.
FROM node:22-bookworm-slim AS assets
WORKDIR /build
COPY package.json package-lock.json* ./
RUN npm install --ignore-scripts
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# --- PHP dependencies ------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
# Scripts are skipped here: package:discover and filament:upgrade need the full
# application tree, which isn't copied yet. They run in the final stage.
RUN composer install \
      --no-dev --no-scripts --no-autoloader \
      --prefer-dist --ignore-platform-reqs

# --- Runtime ---------------------------------------------------------------
FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
      nginx supervisor libvips-tools \
      libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
      libzip-dev libicu-dev libonig-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
      gd zip intl exif bcmath opcache pdo_mysql pdo_sqlite pcntl \
    && apt-get purge -y --auto-remove \
      libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --from=vendor /build/vendor ./vendor
COPY . .
COPY --from=assets /build/public/build ./public/build

# Deferred from the vendor stage, now that artisan and app/ exist.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi \
    && php artisan filament:upgrade

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["/usr/local/bin/entrypoint"]
