# Production image for Render (render.yaml at the repository root): nginx and PHP-FPM in one container — a Render web
# service runs one container, and the free plan has no separate worker — with the code, the Composer packages without
# the development tools, and the PHP settings of docker/php. docker/render/start.sh migrates, caches and starts it.
FROM php:8.4-fpm-bookworm

# qpdf (Apache-2.0): rewrites uploaded 帳票 background PDFs (optional: the code works without it); gettext-base: envsubst
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx gettext-base git unzip libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev qpdf \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl bcmath zip gd opcache pcntl exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-platform.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-platform.conf
COPY docker/render/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-render.conf
COPY docker/render/nginx.conf.template /etc/nginx/templates/default.conf.template

WORKDIR /var/www/html

# the packages first: rebuilt only when composer.lock changes
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
# the views live in the modules; view:cache (php artisan optimize) still looks into resources/views
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache resources/views \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/render/start.sh

# Render sends the traffic to $PORT (10000 unless set)
EXPOSE 10000
CMD ["docker/render/start.sh"]
