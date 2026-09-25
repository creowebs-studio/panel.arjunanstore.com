# Tahap 1: build frontend (Ant Design + React via Vite) → public/build.
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.4-fpm-alpine

# System deps: git/zip/unzip, mysql client, lib headers for extensions.
# Frontend sudah dibangun di tahap 'frontend' — runtime PHP tidak butuh node.
RUN apk add --no-cache \
        git curl unzip bash \
        mysql-client \
        icu-dev libzip-dev oniguruma-dev

RUN set -eux; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring exif pcntl bcmath gd intl zip sockets; \
    pecl install redis && docker-php-ext-enable redis; \
    apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependensi PHP dipasang dari composer.lock milik repo (vendor TIDAK ikut image).
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress

# Kode aplikasi — repo root ADALAH aplikasi (.dockerignore mengecualikan
# vendor/, storage/, out/, tools/, docs/, workbook, dll).
COPY . .

# Aset frontend hasil build tahap 'frontend' (manifest.json + chunks).
COPY --from=frontend /app/public/build ./public/build

RUN chmod +x /var/www/html/entrypoint.sh

EXPOSE 8000

# Boot: .env + APP_KEY + tunggu MySQL + migrate/seed + php artisan serve.
CMD ["bash", "/var/www/html/entrypoint.sh"]
