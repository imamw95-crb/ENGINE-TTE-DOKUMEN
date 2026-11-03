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
        python3 python3-venv \
    && docker-php-ext-install pdo_sqlite mbstring dom curl \
    && python3 -m venv /opt/tte-py \
    && /opt/tte-py/bin/pip install --no-cache-dir "pymupdf>=1.24,<2" \
    && rm -rf /var/lib/apt/lists/*

# Dipakai bin/siapkan_master.py saat unggah LAMPIRAN 1 dari UI (PyMuPDF).
ENV TTE_PYTHON=/opt/tte-py/bin/python

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
