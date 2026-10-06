# SLA — RPO/RTO y Healthchecks (RF-REL-063 + RF-REL-064)

> **RNF-REL-063**: SLA de RPO (Recovery Point Objective) ≤ 24h y
> RTO (Recovery Time Objective) ≤ 4h para cada tienda.
>
> **RNF-REL-064**: Caddy monitorea health del backend y deja de
> rutear tráfico si no responde.

## Definiciones

- **RPO (Recovery Point Objective)**: tiempo máximo de datos que
  podemos perder ante un desastre. Si RPO = 24h, podemos perder como
  mucho las últimas 24h de cambios.
- **RTO (Recovery Time Objective)**: tiempo máximo de downtime
  aceptable después de un incidente. Si RTO = 4h, el servicio debe
  volver a estar disponible en 4h.

## SLAs por tienda

| Tienda | RPO | RTO | Uptime objetivo |
| --- | --- | --- | --- |
| Panda Estampados | 24h | 4h | 99.0% mensual |
| Kitsune Store | 24h | 4h | 99.0% mensual |
| MM Boutique | 24h | 4h | 99.0% mensual |
| Morales Store | 24h | 4h | 99.0% mensual |

(Notas: estos SLAs son aspiracionales para MVP. Aún no son contractual.
  Cuando se firme contrato con cliente, revisar y ajustar.)

## Cómo se cumplen los SLAs

### RPO ≤ 24h: backups automatizados

**Backend**: `docker/instances/<instancia>/backups/` ejecuta cron
diario de `pg_dump` con retención 7 días.

```bash
# Verificar que el cron está activo:
docker exec <backend_container> crontab -l
# Debería mostrar: 0 2 * * * /scripts/backup.sh
```

**Frontend**: assets estáticos en `frontend/dist/`, sin estado.
Si se pierde la build, se regenera con `pnpm build`.

**Failover manual** (no automatizado todavía):

```bash
# 1. Verificar backup más reciente
ls -lt db-backups/

# 2. Restaurar en instancia limpia
docker exec -i <new_pg_container> psql -U postgres < db-backups/2026-09-15.dump

# 3. Apuntar DNS/Caddy a la nueva instancia
sudo sed -i 's/172.21.0.20/NEW_IP/' /etc/caddy/Caddyfile
sudo systemctl restart caddy
```

### RTO ≤ 4h: healthchecks + runbooks

**Healthcheck activo** (Caddy → backend): `reverse_proxy` con
`health_uri /up`. Si el backend no responde 2xx en 5s, Caddy lo marca
unhealthy y deja de rutear tráfico.

```caddyfile
reverse_proxy 172.21.0.20:8000 {
    health_uri /up
    health_interval 30s
    health_timeout 5s
    health_status 2xx
}
```

**Endpoint `/up`** (Laravel built-in): retorna 200 si la app está viva.
NO valida DB ni Redis — es liveness, no readiness. Para readiness
profunda ver `T-1303` (OpenTelemetry tracing).

### Runbooks de incidente

Documentados en `docs/runbooks/` (pendiente de creación):

- `database-down.md` — qué hacer si Postgres cae.
- `backend-crashloop.md` — diagnosticar Laravel en crashloop.
- `caddy-recovery.md` — qué hacer si Caddy no rutea correctamente.
- `rollback-deployment.md` — volver a la versión anterior.

(Pendiente — los runbooks se crean cuando hay incidentes reales.
Por ahora, este doc explica qué SLAs prometemos.)

## Monitoreo recomendado (futuro)

Tareas pendientes para monitoreo proactivo:

1. **Blackbox monitoring** (RNF-REL-063 ampliación): servicio externo
   que hace HTTP GET a `/up` cada 1 min desde múltiples regiones.
   Herramientas: UptimeRobot (free), Better Uptime, Healthchecks.io.

2. **Whitebox metrics** (Fase 13): Prometheus + Alertmanager para
   métricas internas (latencia p95, error rate, queue depth).

3. **Log aggregation**: Loki + Promtail para centralizar logs de las
   3 instancias (panda-estampados, mm-boutique, morales-store).

4. **Status page**: statuspage.io o self-hosted (Cachet) para
   comunicar incidentes a usuarios.

## Tabla de tiempos objetivo (acordar con stakeholders)

| Escenario | Acción | SLA objetivo |
| --- | --- | --- |
| Backend crash (Docker unhealthy) | Caddy quita del pool, alert al on-call | < 30s detección, < 5min respuesta |
| Backend con errores 5xx > 1% | Alertas + rollback | < 1h resolución |
| DB caída | Failover a backup read-only o restaurar | RTO 4h |
| Disk > 90% | Alert + limpieza automática | < 1h |
| Disco lleno (post-mortem) | Escalar volumen, restaurar backup | RPO 24h, RTO 4h |
| Deploy con bug crítico | Rollback git + redeploy | < 30min |

## Upgrade de SLA (cuándo revisar)

- **Trimestral**: revisar uptime real vs objetivo. ¿Cumplimos 99%?
- **Anual**: actualizar tablas de SLAs con datos reales (error budgets).
- **Por incidente**: cada downtime > 1h genera post-mortem y revisión.

## Referencias

- [Google SRE Book — SLO Engineering](https://sre.google/sre-book/service-level-objectives/)
- PR #102 — implementación de healthchecks en Caddyfile.
- PR #98 — API versioning (relevante para rollback sin downtime).
