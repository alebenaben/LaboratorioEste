# Integral del Este — imagen de la aplicación (servicios "app", "worker" y "migrar").
# Se construye desde la raíz del repositorio:  docker compose build

# ---- dependencias PHP (Composer) ----
FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --ignore-platform-reqs --no-autoloader
COPY src/ src/
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---- aplicación ----
FROM php:8.3-apache-bookworm

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip tzdata \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip gd pcntl opcache \
 && apt-get purge -y --auto-remove libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && apt-get install -y --no-install-recommends libpq5 libicu72 libzip4 libpng16-16 libjpeg62-turbo libfreetype6 \
 && rm -rf /var/lib/apt/lists/*

COPY deploy/php/php.ini /usr/local/etc/php/conf.d/integral.ini
COPY deploy/php/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers remoteip \
 && sed -ri 's/ServerTokens OS/ServerTokens Prod/; s/ServerSignature On/ServerSignature Off/' /etc/apache2/conf-available/security.conf

WORKDIR /var/www/integral
COPY --from=vendor /app/vendor/ vendor/
COPY public/ public/
COPY src/ src/
COPY views/ views/
COPY bin/ bin/
COPY database/ database/
RUN mkdir -p storage/mails && chown -R www-data:www-data storage

ENV TZ=America/Argentina/Buenos_Aires
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD php -r "exit(@file_get_contents('http://127.0.0.1/salud') === false ? 1 : 0);"
