# Bases de datos — Postgres + Redis con Docker

Levantar las bases de datos con `Docker Compose`. Docker garantiza
que la versión de `PostgreSQL` sea exactamente la misma en todos los
entornos.

## 1. Levantar los servicios

```bash
cd docker/dev
docker compose up -d
```

Esto arranca:

- `PostgreSQL 18` con `PostGIS 3.6` en puerto 5432.
- Volumen persistente `ecommerce_postgres_data`.

### Verificar

```bash
docker compose ps
# NAME                  STATUS         PORTS
# ecommerce-postgres    Up 30 seconds  0.0.0.0:5432->5432/tcp
```

Probar conexión:

```bash
docker exec -it ecommerce-postgres psql -U postgres -d store -c "SELECT version();"
# PostgreSQL 18.x on x86_64-pc-linux-gnu ...
```

## 2. Verificar la extensión PostGIS

```bash
docker exec -it ecommerce-postgres psql -U postgres -d store -c "SELECT PostGIS_Version();"
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

Si querés interfaz gráfica para inspeccionar la DB:

```bash
# Editar docker-compose.dev.yml y descomentar el servicio pgadmin
docker compose up -d pgadmin
# Disponible en http://localhost:5050
# Login: admin@example.com / admin
```

## Comandos útiles de Docker

```bash
# Ver logs en vivo
docker compose logs -f postgres

# Entrar al container
docker exec -it ecommerce-postgres bash

# Parar todo
docker compose down

# Parar y borrar volúmenes (reset total)
docker compose down -v
```

## Datos persistentes

El volumen `ecommerce_postgres_data` sobrevive a `docker compose down`
y `docker compose up`. Solo se borra con `down -v`.

Verificar:

```bash
docker volume ls | grep ecommerce
```

## Próximo paso

[backend-setup.md](backend-setup.md "backend-setup.md") — Configurar
Laravel con `.env`, `key:generate`, y seeders.
