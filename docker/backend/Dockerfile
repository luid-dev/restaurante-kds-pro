# syntax=docker/dockerfile:1

# API Laravel 13 sobre PHP 8.4-FPM (piso del proyecto: spatie/laravel-activitylog v5).
FROM composer:2 AS composer

# Versión fijada: 8.4.27 corrige CVE-2026-109618 y CVE-2026-109619 (RCE, aviso de php.net del 2026-10-21).
FROM php:8.4.27-fpm-alpine

RUN apk add --no-cache icu-libs libpq libxml2 libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libxml2-dev libzip-dev linux-headers postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl opcache pcntl pdo_pgsql soap zip \
    && apk del .build-deps

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/backend/php.ini /usr/local/etc/php/conf.d/zz-restaurante-kds.ini
COPY docker/backend/entrypoint.sh /usr/local/bin/kds-entrypoint

WORKDIR /var/www/html

# Dependencias primero: la capa se reutiliza mientras composer.lock no cambie.
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY backend/ ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
    && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/kds-entrypoint

USER www-data

ENTRYPOINT ["kds-entrypoint"]
CMD ["php-fpm"]
