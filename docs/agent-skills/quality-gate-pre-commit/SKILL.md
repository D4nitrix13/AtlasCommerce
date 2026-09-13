<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 -->

---
name: quality-gate-pre-commit
description: Pre-commit quality gate - backend: pint + phpstan + phpunit. Frontend: astro check + eslint + prettier. Docs: markdownlint. Infra: docker quality-check (absolute paths en Dockerfile, docker-compose, entrypoint). Auto-evaluation checklist before commit. Triggers: pre-commit, pre-push, CI failures, when asked to commit/push.
---

# Quality Gate Pre-Commit

Source: `CONTRIBUTING.md` sec 8 + `docs/development/linting.md` + `docs/development/testing.md`.

## Why this matters

This project **rejects PRs** that don't pass the checks. Cheap fix is
local; expensive fix is in CI. Run the local quality gate before
committing to avoid 5 rounds of "fix CI".

## Backend (PHP / Laravel)

### Full pipeline

```bash
cd backend

# 1. Format (auto-applies changes)
composer format
# Equivalent: vendor/bin/pint

# 2. Verify format without touching files (CI mode)
composer lint
# Equivalent: vendor/bin/pint --test

# 3. Only modified files
composer lint:dirty

# 4. Static analysis (PHPStan level 7)
composer analyse

# 5. Full test suite
composer test

# 6. All-in-one (recommended pre-commit)
composer precommit
# Equivalent: lint:dirty + analyse + test
```

### Configuration

- **`backend/pint.json`** - `Laravel` preset, `declare_strict_types`,
 `no_unused_imports`, trailing commas in multiline.
- **`backend/phpstan.neon`** - level 7, paths: `app/`, `routes/`,
 `database/`, `config/`. Excludes migrations/factories/seeders.

### Rules enforced

- **Strict types** - `declare(strict_types=1);` in every new file.
- **Named arguments** - mandatory in multi-argument calls.
- **Constructor promotion** - no empty `__construct()`.
- **Return types** - always explicit.
- **No unused imports / variables / methods** - Pint blocks.
- **One class, one responsibility** - PHPStan helps detect.

## Frontend (Astro / React / TypeScript)

### Full pipeline

```bash
cd frontend

# 1. Verify types
pnpm run typecheck
# Equivalent: astro check

# 2. ESLint
pnpm run lint # only verify
pnpm run lint:fix # auto-fix

# 3. Prettier
pnpm run format # format
pnpm run format:check # only verify (CI mode)

# 4. Tests
pnpm vitest run

# 5. All-in-one
pnpm run typecheck && pnpm run lint && pnpm run format:check
```

### Configuration

- **`frontend/eslint.config.js`** - flat config, `@typescript-eslint`,
 React + Hooks, JSX A11y, Prettier compat.
- **`frontend/.prettierrc.json`** - `singleQuote: true`, `semi: true`,
 `trailingComma: "es5"`, `printWidth: 100`, plugins `astro`.

### Custom ESLint rules

- `max-lines-per-function`: 40.
- `max-depth`: 3.
- `max-params`: 5.
- `@typescript-eslint/no-explicit-any`: error.

## Docs (Markdown)

```bash
# All markdown
markdownlint 'docs/**/*.md' --ignore node_modules

# One file
markdownlint docs/README.md

# Partial auto-fix
markdownlint --fix docs/
```

Config in `.markdownlint.json`:

- `MD013` line-length 80 (except code blocks, tables, headings).
- `MD024` siblings_only (no duplicate headings at same level).
- `MD033` allows `br`, `kbd`, `sub`, `sup`.
- `MD041` first line must be H1.
- `MD046` code blocks must be fenced.

Style rules in `docs/agent-skills/documentation-styleguide/`.

## Infra (Docker — paths absolutos)

Regla: cualquier binario invocado desde un `Dockerfile.*`,
`docker-compose*.yml` o `entrypoint*.sh` dentro de `docker/instances/`
debe usar **ruta absoluta**. Mitiga PATH hijacking.

```bash
# Verificación manual
./scripts/docker/quality-check.sh

# Activación por dev (una vez por máquina)
./scripts/install-git-hooks.sh
```

El check vive en [`scripts/docker/quality-check.sh`](../../scripts/docker/quality-check.sh "scripts/docker/quality-check.sh"). Cuando el hook está instalado, el commit se bloquea automáticamente si hay archivos Docker staged con violaciones. Regla completa en
[`docs/agent-skills/docker-stack-orchestration/SKILL.md`](docker-stack-orchestration/SKILL.md "docker-stack-orchestration/SKILL.md").

Bypass explícito: `git commit --no-verify`. Usar solo si sabés
exactamente por qué la excepción es necesaria.

## Pre-commit complete (recommended)

```bash
# Backend
cd backend && composer precommit

# Frontend
cd ../frontend && pnpm run typecheck && pnpm run lint && pnpm run format:check

# Docs
cd .. && markdownlint 'docs/**/*.md' --ignore node_modules

# Infra (Docker). Ya corre vía git hook si install-git-hooks.sh está hecho;
# esta línea es para correrlo manual sin staging.
./scripts/docker/quality-check.sh
```

## Self-evaluation before commit (CONTRIBUTING sec 6)

Answer aloud:

1. Is there a simpler solution?
2. Is there a more maintainable solution?
3. Is there a more secure solution?
4. Is there a more efficient solution?
5. Am I reusing existing code?
6. Am I introducing technical debt?
7. Am I breaking an existing abstraction?

If any answer is **yes**, apply the improvement before commit.

## Blocking criteria (CONTRIBUTING sec 9)

A PR is **rejected** when:

- It breaks existing tests.
- It doesn't include tests for new code.
- It introduces avoidable technical debt.
- It decreases project quality.
- It violates architecture principles.
- It introduces security risks.
- It has unresolved performance issues.
- It is difficult to maintain or extend.

> "Better to delay a delivery than to commit code that compromises
> stability, security, or maintainability."

## CI pipeline

`.github/workflows/quality-gate.yml` runs these checks automatically
on `push` to `master` and on PRs. If anything fails, the PR cannot be
merged.

## Anti-patterns

- ❌ Committing without running the local quality gate.
- ❌ `vendor/bin/pint --test --format agent` (use `--format agent` only, not `--test`).
- ❌ `php artisan test` without `composer config:clear` before.
- ❌ `pnpm run typecheck` if `.astro/` cannot be written (permissions issue).
- ❌ `git commit --no-verify` without asking.
- ❌ `--dirty` is not `precommit` - `lint:dirty` only lints, doesn't test.
- ❌ Skipping the Docker quality-check with `--no-verify` cuando hay archivos Docker staged con violaciones.

## Trigger patterns

Activate when:

- Before every `git commit`.
- After a CI failure.
- When there are Pint / PHPStan / ESLint errors in logs.
- When the user says "I'll commit it" / "open the PR".
- When a test fails locally.

## Related skills

- `conventional-commits-and-prs` - format of the commit after passing the gate.
- `laravel-feature-testing` - PHPUnit test conventions.
- `laravel-architecture-decisions` - when Service vs Controller.
