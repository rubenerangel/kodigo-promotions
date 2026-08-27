#!/bin/sh
set -e

DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
export DB_HOST DB_PORT

echo "[entrypoint] Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT}..."
until php -r 'exit(@fsockopen(getenv("DB_HOST"), (int) getenv("DB_PORT"), $e, $s, 2) ? 0 : 1);'; do
    echo "[entrypoint] PostgreSQL is not accepting connections yet, retrying..."
    sleep 2
done
echo "[entrypoint] PostgreSQL is accepting connections."

# Laravel requires a .env file to boot. Real environment variables injected by
# Compose take precedence over its contents.
[ -f .env ] || cp .env.example .env

# Only generate a key when none was provided. Unsetting the empty variable lets
# Laravel read the freshly written value from .env.
if [ -z "${APP_KEY:-}" ]; then
    unset APP_KEY
    echo "[entrypoint] Generating application key..."
    php artisan key:generate --force
fi

echo "[entrypoint] Running migrations..."
php artisan migrate --force

# Safe on every boot: the seeders use firstOrCreate, so repeated runs neither
# duplicate the catalog nor consume id sequences.
echo "[entrypoint] Seeding catalog..."
php artisan db:seed --force

echo "[entrypoint] Starting HTTP server on 0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
