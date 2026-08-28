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

# Laravel requires a .env file to boot.
[ -f .env ] || cp .env.example .env

# Copy the injected configuration into that file. This is not redundant with
# the environment: `php artisan serve` forwards only a whitelist of variables
# to the process that actually handles requests, so DB_HOST and friends never
# reach it and the app would fall back to the .env.example defaults, trying to
# reach PostgreSQL on 127.0.0.1. Artisan commands run here do see the
# environment, which is why migrations succeed while requests fail.
set_env_value() {
    key="$1"
    value="$2"

    if grep -q "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        printf '%s=%s\n' "${key}" "${value}" >> .env
    fi
}

for key in APP_ENV APP_DEBUG APP_URL DB_CONNECTION DB_HOST DB_PORT \
    DB_DATABASE DB_USERNAME DB_PASSWORD FRONTEND_URL; do
    eval "value=\${${key}:-}"

    if [ -n "${value}" ]; then
        set_env_value "${key}" "${value}"
    fi
done

# Compose always injects APP_KEY, empty when the variable is not set. An empty
# environment variable would win over the .env value, because Laravel's Dotenv
# never overrides an existing variable, so drop it first and let Laravel read
# the file. Generate a key only when neither source holds one: checking the
# environment variable alone would rotate the key on every restart and
# invalidate anything derived from it.
if [ -z "${APP_KEY:-}" ]; then
    unset APP_KEY

    if ! grep -q '^APP_KEY=base64:' .env; then
        echo "[entrypoint] Generating application key..."
        php artisan key:generate --force
    else
        echo "[entrypoint] Reusing the application key already present in .env."
    fi
fi

echo "[entrypoint] Running migrations..."
php artisan migrate --force

# Safe on every boot: the seeders use firstOrCreate, so repeated runs neither
# duplicate the catalog nor consume id sequences.
echo "[entrypoint] Seeding catalog..."
php artisan db:seed --force

echo "[entrypoint] Starting HTTP server on 0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
