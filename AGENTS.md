# AtlasCommerce — Agent Rules (root)

**Repo:** plataforma SaaS multi-tenant sobre Laravel 13 + Astro 7.
Resolución de tenant por dominio (middleware `store.domain`).

Este archivo es un **ancla con punteros**. No duplica convenciones:
estas viven donde corresponde. Antes de editar cualquier archivo:

1. **Leé `.ai/rules/index.md`** y abrí las reglas cuyos globs cubran
   los paths en scope.
2. **Leé el AGENTS.md del scope** (backend / frontend).
3. **Antes de commitear**, consultá
   [`CONTRIBUTING.md`](CONTRIBUTING.md) §"Workflow de cambio" y
   §"Autoevaluación".

## Mapa de paths

| Scope | Reglas |
|---|---|
| `backend/**` | [`backend/AGENTS.md`](backend/AGENTS.md) |
| `frontend/src/**` | [`frontend/AGENTS.md`](frontend/AGENTS.md) |
| Containers / Docker | Usar [`scripts/docker-stack.sh`](scripts/docker-stack.sh) siempre. NUNCA `docker compose` directo. Ver [`.ai/rules/tooling/docker-stack.md`](.ai/rules/tooling/docker-stack.md). |
| Cualquier path | Cómo se ejecutan los comandos: [`.ai/rules/tooling/resource-budget.md`](.ai/rules/tooling/resource-budget.md) |

## Reglas duras

- **Frontend usa `bun` exclusivamente.** NUNCA `pnpm`, NUNCA `npm`.
  Ver [`.ai/rules/tooling/package-manager.md`](.ai/rules/tooling/package-manager.md).
- **Containers van por `scripts/docker-stack.sh`.** NUNCA `docker
  compose` directo, NUNCA `bun astro dev` ad-hoc, NUNCA
  `php artisan serve` ad-hoc.
- **Comandos pesados van en bloques chicos.** Un comando pesado por
  bloque; Docker separado en `build` / `up` / health-check / migrate;
  tests filtrados durante iteración. Ver
  [`.ai/rules/tooling/resource-budget.md`](.ai/rules/tooling/resource-budget.md).

## Workflow

Ver [`CONTRIBUTING.md`](CONTRIBUTING.md) para principios rectores,
convenciones automatizadas en CI (backend, frontend, SQL) y workflow
de cambio + criterios de aceptación.

## Cómo reportar reglas faltantes u obsoletas

Abrir PR que toque `.ai/rules/{categoria}/{nombre-corto}.md` + la fila
correspondiente en [`.ai/rules/index.md`](.ai/rules/index.md). Si una
regla contradice el `AGENTS.md` del scope, gana el `AGENTS.md` del
scope. Si contradice `CONTRIBUTING.md`, gana `CONTRIBUTING.md`.
