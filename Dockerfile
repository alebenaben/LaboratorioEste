# =====================================================================
# Integral del Este — imagen "todo en uno" para EasyPanel (o cualquier Docker).
# =====================================================================
# Un solo contenedor con:
#   · la aplicación web (Apache + PHP 8.3), puerto 80;
#   · el worker (CAE de ARCA y envío de mails);
#   · las copias de seguridad con restic (solo si se configura el bucket);
#   · las migraciones de la base, que se aplican solas en cada arranque.
#
# Única variable obligatoria:
#   DATABASE_URL=postgres://usuario:clave@host:5432/base
#   (en EasyPanel: la "Internal connection URL" del servicio Postgres)
#
# Todo lo demás es opcional; ver docs/EASYPANEL.md.
# El despliegue con Docker Compose (deploy/) sigue usando deploy/Dockerfile.
# =====================================================================

# ---- dependencias PHP (Composer) ----
FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --ignore-platform-reqs --no-autoloader
COPY src/ src/
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---- imagen final ----
FROM php:8.3-apache-bookworm

# Cliente de PostgreSQL de la misma versión que el servidor (pg_dump exige ser igual o más nuevo).
ARG PG_MAJOR=17

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates curl; \
    install -d /usr/share/postgresql-common/pgdg; \
    curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc; \
    echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" \
      > /etc/apt/sources.list.d/pgdg.list; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
      libpq5 libicu72 libzip4 libpng16-16 libjpeg62-turbo libfreetype6 \
      postgresql-client-${PG_MAJOR} restic supervisor tzdata unzip; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip gd pcntl opcache; \
    apt-mark manual libpq5 libicu72 libzip4 libpng16-16 libjpeg62-turbo libfreetype6; \
    apt-get purge -y --auto-remove libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev; \
    rm -rf /var/lib/apt/lists/*; \
    php -m | grep -qi pdo_pgsql; pg_dump --version; restic version; supervisord --version

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
COPY deploy/backup/backup.sh deploy/backup/loop.sh /
COPY deploy/integral/supervisord.conf /etc/supervisor/integral.conf
COPY deploy/integral/entrypoint.sh /usr/local/bin/integral-entrypoint

RUN chmod +x /backup.sh /loop.sh /usr/local/bin/integral-entrypoint bin/integral bin/iniciar-worker.sh \
 && ln -s /var/www/integral/bin/integral /usr/local/bin/integral \
 && mkdir -p storage/mails storage/arca storage/restic-cache \
 && chown -R www-data:www-data storage

ENV TZ=America/Argentina/Buenos_Aires \
    APP_ENV=produccion \
    APP_DIR=/var/www/integral \
    TRUST_PROXY=1 \
    RESTIC_CACHE_DIR=/var/www/integral/storage/restic-cache

EXPOSE 80
# start-period: el primer arranque crea la base (migraciones) antes de levantar Apache.
HEALTHCHECK --interval=30s --timeout=5s --start-period=120s --retries=3 CMD ["php", "/var/www/integral/bin/salud.php"]

ENTRYPOINT ["integral-entrypoint"]
CMD []
