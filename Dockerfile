# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# PHP base: PHP 8.4 FPM + the extensions the app needs (docs/deploy/CHECKLIST.md §1)
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath gd intl opcache pcntl pdo_mysql pdo_pgsql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# Composer dependencies (production only)
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# ---------------------------------------------------------------------------
# Front-end assets (Vite + Tailwind). Tailwind scans vendor pagination views.
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# ---------------------------------------------------------------------------
# Application image: php-fpm (app), queue worker and scheduler all use this.
# ---------------------------------------------------------------------------
FROM base AS app

COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/zz-eslip.ini"
COPY docker/entrypoint.sh /usr/local/bin/eslip-entrypoint

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/eslip-entrypoint

USER www-data

ENTRYPOINT ["eslip-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Web server: nginx serving public/ and passing PHP to the app container.
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
