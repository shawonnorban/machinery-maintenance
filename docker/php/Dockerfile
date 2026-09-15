# syntax=docker/dockerfile:1
# machinery-maintenance — Laravel API runtime (PHP-FPM)
#
# composer.json declares "php": "^8.3", but composer.lock (uncommitted changes
# already in the working tree before this pass — laravel/sanctum and
# spatie/laravel-permission added) now pulls symfony 8.x components requiring
# PHP >=8.4.1. The local WAMP install this repo actually runs on is PHP
# 8.4.15. Built against 8.4 to match what the lock file and the real dev
# environment both need — see the infra notes in
# docs/12-Stack-Migration-Implementation-Plan.md for the CI/composer.json
# mismatch this uncovers, which is pre-existing and unrelated to Docker.
FROM php:8.4-fpm-alpine

# postgresql-client gives pg_dump/psql for ad-hoc backups and debugging.
RUN apk update && apk add --no-cache \
        postgresql-client \
        bash git curl unzip icu-data-full \
        icu-dev postgresql-dev libzip-dev oniguruma-dev linux-headers \
        libpng-dev libjpeg-turbo-dev freetype-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS

# Extensions this app actually uses:
#   pdo_pgsql/pgsql -> PostgreSQL          redis   -> cache, queues, Reverb
#   bcmath          -> money/decimal comparisons (NumberSequenceGenerator,
#                      ApprovalRule condition matching use bccomp)
#   pcntl/posix/sockets -> queue workers and the Reverb websocket server
#   gd/zip          -> imports/exports and image handling
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pgsql \
    && docker-php-ext-install intl zip bcmath \
    && docker-php-ext-install gd exif \
    && docker-php-ext-install pcntl posix sockets

RUN pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/machinery.ini

WORKDIR /var/www/html

# Source is bind-mounted in development; nothing is baked into this image.
EXPOSE 9000
CMD ["php-fpm"]
