# Bases de datos — Postgres + Redis con Docker

Levantar las bases de datos con `Docker Compose`. Docker garantiza
que la versión de `PostgreSQL` sea exactamente la misma en todos los
entornos.

## 1. Levantar los servicios

Multi-instance layout: cada instancia tiene su propio `docker-compose`
bajo `docker/instances/development/<tenant>/`. Usá el dispatcher
canónico `scripts/docker-stack.sh` (NO `docker compose` directo) para
operarla.

```bash
# Levantar la instancia dev del store panda-estampados-and-kitsune
./scripts/docker-stack.sh up dev panda-estampados-and-kitsune
```

Esto arranca:

- `PostgreSQL 18` con `PostGIS 3.6` en puerto 6432 (host) → 5432
  (container).
- `Redis 7` en puerto 7379 (host) → 6379 (container).
- Volumen persistente `panda-estampados-and-kitsune-dev-postgres-data`.

### Verificar

```bash
./scripts/docker-stack.sh status dev panda-estampados-and-kitsune
# NAME                                          STATUS         PORTS
# panda-estampados-and-kitsune-dev-postgres     Up 30 seconds  0.0.0.0:6432->5432/tcp
```

Probar conexión:

```bash
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune postgres \
  psql -U postgres -d store -c "SELECT version();"
# PostgreSQL 18.x on x86_64-pc-linux-gnu ...
```

## 2. Verificar la extensión PostGIS

```bash
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune postgres \
  psql -U postgres -d store -c "SELECT PostGIS_Version();"
```

Si devuelve la versión, la extensión está instalada.

## 3. Conectar desde el backend

El backend Laravel se conecta vía variables de entorno
(`backend/.env`):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=store
DB_USERNAME=postgres
DB_PASSWORD=
```

Probar la conexión desde PHP:

```bash
cd backend
php artisan tinker --execute='DB::connection()->getPdo(); echo "OK\n";'
```

## 4. Correr migraciones y seeders

```bash
cd backend
php artisan migrate
php artisan db:seed
```

Esto crea todas las tablas y carga datos iniciales (Panda Estampados,
Kitsune, monedas, roles, admin user).

### Verificar

```bash
php artisan tinker --execute='
echo "Stores: " . App\Models\Stores\Store::count() . "\n";
echo "Users: " . App\Models\Auth\User::count() . "\n";
echo "Roles: " . App\Models\Auth\Role::count() . "\n";
echo "Currencies: " . App\Models\Settings\Currency::count() . "\n";
'
```

## 5. Reset completo (¡borra datos!)

```bash
cd backend
php artisan migrate:fresh --seed
```

Útil cuando querés arrancar desde cero sin migrar de a una.

## 6. Acceder a `pgAdmin` (opcional)

Si querés interfaz gráfica para inspeccionar la DB, pgAdmin viene
incluido por default en el compose de la instancia (puerto 6050 host).
Solo abrí `http://localhost:6050` después de levantar la instancia.
Login default: `admin@example.com` / `admin`.

## Comandos útiles de Docker

```bash
# Ver logs en vivo del postgres
./scripts/docker-stack.sh logs dev panda-estampados-and-kitsune postgres -f

# Entrar al container de postgres (bash interactivo)
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune postgres bash

# Parar todos los servicios de la instancia (conserva volumenes)
./scripts/docker-stack.sh down dev panda-estampados-and-kitsune

# Reset completo (¡BORRA DATOS!): down + eliminar volumenes
./scripts/docker-stack.sh down dev panda-estampados-and-kitsune -v
```

## Datos persistentes

El volumen `panda-estampados-and-kitsune-dev-postgres-data` sobrevive
a `docker compose down` y vuelve a usarse en el próximo `up`.
Solo se borra con `down -v`.

Verificar:

```bash
docker volume ls | grep panda
```

## Próximo paso

[backend-setup.md](backend-setup.md "backend-setup.md") — Configurar
Laravel con `.env`, `key:generate`, y seeders.
