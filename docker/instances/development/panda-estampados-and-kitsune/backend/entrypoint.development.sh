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
    if /usr/local/bin/php -r "fsockopen('${DB_HOST}', ${DB_PORT:-5432}, \$errno, \$errstr, 2) || exit(1);" 2>/dev/null; then
      echo "[dev] Postgres reachable."
      break
    fi
    sleep 1
  done
fi

# Generar autoloader si no existe (después de un bind mount nuevo).
if [ ! -f vendor/autoload.php ]; then
  echo "[dev] vendor/ no existe, corriendo composer install…"
  /usr/bin/composer install --no-interaction --prefer-dist
fi

# Re-own vendor/ al HOST_UID para que composer require desde el host
# (donde d4nitrix13 corre con uid 1000) pueda escribir sin sudo.
# Solo aplica si el container corre como root (default legacy); con
# el Dockerfile fix actual el vendor/ ya viene correctamente own'd.
if [ "$(id -u)" = "0" ] && [ -d vendor ]; then
  CHOWN_TARGET_UID="${HOST_UID:-1000}"
  /bin/chown -R "${CHOWN_TARGET_UID}:${CHOWN_TARGET_UID}" vendor
fi

# Cache de configuración limpio al arrancar (evita configs viejas
# entre reinicios cuando cambian .env).
/usr/local/bin/php artisan config:clear || true
/usr/local/bin/php artisan route:clear || true
/usr/local/bin/php artisan view:clear || true

# Generar APP_KEY si falta.
if [ -z "${APP_KEY:-}" ] && [ -f .env ]; then
  if ! grep -q "^APP_KEY=base64" .env; then
    echo "[dev] APP_KEY no definida, generando…"
    /usr/local/bin/php artisan key:generate --force
  fi
fi

# Permisos runtime para el usuario www-data (que es el que corre php-fpm).
# Cuando el contenedor corre como root (default legacy), forzamos el
# chown. Si el contenedor corre como no-root (USER www-data en
# Dockerfile) o con un user: override, el chown no se puede aplicar —
# no es necesario porque los archivos ya están bien owner'd al inicio.
if [ "$(id -u)" = "0" ]; then
  /bin/chown -R www-data:www-data storage bootstrap/cache
fi

exec /usr/local/sbin/php-fpm
