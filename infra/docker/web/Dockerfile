# Imagen de la aplicación Laravel (apps/web): PHP-FPM 8.4 con pdo_pgsql.
# En desarrollo el código se monta desde apps/web (compose.dev.yml); la imagen
# de producción que copia el código se arma en T-024.
FROM php:8.4.26-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev libicu-dev libzip-dev unzip git libfcgi-bin \
    && docker-php-ext-install -j2 pdo_pgsql pgsql intl zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/zz-cofrial.ini
COPY php-fpm.conf /usr/local/etc/php-fpm.d/zz-cofrial.conf
COPY entrypoint.sh /usr/local/bin/cofrial-entrypoint
COPY healthcheck.sh /usr/local/bin/cofrial-healthcheck
RUN chmod 755 /usr/local/bin/cofrial-entrypoint /usr/local/bin/cofrial-healthcheck

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

ENTRYPOINT ["cofrial-entrypoint"]
CMD ["php-fpm"]
