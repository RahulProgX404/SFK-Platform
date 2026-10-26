#!/bin/sh
# Development only: started next to php-fpm by docker/php/dev-start.sh.
#
# php.ini turns OPcache's file checks off: through the Windows/macOS file sharing every check is slow, and together
# they cost most of each request. File events do not cross that sharing, so this loop polls the source instead and,
# when something changed, reloads php-fpm (fresh OPcache) and rebuilds what the app would otherwise work out on every
# request from hundreds of files:
#   - the route cache and Filament's component cache (in /tmp, see docker-compose.yml), when routes, resources,
#     pages, providers, config or .env changed, or files were added / moved / removed;
#   - the Composer class map, when PHP files were added / moved / removed.
# Edits show up within a few seconds. Without this loop they show up only after `docker compose restart app`.

cd /var/www/html || exit 1

SOURCES="app bootstrap/app.php bootstrap/providers.php config lang modules resources routes .env vendor/composer/installed.json"
# changes that can alter the routes or the panels' components
STRUCTURE='^(routes/|config/|bootstrap/|\.env$|vendor/)|module\.json$|(Provider|Plugin|Resource)\.php$|/Filament/(Pages|Clusters|Widgets)/'

OLD=/tmp/dev-watch.old
NEW=/tmp/dev-watch.new

log() { echo "[dev-watch] $(date +%H:%M:%S) $*"; }

stamps() { find $SOURCES -type f -not -path '*/Tests/*' -printf '%p %T@ %s\n' 2>/dev/null | sort > "$1"; }

reload() { kill -USR2 1; }

rebuild() {
    [ "$1" = classes ] && composer dump-autoload --optimize --no-scripts --quiet
    php artisan route:cache --quiet || log 'route:cache failed (routes are read uncached until the next change)'
    php artisan filament:cache-components --quiet || log 'filament:cache-components failed'
}

stamps "$OLD"
rebuild classes
reload
log 'caches ready'

while true; do
    sleep 1
    stamps "$NEW"
    if cmp -s "$OLD" "$NEW"; then
        continue
    fi

    # the edit is visible right away; caches follow when needed
    reload
    changed=$(cat "$OLD" "$NEW" | sort | uniq -u | cut -d' ' -f1 | sort -u)
    cut -d' ' -f1 "$OLD" > "$OLD.paths"
    cut -d' ' -f1 "$NEW" > "$NEW.paths"
    if ! cmp -s "$OLD.paths" "$NEW.paths"; then
        log "files added or removed: $(echo "$changed" | head -3 | tr '\n' ' ')"
        rebuild classes
        reload
    elif echo "$changed" | grep -Eq "$STRUCTURE"; then
        log "rebuilding caches for: $(echo "$changed" | head -3 | tr '\n' ' ')"
        rebuild
        reload
    fi
    mv "$NEW" "$OLD"
done
