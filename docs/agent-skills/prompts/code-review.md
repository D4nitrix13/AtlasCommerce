<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 -->

# Code Review Prompt

This is a reusable prompt template that activates the project's skill
catalog to perform a comprehensive code review. It is **not** a skill
(does not auto-load on trigger) — it is a prompt you invoke explicitly
when you want the agent to review code.

## How to use

1. Copy the prompt template below.
2. Replace `[SCOPE]` with the code you want reviewed.
3. Optionally adjust the "Skills to activate" section to focus on a
   specific layer (backend only, frontend only, etc.).
4. Submit it as a request to the agent.

The agent will:

- Load the relevant skills.
- Read the code in context.
- Find issues categorized by severity.
- Output structured findings with file references and suggested fixes.
- Write the response in Spanish per project convention.

---

## Prompt template

```text
Eres un senior code reviewer para este proyecto Laravel 13 + Astro 7
multi-tenant. Tu salida es en español.

## Alcance

Revisá: [SCOPE]

## Skills a activar

Antes de empezar, cargá estas skills (contienen las convenciones del
proyecto):

[Para backend:]
- laravel-architecture-decisions: cuándo usar Controller vs Service
  vs Model vs Notification vs Command vs Job vs Resource vs
  FormRequest vs Migration.
- laravel-multi-tenant-patterns: tenant_id, entidades globales vs
  per-tenant, RLS context, user_stores, customer_stores.
- laravel-feature-testing: PHPUnit 12, RefreshDatabase vs
  DatabaseTransactions, factory states, named args.
- quality-gate-pre-commit: Pint + PHPStan rules, pre-commit checklist.
- redis-caching-patterns: tenant-prefixed cache keys, sessions,
  queues.
- caddyfile-multi-tenant-routing: hosts, IPs fijas.
- docker-stack-orchestration: per-tenant dev environments.

[Para frontend:]
- astro-frontend-structure: dónde poner cada archivo, UI vs domain,
  pages por módulo.
- astro-themes-and-palette: paleta universal auth (#2D9D8E), theme
  packages, theme→brand adapter.
- astro-vitest-component-testing: Vitest 4, mocks de stores, i18n,
  userEvent.
- playwright-e2e-patterns: E2E tests, selectores, multi-tenant.
- quality-gate-pre-commit: ESLint + Prettier.

[Para ambos:]
- documentation-styleguide: si hay cambios en docs.
- conventional-commits-and-prs: formato de commits.
- git-monorepo-workflow: cómo organizar commits por sección.
- adr-format: si hay decisiones arquitectónicas.
- engram-conventions: cuándo mem_save un hallazgo importante.

## Qué buscar

Para cada sección, escaneá:

1. **Bugs y errores de runtime**
   - Manejo de null/undefined.
   - Off-by-one.
   - Race conditions.
   - Edge cases (arrays vacíos, valores 0, division by zero).

2. **Vulnerabilidades de seguridad**
   - SQL injection (raw queries, bindings).
   - XSS (HTML no escapado).
   - CSRF protection.
   - Auth bypass (middleware auth: faltante).
   - Secrets en código.
   - Mass assignment (unguarded $fillable).

3. **Multi-tenant data leaks**
   - store_id filter faltante en queries.
   - store_id hardcoded en controllers (usar
     StoreContextService::current()).
   - Cache keys sin prefijo de tenant.
   - Jobs con queries cross-tenant (deben serializar store_id).

4. **Performance regressions**
   - N+1 queries (eager loading faltante).
   - DB indexes faltantes.
   - Loops ineficientes sobre datasets grandes.
   - Trabajo innecesario en hot paths.

5. **Convention violations**
   - Laravel: missing strict_types, missing named args, unused
     imports, fillable sin guarded.
   - Frontend: any type, magic numbers, hardcoded colors.
   - Auth: brand.accentColor en auth flow (debe ser AUTH_ACCENT).
   - Componentes: business logic en ui/.

6. **Test coverage gaps**
   - Happy path sin failure path.
   - Multi-tenant test faltante.
   - Assertion faltante para error state.
   - Test depende del orden.

7. **Accessibility** (frontend only)
   - alt faltante en <img>.
   - <div> como botón.
   - <label> faltante para <input>.
   - Color contrast issues.

8. **i18n gaps** (frontend only)
   - Strings hardcoded user-facing.
   - Translation keys faltantes.
   - Locale fallback incorrecto.

## Output format

Para cada hallazgo, output:

### [SEVERITY] [CATEGORY]: [short title]

**File**: `path/to/file.php:line`
**Skill**: [skill que detectó el issue]
**Why it matters**: [consecuencia si no se arregla]
**Suggested fix**:
[code snippet o approach]

Severities:
- 🔴 CRITICAL: bug, security, data loss — debe arreglarse antes de merge.
- 🟠 HIGH: performance, broken feature — debería arreglarse antes de
  merge.
- 🟡 MEDIUM: convention violation, missing test — arreglar antes del
  próximo sprint.
- 🟢 LOW: style nit, refactoring — nice to have.
- 💡 INFO: observation — noted for future.

## Tabla de resumen

Al final, agregá:

| Severity | Count | Files affected |
|---|---|---|
| CRITICAL | N | list |
| HIGH | N | list |
| MEDIUM | N | list |
| LOW | N | list |
| INFO | N | list |

## Notas

- Este prompt está en inglés para mejor matching del LLM.
- La salida del agente es en español per convención del proyecto.
- El prompt es reutilizable para cualquier scope de review.
- Ajustá la sección "Skills a activar" según el scope (backend only,
  frontend only, full-stack, etc.).
```

---

## Example invocations

### Full project review

```text
Eres un senior code reviewer para este proyecto Laravel 13 + Astro 7
multi-tenant. Tu salida es en español.

## Alcance

Revisá: todo el proyecto (full stack). Focus en security + multi-tenant.

[resto del prompt...]
```

### Specific file review

```text
Eres un senior code reviewer para este proyecto Laravel 13 + Astro 7
multi-tenant. Tu salida es en español.

## Alcance

Revisá: backend/app/Http/Controllers/Api/Auth/PasswordResetTokenController.php
Focus en la nueva rama de recovery_codes.

[resto del prompt...]
```

### Specific feature/module review

```text
Eres un senior code reviewer para este proyecto Laravel 13 + Astro 7
multi-tenant. Tu salida es en español.

## Alcance

Revisá: el flow 2FA end-to-end (backend + frontend).
- backend/app/Http/Controllers/Api/Auth/AuthController.php
- backend/app/Services/Auth/RecoveryCodeService.php
- frontend/src/components/admin/auth/ResetPasswordForm.tsx
- frontend/src/pages/[locale]/admin/auth/reset-success.astro

Focus en security, multi-tenant, y el contrato API entre backend y
frontend.

[resto del prompt...]
```

### Recent changes review

```text
Eres un senior code reviewer para este proyecto Laravel 13 + Astro 7
multi-tenant. Tu salida es en español.

## Alcance

Revisá: cambios desde el último release (git diff main...HEAD).
Focus en conventions violations y test coverage.

[resto del prompt...]
```

---

## Pre-merge checklist

Use this prompt before each PR:

```text
Eres un senior code reviewer para este proyecto Laravel 13 + Astro 7
multi-tenant. Tu salida es en español.

## Alcance

Revisá: el branch actual (git diff main...HEAD). Focus en todos los
aspectos. Output según el formato de findings.

Si todo está OK, también verificá:

- [ ] composer precommit pasa (backend)
- [ ] pnpm typecheck && pnpm lint && pnpm format:check (frontend)
- [ ] pnpm vitest run (frontend)
- [ ] Los tests backend agregados/modificados pasan
- [ ] La documentación nueva está en español y sigue el styleguide
- [ ] El commit message sigue conventional-commits-and-prs
- [ ] El skill engram-conventions aplica: mem_save si hay un
      hallazgo significativo
```

---

## File location

This prompt lives at `docs/agent-skills/prompts/code-review.md`. It is
intended to be:

- Committed to the repo (versioned with the project).
- Updated as the project evolves (new skills → update the "Skills to
  activate" section).
- Customized per team needs (add categories, change output format,
  etc.).

Reference paths in the prompt use `path/to/file.php:line` to be
language-agnostic.

---

## Related resources

- `docs/agent-skills/` — the 16 skills this prompt activates.
- `CONVENTIONAL_COMMITS.md` (in `docs/development/git-workflow.md`) —
  commit format after fixes.
- `CONTRIBUTING.md` — quality gate rules.
- `backend/AGENTS.md` and `frontend/AGENTS.md` — per-stack conventions.
