FROM php:8.4-fpm-alpine

# System-Abhängigkeiten
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    oniguruma-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    icu-dev \
    linux-headers \
    $PHPIZE_DEPS

# PHP Extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        opcache \
        xml \
        zip

# Redis Extension
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP-Konfiguration
COPY docker/app/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/app/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# App-User anlegen
RUN addgroup -g 1000 -S www && adduser -u 1000 -S www -G www

COPY --chown=www:www . .

RUN composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

USER www

EXPOSE 9000

CMD ["php-fpm"]
