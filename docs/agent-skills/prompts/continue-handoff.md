# Prompt: Listar y detallar tareas pendientes del handoff

> **Reusable prompt** para invocar en futuras sesiones con el fin de
> enumerar, validar y priorizar TODAS las tareas pendientes del
> `HANDOFF-{fecha}.md`. El prompt activa skills, MCP tools y sub-agentes
> relevantes, valida contra el codebase actual (el handoff puede estar
> desactualizado), y produce un output ejecutable.

## Cuándo usarlo

- **Inicio de sesión** cuando hay un HANDOFF-*.md y querés retomar trabajo.
- **Planning** antes de proponer nuevos SDD changes — ver qué ya está pendiente.
- **Review semanal** del backlog técnico.

## Pre-requisitos

1. Debe existir un archivo `docs/HANDOFF-*.md` (convención: kebab-case + ISO date).
2. El repo debe tener `.codegraph/` indexado (corre `codegraph init` si falta).
3. La sesión anterior debe haber guardado observations en engram con
   `scope=project` y topic_key consistente.

## Skills a activar (antes de empezar)

Cargá estas skills — contienen las convenciones del proyecto:

**Backend:**
- `laravel-architecture-decisions`: Controller vs Service vs Model vs Notification vs Command vs Job vs Resource vs FormRequest vs Migration.
- `laravel-multi-tenant-patterns`: tenant_id, entidades globales vs per-tenant, RLS context, user_stores, customer_stores.
- `laravel-feature-testing`: PHPUnit 12, RefreshDatabase vs DatabaseTransactions, factory states, named args.
- `quality-gate-pre-commit`: Pint + PHPStan rules, pre-commit checklist.
- `redis-caching-patterns`: tenant-prefixed cache keys, sessions, queues.
- `caddyfile-multi-tenant-routing`: hosts, IPs fijas.
- `docker-stack-orchestration`: per-tenant dev environments.

**Frontend:**
- `astro-frontend-structure`: dónde poner cada archivo, UI vs domain, pages by module.
- `astro-themes-and-palette`: paleta universal auth, theme packages.
- `astro-vitest-component-testing`: Vitest 4, mocks, i18n, userEvent.
- `playwright-e2e-patterns`: E2E tests, selectores, multi-tenant.
- `quality-gate-pre-commit`: ESLint + Prettier (frontend).

**Ambos / Workflow:**
- `documentation-styleguide`: si vas a crear docs nuevos.
- `conventional-commits-and-prs`: formato de commits y PRs en español.
- `git-monorepo-workflow`: cómo organizar commits por sección.
- `adr-format`: si hay decisiones arquitectónicas nuevas.
- `engram-conventions`: cuándo `mem_save` un hallazgo importante.

## MCP tools a usar

- **`engram`**: persistencia cross-session. Usá:
  - `mem_context` para resumir el estado reciente.
  - `mem_search` con keywords del handoff (ej. `HANDOFF`, `sdd-init/{project}`, `reviews/{fecha}`).
  - `mem_get_observation` para ver contenido completo.
  - `mem_save` para guardar el output final (topic_key: `pickup/{fecha}-{sesion}`).
- **`codegraph`**: código actual. Usá:
  - `codegraph_explore "<símbolos o preguntas>"` para entender el estado actual sin leer 4+ files.
- **`context7`**: docs de librerías. Usá:
  - `resolve-library-id` + `query-docs` solo si necesitás verificar una API específica.

## Sub-agentes disponibles

- **`sdd-explore`**: investigar ideas antes de comprometer a un SDD change.
- **`sdd-propose`**: crear proposal.
- **`sdd-spec`**: escribir spec.
- **`sdd-design`**: crear design.
- **`sdd-tasks`**: descomponer en tasks.
- **`sdd-apply`**: implementar.
- **`sdd-verify`**: validar contra specs.
- **`sdd-archive`**: archivar al cerrar.
- **`sdd-init`**: bootstrap (solo si nunca corrió).
- **`sdd-onboard`**: guiar walkthrough completo (alternativa).
- **`sdd-status`**: status estructurado de un change activo.
- **Reviewers** (`review-readability`, `review-reliability`, `review-resilience`, `review-risk`): revisión R1-R4 fan-out.
- **`judgment-day`**: adversarial review para diffs críticos.

**Regla**: para tareas de **HIGH architectural** (ej. NOT NULL strategy, multi-tenant refactor), usá el ciclo SDD completo. Para tareas **tactical** (ej. eslint override, prettier config, password rules), NO uses SDD — branch + commit + PR directo.

## Convenciones del proyecto (obligatorias)

- **Idioma de output al usuario**: español Rioplatense.
- **Idioma de artifacts técnicos** (código, comments, UI copy, commits, PRs): español para mensajes, English para identificadores técnicos.
- **Conventional commits en español**: `tipo(scope): descripción`.
- **Scopes**: `backend`, `frontend`, `docs`, `infra`, `ci`.
- **Review budget**: 400 líneas por PR. Size exception si excede.
- **Strict TDD**: cuando aplique (test RED → fix → GREEN).
- **Multi-tenant**: siempre verificar `store_id` filter; no leak cross-tenant.
- **Sanctum stateful**: desde PR #2/#9, sesiones usan httpOnly cookies + CSRF.

## Workflow (paso a paso)

1. **Lee el HANDOFF-{fecha}.md**. Identifica todas las tareas listadas como pendiente (HIGH/MEDIUM/LOW).
2. **Busca en engram**:
   - `mem_context` para resumir actividad reciente.
   - `mem_search` con el topic_key del handoff o reviews/CR.
   - Identifica qué PRs/SDD changes ya se cerraron desde el handoff.
3. **Verifica contra el codebase actual** usando `codegraph_explore`:
   - Para cada HIGH/MEDIUM pendiente, ¿el código ya hace lo que el handoff pide?
   - Ejemplo típico: P1 session regen ya estaba cubierto por Laravel 13.
4. **Actualiza el status**: ✅ done / ⚠️ done parcialmente / ❌ pendiente / 🆕 nuevo (descubierto durante la verificación).
5. **Genera el output** en el formato de abajo.
6. **Guarda en engram** con `mem_save` + `topic_key: pickup/{fecha}-{sesion-id}` + `type: discovery` + `capture_prompt: false` (es un artifact automatizado).

## Output format

Output al usuario (Markdown en español):

```markdown
# Tareas pendientes — pickup {fecha sesión}

> **Estado al {fecha}:** {N} tareas listas para pickup. Verificadas contra
> el codebase actual (no contra el handoff directamente — puede estar
> desactualizado).

## Resumen

| # | Status | Tarea | Tipo | Esfuerzo | Prioridad |
|---|---|---|---|---|---|
| 1 | ❌ | {tarea} | tactical / SDD | {h} | HIGH/MEDIUM/LOW |
| 2 | �️ | {tarea ya cubierta por framework} | — | 0 | — |
| ... | | | | | |

## Detalle por tarea

### Tarea 1: {título}

- **Status**: ❌ pendiente / ⚠️ ya cubierta / ✅ done
- **Tipo**: tactical (branch + commit + PR directo) / SDD (ciclo completo)
- **Esfuerzo estimado**: ~{N} horas
- **Prioridad**: HIGH (security/data) / MEDIUM (DX) / LOW (cosmetic)
- **Handoff ref**: {HANDOFF-2026-08-15.md § ...}
- **Por qué importa**: {consecuencia si no se hace}
- **Archivos probables**: {paths}
- **Approach sugerido**: {SDD o tactical con detalle}
- **Skills/MCP/sub-agentes a usar**: {list}
- **Próximo paso concreto**: {primera acción}
- **Dependencias**: {si depende de otro PR/SDD}
- **Discovery notes**: {si el handoff estaba mal, qué dice el código}

### Tarea 2: ...

## Recomendación de orden

1. **Esta sesión**: {top pick con justificación}
2. **Próxima sesión**: {siguiente prioridad}
3. **Mediano plazo**: {lo demás}

## PRs / SDD ya cerrados desde el handoff

| PR/SDD | Cierre | Fecha |
|---|---|---|
| PR #12 | P1 session regen | 2026-08-16 |
| ... | | |
```

## Prompt template

Reemplazá `{HANDOFF_PATH}` con el path al archivo (ej. `docs/HANDOFF-2026-08-15.md`).

```text
Eres un senior architect + project manager técnico para este proyecto
Laravel 13 + Astro 7 multi-tenant. Tu salida al usuario es en español
Rioplatense.

## Alcance

Listar y priorizar TODAS las tareas pendientes del handoff:
{HANDOFF_PATH}

## Skills a activar (antes de empezar)

[Para backend:]
- laravel-architecture-decisions
- laravel-multi-tenant-patterns
- laravel-feature-testing
- quality-gate-pre-commit
- redis-caching-patterns
- caddyfile-multi-tenant-routing
- docker-stack-orchestration

[Para frontend:]
- astro-frontend-structure
- astro-themes-and-palette
- astro-vitest-component-testing
- playwright-e2e-patterns
- quality-gate-pre-commit (frontend)

[Para ambos:]
- documentation-styleguide
- conventional-commits-and-prs
- git-monorepo-workflow
- adr-format
- engram-conventions

## MCP tools

- engram: mem_context, mem_search, mem_get_observation, mem_save
- codegraph: codegraph_explore (preferí esto sobre Read/Grep cuando
  necesites entender símbolos del codebase)
- context7: solo si necesitás verificar API de una librería

## Sub-agentes disponibles

sdd-{explore,propose,spec,design,tasks,apply,verify,archive,init,onboard,status},
review-{readability,reliability,resilience,risk}, judgment-day.

## Workflow

1. Lee el HANDOFF-{fecha}.md. Identifica tareas HIGH/MEDIUM/LOW pendientes.
2. mem_context + mem_search con keywords del handoff.
3. codegraph_explore para verificar el código actual (NO confíes solo en
   el handoff — puede estar desactualizado, ej. session regen ya estaba
   cubierto por Laravel 13).
4. Clasifica cada tarea: ✅ done, ⚠️ parcialmente done, ❌ pendiente,
   🆕 nuevo (descubierto durante la verificación).
5. Para cada ❌/⚠️: estimá esfuerzo, prioridad, tipo (tactical vs SDD),
   archivos probables, approach, dependencias.
6. Genera el output en el formato definido abajo.
7. mem_save el output con topic_key: pickup/{fecha}-{sesion-id},
   type: discovery, capture_prompt: false.

## Output format

[Insertar el template de output de arriba]

## Tabla de resumen

| Status | Count |
|---|---|
| ✅ Done | N |
| ⚠️ Parcial | N |
| ❌ Pendiente | N |
| 🆕 Nuevo | N |

## Convenciones

- Idioma de output: español Rioplatense
- Conventional commits en español (`tipo(scope): descripción`)
- Scopes: backend, frontend, docs, infra, ci
- Review budget: 400 líneas (size:exception si se excede)
- Strict TDD cuando aplique (test RED → fix → GREEN)
- Validar contra el codebase actual antes de proponer (el handoff puede
  estar desactualizado)
- Para HIGH architectural (ej. NOT NULL strategy): SDD completo.
  Para MEDIUM/LOW tactical: branch + commit + PR directo.

## Pre-output checklist

- [ ] Leí el HANDOFF-{fecha}.md completo
- [ ] Busqué en engram (mem_context + mem_search)
- [ ] Verifiqué contra el codebase actual (codegraph_explore)
- [ ] Identifiqué tareas ya cubiertas por framework (ej. Laravel 13)
- [ ] Estimé esfuerzo para cada ❌/⚠️
- [ ] Clasifiqué tipo (tactical vs SDD) para cada ❌
- [ ] mem_save del output antes de cerrar
```

## Example invocations

### Caso 1: Inicio de sesión con handoff

```text
[Insertar el prompt template con HANDOFF_PATH = docs/HANDOFF-2026-08-15.md]
```

### Caso 2: Planning semanal

```text
[Insertar el prompt template con HANDOFF_PATH = docs/HANDOFF-*.md (todos)]
```

### Caso 3: Solo enumerar (sin priorización)

Si solo querés la lista sin effort/tipo, agregá al prompt:
```
NOTA: no clasifiques por esfuerzo ni tipo. Solo listá las tareas con
status, descripción breve y archivos probables.
```

## Output destination

Por defecto, el output va al chat. Si querés que se guarde como archivo:

```text
Además del output en chat, escribí el output como archivo
docs/pickup-{fecha}.md siguiendo exactamente el formato definido.
Commit'ealo con conventional commit `docs: pickup list {fecha}`.
```

## Post-output checklist

Después de generar el output:

- [ ] `mem_save` con `topic_key: pickup/{fecha}-{sesion-id}`, `type: discovery`, `capture_prompt: false`.
- [ ] Si descubriste que el handoff estaba desactualizado en algún item: `mem_save` adicional con `type: discovery` describiendo el gap (ej. `discovery/{tema}-handoff-desactualizado`).
- [ ] Confirmá con el usuario cuál tarea arrancar primero usando la `question` tool.

## File location

Este prompt vive en `docs/agent-skills/prompts/continue-handoff.md`. Está
versionado con el repo. Actualizar cuando:
- Se agregan skills al catálogo.
- Cambian las convenciones del proyecto.
- Se descubre que el output format no captura algo importante.

## Related resources

- `docs/HANDOFF-*.md` — los handoffs que consumís.
- `docs/agent-skills/prompts/code-review.md` — hermana: prompt para code review.
- `docs/agent-skills/` — las skills que activás.
- `CONTRIBUTING.md` — convenciones generales del proyecto.
- Engram: `mem_search` con `sdd-init/{project}` para contexto base.
