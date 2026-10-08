# `docs/operations/runbooks/`

Pasos a seguir cuando algo se rompe en producción. Cada archivo
es un escenario de incidente específico con diagnóstico + mitigación.

## Contenido

- `database-down.md` — Postgres no responde. Qué chequear, cómo
  recovery, cuándo escalar a SRE.
- `redis-down.md` — Redis no responde. Cómo afecta el sistema (sesiones
  van a file fallback, cache se pierde), y cuándo es crítico.
- `rollback-deployment.md` — template genérico para rollback de un
  deploy: cómo revertir, qué validar, cómo documentar.

## Cómo agregar un nuevo runbook

1. Copiá `rollback-deployment.md` como template.
2. Reemplazá `<incident-slug>` por el nombre del incidente
   (kebab-case, descriptivo: `payment-gateway-timeout.md`,
   `cdn-cert-expired.md`, etc).
3. Completá: síntomas, diagnóstico, mitigación, comunicación,
   post-mortem.
4. Agregá un link a este README.md (sección "Contenido").
5. Si el incidente es recurrente, considerá hacerlo en una
   alerta de monitoring.
