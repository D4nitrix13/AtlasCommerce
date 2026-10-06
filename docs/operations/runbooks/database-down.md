# Runbook — Postgres Down

Postgres es crítico para Laravel: sesiones, cache (DB driver),
jobs, scheduler. Si Postgres cae, la app entera cae.

## Detection

Señales típicas:

- Alerta Prometheus: `pg_up == 0` o `pg_stat_activity_count < 1`
- Spike de errores 500 en `/api/v1/*`
- Health check Laravel reporta unhealthy
- Reports de "no puedo loguearme" desde usuarios

## Quick check (60 segundos)

```bash
# 1. ¿Está vivo el container?
./scripts/docker-stack.sh status dev panda-estampados-and-kitsune | grep postgres

# 2. ¿Acepta conexiones?
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune postgres \
  pg_isready -U postgres -d store
# Esperado: "accepting connections"
# Si no, postgres está down o saturado

# 3. ¿Disco lleno?
docker exec <postgres-container> df -h /var/lib/postgresql
# Si /var/lib/postgresql al 100%, ver § "Disk full"
```

## Diagnosis tree

### Container crashed/restarting

```bash
./scripts/docker-stack.sh logs dev panda-estampados-and-kitsune postgres --tail=100
```

Buscá:

- `PANIC:` — crash a nivel Postgres, generalmente corrupción
- `FATAL: could not write to file` — disk full
- `FATAL: sorry, too many clients already` — pool exhaustion
- `database system is shutting down` — restart manual o crash

### Postgres saturado (alive pero slow)

```bash
# Conexiones activas
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune postgres \
  psql -U postgres -d store -c \
  "SELECT count(*) FROM pg_stat_activity;"

# Conexiones por estado
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune postgres \
  psql -U postgres -d store -c \
  "SELECT state, count(*) FROM pg_stat_activity GROUP BY state ORDER BY 2 DESC;"

# Queries lentas
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune postgres \
  psql -U postgres -d store -c \
  "SELECT pid, now() - pg_stat_activity.query_start AS duration, query
   FROM pg_stat_activity
   WHERE state = 'active' AND now() - pg_stat_activity.query_start > interval '5 seconds'
   ORDER BY duration DESC;"
```

Si hay muchas conexiones activas:

- `idle in transaction` → código que no hace COMMIT/ROLLBACK → matar y abrir ticket
- `active` con duration alta → query colgada → matar y abrir ticket

### Disk full

```bash
docker exec <postgres-container> df -h /var/lib/postgresql
docker exec <postgres-container> du -sh /var/lib/postgresql/* | sort -h | tail
```

Mitigación inmediata:

```bash
# 1. Borrar WALs archivados viejos (si los retenés localmente)
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune postgres \
  find /var/lib/postgresql/archive -name "*.gz" -mtime +7 -delete

# 2. Vacuum full de tablas grandes (libera espacio reusable)
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune postgres \
  psql -U postgres -d store -c "VACUUM FULL;"   # agresivo, lockea

# 3. Si no se recupera, truncate tables grandes (last resort)
```

## Mitigation

### Si es container crash (Postgres no acepta conexiones)

```bash
# Restart container
./scripts/docker-stack.sh restart dev panda-estampados-and-kitsune postgres

# Si no arranca, ver logs para diagnóstico
./scripts/docker-stack.sh logs dev panda-estampados-and-kitsune postgres --tail=200
```

### Si es connection pool exhaustion

```bash
# Aplicación: revisar config/database.php -> max_connections
# Por default PostgreSQL permite 100 conexiones.
# Parche rápido: matar conexiones idle in transaction
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune postgres \
  psql -U postgres -d store -c \
  "SELECT pg_terminate_backend(pid)
   FROM pg_stat_activity
   WHERE state IN ('idle in transaction', 'idle in transaction (aborted)')
     AND query_start < now() - interval '1 minute';"
```

### Si es disk full

Ver § "Disk full" arriba. Si no se recupera, escalar el volumen del
host (coordinar con ops).

## Long-term fixes

- **Monitor**: pg_up + pg_stat_activity_count + disk %
- **Alertas**: pg_up == 0 (page), disk >85% (warn), >95% (page)
- **Capacity**: backups/archive WAL rotation, monitoring de trends
- **Pool sizing**: PgBouncer si llega a >80 conexiones concurrentes
  recurrentemente
- **Application**: connection retry logic + circuit breaker

## References

- [operations/sla-and-healthchecks.md](../operations/sla-and-healthchecks.md)
- [operations/observability-runbook.md](../operations/observability-runbook.md)
  § Database down
- [architecture/decisions.md](../architecture/decisions.md) (si hay ADR
  relacionado a Postgres)
