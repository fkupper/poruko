FROM php:8.3-cli-alpine AS base

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
COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

COPY . .

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts

RUN php artisan config:cache || true \
 && php artisan route:cache || true \
 && php artisan view:cache || true

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

