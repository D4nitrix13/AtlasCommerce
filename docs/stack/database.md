# Base de datos — PostgreSQL 18 + Redis + Storage

## Visión general

Cada instancia (`<stage>` × `<store>`) tiene **su propio** stack
interno: `PostgreSQL` con extensión `PostGIS`, `Redis` para cache
y sesiones, y `FTP` para uploads. Los stores NO comparten base de
datos; viven aislados bajo `docker/instances/<stage>/<store>/`.

`S3` (o compatible) es el destino recomendado para assets de
producción.

## Versiones

| Componente                        | Versión            |
| --------------------------------- | ------------------ |
| `PostgreSQL`                      | 18.x               |
| `PostGIS`                         | 3.6+               |
| `Redis`                           | 7.x                |
| Docker image `postgis/postgis`    | `18-3.6`           |
| Docker image `redis`              | `7-alpine`         |
| Docker image `fauria/vsftpd`      | (latest)           |

Defaults en [`docker/versions.env`](../../docker/versions.env "docker/versions.env").

## PostgreSQL + PostGIS

### Por qué PostGIS

Necesario para queries geoespaciales (zonas de envío, ubicación de
almacenes, direcciones de envío). Extiende `PostgreSQL` con tipos
geométricos e índices espaciales.

### Arranque con Docker (recomendado)

```bash
# Levantar el stack de una instancia (dev)
./scripts/docker-stack.sh up development panda-estampados-and-kitsune

# Equivale a (NO usar directo, pero a modo ilustrativo):
# cd docker/instances/development/panda-estampados-and-kitsune
# docker compose up -d postgres
```

El compose de la instancia define el servicio `postgres`:

```yaml
services:
  postgres:
    image: postgis/postgis:18-3.6
    container_name: panda-estampados-and-kitsune-dev-postgres
    restart: unless-stopped
    env_file:
      - environment/.env
      - ../../../versions.env
    ports:
      - "${POSTGRES_PORT:-6432}:5432"
    volumes:
      - ./conf/postgres/postgresql.conf:/etc/postgresql/postgresql.conf:ro
      - ./conf/postgres/pg_hba.conf:/etc/postgresql/pg_hba.conf:ro
      - atlas_commerce_panda_estampados_and_kitsune_dev_postgres_data:/var/lib/postgresql
      - ../../database/postgresql/init:/docker-entrypoint-initdb.d:ro
```

> **Nota:** el `container_name` incluye el slug del store para que
> las 3 instancias dev puedan coexistir si alguien las levanta
> simultáneamente (los volúmenes y containers no chocan entre stores).
> Los puertos host están shifted (`+1000`) para no pisar un Postgres
> local.

### Conexión desde el backend

Variables de entorno del backend (Laravel), leídas desde el `.env`
de la instancia:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=<store_slug>           # ej: panda_estampados_and_kitsune
DB_USERNAME=<store_slug>           # guiones → guiones bajos para PG
DB_PASSWORD=<secret>
```

`DB_HOST=postgres` usa el nombre del servicio en la red interna
de docker, no `127.0.0.1`.

## Redis

`Redis` se usa para:

- Cache de aplicación (`CACHE_STORE=redis`).
- Sesiones (`SESSION_DRIVER=redis`).
- Colas (`QUEUE_CONNECTION=redis`).
- Rate limiting de Laravel.

### Arranque con Docker

```bash
# Dev
./scripts/docker-stack.sh up development panda-estampados-and-kitsune
# Redis queda en container `panda-estampados-and-kitsune-dev-redis`.
```

### Conexión desde el backend

```env
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
```

En dev sin password. En prod se setea `requirepass` en
`conf/redis/redis.conf` y se inyecta vía `REDIS_PASSWORD`.

## Almacenamiento de archivos

Assets del sistema (logos de tienda, imágenes de productos, uploads
de clientes) viven en disco. Dos backends soportados:

### FTP (dev e histórico)

El stack dev incluye un container `vsftpd` (imagen `fauria/vsftpd`)
para subir/descargar assets vía FTP. En dev el backend usa
`Storage::disk('ftp')` para escribir productos vía `tenants:bootstrap`.

```env
FILESYSTEM_DISK=ftp
FTP_HOST=ftp
FTP_USERNAME=<store>-ftp
FTP_PASSWORD=<secret>
FTP_ROOT=/
```

### S3 (recomendado para producción)

Compatible con `AWS S3`, `Cloudflare R2`, `MinIO`, `DigitalOcean Spaces`.

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=atlas-commerce-assets
AWS_ENDPOINT=https://...
```

## Modelo de datos

- ~75 tablas en `PostgreSQL` por instancia.
- Diagrama ER en `database/diagrams/ecommerce-er.mmd`.
- Documentación: [`database/data-model.md`](../database/data-model.md "database/data-model.md").

### Convenciones

- **Money en centavos** (`unsignedBigInteger`).
- **Multi-tenancy por instancia** — cada `<store>` de dev vive en
  una BD separada (no comparten schema). En prod, multi-tenant
  *dentro* de una instancia se modela con `store_id` +
  `BelongsToStore` trait (ver `panda-estampados-and-kitsune`).
- **Soft deletes** en entidades de negocio (`users`, `products`,
  etc.).
- **Índices** en todas las FKs y columnas de búsqueda frecuente.
- **Snake_case** en tablas y columnas.

## Respaldo

- **PostgreSQL**: dumps programados vía
  [`scripts/docker-stack.sh db-backup`](../../scripts/docker-stack.sh "scripts/docker-stack.sh").
  Output: `db-backups/<stage>-<store>-<YYYY-MM-DD-HHMM>.sql.gz`.
- **Redis**: persistencia opcional (`--appendonly yes`) en
  `conf/redis/redis.conf`.
- **Storage**: snapshots de disco + lifecycle en S3.

Ver [`operations/backups.md`](../operations/backups.md "operations/backups.md")
para el detalle operativo (cron, retention, restore).

## Documentación relacionada

- [`architecture/overview.md`](../architecture/overview.md "architecture/overview.md")
- [`database/data-model.md`](../database/data-model.md "database/data-model.md")
- [`getting-started/databases.md`](../getting-started/databases.md "getting-started/databases.md")
- [`operations/docker.md`](../operations/docker.md "operations/docker.md")
- [`operations/backups.md`](../operations/backups.md "operations/backups.md")
