#!/bin/sh
# Development command of the app container (docker-compose.yml): php-fpm plus the source watcher
# (docker/php/dev-watch.sh), which keeps OPcache and the route / component caches current.

# caches left from before a restart may not match the source any more; the watcher rebuilds them
rm -f "$APP_ROUTES_CACHE"
rm -rf "$FILAMENT_CACHE_PATH"
mkdir -p "$(dirname "$APP_ROUTES_CACHE")"

sh docker/php/dev-watch.sh &

exec php-fpm
