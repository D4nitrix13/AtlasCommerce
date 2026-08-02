# Base de datos — PostgreSQL 18 + Redis + Storage

## Visión general

Una sola instancia de `PostgreSQL` con extensión `PostGIS` almacena
todos los datos de ambas tiendas. `Redis` maneja cache, sesiones y
colas. Los archivos (imágenes, assets) viven en `FTP` o `S3`.

## Versiones

| Componente      | Versión  |
| --------------- | -------- |
| `PostgreSQL`    | 18.x     |
| `PostGIS`       | 3.6+     |
| `Redis`         | 7.x      |
| `Docker` `postgis/postgis` tag | `18-3.6` |

## PostgreSQL + PostGIS

### Por qué PostGIS

Necesario para queries geoespaciales (zonas de envío, ubicación de
almacenes, direcciones de envío). Extiende `PostgreSQL` con tipos
geométricos.

### Instalación con Docker (recomendado)

```bash
cd docker/dev
docker compose up -d
```

El `docker-compose.dev.yml` define el servicio `postgres` con:

```yaml
services:
  postgres:
    image: postgis/postgis:18-3.6
    container_name: ecommerce-postgres
    restart: unless-stopped
    env_file: [.env]
    ports: ["${POSTGRES_PORT:-5432}:5432"]
    command:
      - postgres
      - -c
      - shared_preload_libraries=pg_stat_statements
      - -c
      - pg_stat_statements.track=all
    volumes:
      - ecommerce_postgres_data:/var/lib/postgresql
      - ../../database/postgresql/init:/docker-entrypoint-initdb.d:ro
```

### Instalación manual (no recomendado)

```bash
# Ubuntu
sudo apt install postgresql-18 postgresql-18-postgis-3

# Crear DB
sudo -u postgres createuser -P ecommerce
sudo -u postgres createdb -O ecommerce ecommerce
sudo -u postgres psql -d ecommerce -c "CREATE EXTENSION postgis;"
```

### Conexión desde el backend

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=store
DB_USERNAME=postgres
DB_PASSWORD=secret
```

## Redis

`Redis` se usa para:

- Cache de aplicación (`CACHE_STORE=redis`).
- Sesiones (`SESSION_DRIVER=redis`).
- Colas (`QUEUE_CONNECTION=redis`).
- Rate limiting de Laravel.

### Instalación con Docker

```bash
docker compose -f docker/dev/docker-compose.dev.yml up -d redis
```

### Instalación manual

```bash
sudo apt install redis-server
sudo systemctl enable redis-server
```

### Conexión

```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

## Almacenamiento de archivos

Assets del sistema (logos de tienda, imágenes de productos, uploads
de clientes) viven en disco. Dos backends soportados:

### FTP (legacy)

```bash
# Servidor FTP local para dev
sudo apt install vsftpd
sudo systemctl enable vsftpd
```

```env
FILESYSTEM_DISK=ftp
FTP_HOST=127.0.0.1
FTP_USERNAME=ecommerce
FTP_PASSWORD=secret
```

### S3 (recomendado para producción)

Compatible con `AWS S3`, `Cloudflare R2`, `MinIO`, `DigitalOcean Spaces`.

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=ecommerce-assets
AWS_ENDPOINT=https://...
```

## Modelo de datos

- 75 tablas en `PostgreSQL`.
- Diagrama ER en `database/diagrams/ecommerce-er.mmd`.
- Documentación: [database/data-model.md](../database/data-model.md "database/data-model.md").

### Convenciones

- **Money en centavos** (`unsignedBigInteger`).
- **Multi-tenancy** vía `store_id` + `BelongsToStore` trait.
- **Soft deletes** en entidades de negocio (`users`, `products`, etc.).
- **Índices** en todas las FKs y columnas de búsqueda frecuente.
- **Snake_case** en tablas y columnas.

## Respaldo

- **PostgreSQL**: dumps programados vía `docker/dev/scripts/backup.sh`.
- **Redis**: persistencia opcional (`--appendonly yes`).
- **Storage**: snapshots de disco + lifecycle en S3.

Ver [operations/backups.md](../operations/backups.md "operations/backups.md").

## Documentación relacionada

- [architecture/overview.md](../architecture/overview.md "architecture/overview.md")
- [database/data-model.md](../database/data-model.md "database/data-model.md")
- [getting-started/databases.md](../getting-started/databases.md "getting-started/databases.md")
- [operations/docker.md](../operations/docker.md "operations/docker.md")
- [operations/backups.md](../operations/backups.md "operations/backups.md")
