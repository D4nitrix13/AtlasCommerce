# Tasks: rem-viewer

## 1. Overview

Total: **15 tasks across 8 commit units** (3 chained PRs in a feature-branch-chain strategy).
Estimated LOC: **~2050** (frontend-only; backend untouched). Includes **48 `it()` test methods** mapped 1:1 to the **42 spec scenarios** from `specs.md` §3 plus 6 auxiliary tests.
Strict TDD is ACTIVE — every implementation task pairs with its tests in the same commit per `work-unit-commits`.
Delivery strategy: `auto-chain` (orchestrator picks first slice; chain strategy pre-decided here as Feature Branch Chain because total > 400 LoC).

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

## 2. Commit strategy

Each commit stands alone (`pnpm typecheck && pnpm lint && pnpm vitest run && pnpm playwright test` passes after each). One logical work unit per commit. DP1=A precondition is its own commit **C0** because it touches files outside `frontend/` (DP1-A: extend `seed_*.json` + `migrate_to_doorstop.py`; no YAML hand-edits).

| # | Commit | Conventional message | Files touched | LOC est. |
|---|---|---|---|---|
| C0 | `chore(scripts): DP1=A schema extension via seed JSONs + migrate script` | `scripts/requirements/seed_rf.json`, `scripts/requirements/seed_rnf.json`, `scripts/requirements/migrate_to_doorstop.py`, `scripts/requirements/tests/test_migrate.py` | 180 |
| C1 | `feat(frontend): REM viewer domain — types + loader + cache` | `src/lib/rem/types.ts`, `src/lib/rem/loader.ts`, `tests/unit/rem/loader.test.ts`, `tests/unit/rem/fixtures/seed.ts`, `tests/unit/rem/fixtures/seed-invalid.yml` | 360 |
| C2 | `feat(frontend): REM viewer domain — tree + search + format helpers` | `src/lib/rem/tree.ts`, `src/lib/rem/search.ts`, `src/lib/rem/format.ts`, `tests/unit/rem/tree.test.ts`, `tests/unit/rem/search.test.ts`, `tests/unit/rem/fixtures/seed-mini.ts`, `src/types/rem.ts` | 320 |
| C3 | `feat(frontend): REM viewer UI — layout + sidebar + tree + states` | `src/layouts/RemLayout.astro`, `src/components/rem/RemSidebar.tsx`, `src/components/rem/RemTreeNode.tsx`, `src/components/rem/RemEmptyState.astro`, `src/components/rem/RemSkeleton.astro`, `tests/unit/components/rem/RemSidebar.test.tsx` | 380 |
| C4 | `feat(frontend): REM viewer UI — detail panel + field row + print button` | `src/components/rem/RemDetail.astro`, `src/components/rem/RemFieldRow.astro`, `src/components/rem/RemPrintButton.tsx`, `src/components/rem/RemError.astro`, `src/styles/rem-print.css`, `tests/unit/components/rem/RemDetail.test.tsx`, `tests/unit/components/rem/RemFieldRow.test.tsx`, `tests/unit/components/rem/RemPrintButton.test.tsx`, `tests/unit/components/rem/RemError.test.tsx` | 390 |
| C5 | `feat(frontend): REM viewer route + i18n namespace + api endpoint` | `src/pages/[locale]/rem/index.astro`, `src/pages/api/rem/[uid].html.ts`, `src/i18n/types.ts`, `src/i18n/locales/es.ts`, `src/i18n/locales/en.ts` | 220 |
| C6 | `test(frontend): REM viewer e2e — view + PDF + a11y + bundle` | `tests/e2e/rem/rem-viewer.spec.ts`, `tests/e2e/rem/rem-pdf.spec.ts`, `frontend/playwright.config.ts` (baseURL add), `vitest.config.ts` (coverage include), `tests/e2e/rem/__snapshots__/rem-viewer-1280.png` | 400 |
| C7 | `docs(frontend): REM viewer README + skill pointer` | `frontend/README.md` | 50 |
| **Total** | | | **~2300** |

## 3. PR strategy

Per `chained-pr` skill: total forecast ~2050 LoC → **High 400-line budget risk** → chained PRs. Chain strategy: **feature-branch-chain** (academic context favors rollback control + reviewer-friendly base branches over raw speed; each layer builds on the previous; only the tracker merges to `master`).

Tracker branch: `feat/frontend-rem-viewer` (draft, no-merge). Child PRs target the tracker or the previous PR branch per the diagram.

| PR | Base branch | Commits | Scope | Diff budget | Verification |
|---|---|---|---|---|---|
| **PR1** → tracker | `master` | C0, C1, C2 | Domain layer: YAML schema extension (DP1=A) + `lib/rem/*` foundation + unit tests + fixtures | ~860 LoC (`size:exception` required — see note below) | `pnpm vitest run loader tree search` passes; `python scripts/requirements/tests/test_migrate.py` passes |
| **PR2** → PR1 | `feat/frontend-rem-viewer-pr1` | C3, C4, C5 | UI layer: layout, components, page route, i18n, `/api/rem/[uid].html` endpoint | ~990 LoC (`size:exception` required) | `pnpm vitest run` 100% pass; `pnpm run typecheck && pnpm run lint` clean; `/es/rem` renders server-side |
| **PR3** → PR2 | `feat/frontend-rem-viewer-pr2` | C6, C7 | Verification layer: e2e tests, screenshot baseline, coverage thresholds, README | ~450 LoC (close to 400; one optional follow-up commit acceptable) | `pnpm playwright test rem/` passes; Lighthouse a11y ≥ 95; coverage ≥ 85% on `lib/rem/**` + `components/rem/**` |
| **merge** tracker → `master` | — | — | Final integration after PR3 reviewed | — | `pnpm run typecheck && pnpm run lint && pnpm vitest run && pnpm playwright test` all green |

**`size:exception` rationale**: `frontend/` is the only directory in scope; the work splits cleanly by layer, but each individual layer (lib + tests, UI + tests) crosses the 400-line budget because of paired tests + fixtures per strict TDD. PR1's 860 LoC is the cumulative DP1-A + lib + tests cost; splitting it would force C1 to ship without C2's tests verifying tree/search. `size:exception` keeps the contract that **tests ship with the behavior they verify**.

**PR template (Qué cambia / Por qué / Cómo se prueba / Checklist)** — apply to each child PR:

```md
## Qué cambia
- <1-2 sentences: scope of this PR>

## Por qué
- <1-2 sentences: which REQ/NFR it satisfies; link to sdd/openspec change>
- Refs: openspec/changes/rem-viewer/{proposal,specs,design}.md

## Cómo se prueba
- <commands>:
  - `cd frontend && pnpm vitest run <files>`
  - `cd frontend && pnpm run typecheck && pnpm run lint`
  - `cd frontend && pnpm playwright test rem/<spec>`
- Test counts added: <n> unit, <n> e2e

## Checklist
- [ ] `pnpm run typecheck` clean
- [ ] `pnpm run lint` clean
- [ ] `pnpm vitest run` 100% pass (no skip/todo)
- [ ] No `any` in `src/lib/rem/**` or `src/components/rem/**`
- [ ] No YAML hand-edited in `scripts/requirements/req/`
- [ ] No backend (`backend/**`) touched
- [ ] Conventional commit message in Spanish body
- [ ] Sin secrets
```

## 4. Task list

### Phase 1 — Domain foundation (PR1)

### Task 1.1: Extend YAML schema via seed JSONs + migration script (DP1=A)

- **Commit**: C0
- **Files**:
  - `scripts/requirements/seed_rf.json` (modify, +4 fields × 48 items, ~40 LoC diff)
  - `scripts/requirements/seed_rnf.json` (modify, +4 fields × 30 items, ~30 LoC diff)
  - `scripts/requirements/migrate_to_doorstop.py` (modify, +60 LoC to emit new fields with defaults)
  - `scripts/requirements/tests/test_migrate.py` (new, ~50 LoC; asserts fields survive re-run)
- **Spec coverage**: enables REQ-05 #1, #2, #5 (rendered fields); DP1=A lock from design §5
- **Dependencies**: none
- **Acceptance**:
  - `python scripts/requirements/migrate_to_doorstop.py` regenerates 48+30 RF/RNF YAMLs without data loss
  - `python -m pytest scripts/requirements/tests/test_migrate.py` passes
  - All 48 RF now have `version`, `autores`, `fuentes`, `dependencias` fields (default empty/1.0)
  - No hand-edit of `scripts/requirements/req/*.yml` (test asserts byte-equal regen)
- **Notes**: REQ-01#1 loader count check (48 RF + 30 RNF) becomes a precondition for PR1; if C0 lands later, PR1 must include a "regenerate dataset" step. Coordinate with `scripts/requirements/` owner.

### Task 1.2: Domain types (REM domain TS types)

- **Commit**: C1
- **Files**:
  - `frontend/src/lib/rem/types.ts` (new, ~90 LoC; `Kind`, `Priority`, `Status`, `Stakeholder`, `Requirement`, `RemDataset`, `TreeNode`, `FieldKey`, `RemParseError`)
  - `frontend/tests/unit/rem/fixtures/seed.ts` (new, ~110 LoC; full 81-item dataset fixture)
- **Spec coverage**: spec §2 data model; transitively covers REQ-01, REQ-02, REQ-05
- **Dependencies**: Task 1.1 (DP1-A fields present in YAML)
- **Acceptance**:
  - `pnpm run typecheck` clean
  - All 8 new DP1-A fields (`version`, `autores`, `fuentes`, `dependencias`, `importancia`, `urgencia`, `estado`, `estabilidad`) are optional on `Requirement`
  - No `any` in `types.ts`
- **Notes**: Re-export from `src/types/rem.ts` for IDE discoverability (per design §11); keep `lib/rem/types.ts` as production source.

### Task 1.3: Loader — fs + yaml parse + 5-min cache

- **Commit**: C1
- **Files**:
  - `frontend/src/lib/rem/loader.ts` (new, ~110 LoC; `loadAllRequirements`, `clearLoaderCache`)
  - `frontend/src/types/rem.ts` (new, ~5 LoC; `export * from "@/lib/rem/types"`)
  - `frontend/tests/unit/rem/loader.test.ts` (new, ~120 LoC; 5 `it` methods covering REQ-01#1–#5)
  - `frontend/tests/unit/rem/fixtures/seed-invalid.yml` (new, ~5 LoC; broken YAML)
- **Spec coverage**: REQ-01 (5 scenarios), REQ-10#3 (parse error → `RemParseError` w/ file path)
- **Dependencies**: Task 1.2
- **Acceptance**:
  - `pnpm vitest run loader` passes 5 tests
  - Cache hit returns same reference at T+60s (verified with `vi.useFakeTimers`)
  - Cache miss re-reads at T+5min+1s
  - Invalid YAML throws `RemParseError` with `path` field
  - Empty folder returns `{ rf: [], rnf: [], stakeholders: [] }`
- **Notes**: Use `vi.mock('node:fs/promises')` for fixture swap; key the cache Map by folder string + content hash to survive HMR.

### Task 1.4: Tree builder — flat list → hierarchical

- **Commit**: C2
- **Files**:
  - `frontend/src/lib/rem/tree.ts` (new, ~70 LoC; `buildTree`, `RemTree`)
  - `frontend/tests/unit/rem/tree.test.ts` (new, ~90 LoC; 5 `it` methods for REQ-02#1–#4 + NFR-06 determinism)
  - `frontend/tests/unit/rem/fixtures/seed-mini.ts` (new, ~50 LoC; 3 RF + 2 RNF + 1 stakeholder)
- **Spec coverage**: REQ-02 (4 scenarios), NFR-06 (deterministic across 10 calls)
- **Dependencies**: Task 1.3
- **Acceptance**:
  - `pnpm vitest run tree` passes 5 tests
  - RF grouped by `module`; RNF grouped by `category`; stakeholders flat
  - Items inside each group sorted by `code` ascending
  - Same dataset → byte-identical tree across 10 calls
- **Notes**: Return `TreeNode[]` (recursive type in `types.ts`); pure function, no side effects.

### Task 1.5: Search — case-insensitive, scored, sorted

- **Commit**: C2
- **Files**:
  - `frontend/src/lib/rem/search.ts` (new, ~80 LoC; `match`, `SearchHit`, score table)
  - `frontend/tests/unit/rem/search.test.ts` (new, ~90 LoC; 5 `it` methods for REQ-03#1–#4 + NFR-02 < 50ms benchmark)
- **Spec coverage**: REQ-03 (4 scenarios), NFR-02 (perf budget)
- **Dependencies**: Task 1.4
- **Acceptance**:
  - `pnpm vitest run search` passes 5 tests
  - Exact code = 100, prefix = 60, title exact = 50, title substring = 30, module/category/rationale = 10, status = 5
  - Empty query returns 78 items each with score 0
  - 81-item dataset search completes in < 50ms (asserted via `performance.now()`)
- **Notes**: Score constants exported as `SCORE` enum so future tests can reuse them.

### Task 1.6: Format helpers — PD badge builder + priority/status labels

- **Commit**: C2
- **Files**:
  - `frontend/src/lib/rem/format.ts` (new, ~80 LoC; `buildDetailRows`, `formatPriority`, `formatStatus`)
- **Spec coverage**: REQ-05#1, #2, #5 (priority/status rendered with i18n labels); enables REQ-05#3, #4 (PD badge via `RemFieldRow.astro`)
- **Dependencies**: Task 1.2
- **Acceptance**:
  - `pnpm run typecheck` clean
  - `formatPriority('H' | 'M' | 'L')` returns "5" / "3" / "1"
  - `formatStatus(...)` maps to i18n key string
  - `buildDetailRows(...)` returns row specs for RF (8 rows), RNF (7 rows), stakeholder (6 rows)
- **Notes**: Per design §6 trade-off note, this file has no dedicated test file; assertions live in `RemDetail.test.tsx` (Task 4.2) and `RemFieldRow.test.tsx` (Task 4.3). Coverage threshold (NFR-05 ≥ 85% lines) still applies via the test execution on dependent components.

### Phase 2 — UI components (PR2)

### Task 2.1: RemLayout (2-column shell)

- **Commit**: C3
- **Files**:
  - `frontend/src/layouts/RemLayout.astro` (new, ~70 LoC; `header` + `aside` (sidebar slot) + `main` (detail slot) + `footer` landmarks)
- **Spec coverage**: REQ-11#1 (landmarks: header/nav/main/footer one each, `aria-label`ed)
- **Dependencies**: Task 1.4 (uses `RemTree`)
- **Acceptance**:
  - `pnpm run typecheck` clean
  - Renders `<header>`, `<nav aria-label="rem.sidebar.heading">`, `<main>`, `<footer>` exactly once
  - Links `styles/rem-print.css` with `media="print"`
- **Notes**: Pure shell; no data fetching. Receives `title` and `locale` as props.

### Task 2.2: RemTreeNode (recursive node renderer)

- **Commit**: C3
- **Files**:
  - `frontend/src/components/rem/RemTreeNode.tsx` (new, ~70 LoC; `role="treeitem"`, `aria-expanded`, `aria-level`)
- **Spec coverage**: REQ-09 (tree semantics: `role="tree"`, `role="treeitem"`, `aria-selected`, `aria-current="true"` on selected)
- **Dependencies**: Task 1.4
- **Acceptance**:
  - `pnpm run typecheck` clean
  - Renders groups (top-level) and leaves (selectable)
  - Selected node has `aria-current="true"`
- **Notes**: Recursive component; memoized on `node.id` for perf.

### Task 2.3: RemSidebar (search box + tree + URL state)

- **Commit**: C3
- **Files**:
  - `frontend/src/components/rem/RemSidebar.tsx` (new, ~140 LoC; state: `query`, `selectedUid`; effects: `?id=` sync via `history.replaceState`)
  - `frontend/src/components/rem/RemEmptyState.astro` (new, ~25 LoC; "Sin resultados" + "Limpiar búsqueda" button)
  - `frontend/src/components/rem/RemSkeleton.astro` (new, ~20 LoC; `aria-busy="true"`)
- **Spec coverage**: REQ-03#5 (empty), REQ-04#3 (clear search keeps selection), REQ-09×4 (Tab/Arrow/Enter/Escape), REQ-10#1,#2 (skeleton + empty), REQ-12#1,#3 (URL sync + fallback)
- **Dependencies**: Tasks 1.5, 2.2
- **Acceptance**:
  - `pnpm run typecheck` clean
  - On selection, `window.location.search` becomes `?id=<uid>` without reload
  - On invalid `?id=`, falls back to `rf_001`
  - Empty result set renders `RemEmptyState`
  - Skeleton mounts with `aria-busy="true"` until first `useEffect` resolves
- **Notes**: Pass dataset as JSON prop (~30 KB); use `client:visible` for hydration.

### Task 2.4: RemSidebar unit tests (10 `it` methods)

- **Commit**: C3
- **Files**:
  - `frontend/tests/unit/components/rem/RemSidebar.test.tsx` (new, ~210 LoC; 10 `it`)
- **Spec coverage**: REQ-03#5, REQ-04#3, REQ-09×4, REQ-10#1,#2, REQ-12#1,#3
- **Dependencies**: Task 2.3
- **Acceptance**:
  - `pnpm vitest run RemSidebar` passes 10 tests
  - Tests use `vi.mock('../../../src/i18n', ...)` identity mock
  - Tests use `window.history.replaceState` setup in `beforeEach`
- **Notes**: One `it` per spec scenario per design §6 mapping table.

### Task 2.5: RemDetail (server-rendered detail panel)

- **Commit**: C4
- **Files**:
  - `frontend/src/components/rem/RemDetail.astro` (new, ~110 LoC; header row + 2-column table + imports `RemFieldRow`)
- **Spec coverage**: REQ-05#1 (RF rows: Código, Título, Módulo, Prioridad, Estado, Verificado por, Fuentes/Dependencias, Descripción), REQ-05#2 (RNF: Categoría + no Verificado por), REQ-05#5 (stakeholder rows)
- **Dependencies**: Task 1.6
- **Acceptance**:
  - `pnpm run typecheck` clean
  - For RF: 8 rows; for RNF: 7 rows; for stakeholder: 6 rows
  - "Verificado por" shows comma-joined stakeholder names from `verifiedBy` UIDs
- **Notes**: Server-rendered, zero JS; uses `Intl.DateTimeFormat` for dates; accepts `item: Requirement | Stakeholder` as prop.

### Task 2.6: RemFieldRow (one field/value row + PD/? markers)

- **Commit**: C4
- **Files**:
  - `frontend/src/components/rem/RemFieldRow.astro` (new, ~50 LoC; PD badge `bg-[#fef3c7]`/`text-[#92400e]`; red `?` `bg-[#fee2e2]`/`text-[#b91c1c]` with `aria-label`)
- **Spec coverage**: REQ-05#3 (PD for empty notes), REQ-05#4 (red `?` for empty links), REQ-11#3 (contrast ≥ 4.5:1)
- **Dependencies**: none
- **Acceptance**:
  - `pnpm run typecheck` clean
  - PD badge visible when value is empty/null
  - Red `?` with `aria-label="rem.detail.noLinks"` for empty `links`
- **Notes**: Tailwind class names use `bg-[#fef3c7]` pattern to match existing UI components.

### Task 2.7: RemPrintButton (print-to-PDF trigger)

- **Commit**: C4
- **Files**:
  - `frontend/src/components/rem/RemPrintButton.tsx` (new, ~80 LoC; `window.print()` call; sets `body[data-rem-scope]` to `single` or `full`)
  - `frontend/src/styles/rem-print.css` (new, ~60 LoC; `@media print` rules, A4 page setup, cover/TOC/detail scopes)
- **Spec coverage**: REQ-06#1,#2 (single print); REQ-07#1,#2 (full report cover + TOC + 78 pages); NFR-08 (Chromium primary)
- **Dependencies**: Task 1.5
- **Acceptance**:
  - `pnpm run typecheck` clean
  - Click "Imprimir PDF" → `window.print()` invoked within 100ms
  - Click "Informe completo" → sets `body[data-rem-scope="full"]` before `window.print()`
  - Print stylesheet hides `.rem-sidebar`, applies `page-break-after: always` to `.rem-detail`
- **Notes**: No new npm packages; native `window.print()` only (DP2=A locked).

### Task 2.8: RemError (loader error state)

- **Commit**: C4
- **Files**:
  - `frontend/src/components/rem/RemError.astro` (new, ~25 LoC; Alert wrapper with code + body)
- **Spec coverage**: REQ-10#3 (parse error → Alert with copy + code; sidebar/detail NOT rendered)
- **Dependencies**: none
- **Acceptance**:
  - `pnpm run typecheck` clean
  - Renders `Alert.astro` with `rem.error.load.title` + `rem.error.load.body` + error code
- **Notes**: Server-rendered; receives `errorCode: string` prop.

### Task 2.9: RemDetail + RemFieldRow + RemPrintButton + RemError unit tests

- **Commit**: C4
- **Files**:
  - `frontend/tests/unit/components/rem/RemDetail.test.tsx` (new, ~110 LoC; 5 `it` for REQ-04#2 + REQ-05#1,#2,#5 + format helpers)
  - `frontend/tests/unit/components/rem/RemFieldRow.test.tsx` (new, ~50 LoC; 2 `it` for REQ-05#3,#4)
  - `frontend/tests/unit/components/rem/RemPrintButton.test.tsx` (new, ~70 LoC; 3 `it` for REQ-06#1,#2 + full-scope)
  - `frontend/tests/unit/components/rem/RemError.test.tsx` (new, ~25 LoC; 1 `it` for REQ-10#3)
- **Spec coverage**: REQ-04#2, REQ-05#1-#5, REQ-06#1,#2, REQ-10#3
- **Dependencies**: Tasks 2.5, 2.6, 2.7, 2.8
- **Acceptance**:
  - `pnpm vitest run RemDetail RemFieldRow RemPrintButton RemError` passes 11 tests total
  - `RemPrintButton.test.tsx` stubs `window.print` with `vi.fn()`; measures call timing
- **Notes**: For `RemDetail.astro`, tests target the `buildDetailRows()` helper from `format.ts` (same pattern as design §6).

### Phase 3 — Integration (PR2 tail + PR3)

### Task 3.1: Page route `/[locale]/rem/index.astro`

- **Commit**: C5
- **Files**:
  - `frontend/src/pages/[locale]/rem/index.astro` (new, ~80 LoC; frontmatter calls `loadAllRequirements` in try/catch; reads `Astro.url.searchParams.get('id')`; renders `<RemLayout>` + `<RemSidebar client:visible>` + `<RemDetail>` + `<RemPrintButton client:idle>`)
- **Spec coverage**: REQ-04#2 (default preselection), REQ-12#2 (?id= hydration), REQ-12#3 (invalid id fallback)
- **Dependencies**: Tasks 1.3, 2.3, 2.5, 2.7
- **Acceptance**:
  - `pnpm run typecheck` clean
  - `GET /es/rem` returns 200 with default `rf_001` selected
  - `GET /es/rem?id=rf_007` returns 200 with `rf_007` preselected
  - `GET /es/rem?id=does-not-exist` falls back to `rf_001` (no crash)
  - `GET /es/rem` with `loadAllRequirements()` throwing → renders `<RemError>` only (no sidebar/detail)
- **Notes**: Mirror pattern from `src/pages/[locale]/admin/returns/index.astro:1-14`; locale resolved from Astro params.

### Task 3.2: SSR endpoint `/api/rem/[uid].html`

- **Commit**: C5
- **Files**:
  - `frontend/src/pages/api/rem/[uid].html.ts` (new, ~30 LoC; Astro endpoint that re-renders `RemDetail` for one UID, returns ~2 KB HTML fragment)
- **Spec coverage**: design §4 step 8 (detail panel update via `fetch('/api/rem/<uid>.html').then(r => r.text())`)
- **Dependencies**: Task 2.5
- **Acceptance**:
  - `pnpm run typecheck` clean
  - `GET /api/rem/rf_001.html` returns 200 with `text/html` content-type
  - Response body contains the RF-AUTH-001 header row + 8 detail rows
  - `GET /api/rem/does-not-exist.html` returns 404
- **Notes**: Used by `RemSidebar` to swap detail panel HTML on selection without React subtree.

### Task 3.3: i18n namespace — 32 keys × 2 locales + type union

- **Commit**: C5
- **Files**:
  - `frontend/src/i18n/types.ts` (modify, +32 lines under `// ─── rem ───`)
  - `frontend/src/i18n/locales/es.ts` (modify, +32 lines)
  - `frontend/src/i18n/locales/en.ts` (modify, +32 lines)
- **Spec coverage**: REQ-08#1 (es + en copy), REQ-08#2 (typecheck fails on missing key), NFR-07 (every UI string has both locales)
- **Dependencies**: none
- **Acceptance**:
  - `pnpm run typecheck` clean (proves both locales have all 32 keys)
  - All keys from design §7 table present in both files
  - No existing key removed or renamed
- **Notes**: Add a `// ─── rem ───` section header at the bottom of `types.ts` before `format.empty`. Order: keys must match between `es.ts` and `en.ts` for grep-ability.

### Phase 4 — Verification (PR3)

### Task 4.1: e2e tests — view (9 `it` methods)

- **Commit**: C6
- **Files**:
  - `frontend/tests/e2e/rem/rem-viewer.spec.ts` (new, ~220 LoC; 9 `it` for REQ-04#1, REQ-08×2, REQ-11×3, REQ-12#2, NFR-01, NFR-03, NFR-04)
  - `frontend/playwright.config.ts` (modify, +5 LoC: `webServer` flag or `baseURL` alias for `/rem`)
- **Spec coverage**: REQ-04#1, REQ-08#1, #2, REQ-11#1-#3, REQ-12#2, NFR-01 (TTFB ≤ 500ms), NFR-03 (Lighthouse a11y ≥ 95), NFR-04 (bundle ≤ 100 KB gzipped)
- **Dependencies**: Tasks 3.1, 3.2, 3.3
- **Acceptance**:
  - `pnpm playwright test rem/rem-viewer.spec.ts` passes 9 tests
  - Lighthouse a11y ≥ 95 (asserted via `playwright-lighthouse` if installed; otherwise documented as gate)
  - `response.timing()` p95 ≤ 500ms
  - Bundle transfer size ≤ 100 KB gzipped
- **Notes**: Pre-requisite: backend, frontend, Caddy running locally (per `playwright-e2e-patterns` skill).

### Task 4.2: e2e tests — PDF (3 `it` methods) + snapshot baseline

- **Commit**: C6
- **Files**:
  - `frontend/tests/e2e/rem/rem-pdf.spec.ts` (new, ~130 LoC; 3 `it` for REQ-06#3, REQ-07#1, #2)
  - `frontend/tests/e2e/rem/__snapshots__/rem-viewer-1280.png` (new binary, 1280×800 baseline)
  - `frontend/vitest.config.ts` (modify, +5 LoC: coverage include `src/lib/rem/**` + `src/components/rem/**`, threshold lines 85)
- **Spec coverage**: REQ-06#3 (single PDF ≥ 2 pages), REQ-07#1 (full cover + TOC + 78 details), REQ-07#2 (each item `page-break-before: always`)
- **Dependencies**: Task 4.1
- **Acceptance**:
  - `pnpm playwright test rem/rem-pdf.spec.ts` passes 3 tests
  - Single print: PDF ≥ 2 pages
  - Full print: PDF ≥ 80 pages
  - Snapshot at 1280×800 captured; future runs diff against it
- **Notes**: Use `await page.emulateMedia({ media: 'print' })` then `await page.pdf({ format: 'A4' })`; gate tests behind `if (process.env.PDF_TESTS === '1')` per design §13 risk #11.

### Task 4.3: README — REM viewer section

- **Commit**: C7
- **Files**:
  - `frontend/README.md` (modify, +50 LoC under "Special routes" or new "REM viewer" section)
- **Spec coverage**: NFR-08 (browser support documented)
- **Dependencies**: none (docs only)
- **Acceptance**:
  - `markdownlint frontend/README.md` passes
  - Section explains `/[locale]/rem`, dataset source, print-to-PDF, dev setup
- **Notes**: No `.claude/skills/` change in this PR (out of scope per design §12 C6).

## 5. Test plan summary

**Unit (Vitest, 8 files, 31 `it` methods):**

| File | `it` count | REQs / NFRs covered |
|---|---:|---|
| `tests/unit/rem/loader.test.ts` | 5 | REQ-01#1-#5, REQ-10#3 |
| `tests/unit/rem/tree.test.ts` | 5 | REQ-02#1-#4, NFR-06 |
| `tests/unit/rem/search.test.ts` | 5 | REQ-03#1-#4, NFR-02 |
| `tests/unit/components/rem/RemSidebar.test.tsx` | 10 | REQ-03#5, REQ-04#3, REQ-09×4, REQ-10#1,#2, REQ-12#1,#3 |
| `tests/unit/components/rem/RemDetail.test.tsx` | 5 | REQ-04#2, REQ-05#1,#2,#5 + format helpers |
| `tests/unit/components/rem/RemFieldRow.test.tsx` | 2 | REQ-05#3,#4 |
| `tests/unit/components/rem/RemPrintButton.test.tsx` | 3 | REQ-06#1,#2 + full-scope |
| `tests/unit/components/rem/RemError.test.tsx` | 1 | REQ-10#3 |

**E2E (Playwright, 2 files, 12 `it` methods):**

| File | `it` count | REQs / NFRs covered |
|---|---:|---|
| `tests/e2e/rem/rem-viewer.spec.ts` | 9 | REQ-04#1, REQ-08×2, REQ-11×3, REQ-12#2, NFR-01, NFR-03, NFR-04 |
| `tests/e2e/rem/rem-pdf.spec.ts` | 3 | REQ-06#3, REQ-07×1,#2 |

**Total: 43 `it` methods** (31 unit + 12 e2e) covering **all 42 spec scenarios + 1 auxiliary format helper** per design §6.

**Fixtures** (under `frontend/tests/unit/rem/fixtures/`):
- `seed.ts` — full 81-item dataset (loader.test.ts)
- `seed-mini.ts` — 3 RF + 2 RNF + 1 stakeholder (tree.test.ts, search.test.ts)
- `seed-invalid.yml` — broken YAML for parse-error path (loader.test.ts)

**Coverage thresholds** (`vitest.config.ts`):
```ts
coverage: {
  provider: 'v8',
  reporter: ['text', 'html', 'lcov'],
  include: ['src/lib/rem/**', 'src/components/rem/**'],
  thresholds: { lines: 85 },
}
```
Enforced in CI via `pnpm vitest run --coverage --coverage.reporter=text-summary`.

**Mock strategy:**
- `vi.mock('node:fs/promises', ...)` to swap real Doorstop folder for fixtures (loader.test.ts)
- `vi.useFakeTimers({ now: 0 })` to advance past 5-min TTL deterministically (loader.test.ts)
- `vi.mock('../../../src/i18n', ...)` identity mock for React tests (key → key)
- `vi.fn()` for `window.print` in `RemPrintButton.test.tsx`
- `await page.emulateMedia({ media: 'print' })` in `rem-pdf.spec.ts`

**Lighthouse a11y:** Playwright test in `rem-viewer.spec.ts` uses `playwright-lighthouse` (or manual `page.evaluate(() => document.querySelectorAll('...'))` if package not added). Asserts ≥ 95 score. Document as gate in CI workflow.

## 6. Acceptance for sdd-apply

`sdd-apply` phase must pass every gate below before declaring complete:

- [ ] All 15 tasks marked complete; each task's "Acceptance" criteria checked off
- [ ] `pnpm run typecheck` clean (no errors, no `any` in `src/lib/rem/**` or `src/components/rem/**`)
- [ ] `pnpm run lint` clean
- [ ] `pnpm vitest run` 100% passing (no `skip`/`todo`/`xfail`)
- [ ] `pnpm vitest run --coverage` ≥ 85% lines for `src/lib/rem/**` + `src/components/rem/**`
- [ ] `pnpm playwright test rem/` 100% passing (12 e2e tests)
- [ ] Lighthouse a11y ≥ 95 on `/es/rem` route (asserted in `rem-viewer.spec.ts`)
- [ ] Bundle transfer size ≤ 100 KB gzipped (asserted in `rem-viewer.spec.ts`)
- [ ] TTFB ≤ 500 ms p95 on warm cache (asserted in `rem-viewer.spec.ts`)
- [ ] No backend file (`backend/**`) touched
- [ ] No `scripts/requirements/req/*.yml` hand-edited (regenerated by C0 only)
- [ ] No npm package added to `frontend/package.json`
- [ ] No `.claude/skills/**` modified
- [ ] Each commit message in Spanish, conventional format
- [ ] No `Co-Authored-By` AI attribution in commits
- [ ] 1280×800 screenshot baseline committed to `frontend/tests/e2e/rem/__snapshots__/rem-viewer-1280.png`
- [ ] Feature branch `feat/frontend-rem-viewer` exists as tracker; 3 child PRs created per chain strategy

## 7. Risk register for tasks

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | DP1=A C0 may slip or fail (YAML regen mismatch) | Medium | If C0 not applied first, PR1 loader test must mock the new fields; the optional-on-`Requirement` typing makes it compile-clean |
| 2 | `i18n` `TranslationKey` union refactor: adding 32 keys to `types.ts` may collide with existing keys or break other locales | Low | Grep first; `pnpm run typecheck` is the canonical gate |
| 3 | `Astro.url.searchParams.get('id')` for REQ-12#2 may need `prefixDefaultLocale` config adjustment | Low | Already configured per design §7 |
| 4 | `/api/rem/[uid].html.ts` Astro endpoint syntax may differ from `.astro` page (uses `APIRoute` type) | Low | Reference existing endpoint pattern if any; else use `export const GET: APIRoute = async (...) => ...` |
| 5 | `playwright-lighthouse` may not be installed; NFR-03 assertion could block | Medium | Either install (with explicit approval) or assert contrast manually via `page.evaluate(() => getComputedStyle(...))` |
| 6 | Snapshot baseline `rem-viewer-1280.png` is binary; PR review can't see diff | Low | Mark as visual regression baseline; document in PR body |
| 7 | `vi.useFakeTimers` + `vi.mock('node:fs/promises')` interplay may flake in CI | Low | Use `vi.advanceTimersByTime` deterministically; assert cache reference equality |
| 8 | Print stylesheet `@page` behavior differs between Chromium CI and professor's browser | Medium | Document Chromium primary (NFR-08); browser screenshots as fallback per proposal §DP2 |
| 9 | Feature Branch Chain requires 3 PRs; reviewers may want single PR with `size:exception` | Medium | Plan proposes `size:exception` with rationale; chain strategy is editable before C1 lands |
| 10 | `define:vars`-style JSON island props for ~30 KB dataset may inflate first paint | Low | Use `client:visible` not `client:load`; measure via NFR-04 |

## 8. Open questions for sdd-apply

| # | Question | Blocks `sdd-apply`? |
|---|---|---|
| 1 | DP1 sequencing: confirm Option A (extend schema via `seed_*.json` + `migrate_to_doorstop.py`)? Or fall back to Option B (ship viewer with `PD` markers for missing fields)? Design locks A but proposal §DP1 lists it as a decision point. | Yes for C0; No for C1+ (loader reads new fields as optional) |
| 2 | `playwright-lighthouse` install: add to devDependencies? Hard constraint says "no npm install"; can we defer NFR-03 assertion to manual? | Yes if strict on no-install; No otherwise |
| 3 | Chain strategy: confirm Feature Branch Chain (3 PRs with tracker)? Or accept `size:exception` and ship single PR? | Yes — affects branching setup |
| 4 | Browser for class session (NFR-08): Chromium primary, but do we need Firefox/Safari screenshots committed too? | No — Chromium CI is enough |
| 5 | `?id` vs `#uid` URL state: design locks `?id` query param. Confirm? | No |
| 6 | PDF "Informe completo" button (REQ-07): ship both buttons, or only "Imprimir PDF" per spec open question 4? | No — design says both ship; team decision in PR review |
| 7 | Auth gate (spec open question 5): confirm public route (no auth)? | No — proposal confirms public |

---

**Total LOC budget per PR** (recheck at PR creation; if any exceeds 400 by > 50 LoC, split the largest commit into two):

- PR1: C0 (180) + C1 (360) + C2 (320) = ~860 LoC → `size:exception` required (justify in PR body)
- PR2: C3 (380) + C4 (390) + C5 (220) = ~990 LoC → `size:exception` required
- PR3: C6 (400) + C7 (50) = ~450 LoC → `size:exception` borderline; monitor diff
