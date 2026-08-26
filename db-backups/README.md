# db-backups/

Backups locales de PostgreSQL para desarrollo y fixtures de testing.

## ⚠ Reglas críticas

1. **NUNCA** commitear archivos de backup a git. El `.gitignore` de
   este directorio bloquea `*.sql`, `*.sql.gz` y `*.dump`.
2. Los dumps pueden contener **datos personales** (emails de clientes,
   passwords hasheadas, direcciones). Si vienen de producción,
   **anonimizar antes** de compartir.
3. Los backups son **efímeros**. No son sustituto de un sistema de
   backup serio (usar `pgbackrest`, `barman`, WAL archiving, etc.).

## Workflow

```bash
# 1. Levantar la app con datos fake (seeders):
./scripts/docker-stack.sh bootstrap dev panda-estampados --with-seed

# 2. Cuando los datos estan utiles para el equipo, exportar:
./scripts/docker-stack.sh db-backup dev panda-estampados
# → db-backups/dev-panda-estampados-2026-08-26-1430.sql.gz

# 3. Subir el .gz a donde el equipo lo pueda descargar (no a git):
#   - Drive / Dropbox del equipo
#   - S3 bucket dedicado para fixtures
#   - Slack/Discord como attachment
#   - NUNCA al repo de git

# 4. Otro dev clona el repo + descarga el archivo a este directorio
#    manualmente, despues restaura:
./scripts/docker-stack.sh db-restore dev panda-estampados \
    dev-panda-estampados-2026-08-26-1430.sql.gz

# O como parte del bootstrap (recomendado para setup inicial):
./scripts/docker-stack.sh bootstrap dev panda-estampados \
    --with-backup dev-panda-estampados-2026-08-26-1430.sql.gz
```

## Naming convention

`<stage>-<store>-<YYYY-MM-DD-HHMM>.sql.gz`

Ejemplos:
- `dev-panda-estampados-2026-08-26-1430.sql.gz`
- `production-mm-boutique-2026-08-20-0900.sql.gz`

El timestamp es **local del dev que hizo el backup**, no UTC. Si
compartis entre zonas horarias, convertir manualmente.

## ¿Qué incluye el dump?

`pg_dump --no-owner --clean --if-exists` por defecto. Eso significa:
- Schema (CREATE TABLE, INDEX, etc.)
- Data (INSERT)
- DROP statements al inicio (--clean --if-exists)

NO incluye:
- Roles/usuarios de Postgres (--no-owner)
- Permisos GRANT/REVOKE
- Configuracion de servidor (postgresql.conf)

Si necesitás incluir roles, usar `pg_dumpall --globals-only` aparte.

## Scripts relacionados

- `scripts/docker/db/backup.sh` — crea el dump
- `scripts/docker/db/restore.sh` — carga el dump a una BD
- `scripts/docker/setup/bootstrap.sh` — integra `--with-backup <file>`