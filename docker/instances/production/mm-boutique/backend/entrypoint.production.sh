#!/bin/sh
# Entrypoint del backend Laravel.
# En el primer arranque corre migraciones + seeders + tenants:bootstrap.
# En arranques subsecuentes solo corre migraciones (idempotente).

set -e

cd /var/www/html

# Esperar a que Postgres esté listo.
echo "[entrypoint] Esperando a Postgres en ${DB_HOST}:${DB_PORT}..."
until /usr/bin/pg_isready -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" 2>/dev/null; do
    echo "[entrypoint] Postgres no está listo, reintentando en 2s..."
    sleep 2
done
echo "[entrypoint] Postgres listo."

# Generar APP_KEY si no existe.
if [ -z "${APP_KEY}" ]; then
    echo "[entrypoint] Generando APP_KEY..."
    /usr/local/bin/php artisan key:generate --force
fi

# Cache de configuración para producción.
echo "[entrypoint] Optimizando caches de Laravel..."
/usr/local/bin/php artisan config:cache
/usr/local/bin/php artisan route:cache
/usr/local/bin/php artisan view:cache 2>/dev/null || true

# Correr migraciones (idempotente).
echo "[entrypoint] Corriendo migraciones..."
/usr/local/bin/php artisan migrate --force

# Correr seeders (solo currencies + permissions, idempotente).
echo "[entrypoint] Corriendo seeders base..."
/usr/local/bin/php artisan db:seed --force

# Si hay archivo de tenants declarado, correr el bootstrap.
# TENANTS_SEED_FILE es la ruta al JSON dentro del container
# (ej: /tmp/seeds/mm-boutique.json).
if [ -n "${TENANTS_SEED_FILE}" ] && [ -f "${TENANTS_SEED_FILE}" ]; then
    echo "[entrypoint] Corriendo tenants:bootstrap con ${TENANTS_SEED_FILE}..."
    /usr/local/bin/php artisan tenants:bootstrap "${TENANTS_SEED_FILE}"
else
    echo "[entrypoint] No se encontró TENANTS_SEED_FILE, saltando tenants:bootstrap."
fi

# Arrancar PHP-FPM + servidor HTTP en :8000.
echo "[entrypoint] Iniciando PHP-FPM en background..."
/usr/local/sbin/php-fpm -D

echo "[entrypoint] Iniciando php artisan serve en 0.0.0.0:8000..."
exec /usr/local/bin/php artisan serve --host=0.0.0.0 --port=8000