# Backend — Setup de Laravel

Configurar el backend con sus variables de entorno, correr
migraciones y seeders.

## 1. Variables de entorno

```bash
cd backend
cp .env.example .env
php artisan key:generate
```

Editá `backend/.env` con tus valores locales:

```env
APP_NAME=Ecommerce
APP_ENV=local
APP_KEY=base64:GENERADO_POR_KEY:GENERATE
APP_DEBUG=true
APP_URL=http://localhost:9000

# Base de datos (Postgres local)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=store
DB_USERNAME=postgres
DB_PASSWORD=

# Redis (si lo activás)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Sanctum — dominios permitidos
SANCTUM_STATEFUL_DOMAINS=pandas-estampados.com:8080,kitsune.com:8080,localhost:5321
SESSION_DOMAIN=localhost
```

Para setup local con subdominios sin HTTPS, agregar al `SANCTUM_STATEFUL_DOMAINS`
los dominios con puerto `:8080` (porque `Caddy` escucha en 8080 sin TLS).

## 2. Generar la clave de aplicación

```bash
php artisan key:generate
```

Esto setea `APP_KEY` en `.env`. La clave se usa para encriptar
sesiones, cookies y datos sensibles.

## 3. Limpiar caché de configuración

```bash
php artisan config:clear
php artisan cache:clear
```

Esencial después de modificar `.env`.

## 4. Migraciones y seeders

```bash
# Solo migraciones
php artisan migrate

# Migraciones + seeders
php artisan db:seed

# Reset completo (¡borra todo!)
php artisan migrate:fresh --seed
```

### Qué se crea con los seeders

- `Currencies`: USD (primaria) + NIO.
- `Stores`: Panda Estampados + Kitsune.
- `Roles`: admin + staff.
- `Permissions`: 26 permisos agrupados por módulo.
- `AdminUser`: `admin@panda-estampados.test` / `password`.

Resetear y volver a seedear:

```bash
php artisan migrate:fresh --seed
```

## 5. Verificar instalación

```bash
# Estado del backend
curl -sI http://127.0.0.1:8000/up
# HTTP/1.1 200 OK

# Estado de la base de datos
php artisan tinker --execute='echo "Users: " . App\Models\Auth\User::count() . "\n";'
# Users: 1

# Rutas registradas
php artisan route:list --path=api
```

## 6. Comandos artisan útiles

```bash
# Limpiar todo el caché
php artisan optimize:clear

# Generar IDE Helper (autocomplete en IDEs)
composer ide-helper

# Inspeccionar policies
php artisan policy:list

# Ver jobs fallidos
php artisan queue:failed

# Reintentar jobs fallidos
php artisan queue:retry all
```

## 7. Logs

Los logs se guardan en `backend/storage/logs/laravel.log`. En desarrollo
con `APP_DEBUG=true`, los errores también se muestran en el navegador.

```bash
# Ver logs en vivo
tail -f storage/logs/laravel.log
```

## 8. Problemas comunes

### `SQLSTATE[HY000] [2002] Connection refused`

Postgres no está corriendo. Levantarlo:

```bash
cd ../docker/dev && docker compose up -d
```

### `Class 'Redis' not found`

La extensión `php-redis` no está instalada:

```bash
sudo apt install php8.3-redis
```

### `No application encryption key has been specified`

Falta correr `php artisan key:generate`.

### `cURL error 60: SSL certificate problem`

Temporal — agregar al `.env`:

```env
CURLOPT_SSL_VERIFYPEER=false
```

Solo para dev. En producción, configurar CA correcta.

## Próximo paso

[frontend-setup.md](frontend-setup.md "frontend-setup.md") — Configurar
Astro con `PUBLIC_API_URL`.
