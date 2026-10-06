# Runbook de Observabilidad — AtlasCommerce

> **Audience**: DevOps, on-call engineers, SRE.
>
> **Propósito**: Referencia operacional para el sistema de telemetría
> implementado en Fase 13 (PRs #121-#125). Cubre cómo interpretar
> las métricas, dónde encontrar logs/traces/errors, y qué hacer
> ante cada alerta común.

## TL;DR — Stack de observabilidad

| Señal | Endpoint / destino | Backend | Cómo verlo |
| --- | --- | --- | --- |
| Logs estructurados | `storage/logs/laravel.json.log` (local) | Loki + Promtail | Grafana → Explore → datasource Loki |
| Métricas whitebox | `GET /metrics` | Prometheus → Grafana | Grafana → Dashboards → AtlasCommerce |
| Trazas distribuidas | OTLP (configurable) | Tempo / Jaeger / SigNoz | Grafana → Explore → datasource Tempo |
| Errores production | Sentry (DSN via env) | sentry.io | Sentry dashboard del proyecto |
| Healthcheck | `GET /up`, `GET /health/ready` | Caddy health_uri | `curl` directo o alerting externo |

Todos los signals comparten **request_id** (UUIDv7), **trace_id** y
**span_id** (W3C tracecontext) — un identificador en cualquiera de
ellos nos lleva al resto (Logs ↔ Traces ↔ Errors ↔ Metrics).

## 1. Correlación de telemetría

```text
Request HTTP → LogContext.start() genera request_id (UUIDv7) + trace_id
                                  ↓
                   CorrelationIdMiddleware lo emite al cliente en
                   X-Request-Id y traceparent headers
                                  ↓
                   Todos los logs estructurados (PR1) lo incluyen
                                  ↓
                   HttpMetricsMiddleware (PR3) lo usa como label
                                  ↓
                   HttpTracingMiddleware (PR4) crea span OTel con
                   trace_id + span_id
                                  ↓
                   SentryBreadcrumbsConfigurator (PR5) lo inyecta
                   en cada breadcrumb
```

Operacionalmente: **un único identificador (`request_id` o
`trace_id`) te lleva al incidente entero** — desde el primer log
hasta el último span, pasando por el breadcrumb que disparó el
error en Sentry.

## 2. Métricas Prometheus (PR3)

### `atlascommerce_http_requests_total` (counter)

```promql
# Rate de requests por segundo, etiquetado por status code.
sum by (status) (rate(atlascommerce_http_requests_total[5m]))

# Error rate (5xx) — alertable.
sum(rate(atlascommerce_http_requests_total{status=~"5.."}[5m]))
  / sum(rate(atlascommerce_http_requests_total[5m]))
```

### `atlascommerce_http_request_duration_seconds` (histogram)

```promql
# p95 latency por route.
histogram_quantile(0.95,
  sum by (route, le) (
    rate(atlascommerce_http_request_duration_seconds_bucket[5m])
  )
)

# p99 por route (para alertas finos).
histogram_quantile(0.99,
  sum by (route, le) (
    rate(atlascommerce_http_request_duration_seconds_bucket[5m])
  )
)
```

### `atlascommerce_queue_size` (gauge)

```promql
# Tamaño actual de la cola default.
atlascommerce_queue_size{queue="default"}
```

### `atlascommerce_business_events_total` (counter)

```promql
# Eventos de negocio discretos. Emitidos por los servicios de dominio.
sum by (event) (rate(atlascommerce_business_events_total[5m]))
```

## 3. SLOs y alertas

### SLOs target (RNF-REL-063)

| SLO | Target | Cómo se mide | Alerta |
| --- | --- | --- | --- |
| Uptime mensual | 99.0% | `1 - sum(rate(...status=~"5.."}[30d])) / sum(rate(...[30d]))` | ErrorBudgetBurnAlert |
| p95 latency API | < 500ms | `histogram_quantile(0.95, ...)` | `ApiLatencyP95High` |
| p99 latency checkout | < 2s | `histogram_quantile(0.99, route=~"/api/v1/checkout/...")` | `CheckoutLatencyP99High` |
| Error rate 5xx | < 1% | ratio de 5xx vs total | `Http5xxRateHigh` |

### Alertas activas

Las reglas Prometheus viven en `deploy/prometheus/alerts.yml`. Se
cargan con `rule_files` en `prometheus.yml`. Resumen de las alertas
críticas (ver archivo para thresholds completos y `for:` durations):

| Alerta | Severidad | Condición | Acción |
| --- | --- | --- | --- |
| `Http5xxRateHigh` | critical | 5xx rate > 1% por 5m | Ver §4.1 |
| `ApiLatencyP95High` | warning | p95 > 500ms por 10m | Ver §4.2 |
| `CheckoutLatencyP99High` | critical | p99 checkout > 2s por 5m | Ver §4.3 |
| `DatabaseDown` | critical | readiness 503 por 2m | Ver §4.4 |
| `StorageDown` | critical | readiness 503 por 2m | Ver §4.5 |
| `QueueBacklogGrowing` | warning | queue_size > 1000 por 15m | Ver §4.6 |
| `RedisDown` | critical | sin scrape de /metrics por 1m | Ver §4.7 |
| `SentryErrorRateSpike` | warning | rate de errors > 2x baseline | Ver §4.8 |

## 4. Runbooks por alerta

### 4.1 `Http5xxRateHigh`

**Síntoma**: Más del 1% de requests devuelven 5xx sostenido por 5 min.

**Triage**:

1. Identifica la route afectada:

   ```promql
   topk(5, sum by (route) (
     rate(atlascommerce_http_requests_total{status=~"5.."}[5m])
   ))
   ```

2. Cruza con Sentry: busca errores recientes con la misma `route` y
   `trace_id` para ver el stack trace.
3. Mira logs estructurados: filtra por `status=~"5.."` y `route=<X>`
   en Loki para ver el contexto completo de cada error.

**Causas comunes**:

- Deploy reciente con bug → rollback (ver `docs/runbooks/rollback-deployment.md`).
- DB saturada → ver §4.4.
- Dependencia externa caída → ver `status.panda-estampados.com` y proveedores.
- Cache invalidado (cold cache, latencia DB) → monitorear, esperar warmup.

### 4.2 `ApiLatencyP95High`

**Síntoma**: p95 latency > 500ms por 10 min.

**Triage**:

1. Identifica las routes más lentas:

   ```promql
   topk(10, histogram_quantile(0.95,
     sum by (route, le) (rate(atlascommerce_http_request_duration_seconds_bucket[5m]))
   ))
   ```

2. Compara con la misma métrica en otras tiendas (label store_id
   cuando esté disponible) — si es global, es backend; si es una
   sola tienda, es algo específico de esa tienda (probablemente DB).
3. Si hay traces en Tempo, samplea 5 traces lentos de esa route —
   busca spans con duración alta en DB/cache/queue.

**Causas comunes**:

- N+1 query → `scripts/check-n-plus-one.sh` o ver queries en traces.
- DB connection pool agotado → métricas de DB (futuro).
- Cold cache después de deploy → esperar 5-10 min.
- Slow query → EXPLAIN en el query del span lento.

### 4.3 `CheckoutLatencyP99High`

**Síntoma**: p99 del checkout > 2s por 5 min. CRITICAL — bloquea revenue.

**Triage**:

1. **PRIORIDAD ALTA**: revenue impact. Pagar un coffee al on-call si se
   arregla en < 15 min.
2. Verifica pasarela de pago (MercadoPago, etc.) primero — son la
   causa #1 de checkout lento.
3. Si no es la pasarela, ver N+1 en `CheckoutService`.
4. Si el problema es de DB, verificar índices con EXPLAIN.

### 4.4 `DatabaseDown`

**Síntoma**: `/health/ready` devuelve 503 por 2 min.

**Triage**:

1. `curl http://<instance>/health/ready` directo → confirma el payload.
2. Verifica conexión del container al Postgres:

   ```bash
   ./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune backend
     php artisan tinker --execute 'DB::connection()->selectOne("SELECT 1");'
   ```

3. Si Postgres está caído: ver `docs/runbooks/database-down.md`.
4. Si Postgres está OK pero el check falla: revisar network/firewall.

### 4.5 `StorageDown`

**Síntoma**: `/health/ready` devuelve 503 por 2 min con `storage.healthy=false`.

**Triage**:

1. Verifica el disk configurado:

   ```bash
   ./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune backend
     php artisan tinker --execute 'Storage::disk()->put("test", "ok"); Storage::disk()->get("test");'
   ```

2. Si es FTP (producción): ver si el servidor FTP responde.
3. Si es local: ver espacio en disco (`df -h`).

### 4.6 `QueueBacklogGrowing`

**Síntoma**: queue_size > 1000 por 15 min.

**Triage**:

1. Verifica si hay workers corriendo:

   ```bash
   ./scripts/docker-stack.sh ps dev panda-estampados-and-kitsune backend
   ```

2. Si no hay workers: restart `queue:work`.
3. Si hay workers pero lento: jobs lentos (ver traces de jobs en
   Tempo si los hay).
4. Backlog legítimo (pico de orders): esperar — monitorear.

### 4.7 `RedisDown`

**Síntoma**: No hay scrape de `/metrics` por 1 min. Prometheus
reporta `up{job="atlascommerce-backend"} == 0`.

**Triage**:

1. Verifica Redis: `redis-cli -h redis ping`.
2. Si Redis está caído: ver `docs/runbooks/redis-down.md`.
3. Si Redis está OK pero /metrics falla: revisar conexión backend ↔ Redis.

### 4.8 `SentryErrorRateSpike`

**Síntoma**: Tasa de errores en Sentry > 2x el baseline (mediana 7d).

**Triage**:

1. Abrir Sentry → Issues → ordenar por frequency.
2. Si es un error nuevo: puede ser release nuevo con bug → rollback.
3. Si es un error conocido que de repente crece: ver cambios
   externos (tráfico, deploys de dependencias).
4. Filtrar por breadcrumb con `request_id` para correlacionar con
   logs/traces.

## 5. Operación normal

### Verificar salud de un store específico

```bash
# Healthcheck
curl -s https://panda-estampados.com/up | jq
curl -s https://panda-estampados.com/health/ready | jq

# Métricas (filtrar si el DSN está protegido por IP)
curl -s https://panda-estampados.com/metrics | grep atlascommerce_

# Buscar un request_id específico en logs
grep '"request_id":"abc-123"' storage/logs/laravel.json.log | tail -20
```

### Investigar un incidente

1. Recibir alerta (5xx, latency, error en Sentry).
2. Obtener `trace_id` o `request_id` desde la alerta.
3. En Loki: filtrar por `trace_id` → ver todos los logs del trace.
4. En Tempo: pegar `trace_id` → ver el waterfall de spans.
5. En Sentry: filtrar por `breadcrumb.trace_id` → ver la "historia"
   antes del error.

### Post-mortem

Después de un incidente > 1h:

1. Documentar en `docs/runbooks/<incident-slug>.md` siguiendo el
   template en `docs/runbooks/rollback-deployment.md`.
2. Abrir tareas de follow-up (mejorar alerta, agregar tests, etc.).
3. Si fue un bug: agregar test de regresión + alerta más fina.

## 6. Referencias

- [Fase 13 PR1 — Structured logging + correlation IDs][pr1] (`#121`)
- [Fase 13 PR2 — Deep readiness probe][pr2] (`#122`)
- [Fase 13 PR3 — Prometheus metrics][pr3] (`#123`)
- [Fase 13 PR4 — OpenTelemetry tracing][pr4] (`#124`)
- [Fase 13 PR5 — Sentry error tracking][pr5] (`#125`)

[pr1]: ../Fase-13-Observabilidad/README.md#pr1
[pr2]: ../Fase-13-Observabilidad/README.md#pr2
[pr3]: ../Fase-13-Observabilidad/README.md#pr3
[pr4]: ../Fase-13-Observabilidad/README.md#pr4
[pr5]: ../Fase-13-Observabilidad/README.md#pr5

- [Google SRE Book — SLO Engineering](https://sre.google/sre-book/service-level-objectives/)
- [W3C Trace Context](https://www.w3.org/TR/trace-context/)
- [Prometheus alerting best practices](https://prometheus.io/docs/practices/alerting/)
