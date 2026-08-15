---
name: conventional-commits-and-prs
description: Conventional Commits enforced in this project - Spanish message body, type(scope) format, scopes backend/frontend/docs/infra/ci. Branch naming feat/<scope>-desc. PR template with Qué cambia/Por qué/Cómo se prueba/Checklist. Triggers: every commit, every PR, branch creation, merge.
---

# Conventional Commits & PRs

Source: `docs/development/git-workflow.md` + `CONTRIBUTING.md`.

## Why this matters

Every commit and PR in this project follows Conventional Commits with
**Spanish message body** and **scope mandatory**. Without it, the CI
release-please workflow cannot classify changes, the changelog gets
dirty, and reviewers cannot understand the scope.

> The skill content is in English for better LLM matching. The agent
> applies it writing messages in Spanish per project convention.

## Commit format

```txt
tipo(scope): descripción corta en español

[cuerpo en español - por qué, no qué]

[footer opcional - refs a issues, breaking changes]
```

### Types

| Type | When to use |
| ----------- | -------------------------------------------------- |
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `style` | Formatting (prettier, pint) without logic change |
| `refactor` | Refactor without behavior change |
| `test` | Add or fix tests |
| `chore` | Maintenance (deps, build, infra, scripts) |
| `ci` | Changes in `.github/workflows/` |

### Project scopes

| Scope | Covers |
| ------------ | --------------------------------------------------- |
| `backend` | Changes in `backend/` (Laravel, PHP, tests) |
| `frontend` | Changes in `frontend/` (Astro, React, components) |
| `docs` | Changes in `docs/` |
| `infra` | Changes in `docker/`, `Caddyfile`, infra |
| `ci` | Changes in `.github/workflows/` |
| `*` (no scope) | Global changes (root, configs, scripts) |

### Valid examples

```txt
feat(backend): endpoint /api/auth/login con Sanctum tokens

- Implementa AuthController.login con validación via FormRequest
- Token Sanctum con abilities y device_name
- Tests E2E con happy path + credenciales inválidas

fix(frontend): redirect post-login mantiene subdominio admin

Antes: window.location.href = '/es/admin/dashboard' (forzaba host)
Ahora: window.location.href = window.location.origin + '/dashboard'

docs(frontend): guía de setup para development local

- Cubre instalación de PHP, Node, Docker
- Configuración de /etc/hosts para multi-tienda
- Caddyfile con routing por host
```

## Branches

Format: `tipo/scope-descripcion-corta`.

```bash
feat/auth-login-frontend
fix/cart-overflow-mobile
refactor/backend-payments-service
docs/multi-store-decisions
```

**Never** push directly to `master`. Everything goes through PR.

## Pull Requests

### Title

Same format as commits:

```txt
feat(backend): endpoint /api/auth/login con Sanctum tokens
```

### Description (template)

```md
## Qué cambia

- Resumen del cambio en 1-2 frases.

## Por qué

- Problema que resuelve.
- Link al issue/ticket (si hay).

## Cómo se prueba

- Pasos para reproducir.
- Tests agregados/modificados.

## Screenshots

- Si es UI.

## Checklist

- [ ] Tests pasan localmente
- [ ] Linter pasa
- [ ] Docs actualizadas (si aplica)
- [ ] Sin secrets commiteados
```

### Reviewers

- **Backend**: 1 reviewer with Laravel knowledge.
- **Frontend**: 1 reviewer with Astro/React knowledge.
- **Large changes (> 400 lines)**: 2 reviewers.

### Merge strategy

- **Squash** when the PR has small commits.
- **Rebase** when the commits are clean.
- **Never** force-push to `master` during an open PR.

## Anti-patterns

- ❌ `feature:` (typo - use `feat`).
- ❌ `feat: agregué login` - missing scope.
- ❌ `feat(backend): cambios` - generic message.
- ❌ `Update` / `Fix` / `WIP` - no context.
- ❌ Co-authored-by of AIs (project rule from AGENTS.md).
- ❌ `git commit --amend` after a failure (create a new one).
- ❌ `git push --force` without asking.
- ❌ Commits with `Co-Authored-By: ... <noreply@anthropic.com>`.
- ❌ Mixing refactor + feat in a single commit.
- ❌ `git commit --no-verify` without asking.

## TL;DR - commands

```bash
# 1. Branch
git switch -c feat/<descripcion>

# 2. Changes + tests
# ...

# 3. Quality gate local
composer precommit # backend
pnpm run typecheck && pnpm run lint # frontend

# 4. Commit
git add -p
git commit -m "feat(backend): descripcion"

# 5. Push + PR
git push origin feat/<descripcion>
gh pr create --title "feat(backend): ..." --body "..."
```

## Trigger patterns

Activate when:

- Before any `git commit`.
- Before creating / merging a PR.
- When the user asks to "make a commit" or "open a PR".
- When a commit message does not follow the format.
- When writing the description of a PR.

## Related skills

- `quality-gate-pre-commit` - checks that run BEFORE commit.
- `git-monorepo-workflow` - monorepo conventions (subdirs, pnpm).
- `documentation-styleguide` - format for the docs you touch.
