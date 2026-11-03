# syntax=docker/dockerfile:1
# TTE PATROL ENGINE — PHP 8.3 + Apache

# 1) Ambil library Composer (vendor/) tanpa menyertakannya di repo.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --no-scripts --optimize-autoloader

# 2) Runtime aplikasi.
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libonig-dev libxml2-dev libcurl4-openssl-dev libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite mbstring dom curl \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-tte-patrol.ini
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html
COPY . /var/www/html
COPY --from=vendor /app/vendor /var/www/html/vendor

# Folder storage selalu dibuat ulang saat start (isinya dari volume).
RUN rm -rf /var/www/html/storage

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["apache2-foreground"]
