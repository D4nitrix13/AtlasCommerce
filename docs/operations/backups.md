# Estrategia de Respaldos

> Mentalidad clave: **cada instancia tiene su propia base de datos**.
> NO existe un solo Postgres; hay 6 (3 dev + 3 prod). Un backup
> que falla por tienda no compromete el sistema entero.
>
> **Operación canónica:** todo dump/restore va por
> `scripts/docker-stack.sh db-backup <stage> <store>` y
> `scripts/docker-stack.sh db-restore <stage> <store> <file>`. El
> dispatcher resuelve stage + store, valida la combinación, y
> dispatchea contra el `postgres` (dev) o `db` (prod) del tenant.

## Qué respaldar

### Bases de datos (crítico)

- **Dump completo de PostgreSQL por instancia** — Frecuencia diaria,
  retención 30 días. Comando canónico:
  `./scripts/docker-stack.sh db-backup <stage> <store>`.
- **WAL archives** (si se activa) — Frecuencia continua, retención
  7 días.

### Volúmenes Docker (per-store)

Volúmenes nombrados con el patrón
`atlas_commerce_<store>_<stage>_<service>_data` (definidos en cada
compose de `docker/instances/<stage>/<store>/docker-compose.yml`).
Ver [`operations/docker.md`](docker.md "operations/docker.md") para
la convención de naming.

| Volumen (ejemplo Panda dev)              | Qué guarda                    | Frecuencia      | Retención  |
| ---------------------------------------- | ----------------------------- | --------------- | ---------- |
| `atlas_commerce_panda_estampados_and_kitsune_dev_postgres_data` | BD Postgres                   | Diaria          | 30 días    |
| `atlas_commerce_panda_estampados_and_kitsune_dev_redis_data`    | Cache + sesiones + queues     | Semanal         | 7 días     |
| `atlas_commerce_panda_estampados_and_kitsune_dev_storage`       | Storage Laravel (`storage/`)  | Diaria          | 30 días    |
| `atlas_commerce_panda_estampados_and_kitsune_dev_ftp_data`      | Uploads FTP del tenant        | Diaria          | 30 días    |

En prod aplica el mismo patrón con `prod` en lugar de `dev` y el
store slug correspondiente. Panda + Kitsune prod además tiene el
sidecar `nginx` (volumen `nginx_logs`).

### Código fuente

- **Repositorio Git** — Frecuencia continua (push), retención
  indefinida (GitHub).

## Qué NO respaldar

- `node_modules/`
- `vendor/` (se puede reinstalar con `composer install`)
- `storage/logs/` (logs temporales)
- `bootstrap/cache/` (cache regenerable)
- `.env` (se debe tener en gestión de secretos, no en respaldo)

## Comandos de respaldo

### PostgreSQL — vía dispatcher canónico

Forma recomendada (multi-DB, cross-stage, cross-store):

```bash
# Dump de una instancia (output: db-backups/<stage>-<store>-<TS>.sql.gz)
./scripts/docker-stack.sh db-backup development panda-estampados-and-kitsune
./scripts/docker-stack.sh db-backup production mm-boutique

# Dump de todas las dev (3 archivos, uno por store)
./scripts/docker-stack.sh db-backup development all

# Dump schema-only (útil para fixtures / migrar sin data)
./scripts/docker-stack.sh db-backup development mm-boutique --schema-only

# Sin gzip (deja .sql plain)
./scripts/docker-stack.sh db-backup production panda-estampados-and-kitsune --no-compress
```

El comando internamente hace
`docker exec <store>-<stage_alias>-postgres pg_dump ...`. Ver
[`scripts/docker/db/backup.sh`](../../scripts/docker/db/backup.sh "scripts/docker/db/backup.sh")
para la implementación.

### PostgreSQL — manual (NO recomendado, solo debug)

Si necesitás un dump ad-hoc sin pasar por el dispatcher:

```bash
# Dump comprimido al directorio actual
docker exec <store>-<stage_alias>-postgres \
  pg_dump -U postgres -d <store_slug> \
  | gzip > backup_<store>_<stage>_$(date +%Y%m%d_%H%M%S).sql.gz

# Restore (manual)
gunzip < backup.sql.gz \
  | docker exec -i <store>-<stage_alias>-postgres \
    psql -U postgres -d <store_slug>
```

Reemplazá `<store>` por el slug (`panda-estampados-and-kitsune`,
`mm-boutique`, `morales-store`), `<stage_alias>` por `dev` o `prod`,
y `<store_slug>` por el slug con guiones → guiones bajos (ej:
`panda_estampados_and_kitsune`).

### Volúmenes Docker

Forma portable (un container `alpine` con `tar` lee el volumen):

```bash
# Backup de un volumen nombrado a un .tar.gz en el host
docker run --rm \
  -v atlas_commerce_panda_estampados_and_kitsune_dev_postgres_data:/data \
  -v $(pwd):/backup \
  alpine tar czf \
  /backup/postgres_data_$(date +%Y%m%d).tar.gz \
  -C /data .

# Restore
docker run --rm \
  -v atlas_commerce_panda_estampados_and_kitsune_dev_postgres_data:/data \
  -v $(pwd):/backup \
  alpine tar xzf \
  /backup/postgres_data_20260927.tar.gz \
  -C /data
```

> En prod se recomienda backup a nivel de filesystem del host
> (snapshots LVM/ZFS si el VPS los provee) en lugar de copiar vía
> container. El método `alpine tar` sirve para dev y para
> emergencias puntuales.

## Frecuencia recomendada

- **PostgreSQL dump** — Diario a las 3:00 AM, por instancia
  (método: cron job — ver más abajo).
- **Volumen PostgreSQL data** — Semanal (domingo) por instancia.
- **Volumen Redis** — Semanal por instancia.
- **Volumen Storage** — Diario por instancia (junto con el dump).
- **Código fuente** — Continuo (método: `git push`).

## Almacenamiento de respaldos

### Local (mínimo)

```bash
# Cada VPS mantiene un directorio de respaldos locales
mkdir -p /var/backups/atlas-commerce
```

### Remoto (recomendado)

- **S3-compatible** (MinIO, DigitalOcean Spaces, AWS S3).
- **Rsync** a otro servidor.
- **GitHub/GitLab** para código fuente.

Naming convention sugerido para S3:

```text
s3://<bucket>-backups/<stage>/<store>/<YYYY-MM-DD-HHMM>.sql.gz
```

## Verificación de respaldos

### Verificar dump de PostgreSQL

```bash
# El archivo .sql.gz debe existir y no estar vacío
ls -la db-backups/<stage>-<store>-<*.sql.gz

# Validar gzip integrity
gunzip -t db-backups/<stage>-<store>-<TS>.sql.gz

# Inspeccionar primeras líneas
zcat db-backups/<stage>-<store>-<TS>.sql.gz | head -50
```

### Verificar restore (en BD de prueba)

```bash
# Crear BD temporal dentro del postgres del tenant
./scripts/docker-stack.sh exec <stage> <store> postgres \
    createdb -U postgres <store_slug>_test

# Restaurar el dump en la BD de prueba
gunzip < db-backups/<stage>-<store>-<TS>.sql.gz \
  | ./scripts/docker-stack.sh exec <stage> <store> postgres \
    psql -U postgres -d <store_slug>_test

# Verificar tablas
./scripts/docker-stack.sh exec <stage> <store> postgres \
    psql -U postgres -d <store_slug>_test -c '\dt'

# Limpiar
./scripts/docker-stack.sh exec <stage> <store> postgres \
    dropdb -U postgres <store_slug>_test
```

## Cron job de respaldo

Forma canónica — usa el subcomando `db-backup` del dispatcher:

```bash
# Editar crontab del servidor
sudo crontab -e

# Backup diario de PostgreSQL, 1 entrada por instancia prod
# (los 3 stores viven en hosts distintos en prod)
0 3 * * * cd /opt/atlas-commerce/AtlasCommerce \
  && ./scripts/docker-stack.sh db-backup production panda-estampados-and-kitsune \
  && aws s3 cp db-backups/production-panda-estampados-and-kitsune-*.sql.gz \
       s3://<bucket>-backups/production/panda-estampados-and-kitsune/ \
  >> /var/log/atlas-commerce-backup.log 2>&1

# Limpiar respaldos locales mayores a 30 días
0 5 * * * find /opt/atlas-commerce/AtlasCommerce/db-backups \
    -name "*.sql.gz" -mtime +30 -delete
```

Para dev se puede usar el mismo cron con `stage=development` y
un bucket separado (o ninguno — los dev dumps son descartables).

## Restauración completa (DR)

En caso de desastre (VPS perdido, BD corrupta):

```bash
# 1. Levantar servicios de la instancia
./scripts/docker-stack.sh up production <store>

# 2. Bajar el último backup desde S3 (o donde esté)
aws s3 cp s3://<bucket>-backups/<stage>/<store>/<timestamp>.sql.gz /tmp/

# 3. Restaurar vía db-restore (forma canónica)
./scripts/docker-stack.sh db-restore production <store> \
    /tmp/<file>.sql.gz

# 4. Verificar
./scripts/docker-stack.sh exec production <store> backend \
    php artisan migrate:status
```

`db-restore` lee el archivo, lo descomprime si hace falta, y lo
carga al postgres del tenant. Ver
[`scripts/docker/db/restore.sh`](../../scripts/docker/db/restore.sh "scripts/docker/db/restore.sh").
