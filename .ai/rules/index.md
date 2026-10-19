# Project Rules — AtlasCommerce

> **Reglas load-bearing.** Antes de CUALQUIER trabajo (exploración,
> edición, creación, commit), leer las reglas que correspondan al
> scope del path en cuestión. Si no leés esto, vas a violar
> convenciones que ya están automatizadas en CI.
>
> **Por qué existe este archivo:** el sistema AGENTS.md global
> (`backend/AGENTS.md`, `frontend/AGENTS.md`, etc.) cubre las
> convenciones load-bearing del lenguaje (Laravel, Astro, etc.).
> Este directorio cubre las convenciones **del proyecto** que son
> transversales y no encajan en un solo archivo AGENTS.md.

---

## Índice de reglas

| Path | Cubre |
|---|---|
| [`tooling/package-manager.md`](tooling/package-manager.md) | Frontend usa `bun` exclusivamente. NO `pnpm`, NO `npm`. |
| [`tooling/docker-stack.md`](tooling/docker-stack.md) | Toda operación con containers va por `scripts/docker-stack.sh`. NO `docker compose` directo, NO `bun astro dev` ad-hoc. |
| [`assets-naming-policy.md`](assets-naming-policy.md) | Estructura per-tenant de `assets/products/`, naming legible en repo vs hasheado en FTP, roles de galería, comandos del seeder. |
| [`workflow/conventions-first.md`](workflow/conventions-first.md) | Antes de cualquier edición, leer las reglas del scope + AGENTS.md + skill load-bearing. |

---

## Cómo cargar las reglas

1. **Path glob** del archivo que vas a tocar:
   - `frontend/src/**` → leer `tooling/package-manager.md` (bun) + `frontend/AGENTS.md` (frontend conventions)
   - `backend/**` → leer `backend/AGENTS.md` (PHP conventions) + `docs/agent-skills/*` si aplica
   - `database/migrations/**` → leer `docs/architecture/migration-pattern.md` (Fase 4)
   - `docs/architecture/**` → leer docs/styleguide si existe
   - `docker/**` → leer `docs/agent-skills/docker-stack-orchestration/SKILL.md`
   - `assets/**` → leer [`assets-naming-policy.md`](assets-naming-policy.md) (per-tenant, naming, roles, comandos)

2. **Reglas load-bearing** (las que ya están automatizadas en CI):
   - `docs/agent-skills/conventional-commits-and-prs/SKILL.md` — commits
   - `docs/agent-skills/quality-gate-pre-commit/SKILL.md` — pre-commit
   - `docs/agent-skills/git-monorepo-workflow/SKILL.md` — monorepo

3. **Skill registry** para áreas específicas (testing, frontend structure, etc.):
   - `docs/agent-skills/README.md` tiene el índice completo.

---

## Cómo agregar una regla nueva

1. Crear el archivo en `.ai/rules/{categoria}/{nombre-corto}.md`.
2. Actualizar este índice con la ruta del nuevo archivo.
3. Si la regla contradice AGENTS.md, gana AGENTS.md (este directorio complementa, no reemplaza).

---

## Cuándo NO crear reglas

- Si la regla ya existe en `AGENTS.md` del scope → no duplicar.
- Si la regla es obvia o genérica (ej. "usar TypeScript") → no documentar.
- Si la regla es de un solo archivo → ponerla en el docblock del archivo, no acá.