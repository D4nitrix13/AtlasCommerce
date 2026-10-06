# Runbook — Redis Down

Redis se usa en AtlasCommerce para cache, sesiones y colas. Si Redis
cae, **la app sigue funcionando** pero con latencia incrementada
(cache miss → consultas a DB). Sesiones de Sanctum stateful también
se pierden.

## Detection

- Alerta Prometheus: `redis_up == 0`
- Spike de latencia en `/api/v1/*`
- Reportes de "tuve que re-loguearme" desde usuarios (sesiones perdidas)
- Health check Laravel reporta degraded (DB ok, Redis not ok)

## Quick check (30 segundos)

```bash
# 1. ¿Está vivo el container?
./scripts/docker-stack.sh status dev panda-estampados-and-kitsune | grep redis

# 2. ¿Acepta conexiones?
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune redis \
  redis-cli ping
# Esperado: "PONG"

# 3. ¿Memoria llena?
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune redis \
  redis-cli INFO memory | grep used_memory_human
```

## Diagnosis

### Container crashed

```bash
./scripts/docker-stack.sh logs dev panda-estampados-and-kitsune redis --tail=100
```

Buscá:

- `Out of memory` → see § "Memory pressure"
- `Can't save in background` → fork failures → memoria o fork-related

### Memoria saturada (OOM / eviction storm)

```bash
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune redis \
  redis-cli INFO memory
```

Métricas clave:

- `used_memory_human`: tamaño actual
- `used_memory_peak_human`: pico histórico
- `maxmemory_human`: límite configurado (si está)
- `mem_fragmentation_ratio`: >1.5 indica fragmentación excesiva

```bash
# Top keys por memoria
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune redis \
  redis-cli --bigkeys

# Sample de keys
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune redis \
  redis-cli --scan --pattern '*' | head -20
```

### Conexión rechazada (pool exhausted)

```bash
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune redis \
  redis-cli INFO clients | grep connected_clients
```

Si `connected_clients` está cerca del max, código PHP no está usando
pool correctamente o hay una fuga.

## Mitigation

### Container crash

```bash
# Restart (data persiste en volumen si AOF+RDB configurados)
./scripts/docker-stack.sh restart dev panda-estampados-and-kitsune redis

# Si no arranca, logs para diagnóstico
./scripts/docker-stack.sh logs dev panda-estampados-and-kitsune redis --tail=200
```

### Memory pressure

```bash
# 1. Flush keys que no necesitamos (cache de desarrollo, etc)
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune redis \
  redis-cli FLUSHDB

# 2. Si la app está bien configurada, los TTLs evitan que se acumule basura.
#    Si acumulas, es bug de la app: keys sin TTL -> ticket.

# 3. Configurar maxmemory + maxmemory-policy si no está
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune redis \
  redis-cli CONFIG SET maxmemory 256mb
./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune redis \
  redis-cli CONFIG SET maxmemory-policy allkeys-lru
```

### Sesiones perdidas

Cuando Redis vuelve, todos los usuarios tienen que re-loguearse.
No hay forma de recuperar las sesiones (es correcto por seguridad).

Si querés minimizar el impacto: configurar `SESSION_DRIVER=database`
en `backend/.env` como fallback mientras Redis esté caído. Migración
sesiones DB ya existe (`sessions` table).

## Impact en la app

| Funcionalidad     | Sin Redis                            |
| ----------------- | ------------------------------------ |
| Cache             | Miss → query a DB → más lento        |
| Sesiones Sanctum  | Perdidas → usuario tiene que re-login |
| Colas (jobs)      | Si driver=redis → jobs quedan en cola local hasta que vuelva |
| Rate limiting     | Si driver=redis → falla abrir        |

Si la app sigue sirviendo requests (con latencia alta), es solo
degraded, no caído. Si el código PHP crashea por no encontrar Redis,
la app se cae.

## Long-term fixes

- **Fallback**: configurar Laravel para que cache/sesiones degraden
  gracefully a DB si Redis no responde (en `config/cache.php` y
  `config/session.php`)
- **Monitoring**: redis_up, used_memory, connected_clients
- **Alertas**: redis_up == 0 (warn si app degrada OK, page si app cae)
- **Capacity**: track growth de memoria, ajustar maxmemory antes de OOM
- **Application**: usar TTL en TODAS las keys (no keys sin expiración)

## References

- [operations/sla-and-healthchecks.md](../operations/sla-and-healthchecks.md)
- [operations/observability-runbook.md](../operations/observability-runbook.md)
  § Redis down
- [agent-skills/redis-caching-patterns/SKILL.md](../agent-skills/redis-caching-patterns/SKILL.md)
