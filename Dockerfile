# Single Poruko release image: Laravel API + built SPA + nginx.
# Roles via command / PORUKO_ROLE:
#   api (default): php artisan serve
#   queue / scheduler: override command
#   web: PORUKO_ROLE=web + nginx

FROM node:22-alpine AS web-build

WORKDIR /app

COPY web/package.json web/package-lock.json* ./
RUN npm install --legacy-peer-deps

COPY web/ .
RUN npm run build

FROM php:8.3-cli-alpine AS api-build

RUN apk add --no-cache \
    git \
    zip \
    unzip \
    libpq \
    libpq-dev \
    oniguruma-dev \
    bash \
    icu-dev \
    libzip-dev \
    $PHPIZE_DEPS \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && docker-php-ext-configure intl \
 && docker-php-ext-install \
    pdo_pgsql \
    intl \
    pcntl \
    zip \
    opcache \
 && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY api/docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

COPY api/ .

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts \
 && php artisan package:discover --ansi || true \
 && mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
 && chmod -R ug+rwx storage bootstrap/cache

FROM php:8.3-cli-alpine

RUN apk add --no-cache \
    nginx \
    libpq \
    bash \
    icu-libs \
    libzip \
 && rm -f /etc/nginx/http.d/default.conf \
 && mkdir -p /run/nginx

COPY --from=api-build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=api-build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=api-build /var/www/html /var/www/html
COPY --from=web-build /app/dist /usr/share/nginx/html
COPY web/nginx.conf /etc/nginx/http.d/poruko.conf
COPY api/docker/entrypoint.sh /usr/local/bin/poruko-entrypoint

RUN chmod +x /usr/local/bin/poruko-entrypoint

WORKDIR /var/www/html

EXPOSE 80 8000

ENTRYPOINT ["poruko-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
