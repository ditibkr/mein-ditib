FROM php:8.3-cli-alpine

# 1. System-Abhängigkeiten (linux-headers hinzugefügt!)
RUN apk add --no-cache \
    $PHPIZE_DEPS \
    linux-headers \
    sqlite-dev \
    qpdf \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    imap-dev \
    krb5-dev \
    openssl-dev \
    unzip \
    bash \
    icu-dev

# 2. PHP-Erweiterungen
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install pdo pdo_sqlite zip gd imap

# 3. Xdebug installieren (jetzt mit den nötigen Headern)
RUN pecl install xdebug && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# 4. Composer wie gehabt
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

# ... hier geht es weiter mit deinem COPY composer.json etc.

COPY composer.json .
RUN composer install --no-dev --optimize-autoloader

COPY . .

RUN mkdir -p output data logs pdf_templates \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 output data logs

COPY docker/crontab /etc/cron.d/spendenportal
RUN chmod 0644 /etc/cron.d/spendenportal && crontab /etc/cron.d/spendenportal

COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 3232
CMD ["/start.sh"]
