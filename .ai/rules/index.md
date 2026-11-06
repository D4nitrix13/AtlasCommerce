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
| [`tooling/docker-stack.md`](tooling/docker-stack.md) | Toda operación con containers va por `scripts/docker-stack.sh`. NO `docker compose` directo, NO `bun astro dev` ad-hoc. **Secuencia de reinicio tras recrear el backend**, caché del dev server y assets. |
| [`tooling/resource-budget.md`](tooling/resource-budget.md) | Presupuesto de recursos: un comando pesado por bloque, Docker en bloques separados, preflight de carga, higiene de disco. |
| [`tooling/dev-domains-and-session.md`](tooling/dev-domains-and-session.md) | Acceso en dev (Caddy del host, puerto 5321) y los 3 invariantes de la sesión: SANCTUM_STATEFUL_DOMAINS con `host:puerto`, proxy `/sanctum`+`/api` con `changeOrigin:false`, SSR autenticado reenvía `Cookie`+`Origin`. |
| [`tooling/dev-data-safety.md`](tooling/dev-data-safety.md) | BD `_test` vs BD dev: tests SOLO contra `_test`, dumps antes de operaciones destructivas, limpieza de locks, `.env` de worktrees. |
| [`tooling/diagnostic-checklist.md`](tooling/diagnostic-checklist.md) | Antes de "no se ve / está roto": stack arriba, curl con `Host`, HTML servido vs marcador, caché de Astro, bind mounts, caché del navegador. |
| [`assets-naming-policy.md`](assets-naming-policy.md) | Estructura per-tenant de `assets/products/`, naming legible en repo vs hasheado en FTP, roles de galería, comandos del seeder. |
| [`workflow/conventions-first.md`](workflow/conventions-first.md) | Antes de cualquier edición, leer las reglas del scope + AGENTS.md + skill load-bearing. |
| [`workflow/multi-session-isolation.md`](workflow/multi-session-isolation.md) | **Sesiones en paralelo**: worktree propio por sesión, bloque de estado ✅/⏳/⬜ en cada respuesta, y entrega push+PR+merge al terminar. |
| [`workflow/quality-gates.md`](workflow/quality-gates.md) | Gate de entrega: tests, typecheck, lint scoped (0 errores nuevos), pint, CI vs baseline preexistente. |
| [`workflow/i18n-and-references.md`](workflow/i18n-and-references.md) | Ciclo de vida de claves i18n (es+en+types juntos, grep de referencias antes de borrar) y edición aditiva en archivos compartidos. |
| [`workflow/pr-sizing.md`](workflow/pr-sizing.md) | Un PR = un comportamiento revisable, ~400 líneas autoras, slices encadenados, commits como work-units. |
| [`workflow/api-error-locale-contract.md`](workflow/api-error-locale-contract.md) | Contrato 422 `message`+`errors`, `X-Locale`, mapeo por campo en forms, y meta-regla: toda interfaz compartida nueva se documenta como regla. |
| [`workflow/decisions-storefront-rendering.md`](workflow/decisions-storefront-rendering.md) | Decisión CSR vs SSR del listado de productos del storefront y su porqué. |
| [`workflow/browser-auth-contract.md`](workflow/browser-auth-contract.md) | Contrato del login con 2FA (challenge → `/es/account/2fa`, verify público `verify-totp-login`), credenciales dev con TOTP, y la resolución de origin de `apiFetch`. |

---

## Cómo cargar las reglas

> **Regla universal — independiente del path:**
> [`tooling/resource-budget.md`](tooling/resource-budget.md) aplica a
> TODA tarea del repo. Regula CÓMO se ejecutan los comandos (un comando
> pesado por bloque, Docker en bloques separados, tests filtrados
> durante iteración, preflight de carga, higiene de disco), no un path
> específico. Es la única regla transversal de ejecución.

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