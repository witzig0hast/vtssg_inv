# syntax=docker/dockerfile:1.7
# VTSSG Inventar (vtssg_inv02) – Multi-Stage-Build
#   target "app"    → PHP-FPM (App, Queue-Worker, Scheduler)
#   target "web"    → Caddy (TLS, statische Dateien, Reverse-Proxy zu PHP-FPM)

ARG PHP_VERSION=8.3

# ── PHP-Basis mit allen Erweiterungen ────────────────────────────────
FROM php:${PHP_VERSION}-fpm-alpine AS base
COPY --from=ghcr.io/mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN apk add --no-cache fcgi tzdata \
 && install-php-extensions pdo_pgsql pgsql intl gd zip bcmath pcntl opcache redis exif \
 && rm -f /usr/local/bin/install-php-extensions
ENV TZ=Europe/Berlin
WORKDIR /var/www/html

# ── Composer-Abhängigkeiten ──────────────────────────────────────────
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
RUN composer dump-autoload --no-dev --classmap-authoritative --optimize --no-scripts

# ── Frontend-Build ───────────────────────────────────────────────────
FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# ── Laufzeit-Image: PHP-FPM ──────────────────────────────────────────
FROM base AS app
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod 0555 /usr/local/bin/entrypoint

COPY --from=vendor /var/www/html/vendor ./vendor
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY lang ./lang
COPY resources/views ./resources/views
COPY routes ./routes
COPY public ./public
COPY artisan composer.json ./
COPY --from=assets /build/public/build ./public/build

# Beschreibbare Verzeichnisse (werden beim ersten Start in benannte Volumes übernommen)
RUN mkdir -p storage/app/private storage/fonts storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R u=rwX,g=rX,o= storage bootstrap/cache \
 && rm -f public/hot

USER www-data
EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm", "-F"]
HEALTHCHECK --interval=15s --timeout=5s --start-period=90s --retries=5 \
  CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

# ── Laufzeit-Image: Caddy (Web) ──────────────────────────────────────
FROM caddy:2-alpine AS web
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
# Pfad identisch zum PHP-Container, damit SCRIPT_FILENAME stimmt
COPY --from=app /var/www/html/public /var/www/html/public
EXPOSE 80 443
