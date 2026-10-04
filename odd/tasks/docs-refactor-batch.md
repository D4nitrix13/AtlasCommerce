# Feature: docs/ refactor batch — 16 REFACTORs pendientes

## Objetivo

Resolver los **16 REFACTORs** documentados en `atlascommerce/docs-audit-2026-09-24` (memoria obs #367) que quedaron
pendientes después del audit de docs/ del 24-sep. Las fases MIGRATE(5) + DELETE(7) + nav fixes(2)
ya se aplicaron — esta es la última fase del cleanup.

## Por qué

`docs/` tenía 92 archivos `.md` con paths rotos, comandos `docker compose` directos (prohibidos por
AGENTS.md raíz — debe ser `scripts/docker-stack.sh`), referencias a K8s (no usamos), dominios y
service names obsoletos, typos graves y chars cirílicos por encoding bug. Esto confunde a cualquier
dev o agente que intente seguir las instrucciones.

## Scope

**In-scope**:
- Reescritura/refactor de 16 archivos `.md` en `docs/` agrupados por tema (A: Docker paths, B: API
  versioning, C: Multi-tenant framing)
- Encoding fixes (chars cirílicos) en `docs/operations/ports.md` y `docs/deployment/instances.md`
- Typo fix "panda-estampados-and-kitsune-and-kitsune" en `docs/architecture/multi-instance-roadmap.md`
- Solo los 3 commits ODD de este plan — ningún cambio a código PHP/JS/TS

**Out-of-scope**:
- Nuevos ADRs en `architecture/decisions.md` (queda para fase de docs formal)
- Cualquier cambio a backend o frontend
- Nuevos archivos en `docs/` (excepto si un REFACTOR lo requiere justificadamente)
- Push a remote (decisión humana separada)

## Constraints

- Conventional Commits en español (scope: `docs`). Mensaje describe outcome, no lista de archivos.
- NO usar `Co-Authored-By` ni atribuciones de AI.
- Markdownlint debe pasar al 100%: `npx markdownlint-cli2 "docs/**/*.md"` (config en `.markdownlint.json`,
  80 chars/line, MD013 enabled).
- Seguir `docs/DOCUMENTATION_STYLEGUIDE.md` (español, nombres técnicos en inglés, 80 chars/line).
- Reemplazar **TODOS** los `docker compose` directos por referencias a `scripts/docker-stack.sh`.
- Reemplazar **TODOS** los paths `docker/dev/` por `docker/instances/development/<tenant>/`.
- NO agrego links a archivos que no existan. Verifico cada link con `find` o `ls` antes.
- NO toco archivos fuera del scope del REFACTOR asignado.

## Archivos por grupo

### Grupo A — Multi-instance Docker paths (8 archivos + encoding fixes)

| Archivo | Cambio principal |
|---|---|
| `docs/operations/ports.md` | Reescribir contra `docker/instances/development/<tenant>/` + fix chars cirílicos |
| `docs/stack/infrastructure.md` | Eliminar sección K8s especulativa, reemplazar `docker compose` |
| `docs/stack/database.md` | Paths corregidos a `docker/instances/...`, separar stack vs ops |
| `docs/operations/deployment.md` | Diagrama multi-instance (3 dev + 3 prod), fix puertos |
| `docs/deployment/instances.md` | Reemplazar 17+ `docker compose` directos + fix char cirílico |
| `docs/operations/secret-rotation.md` | Procedimientos per-instance, service names actuales |
| `docs/getting-started/local-setup.md` | Paths con stage prefix + service names actuales |
| `docs/operations/backups.md` | Mentalidad multi-DB, paths per-instance |

### Grupo B — API versioning (2 archivos)

| Archivo | Cambio principal |
|---|---|
| `docs/backend/api/authentication.md` | `/api/auth/...` → `/api/v1/auth/...`, 2FA status actualizado |
| `docs/backend/auth/auth-2fa.md` | `/api/auth/2fa/...` → `/api/v1/auth/2fa/...`, reconciliar contradicciones 2FA |

### Grupo C — Multi-tenant → Multi-instance framing (8 archivos + typo fix)

| Archivo | Cambio principal |
|---|---|
| `docs/architecture/multi-instance-roadmap.md` | Fix typo "panda-estampados-and-kitsune-**and-kitsune**" (5 ocurrencias), reconciliar métricas |
| `docs/architecture/flow.md` | Dominio correcto, eliminar refs K8s, fecha corregida, 3 stores |
| `docs/architecture/overview.md` | Reescribir framing para 3 instancias/4 brands |
| `docs/architecture/multi-store.md` | 3 stores (no 2), dominios actualizados |
| `docs/database/data-model.md` | Estructura multi-instance explícita |
| `docs/architecture/decisions.md` | Sin ADRs nuevos (out-of-scope), pero corregir errores |
| `docs/operations/environments.md` | Env paths per-instance, ports Mailpit verificados |
| `docs/operations/oauth-providers.md` | Env paths per-instance, tabla dominios OAuth completa |

## Tareas

### T1 — Grupo A (writer: agent "general")
- [ ] Refactorizar 8 archivos de `docs/` listados arriba
- [ ] Fix chars cirílicos en `ports.md` y `instances.md`
- [ ] Correr `npx markdownlint-cli2 "docs/**/*.md"` → debe pasar
- [ ] `git add` solo archivos del grupo A
- [ ] Commit con mensaje `docs(repo): multi-instance Docker paths (Grupo A — 8 archivos)`
- [ ] Reportar hash del commit + lista de archivos cambiados

### T2 — Grupo B (writer: agent "general")
- [ ] Refactorizar 2 archivos (`authentication.md`, `auth-2fa.md`)
- [ ] Correr `npx markdownlint-cli2 "docs/**/*.md"` → debe pasar
- [ ] `git add` solo archivos del grupo B
- [ ] Commit con mensaje `docs(repo): API versioning /v1/ en auth y 2FA (Grupo B)`
- [ ] Reportar hash del commit + lista de archivos cambiados

### T3 — Grupo C (writer: agent "general")
- [ ] Refactorizar 8 archivos listados arriba
- [ ] Fix typo "panda-estampados-and-kitsune-and-kitsune" 5 ocurrencias en `multi-instance-roadmap.md`
- [ ] Correr `npx markdownlint-cli2 "docs/**/*.md"` → debe pasar
- [ ] `git add` solo archivos del grupo C
- [ ] Commit con mensaje `docs(repo): multi-tenant a multi-instance framing (Grupo C — 8 archivos)`
- [ ] Reportar hash del commit + lista de archivos cambiados

### T4 — Cierre (orchestrator)
- [ ] Verificar 3 commits en `git log --oneline` desde `master`
- [ ] Verificar `git status` limpio
- [ ] Memory save (síntesis)

## Acceptance criteria

- 3 commits atómicos en master
- Markdownlint 100% verde
- Cero cambios fuera de `docs/`
- Conventional commits respetados
- Cada grupo entregado como PR-ready (futuro)

## Verification

Después de T1+T2+T3 cerrados:
- `git log --oneline -4` → 3 commits ODD visibles
- `git status` → clean
- `npx markdownlint-cli2 "docs/**/*.md"` → exit 0
- `wc -l docs/**/*.md` → no debería crecer ni bajar >20% (refactor, no rewrite)

## Routing

3 writers en paralelo vía sub-agent `general`, cada uno con su scope aislado. No delego el orquestador
a sí mismo para mantener la separación.

## Decisiones tomadas

- **NO nuevos ADRs** en `decisions.md` para multi-instance/Composer plugins/Core namespace/RLS — eso
  queda para una fase de docs formal separada.
- **NO** nuevo archivo de "summary" o "wrap-up". Solo refactor.
- **NO** push automático — el usuario decide.
- **Deliverable como docs PR-ready**: si el usuario pide PR, cada commit es un slice válido.
