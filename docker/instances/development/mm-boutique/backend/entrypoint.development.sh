#!/usr/bin/env sh
# entrypoint-backend.dev.sh — Entrypoint para el contenedor backend en dev.
# A diferencia del entrypoint de producción, este NO corre migraciones
# automáticamente ni el tenants:bootstrap. El developer los corre a mano
# o desde un IDE contra el contenedor.

set -eu

cd /var/www/html

# Esperar a Postgres si está configurado (default 60s).
if [ -n "${DB_HOST:-}" ]; then
    echo "[dev] Esperando a Postgres en ${DB_HOST}:${DB_PORT:-5432}…"
    for _ in $(seq 1 60); do
        if php -r "fsockopen('${DB_HOST}', ${DB_PORT:-5432}, \$errno, \$errstr, 2) || exit(1);" 2>/dev/null; then
            echo "[dev] Postgres reachable."
            break
        fi
        sleep 1
    done
fi

# Generar autoloader si no existe (después de un bind mount nuevo).
if [ ! -f vendor/autoload.php ]; then
    echo "[dev] vendor/ no existe, corriendo composer install…"
    composer install --no-interaction --prefer-dist
fi

# Cache de configuración limpio al arrancar (evita configs viejas
# entre reinicios cuando cambian .env).
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Generar APP_KEY si falta.
if [ -z "${APP_KEY:-}" ] && [ -f .env ]; then
    if ! grep -q "^APP_KEY=base64" .env; then
        echo "[dev] APP_KEY no definida, generando…"
        php artisan key:generate --force
    fi
fi

# Permisos runtime para el usuario www-data (que es el que corre php-fpm).
chown -R www-data:www-data storage bootstrap/cache

exec php-fpm