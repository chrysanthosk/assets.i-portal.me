#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

log() { echo -e "\033[1;32m[entrypoint]\033[0m $*"; }

# Capture the intended runtime drivers, then bootstrap with filesystem-backed
# cache/session so early artisan commands don't touch DB tables that the
# migrations haven't created yet (cache, sessions). Restored before launch.
TARGET_CACHE="${CACHE_STORE:-database}"
TARGET_SESSION="${SESSION_DRIVER:-database}"
export CACHE_STORE=file
export SESSION_DRIVER=file

###############################################
# 1) Ensure an .env file + application key exist
###############################################
if [ ! -f .env ]; then
    log "No .env found — creating one from .env.example"
    cp .env.example .env
fi

# Generate APP_KEY only when it is missing/empty
if ! grep -qE '^APP_KEY=base64:.+' .env; then
    log "Generating application key"
    php artisan key:generate --force --no-interaction
fi

###############################################
# 2) Wait for the database to accept connections
###############################################
log "Waiting for database ${DB_HOST:-db}:${DB_PORT:-3306} ..."
ATTEMPTS=0
until php -r '
    $h=getenv("DB_HOST")?:"db";
    $p=getenv("DB_PORT")?:"3306";
    $d=getenv("DB_DATABASE")?:"assets";
    $u=getenv("DB_USERNAME")?:"assets";
    $w=getenv("DB_PASSWORD")?:"";
    try { new PDO("mysql:host=$h;port=$p;dbname=$d", $u, $w); exit(0); }
    catch (Throwable $e) { exit(1); }
' >/dev/null 2>&1; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -ge 30 ]; then
        log "Database not reachable after ${ATTEMPTS} attempts — aborting."
        exit 1
    fi
    sleep 2
done
log "Database is ready."

###############################################
# 3) Migrate + seed (idempotent) and cache config
###############################################
log "Running migrations"
php artisan migrate --force

log "Seeding portal permissions/roles (idempotent)"
php artisan db:seed --class='Database\Seeders\PortalPermissionsSeeder' --force || true

# Optional one-shot admin bootstrap when credentials are provided via env
if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    log "Ensuring admin user ${ADMIN_EMAIL} (login username: ${ADMIN_USERNAME:-admin})"
    php artisan make:admin \
        --name="${ADMIN_NAME:-Admin}" \
        --email="${ADMIN_EMAIL}" \
        --username="${ADMIN_USERNAME:-admin}" \
        --password="${ADMIN_PASSWORD}" \
        --force || true
fi

# Restore the intended runtime drivers now that the tables exist, so the
# cached config (and the long-running php-fpm / queue processes) use them.
export CACHE_STORE="$TARGET_CACHE"
export SESSION_DRIVER="$TARGET_SESSION"

log "Linking storage + optimizing (cache=${CACHE_STORE}, session=${SESSION_DRIVER})"
php artisan storage:link >/dev/null 2>&1 || true
php artisan optimize

# Make sure runtime dirs are writable by the web/php-fpm user
chown -R www-data:www-data storage bootstrap/cache || true

log "Startup tasks complete — handing over to: $*"
exec "$@"
