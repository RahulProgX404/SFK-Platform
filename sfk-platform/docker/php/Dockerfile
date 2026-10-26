# PHP-FPM image for the SFK platform (development & base for production builds).
FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl bcmath zip gd opcache pcntl exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-platform.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-platform.conf

# The vendor directory is the mount point of the `vendor` volume (docker-compose.yml); owned by www-data so
# composer can write it.
RUN groupmod -o -g "${GID}" www-data && usermod -o -u "${UID}" -g www-data www-data \
    && mkdir -p /var/www/html/vendor && chown www-data:www-data /var/www/html/vendor

WORKDIR /var/www/html
USER www-data
