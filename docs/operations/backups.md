# Estrategia de Respaldos

## Qué respaldar

### Base de datos (crítico)

- **Dump completo de PostgreSQL** — Frecuencia diaria,
  retención 30 días
- **WAL archives** (si aplica) — Frecuencia continua,
  retención 7 días

### Volúmenes Docker

- **`postgres_data`** — Frecuencia diaria,
  retención 30 días
- **`redis_data`** — Frecuencia semanal,
  retención 7 días
- **`caddy_data`** — Frecuencia semanal,
  retención 30 días

### Código fuente

- **Repositorio Git** — Frecuencia continua (push),
  retención indefinida

## Qué NO respaldar

- `node_modules/`
- `vendor/` (se puede reinstalar con
  `composer install`)
- `storage/logs/` (logs temporales)
- `bootstrap/cache/` (cache regenerable)
- `.env` (se debe tener en gestión de secretos,
  no en respaldo)

## Comandos de respaldo

### PostgreSQL

```bash
# Dump completo
docker compose exec postgres \
  pg_dump -U user -d ecommerce \
  > backup_$(date +%Y%m%d_%H%M%S).sql

# Dump comprimido
docker compose exec postgres \
  pg_dump -U user -d ecommerce \
  | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz

# Restore
cat backup.sql \
  | docker compose exec -T postgres \
    psql -U user -d ecommerce
```

### Comandos de volúmenes Docker

```bash
# Backup de volumen
docker run --rm \
  -v ecommerce_postgres_data:/data \
  -v $(pwd):/backup \
  alpine tar czf \
  /backup/postgres_data_$(date +%Y%m%d).tar.gz \
  -C /data .

# Restore de volumen
docker run --rm \
  -v ecommerce_postgres_data:/data \
  -v $(pwd):/backup \
  alpine tar xzf \
  /backup/postgres_data_20260627.tar.gz \
  -C /data
```

## Frecuencia recomendada

- **PostgreSQL dump** — Diario a las 3:00 AM
  (método: cron job)
- **Volumen PostgreSQL** — Semanal (domingo)
  (método: cron job)
- **Volumen Redis** — Semanal (método: cron job)
- **Volumen Caddy** — Mensual (método: cron job)
- **Código fuente** — Continuo (método: `Git` push)

## Almacenamiento de respaldos

### Local (mínimo)

```bash
# Directorio de respaldos
mkdir -p /var/backups/ecommerce
```

### Remoto (recomendado)

- **S3-compatible** (MinIO, DigitalOcean Spaces,
  AWS S3)
- **Rsync** a otro servidor
- **GitHub/GitLab** para código fuente

## Verificación de respaldos

### Verificar dump de PostgreSQL

```bash
# Verificar que el dump no esté corrupto
docker compose exec postgres pg_restore --list backup.sql.gz
```

### Verificar restore

```bash
# Crear BD de prueba
docker compose exec postgres \
  createdb -U user ecommerce_test

# Restore en BD de prueba
cat backup.sql \
  | docker compose exec -T postgres \
    psql -U user -d ecommerce_test

# Verificar tablas
docker compose exec postgres \
  psql -U user -d ecommerce_test -c "\dt"
```

## Cron job de respaldo

```bash
# Editar crontab
crontab -e

# Agregar respaldo diario de PostgreSQL
0 3 * * * cd /var/www/ecommerce \
  && docker compose exec -T postgres \
    pg_dump -U user -d ecommerce \
  | gzip > /var/backups/ecommerce/db_$(date +\%Y\%m\%d).sql.gz

# Limpiar respaldos mayores a 30 días
0 4 * * * find /var/backups/ecommerce \
  -name "*.sql.gz" -mtime +30 -delete
```

## Restauración completa

En caso de desastre:

```bash
# 1. Levantar servicios
docker compose up -d

# 2. Restaurar PostgreSQL
gunzip < backup_20260627.sql.gz \
  | docker compose exec -T postgres \
    psql -U user -d ecommerce

# 3. Verificar
docker compose exec backend php artisan migrate:status
```
