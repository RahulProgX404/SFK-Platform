#!/bin/sh
# Start of the Render container (docker/render/Dockerfile). Render's free plan has no pre-deploy step and no worker, so
# every start: caches the app, migrates the database, keeps the super admin and the app registry in step with the
# environment and the code, then runs PHP-FPM, the queue worker and the scheduler (while the service is awake) and
# nginx in front. The artisan commands run as www-data, the user of PHP-FPM, so the files they write stay writable.
set -e
cd /var/www/html

export PORT="${PORT:-10000}"
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

artisan() {
    runuser -u www-data -- php artisan "$@"
}

artisan optimize
artisan migrate --force
if [ -n "${SUPER_ADMIN_EMAIL:-}" ] && [ -n "${SUPER_ADMIN_PASSWORD:-}" ]; then
    artisan core:super-admin
fi
artisan core:apps-sync

php-fpm -D

# no free background worker on Render: the queue and the scheduler run in this container while it is awake
runuser -u www-data -- php artisan queue:work --tries=3 --timeout=300 --sleep=3 &
runuser -u www-data -- php artisan schedule:work &

exec nginx -g 'daemon off;'
