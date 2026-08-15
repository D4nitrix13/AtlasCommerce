---
name: git-monorepo-workflow
description: Monorepo conventions (backend Laravel + frontend Astro + docker + docs), commits by section, pnpm workspace sync, cross-stack changes. Triggers: PRs that cross backend↔frontend, commits spanning multiple subdirs, releases.
---

# Git Monorepo Workflow

## Topology

```
/
├── backend/ ← Laravel 13 + PHP 8.5
├── frontend/ ← Astro 7 + React 19 + pnpm workspace
│ ├── packages/themes/ ← @theme/<slug> workspaces
│ └── pnpm-workspace.yaml
├── database/ ← migrations + ER diagrams
├── docker/ ← instances/orchestration
├── docs/ ← docs/operations, docs/database, docs/architecture
├── scripts/ ← docker-stack.sh + scripts/docker/
├── CONTRIBUTING.md ← global rules
├── backend/AGENTS.md ← Laravel conventions
├── frontend/AGENTS.md ← Astro conventions
├── .claude/ ← project-level skills (this dir)
└── Caddyfile ← multi-tenant routing
```

## Commit conventions

### Conventional commits, Spanish

- `feat:`, `fix:`, `refactor:`, `docs:`, `chore:`, `test:`, `style:`, `perf:`, `build:`, `ci:`.
- Scope when applicable: `feat(backend):`, `fix(frontend):`, `docs(database):`.
- Message in Spanish (see commit `7eb0063` as example).
- **ALWAYS** descriptive messages, not "Update" / "Fix".

### By logical section

When a change crosses frontend + backend + docs, **split into commits**:

```
chore: add migration for <table>
feat(backend): add controller for <resource>
feat(frontend): add page for <resource>
docs: update docs/api/<resource>.md
```

No commit `feat: implement <big-feature>` that touches 50 files. Split.

### Ignored

- `.atl/` (AI tooling) - in `.gitignore` at all levels.
- `frontend/.astro/` (Astro cache) - gitignored.
- `backend/storage/logs/*` - gitignored.

## When to touch which subdir

| Change | Subdir |
|---|---|
| API endpoint | `backend/app/Http/Controllers/Api/` + `routes/api.php` + tests |
| Page | `frontend/src/pages/[locale]/...` + components |
| Tenant config | `frontend/packages/themes/<slug>/` + `frontend/src/config/stores.ts` + `docker/instances/<env>/<slug>/` + `Caddyfile` |
| DB schema | `backend/database/migrations/` + `database/` (ER diagrams) |
| Deploy | `docker/` + `Caddyfile` + `scripts/` |
| Skill / agent | `.claude/skills/` or `~/.config/opencode/skills/` |

## Cross-stack changes (frontend + backend)

A PR that changes API contract + UI:

1. **Backend first**: migration → model → controller → routes → tests.
2. **Frontend after**: types in `src/types/` → components → pages.
3. **Docs at the end**: `docs/api/<endpoint>.md` or `docs/architecture/`.

Example: the auth palette refactor (recent commit).

## pnpm workspace sync

`frontend/node_modules/@theme/<slug>` → symlink to `frontend/packages/themes/<slug>`.

If you add a new theme:
```bash
cd frontend
mkdir -p packages/themes/<slug>/src
# create index.ts
ln -s ../../packages/themes/<slug> node_modules/@theme/<slug>
pnpm install
```

## Trigger patterns

Activate when:

- PR that touches 2+ subdirs.
- Bug that crosses backend↔frontend (e.g., API contract change).
- Release / deploy (multi-instance).
- Setting up a new dev (clone + setup).
- Sync changes after an upgrade.

## Pre-commit checklist

```bash
# Backend
cd backend
composer lint:dirty # pint
composer analyse # phpstan
composer test # phpunit

# Frontend
cd frontend
pnpm vitest run # vitest
pnpm run lint # eslint
# pnpm run typecheck # astro check (if no perms in .astro/)
```

## Branching

- Default branch: `master` (not `main`).
- Feature branches: `feat/<slug>` or `fix/<slug>`.
- Trunk-based when possible; PRs small (< 400 lines preferred, see `chained-pr` skill).

## Anti-patterns

- ❌ Commits with `Co-Authored-By: AI` or AI attributions.
- ❌ `git commit --amend` after a failure (create a new one).
- ❌ `git push --force` (without explicit user request).
- ❌ Mixing refactor with feat in one commit.
- ❌ Touching `frontend/.astro/` (root-owned, not commitable).

## File map

```
/CONTRIBUTING.md ← global monorepo rules
/backend/AGENTS.md ← Laravel conventions
/frontend/AGENTS.md ← Astro conventions
/.claude/skills/ ← project skills (this dir, commitable)
/scripts/docker-stack.sh ← entry point
/docker/versions.env ← pinned versions
```

## Related skills

- `branch-pr` - PR creation.
- `chained-pr` - PRs > 400 lines stacked.
- `work-unit-commits` - commits as review units.
- `sdd-archive` - end-of-change archiving.
