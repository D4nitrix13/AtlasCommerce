# Runbook Template — `<incident-slug>`

> **Este archivo es el template.** Para un incidente real, copiá
> este archivo a `docs/runbooks/<fecha>-<slug>.md` (ej:
> `2026-09-25-postgres-saturated.md`) y completá cada sección
> con la información específica del incidente.

## Metadata

```yaml
title: "<título descriptivo del incidente>"
slug: "<incident-slug>"             # ej: postgres-saturated
date: "YYYY-MM-DD"
detection: "<cómo nos dimos cuenta>"   # alerta, reporte de usuario, métrica
severity: SEV-3 | SEV-2 | SEV-1       # SEV-1 = crítico, SEV-3 = menor
duration_minutes: N
resolved_by: "@usuario que cerró el incidente"
related_rfs: []                       # ej: [RF-PERF-024, RF-OBS-135]
```

## TL;DR

Una o dos frases describiendo qué pasó y qué se hizo. Lo primero
que alguien lee en 6 meses.

```text
<qué pasó> → <acción principal> → <estado actual>
```

## Timeline (con hora UTC)

| Hora   | Evento                                                |
| ------ | ----------------------------------------------------- |
| HH:MM  | Detección (primera señal — alerta, ticket, métrica)   |
| HH:MM  | Acknowledgment (alguien tomó el incidente)            |
| HH:MM  | Diagnóstico (qué estaba pasando realmente)            |
| HH:MM  | Mitigación (acción para reducir impacto)              |
| HH:MM  | Resolución (causa raíz corregida)                     |
| HH:MM  | Post-mortem agendado (si SEV-1 o SEV-2)               |

## Impact

- **Quién se vio afectado**: usuarios finales, admins, ambos, nadie
- **Cuántos**: porcentaje de requests fallidos, usuarios afectados,
  revenue perdido (estimado)
- **Cuánto tiempo**: duración total del impacto (no del incidente)

## Detection

¿Cómo nos dimos cuenta?

- [ ] Alerta automática (Sentry / Prometheus)
- [ ] Reporte de usuario (soporte, redes sociales, mail)
- [ ] PagerDuty / on-call rotation
- [ ] Métrica anómala detectada durante revisión de rutina

Pegar el link al dashboard / alerta original.

## Root cause

Una explicación técnica de POR QUÉ pasó. Incluir:

- Bug de código (con PR de fix)
- Config mal seteada (con cambio de config)
- Dependencia externa caída (con status page link)
- Capacidad saturada (con métrica que lo demuestra)
- Error humano (con procedimiento nuevo para evitarlo)

## Mitigation / Resolution

Acciones tomadas paso a paso. Incluir:

- Comandos exactos ejecutados
- Output relevante (truncado)
- Decisión de rollback vs fix-forward
- Tiempo de cada paso

```bash
# Ejemplo: rollback a commit anterior
git revert <bad-commit-sha>
./scripts/docker-stack.sh exec production panda-estampados-and-kitsune backend \
  php artisan migrate --force    # si aplica
./scripts/docker-stack.sh restart production panda-estampados-and-kitsune backend frontend
```

## Prevention

Acciones de seguimiento para que NO vuelva a pasar:

- [ ] Test nuevo (con link al PR)
- [ ] Alerta mejorada (con dashboard)
- [ ] Documentación actualizada (link)
- [ ] Runbook mejorado (este archivo)
- [ ] Capacidad aumentada (con justificación de costo)
- [ ] Acción de training para el equipo

## Lessons learned

Qué aprendimos. Lo más importante: NO tenés que haber aprendido esto
durmiendo poco bajo presión.

## References

- Dashboards de Grafana
- Alertas de Prometheus / Sentry
- PRs de fix
- ADRs (decisiones tomadas después del incidente)
- Threads de Slack / discussions relevantes
