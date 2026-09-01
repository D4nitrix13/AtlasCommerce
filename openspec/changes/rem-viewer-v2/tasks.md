# Tasks: rem-viewer-v2

## 1. Overview

Total: **15 tasks across 7 commit units** (D0..D6) organized into **3 chained PRs** in `feature-branch-chain` strategy.
Estimated LoC: **~1850** (frontend-only; backend untouched; v1 archive untouched).
Strict TDD ACTIVE — every implementation task ships paired tests in the same commit per `work-unit-commits`. All **45 v2 spec scenarios** (REQ-13..24 + NFR-09..14) are covered 1:1 by test methods in the same commit as their implementation.
Delivery strategy: `auto-chain` (orchestrator picks first slice; chain strategy pre-locked here as Feature Branch Chain because total > 400 LoC).

Decision needed before apply: No
Chained PRs recommended: Yes
Chain strategy: feature-branch-chain
400-line budget risk: High

## 2. Commit strategy

Each commit stands alone (`pnpm typecheck && pnpm lint && pnpm vitest run` passes after each). One logical work unit per commit; tests ship with behavior per Strict TDD.

| # | Commit hash | Conventional message (Spanish) | Files touched | LoC est. |
|---|---|---|---|---|
| **D0** | placeholder | `feat(frontend): REM viewer v2 - esquemas Zod + yaml-writer` | `lib/rem/{rf,rnf,stakeholder}.schema.ts` (~3×60 LoC), `lib/rem/yaml-writer.ts` (~150 LoC), `lib/rem/yaml-roundtrip.ts` (~30 LoC), `lib/rem/crud-errors.ts` (~40 LoC), `lib/rem/types.ts` (+30 LoC ext.), 4 unit test files, 6 fixtures | ~580 |
| **D1** | placeholder | `feat(frontend): REM viewer v2 - sidebar colapsable + resize handle` | `components/rem/RemGroupShell.tsx` (~120 LoC), `components/rem/RemResizeHandle.tsx` (~80 LoC), `components/rem/RemSidebar.tsx` (modify, +60 LoC diff), `components/rem/RemTreeNode.tsx` (modify, +20 LoC), 4 unit test files, `RemLayout.astro` (modify, +5 LoC) | ~430 |
| **D2** | placeholder | `feat(frontend): REM viewer v2 - kebab menu + edit dialog (esqueleto)` | `components/rem/RemKebabMenu.tsx` (~140 LoC), `components/rem/RemEditDialog.tsx` (~150 LoC), `components/rem/forms/*` skeleton (3×30 LoC stubs), `RemTreeNode.tsx` kebab slot (+15 LoC), `RemSidebar.tsx` button (+10 LoC), 2 unit test files, `i18n/locales/{es,en}.ts` (+30 keys), `i18n/types.ts` (+30 lines) | ~430 |
| **D3** | placeholder | `feat(frontend): REM viewer v2 - 9 endpoints CRUD` | `pages/api/rem/stakeholder/{index,[uid]}.ts` (~2×100 LoC), `pages/api/rem/rf/{index,[uid]}.ts` (~2×100 LoC), `pages/api/rem/rnf/{index,[uid]}.ts` (~2×100 LoC), 9 endpoint test files | ~1080 |
| **D4** | placeholder | `feat(frontend): REM viewer v2 - form submit + tree-region swap` | `forms/{RemRfForm,RemRnfForm,RemStakeholderForm}.tsx` (~3×120 LoC), `RemEditDialog.tsx` wire-up (+50 LoC), `RemSidebar.tsx` scroll-preserve hook (+30 LoC), 3 form tests, 1 integration test, e2e `rem-crud.spec.ts` (~150 LoC, 8 it) | ~510 |
| **D5** | placeholder | `feat(frontend): REM viewer v2 - palette refresh + detalle widen` | `RemSidebar.tsx` (palette, +20 LoC), `RemTreeNode.tsx` (palette, +10 LoC), `RemPrintButton.tsx` (hover bg, +5 LoC), `RemLayout.astro` (`max-w-3xl` → `max-w-6xl`, +5 LoC), `RemEditDialog.tsx` (palette, +5 LoC), e2e `rem-sidebar.spec.ts` (~120 LoC, 6 it), e2e `rem-a11y.spec.ts` (~80 LoC, 3 it), e2e snapshot baseline regen | ~330 |
| **D6** | placeholder | `docs(frontend): REM viewer v2 - README detallado` | `frontend/README.md` (~150 LoC REM Viewer section) | ~150 |
| **Total** | | | | **~3510** |

> LoC totals include paired test files + fixtures. Pure implementation LoC per
> design §12 ≈ 1850 (commit LoC ≥ that due to TDD 1:1 tests).

## 3. PR strategy

Per `chained-pr` skill: total forecast ~1850 LoC → **High 400-line budget risk** → chained PRs. Chain strategy: **feature-branch-chain** (academic context favors rollback control; each layer builds on the previous; only the tracker merges to `master`).

Tracker branch: `feat/rem-viewer-v2` (the branch where this `tasks.md` lives).

| PR | Base branch | Commits | Scope | Diff budget | Verification |
|---|---|---|---|---|---|
| **PR1** → tracker | `master` | D0 + D1 | Backend foundation (Zod + yaml-writer) + sidebar UI (collapsibles + resize + widen) | ~620 LoC (`size:exception` required) | `pnpm vitest run tests/unit/lib/rem tests/unit/components/rem/RemSidebar tests/unit/components/rem/RemGroupShell tests/unit/components/rem/RemResizeHandle` passes; `pnpm run typecheck && pnpm run lint` clean |
| **PR2** → PR1 | `feat/rem-viewer-v2-pr1` | D2 + D3 + D4 | Kebab + dialog skeleton + 9 endpoints + form wiring + e2e CRUD spec | ~900 LoC (`size:exception` required) | `pnpm vitest run` 100% pass; `pnpm playwright test rem/rem-crud.spec.ts` 8 it pass |
| **PR3** → PR2 | `feat/rem-viewer-v2-pr2` | D5 + D6 | Palette refresh + detail widen + e2e sidebar/a11y + README | ~330 LoC (within budget) | `pnpm playwright test rem/` 17 it pass; `markdownlint frontend/README.md` clean; e2e snapshot baseline regen reviewed |
| **merge** tracker → `master` | — | — | Final integration after PR3 reviewed | — | Full pre-commit gate (`typecheck && lint && vitest && playwright`) |

**`size:exception` rationale**: PR1 and PR2 each cross the 400-line budget because they bundle foundation + paired tests per Strict TDD. Splitting D3 (9 endpoints × ~120 LoC each = 1080 LoC) into 3 sub-PRs would force the third sub-PR to ship without the form wiring that depends on the endpoint contracts. The 1:1 test pairing is the strictest constraint the project enforces, and `size:exception` keeps the contract that **tests ship with the behavior they verify**.

**PR template body** uses `conventional-commits-and-prs` Spanish template
(`Qué cambia` / `Por qué` / `Cómo se prueba` / `Checklist`).

## 4. Task list

### Phase 1 — Foundation (PR1 / D0)

### Task 1.1: Add Zod schemas (rf / rnf / stakeholder) + storage key helpers

- **Commit**: D0
- **Files**:
  - `frontend/src/lib/rem/rf.schema.ts` (NEW, ~70 LoC; `rfCreateSchema`, `rfUpdateSchema`)
  - `frontend/src/lib/rem/rnf.schema.ts` (NEW, ~70 LoC)
  - `frontend/src/lib/rem/stakeholder.schema.ts` (NEW, ~60 LoC)
  - `frontend/src/lib/rem/types.ts` (modify, +30 LoC: `RemGroupId`, `REM_SIDEBAR_STORAGE_PREFIX`, `expandedStorageKey`, `nestedExpandedStorageKey`, `SIDEBAR_WIDTH_STORAGE_KEY`)
  - `frontend/tests/unit/lib/rem/schemas.test.ts` (NEW, ~120 LoC; 9 it for REQ-19#1, REQ-20#2, REQ-21#3)
  - `frontend/tests/unit/lib/rem/storageKeys.test.ts` (NEW, ~40 LoC; 3 it)
  - `frontend/tests/unit/lib/rem/fixtures/writer/*.yml` (NEW, 6 fixtures: 2 RF + 2 RNF + 2 stakeholder; one of each with empty lists)
- **Spec coverage**: REQ-19#1, REQ-20#2, REQ-21#3 (Zod), REQ-13/14/15 helpers (storage keys), NFR-11 fixtures
- **Dependencies**: none (no upstream; uses v1's `Requirement`/`Stakeholder` types as-is)
- **Acceptance**:
  - `pnpm vitest run tests/unit/lib/rem/schemas tests/unit/lib/rem/storageKeys` passes 12 tests
  - `pnpm run typecheck` clean (Zod 4.x imports resolve)
  - `pnpm run lint` clean
  - `expandedStorageKey('rf')` === `'rem.viewer.expanded.rf'`, `SIDEBAR_WIDTH_STORAGE_KEY` === `'rem.viewer.sidebar.width'`
  - All 6 fixtures parse via v1's `loader.ts` and round-trip through `serializeYamlSubset` byte-identical after NFR-11 deep-equal
- **Notes**: Verify `@hookform/resolvers/zod` is in `frontend/package.json` deps (design risk #9 — if missing, fall back to a 5-line manual resolver per RHF docs; no new package).

### Task 1.2: YAML writer (atomic + .bak) + round-trip helper + error helper

- **Commit**: D0
- **Files**:
  - `frontend/src/lib/rem/yaml-writer.ts` (NEW, ~150 LoC; `serializeYamlSubset`, `writeYamlAtomic`, `rollbackFromBackup`)
  - `frontend/src/lib/rem/yaml-roundtrip.ts` (NEW, ~30 LoC; `assertRoundTrip(uid, fixtureDir)`)
  - `frontend/src/lib/rem/crud-errors.ts` (NEW, ~40 LoC; `errorBody`, `crudErrorResponse`)
  - `frontend/tests/unit/lib/rem/serializeYamlSubset.test.ts` (NEW, ~80 LoC; 4 it: stakeholder empty lists, stakeholder with notes, RF with deps, RNF with category)
  - `frontend/tests/unit/lib/rem/writer.test.ts` (NEW, ~120 LoC; 5 it: torn write, atomic rename, rollback, NFR-10 sibling untouched, NFR-11 round-trip deep-equal)
- **Spec coverage**: NFR-10 (torn write), NFR-11 (round-trip deep-equal)
- **Dependencies**: Task 1.1 (schemas + fixtures)
- **Acceptance**:
  - `pnpm vitest run tests/unit/lib/rem/writer tests/unit/lib/rem/serializeYamlSubset` passes 9 tests
  - 6 fixtures round-trip deep-equal (whitespace-stripped) per NFR-11
  - Torn-write test mocks `fs.writeFile` to throw after `.tmp` is created; asserts `rename` never called and `rollbackFromBackup` restores the original bytes
  - `.bak.<ts>` files live in `os.tmpdir()` (NOT in `scripts/requirements/req/`)
  - `errorBody('crud:notfound:rf_999', 404)` returns `Response(JSON.stringify({ok:false,error:'crud:notfound:rf_999'}), {status:404, headers:{'Content-Type':'application/json; charset=utf-8'}})`
- **Notes**: `serializeYamlSubset` must match `migrate_to_doorstop.py:45-65` single-line shape EXACTLY (design risk #12). Don't add `# Stakeholder:` comment header on round-trip — v1 parser already skips `#` lines.

### Phase 2 — Sidebar restructure (PR1 / D1)

### Task 2.1: `RemGroupShell` (3 collapsible `<details>` + nested)

- **Commit**: D1
- **Files**:
  - `frontend/src/components/rem/RemGroupShell.tsx` (NEW, ~120 LoC; native `<details>`/`<summary>`, `expandedStorageKey`, `nestedExpandedStorageKey`, `+ Nuevo` button slot)
  - `frontend/tests/unit/components/rem/RemGroupShell.test.tsx` (NEW, ~150 LoC; 6 it for REQ-13#1..#5 + REQ-14#1)
  - `frontend/tests/unit/components/rem/RemTreeNode.test.tsx` (modify, +60 LoC; +3 it for REQ-13#4 legacy-key fallback, REQ-14#1 collapsed nested, REQ-17#2 DELETE removes row)
- **Spec coverage**: REQ-13#1..#5 (3 top-level groups, persistence, reload), REQ-14#1..#3 (nested collapsibles per `module`/`category`), REQ-18#1 (+ Nuevo button per group)
- **Dependencies**: Task 1.1 (storage key helpers)
- **Acceptance**:
  - `pnpm vitest run tests/unit/components/rem/RemGroupShell tests/unit/components/rem/RemTreeNode` passes 6 + 3 tests
  - 3 `<details data-rem-group-top="<stakeholders|rf|rnf>">` rendered with `<summary>` text exactly matching i18n keys `rem.group.{id}.heading`
  - RF group renders nested `<details data-rem-group-nest="rf.<module>">` per `module`; RNF per `category`; stakeholders flat
  - `vi.spyOn(window.localStorage, 'setItem')` confirms exactly 1 `setItem` per toggle (no scroll writes)
  - First-visit default is `open` (REQ-13#5); legacy `'open' | 'closed'` key values don't throw (REQ-13#4)
- **Notes**: Reuse v1's `RemSidebar.tsx:235` `fetch + innerHTML` pattern (DD5 lock) for tree-region swap after CRUD. `RemGroupShell` is the only place the `+ Nuevo` button lives.

### Task 2.2: `RemResizeHandle` + sidebar width CSS hook

- **Commit**: D1
- **Files**:
  - `frontend/src/components/rem/RemResizeHandle.tsx` (NEW, ~80 LoC; CSS `resize: horizontal`, clamp [240, 480], `SIDEBAR_WIDTH_STORAGE_KEY` persist on `resize` event, `hidden lg:block`)
  - `frontend/tests/unit/components/rem/RemResizeHandle.test.tsx` (NEW, ~120 LoC; 5 it for REQ-15#1..#5)
- **Spec coverage**: REQ-15#1..#5 (drag, clamp, persist, hidden < lg)
- **Dependencies**: Task 1.1 (storage key helper)
- **Acceptance**:
  - `pnpm vitest run tests/unit/components/rem/RemResizeHandle` passes 5 tests
  - Default width 320 px; clamp `[240, 480]`; out-of-range stored values clamp and overwrite on first render
  - Persist happens exactly once per `resize` event (not on every pixel) — verified via `setItem` spy
  - Non-numeric stored value (`'abc'`) ignored, falls back to 320
  - `hidden lg:block` hides the handle below 1024 px; no `localStorage.setItem` fires in this mode
- **Notes**: CSS `resize: horizontal` (DD2 lock) handles drag geometry; we only listen to the `resize` event for persistence. Width is set via `--rem-sidebar-width` CSS variable on `<aside class="rem-shell__nav">`.

### Task 2.3: `RemSidebar` widen + palette base + tree region hook

- **Commit**: D1
- **Files**:
  - `frontend/src/components/rem/RemSidebar.tsx` (modify, +60 LoC: wire `RemGroupShell` over 3 branches, mount `RemResizeHandle`, expose `data-rem-tree-region` hook for D4 swap, add `bg-[#f8fafc]` base per REQ-24)
  - `frontend/src/layouts/RemLayout.astro` (modify, +5 LoC: `<main class="... max-w-3xl">` → `max-w-6xl` per REQ-23)
  - `frontend/tests/unit/components/rem/RemSidebar.test.tsx` (modify, +90 LoC: +11 it for REQ-13#2 click persists, REQ-14#2 nested persistence, REQ-15#1 default visible, REQ-16#1 kebab per row, REQ-18#1 create button per group, REQ-23#1 max-w-6xl, REQ-24#3 slate-300 borders)
- **Spec coverage**: REQ-13#2, REQ-14#2, REQ-15#1, REQ-16#1, REQ-18#1, REQ-23#1, REQ-24#3
- **Dependencies**: Tasks 2.1, 2.2
- **Acceptance**:
  - `pnpm vitest run tests/unit/components/rem/RemSidebar` passes 21 tests (10 v1 + 11 v2)
  - On mount, `aside.rem-shell__nav` has `data-rem-tree-region` attribute (used by D4 swap)
  - Card/group border class is `border-slate-300` (REQ-24#3)
  - Computed `<main>` width on a 1280-px viewport is between 1100 and 1180 px (REQ-23#1)
  - All v1 RemSidebar tests still pass (no regression)
- **Notes**: v1's `RemSidebar.tsx:235` `fetch + innerHTML` pattern must be reused for the tree-region swap in D4. Keep the existing `[data-rem-tree-region]` CSS hook loose enough to swap the whole `<nav>` content area.

### Phase 3 — Dialog + kebab (PR2 / D2)

### Task 3.1: `RemKebabMenu` (⋮ button + popover + `Eliminar` confirm)

- **Commit**: D2
- **Files**:
  - `frontend/src/components/rem/RemKebabMenu.tsx` (NEW, ~140 LoC; always-visible `⋮` button, `Editar` / `Eliminar` actions, Escape + click-outside close, `window.confirm()` for delete)
  - `frontend/src/components/rem/RemTreeNode.tsx` (modify, +15 LoC: kebab slot per leaf)
  - `frontend/src/components/rem/RemSidebar.tsx` (modify, +10 LoC: `+ Nuevo` button + dialog wiring stub)
  - `frontend/tests/unit/components/rem/RemKebabMenu.test.tsx` (NEW, ~180 LoC; 8 it for REQ-16#1..#4, REQ-17#1..#4)
  - `frontend/tests/unit/components/rem/RemTreeNode.test.tsx` (modify, +30 LoC; +2 it for kebab slot integration, REM-17#4 deleted UID renders RemError)
- **Spec coverage**: REQ-16#1..#4 (kebab per row, popover, Escape, click-outside), REQ-17#1..#4 (confirm + DELETE + row removal + notfound)
- **Dependencies**: Task 2.3 (sidebar slot)
- **Acceptance**:
  - `pnpm vitest run tests/unit/components/rem/RemKebabMenu` passes 8 tests
  - Every leaf renders exactly one `button[aria-label="rem.crud.actions"]` with `⋮`
  - Escape closes the menu and returns focus to the kebab button
  - Click-outside closes without selecting the row
  - `Eliminar` calls `window.confirm(t('rem.crud.delete.confirm'))` exactly once
  - On `confirm() === true` and `fetch` resolves 204: row is removed in DOM; `clearLoaderCache()` was called (spy)
  - On `fetch` 500: row stays in place; `rem.crud.delete.error` alert renders
- **Notes**: `RemKebabMenu` does NOT call `fetch` itself — it invokes `onDelete(uid)` callback that the parent (RemSidebar) wires in D4 to the DELETE endpoint. This keeps the component testable without HTTP mocks.

### Task 3.2: `RemEditDialog` skeleton + i18n keys

- **Commit**: D2
- **Files**:
  - `frontend/src/components/rem/RemEditDialog.tsx` (NEW, ~150 LoC; `<dialog>` modal, `mode: 'create'|'edit'`, `kind: 'rf'|'rnf'|'stakeholder'`, embed form via prop slot — actual form bodies land in D4)
  - `frontend/src/i18n/types.ts` (modify, +30 LoC: ~30 new `rem.*` keys union)
  - `frontend/src/i18n/locales/es.ts` (modify, +30 lines)
  - `frontend/src/i18n/locales/en.ts` (modify, +30 lines)
  - `frontend/tests/unit/components/rem/RemEditDialog.test.tsx` (NEW, ~80 LoC; 2 skeleton tests: renders with `mode='create' kind='stakeholder'` shows 5 fields, cancel button closes)
- **Spec coverage**: REQ-19#1 (Zod errors surface), REQ-19#4 (Cancel/Escape no fetch); partial REQ-18#2/#3 dialog shell
- **Dependencies**: Task 3.1 (kebab triggers `Editar` → open dialog)
- **Acceptance**:
  - `pnpm vitest run tests/unit/components/rem/RemEditDialog` passes 2 tests (D4 adds 3 more)
  - `pnpm run typecheck` clean — proves both locales have all new keys (REQ-08#2 from v1 carries over)
  - Dialog uses native `<dialog>` element with `Escape` and backdrop close → `onClose` (no fetch)
  - Submit button label and dialog title use `useTranslations()` keys (no hardcoded Spanish)
- **Notes**: Skeleton ships form children as `children` prop slot; D4 plugs `RemRfForm`/`RemRnfForm`/`RemStakeholderForm` in. New i18n keys listed in design §7 (~30 total: `rem.group.*.heading`, `rem.crud.*`, `rem.form.*.label`).

### Phase 4 — CRUD endpoints (PR2 / D3)

### Task 4.1: Stakeholder endpoints (POST + PUT + DELETE)

- **Commit**: D3
- **Files**:
  - `frontend/src/pages/api/rem/stakeholder/index.ts` (NEW, ~80 LoC; `POST` handler, Zod validate → allocate `stakeholder_NNN` slot → `writeYamlAtomic` → `clearLoaderCache()` → 201)
  - `frontend/src/pages/api/rem/stakeholder/[uid].ts` (NEW, ~100 LoC; `PUT` (update) + `DELETE` (unlink) method switch)
  - `frontend/tests/unit/pages/api/rem/stakeholder.test.ts` (NEW, ~80 LoC; 4 it for REQ-20#1..#4)
  - `frontend/tests/unit/pages/api/rem/stakeholder_uid.test.ts` (NEW, ~60 LoC; 3 it for REQ-21#1, #3, REQ-22#2)
  - `frontend/tests/unit/pages/api/rem/stakeholder_delete.test.ts` (NEW, ~80 LoC; 4 it for REQ-22#1..#4)
- **Spec coverage**: REQ-20#1..#4 stakeholder (POST 201/400/500/cache), REQ-21#1/#3 (PUT 200/400), REQ-22#1..#4 (DELETE 204/404/500/cache + loader excludes)
- **Dependencies**: Tasks 1.1, 1.2 (schemas + writer)
- **Acceptance**:
  - `pnpm vitest run tests/unit/pages/api/rem/stakeholder*` passes 11 tests
  - `POST /api/rem/stakeholder` with valid body → 201 + `{ok:true, uid:'stakeholder_004'}`; new YAML at `scripts/requirements/req/stakeholder_004_<slug>.yml`
  - `POST` with `priority: 'X'` (invalid) → 400 with Zod issues map; no YAML written
  - `PUT /api/rem/stakeholder/stakeholder_001` with `{title: 'Owner'}` → 200 + `{ok:true, uid, item}`; file's `title` rewritten
  - `PUT` on unknown UID → 404 + `{ok:false, error:'crud:notfound:stakeholder_999'}`
  - `DELETE /api/rem/stakeholder/stakeholder_001` → 204 with empty body, `Content-Length: 0`; file removed; `clearLoaderCache()` called
  - 500 on `fs.writeFile` throw: response 500, sibling YAMLs untouched (NFR-10)
- **Notes**: Mirror v1's `pages/api/rem/[uid].html.ts:57` `findItemByUid` pattern for the PUT/DELETE handlers. UID slot allocation: scan `readdirSync(req)` for `^stakeholder_\d{3}_`, pick `max + 1`, zero-pad.

### Task 4.2: RF endpoints (POST + PUT + DELETE)

- **Commit**: D3
- **Files**:
  - `frontend/src/pages/api/rem/rf/index.ts` (NEW, ~80 LoC)
  - `frontend/src/pages/api/rem/rf/[uid].ts` (NEW, ~100 LoC)
  - `frontend/tests/unit/pages/api/rem/rf.test.ts` (NEW, ~80 LoC; 4 it for REQ-20#1..#4 RF)
  - `frontend/tests/unit/pages/api/rem/rf_uid.test.ts` (NEW, ~80 LoC; 4 it for REQ-21#1..#4)
  - `frontend/tests/unit/pages/api/rem/rf_delete.test.ts` (NEW, ~80 LoC; 4 it for REQ-22#1..#4)
- **Spec coverage**: REQ-20..22 RF (12 scenarios total)
- **Dependencies**: Task 4.1 (mirrors stakeholder pattern)
- **Acceptance**:
  - `pnpm vitest run tests/unit/pages/api/rem/rf*` passes 12 tests
  - `PUT /api/rem/rf/rf_001` with empty `{}` body → 200; YAML rewritten byte-identical (NFR-11 round-trip case; covered in `rf_uid.test.ts` per design §6 mapping)
  - All 4 endpoint scenarios per spec scenario (POST 201/400/500/cache; PUT 200/400/404/round-trip; DELETE 204/404/500/cache + loader excludes)
- **Notes**: Extract the common endpoint scaffolding (`prerender = false`, `errorBody` import) into a shared helper once all three item types are proven identical (refactor opportunity for follow-up — out of scope for v2).

### Task 4.3: RNF endpoints (POST + PUT + DELETE)

- **Commit**: D3
- **Files**:
  - `frontend/src/pages/api/rem/rnf/index.ts` (NEW, ~80 LoC)
  - `frontend/src/pages/api/rem/rnf/[uid].ts` (NEW, ~100 LoC)
  - `frontend/tests/unit/pages/api/rem/rnf.test.ts` (NEW, ~80 LoC; 4 it)
  - `frontend/tests/unit/pages/api/rem/rnf_uid.test.ts` (NEW, ~80 LoC; 4 it)
  - `frontend/tests/unit/pages/api/rem/rnf_delete.test.ts` (NEW, ~80 LoC; 4 it)
- **Spec coverage**: REQ-20..22 RNF (12 scenarios total); completes the 9-endpoint matrix (REQ-20..22 × 3 item types)
- **Dependencies**: Task 4.2 (mirrors RF pattern)
- **Acceptance**:
  - `pnpm vitest run tests/unit/pages/api/rem/rnf*` passes 12 tests
  - All 36 endpoint tests across the 9 files pass in `pnpm vitest run tests/unit/pages/api/rem`
- **Notes**: No `verifiedBy` field in RNF schemas (design §11 RNF mirror). After this task lands, all 9 endpoints are green and the e2e CRUD spec (D4) can rely on them.

### Phase 5 — Form wiring + tree-region swap (PR2 / D4)

### Task 5.1: 3 form components (RF / RNF / Stakeholder)

- **Commit**: D4
- **Files**:
  - `frontend/src/components/rem/forms/RemRfForm.tsx` (NEW, ~140 LoC; `useForm({ resolver: zodResolver(rfCreateSchema|rfUpdateSchema), mode: 'onBlur' })`)
  - `frontend/src/components/rem/forms/RemRnfForm.tsx` (NEW, ~120 LoC)
  - `frontend/src/components/rem/forms/RemStakeholderForm.tsx` (NEW, ~100 LoC)
  - `frontend/tests/unit/components/rem/forms/RemRfForm.test.tsx` (NEW, ~80 LoC; 3 it: invalid rejects, valid submits, cancel doesn't fire)
  - `frontend/tests/unit/components/rem/forms/RemRnfForm.test.tsx` (NEW, ~80 LoC; 3 it)
  - `frontend/tests/unit/components/rem/forms/RemStakeholderForm.test.tsx` (NEW, ~80 LoC; 3 it)
- **Spec coverage**: REQ-19#1 (Zod errors surface via `formState.errors`)
- **Dependencies**: Task 3.2 (dialog skeleton)
- **Acceptance**:
  - `pnpm vitest run tests/unit/components/rem/forms` passes 9 tests
  - Invalid `code` (`'RF-NEW-999'` for the schema regex) → submit blocked; field shows `rem.form.code.invalid`
  - Valid submit → `onSubmit(values)` called with the parsed object (Zod-coerced)
  - Cancel button → no `onSubmit` call; no fetch
  - Submit button label comes from `useTranslations().t('rem.crud.create.submit' | 'rem.crud.edit.submit')`
- **Notes**: Each form is < 250 LoC per AGENTS.md §1. If a form grows past 150 LoC during implementation, split into `.{tsx,Field.tsx}` per design §11.

### Task 5.2: Dialog submit wiring + tree-region swap + e2e CRUD spec

- **Commit**: D4
- **Files**:
  - `frontend/src/components/rem/RemEditDialog.tsx` (modify, +50 LoC: plug the 3 forms from 5.1, POST/PUT dispatch, success → `clearLoaderCache()` + parent `onSuccess` callback)
  - `frontend/src/components/rem/RemSidebar.tsx` (modify, +30 LoC: capture `aside.rem-shell__nav [data-rem-tree-region] .overflow-y-auto` `scrollTop` before fetch, restore after innerHTML swap, `setSelectedUid(newUid)` to auto-select per DD7)
  - `frontend/tests/unit/components/rem/RemEditDialog.test.tsx` (modify, +100 LoC; +3 it for REQ-19#2 #3 #5: 201 closes + scroll preserved + cache cleared; 200 swaps detail; 500 stays open + error alert)
  - `frontend/tests/unit/components/rem/RemSidebar.test.tsx` (modify, +40 LoC; +2 it: tree-region swap on success, scroll preservation)
  - `frontend/tests/e2e/rem/rem-crud.spec.ts` (NEW, ~180 LoC; 8 it: REQ-18#1, REQ-19#2 #3, REQ-20#1, REQ-21#1, REQ-22#1, REQ-23#1)
- **Spec coverage**: REQ-19#2 #3 #4 #5 (submit success/error paths + scroll preservation), REQ-17#2 #4 (DELETE removes + loader excludes), NFR-09 (CRUD ≤ 200 ms p95 via `performance.now()`)
- **Dependencies**: Task 5.1 (forms)
- **Acceptance**:
  - `pnpm vitest run tests/unit/components/rem/RemEditDialog tests/unit/components/rem/RemSidebar` passes 5 + 23 tests
  - `pnpm playwright test rem/rem-crud.spec.ts` passes 8 tests (gated on `process.env.E2E === '1'`)
  - 201 response → dialog closes, `clearLoaderCache()` called, tree-region innerHTML refreshed, sidebar scrollTop preserved within ±5 px
  - 200 response → detail panel re-renders via existing `fetch + innerHTML` path (`/api/rem/<uid>.html`)
  - 500 response → dialog stays open, submit button re-enabled, alert with `rem.crud.write.error` + response body verbatim
  - After successful POST, the new leaf is auto-selected via `setSelectedUid` (design open question #7 → "yes" lock)
- **Notes**: Pre-requisite per `playwright-e2e-patterns`: backend + frontend + Caddy running locally; gated on `E2E=1` to keep `pnpm vitest run` headless-only in local dev.

### Phase 6 — Palette + widening (PR3 / D5)

### Task 6.1: Palette tokens (#2D9D8E) + slate-300 borders + detail widening

- **Commit**: D5
- **Files**:
  - `frontend/src/components/rem/RemSidebar.tsx` (modify, +20 LoC: selected leaf `bg-[#2D9D8E]/10 text-[#1E6F62]`, group/card `border-slate-300`)
  - `frontend/src/components/rem/RemTreeNode.tsx` (modify, +10 LoC: selected state class swap)
  - `frontend/src/components/rem/RemPrintButton.tsx` (modify, +5 LoC: hover bg `#2D9D8E` / `#258777`)
  - `frontend/src/components/rem/RemEditDialog.tsx` (modify, +5 LoC: active form input border `#2D9D8E`)
  - `frontend/tests/unit/components/rem/RemPrintButton.test.tsx` (modify, +20 LoC; +1 it for REQ-24#4 hover bg)
  - `frontend/tests/unit/components/rem/RemSidebar.test.tsx` (modify, +20 LoC; +1 it for REQ-24#3 border-slate-300 assertion)
- **Spec coverage**: REQ-24#1 #3 #4 (palette anchor + slate-300 + hover)
- **Dependencies**: none (visual changes only)
- **Acceptance**:
  - `pnpm vitest run tests/unit/components/rem/RemPrintButton tests/unit/components/rem/RemSidebar tests/unit/components/rem/RemEditDialog` all pass
  - PD badge stays at `bg-[#fef3c7] text-[#92400e]` (REQ-24#2 contrast ≥ 4.5:1 preserved)
  - Red `?` marker stays at `bg-[#fee2e2] text-[#b91c1c]`
- **Notes**: Keep the print stylesheet (`rem-print.css`) untouched — palette is screen-only.

### Task 6.2: E2E sidebar + a11y specs + snapshot baseline regen

- **Commit**: D5
- **Files**:
  - `frontend/tests/e2e/rem/rem-sidebar.spec.ts` (NEW, ~150 LoC; 6 it: REQ-13#2, REQ-14#2, REQ-15#1, REQ-15#4, REQ-23#1, REQ-24#1)
  - `frontend/tests/e2e/rem/rem-a11y.spec.ts` (NEW, ~100 LoC; 3 it: REQ-13#1 `<details>` count, REQ-16#3 kebab a11y, NFR-13 `axe-core` zero AA violations)
  - `frontend/tests/e2e/rem/__snapshots__/rem-viewer-1280.png` (regenerated binary baseline per design risk #5)
  - `frontend/playwright.config.ts` (modify, +5 LoC: add `axe-core` import path; gated on existing `baseURL`)
- **Spec coverage**: REQ-13#2, REQ-14#2, REQ-15#1 #4, REQ-23#1, REQ-24#1, REQ-16#3, NFR-13 (WCAG 2.2 AA)
- **Dependencies**: Task 6.1 (palette must be in place for screenshot regen)
- **Acceptance**:
  - `pnpm playwright test rem/rem-sidebar.spec.ts rem/rem-a11y.spec.ts` passes 9 tests
  - `axe-core` scan reports zero AA violations on `/es/rem`
  - `getComputedStyle(selectedLeaf).backgroundColor` === `'rgba(45, 157, 142, 0.1)'` (Tailwind `#2D9D8E/10`)
  - Snapshot at 1280×800 regenerated; diff within 5% of baseline per `playwright.config.ts` convention
- **Notes**: Snapshot regeneration is a known visual regression risk (design risk #5). Mark the baseline as visual regression baseline in PR3 body; reviewers compare PNG diff.

### Phase 7 — Documentation (PR3 / D6)

### Task 7.1: `frontend/README.md` REM Viewer section (8 subsections)

- **Commit**: D6
- **Files**:
  - `frontend/README.md` (modify, +150 LoC under "REM Viewer" heading)
- **Spec coverage**: NFR-14 (8 H3 subsections: Architecture, Endpoints, Data flow, Running, Testing, Accessibility, i18n, Migration)
- **Dependencies**: none (docs only)
- **Acceptance**:
  - `markdownlint frontend/README.md` passes (project style guide: 80 cols, Spanish prose with English technical names)
  - All 8 required H3 headings present: `### Architecture`, `### Endpoints`, `### Data flow`, `### Running`, `### Testing`, `### Accessibility`, `### i18n`, `### Migration`
  - Endpoints section enumerates 1 GET (`/api/rem/[uid].html`) + 9 CRUD (3 item types × POST/PUT/DELETE) = 10+ entries with method, path, body schema, success/error codes
  - i18n section documents `rem.*` namespace now spans ~75 keys (~45 v1 + ~30 v2)
  - Migration section points to `scripts/requirements/migrate_to_doorstop.py` for YAML regeneration
  - Security note: write endpoints are unauthenticated (academic-only); TODO link to a future auth change for any production use (design risk #6)
- **Notes**: No `.claude/skills/**` change in this task. Doc-only commit; safe to merge independent of code review.

## 5. Test plan summary

Per spec §"Test Plan Summary" + design §6:

**Unit (Vitest, 14 new + 5 v1-extended files, 85 v2 `it` + ~85 v1 baseline = ~170 total):**

| File | `it` count | REQs / NFRs covered |
|---|---:|---|
| `tests/unit/lib/rem/schemas.test.ts` (NEW, D0) | 9 | REQ-19#1, REQ-20#2, REQ-21#3 |
| `tests/unit/lib/rem/storageKeys.test.ts` (NEW, D0) | 3 | REQ-13/14/15 key helpers |
| `tests/unit/lib/rem/writer.test.ts` (NEW, D0) | 5 | NFR-10, NFR-11 |
| `tests/unit/lib/rem/serializeYamlSubset.test.ts` (NEW, D0) | 4 | NFR-11 |
| `tests/unit/components/rem/RemGroupShell.test.tsx` (NEW, D1) | 6 | REQ-13#1..#5, REQ-14#1 |
| `tests/unit/components/rem/RemResizeHandle.test.tsx` (NEW, D1) | 5 | REQ-15#1..#5 |
| `tests/unit/components/rem/RemSidebar.test.tsx` (v1 extended, D1+D3) | +11 (21 total) | REQ-13#2, REQ-14#2, REQ-15#1, REQ-16#1, REQ-18#1, REQ-23#1, REQ-24#3 |
| `tests/unit/components/rem/RemTreeNode.test.tsx` (v1 extended, D1+D2) | +5 (8 total) | REQ-13#4, REQ-14#1, REQ-17#2, REQ-17#4 |
| `tests/unit/components/rem/RemKebabMenu.test.tsx` (NEW, D2) | 8 | REQ-16#1..#4, REQ-17#1..#4 |
| `tests/unit/components/rem/RemEditDialog.test.tsx` (NEW, D2 + D4) | 5 | REQ-19#1..#5 |
| `tests/unit/components/rem/forms/*.test.tsx` (NEW, D4, 3 files) | 9 | REQ-19#1 |
| `tests/unit/pages/api/rem/stakeholder*.test.ts` (NEW, D3, 3 files) | 11 | REQ-20..22 stakeholder |
| `tests/unit/pages/api/rem/rf*.test.ts` (NEW, D3, 3 files) | 12 | REQ-20..22 rf |
| `tests/unit/pages/api/rem/rnf*.test.ts` (NEW, D3, 3 files) | 12 | REQ-20..22 rnf |
| `tests/unit/components/rem/RemPrintButton.test.tsx` (v1 extended, D5) | +1 (4 total) | REQ-24#4 |

**E2E (Playwright, 3 new files, 17 v2 `it` + 12 v1 baseline = 29 total):**

| File | `it` count | REQs / NFRs covered |
|---|---:|---|
| `tests/e2e/rem/rem-crud.spec.ts` (NEW, D4) | 8 | REQ-18#1, REQ-19#2 #3, REQ-20#1, REQ-21#1, REQ-22#1, REQ-23#1 |
| `tests/e2e/rem/rem-sidebar.spec.ts` (NEW, D5) | 6 | REQ-13#2, REQ-14#2, REQ-15#1 #4, REQ-23#1, REQ-24#1 |
| `tests/e2e/rem/rem-a11y.spec.ts` (NEW, D5) | 3 | REQ-13#1, REQ-16#3, NFR-13 |

**Total v2**: 85 unit + 17 e2e = **102 new `it` methods** covering **all 45 v2 spec §3 scenarios + 6 v2 NFRs**.

**Coverage thresholds** (extend `frontend/vitest.config.ts`):

```ts
coverage: {
  include: [
    "src/**/*.{ts,tsx}",
    "src/lib/rem/**",
    "src/components/rem/**",
    "src/lib/rem/{rf,rnf,stakeholder}.schema.ts",
    "src/components/rem/RemEditDialog.tsx",
    "src/pages/api/rem/**/*.ts",
  ],
  thresholds: { lines: 85, functions: 85, branches: 80, statements: 85 },
}
```

**Fixtures** (under `frontend/tests/unit/lib/rem/fixtures/writer/`):
- 6 v1-shaped YAML files (2 RF + 2 RNF + 2 stakeholder; one of each with empty lists, one with content)
- Generated by extending `_generate.test.ts` to copy from `scripts/requirements/req/` when `GENERATE_FIXTURES=1`

**Mocking strategy**:
- `vi.mock('node:fs/promises', ...)` for write-side endpoint + writer tests
- `vi.mock('../../../src/i18n', ...)` identity mock for React component tests
- `vi.spyOn(window, 'confirm')` for `Eliminar` tests
- `vi.spyOn(window.localStorage, 'setItem')` for persistence scenarios
- `vi.useFakeTimers` for the 5-min loader cache scenarios (no v2 changes here — already in v1)

## 6. Acceptance for sdd-apply

`sdd-apply` phase must pass every gate below before declaring complete:

- [ ] All 15 tasks marked complete; each task's "Acceptance" criteria checked off
- [ ] `pnpm run typecheck` clean (no errors; pre-existing failures outside the change are NOT a regression)
- [ ] `pnpm run lint` clean
- [ ] `pnpm vitest run` 100% passing (no `skip`/`todo`/`xfail`)
- [ ] `pnpm vitest run --coverage` ≥ 85% lines for `src/lib/rem/**` + `src/components/rem/**` + new `pages/api/rem/**` + new `lib/rem/{rf,rnf,stakeholder}.schema.ts`
- [ ] `pnpm playwright test rem/` 100% passing (17 v2 + 12 v1 = 29 e2e tests, gated on `E2E=1`)
- [ ] NFR-11 round-trip test passes for all 3 item types (RF / RNF / stakeholder) via 6 fixtures
- [ ] NFR-13 `axe-core` scan reports zero AA violations on `/es/rem`
- [ ] No backend file (`backend/**`) touched
- [ ] No `scripts/requirements/req/*.yml` hand-edited during v2 (all changes via endpoints or `migrate_to_doorstop.py`)
- [ ] No npm package added to `frontend/package.json`
- [ ] No `.claude/skills/**` modified
- [ ] v1 archive files at `openspec/changes/rem-viewer/{proposal,specs,design,tasks,apply-progress,archive-report}.md` are byte-identical (no modifications)
- [ ] Each commit message in Spanish, conventional format (`feat(frontend):` / `docs(frontend):`)
- [ ] No `Co-Authored-By` AI attribution in commits
- [ ] 1280×800 e2e snapshot baseline committed to `frontend/tests/e2e/rem/__snapshots__/rem-viewer-1280.png`
- [ ] Feature branch `feat/rem-viewer-v2` exists as tracker; 3 child PRs created per chain strategy
- [ ] `frontend/README.md` REM Viewer section has all 8 required H3 subsections (NFR-14)
- [ ] `markdownlint frontend/README.md` passes

## 7. Risk register for tasks

Carrying over design §13 + task-time additions:

| # | Risk | Likelihood | Mitigation |
|---|---|---|---|
| 1 | Deadline pressure (academic, v1's own window). | High | D0..D6 each shippable independently; per AGENTS.md §8 work-unit-commits; can cut edit/delete per-item icons if time tight (bare CRUD via top-of-group menu acceptable per proposal risk #1). |
| 2 | CRUD write-back corrupts YAML if parser sees an edge case. | Medium | `serializeYamlSubset` matches `migrate_to_doorstop.py:45-65` exactly; NFR-10 torn-write test + NFR-11 round-trip test on 6 fixtures in D0. |
| 3 | Sidebar resize handle collides with scroll on small screens. | Low | CSS `resize: horizontal` (DD2); `hidden lg:block` below `lg` (REQ-15#5). |
| 4 | `localStorage` desyncs after multi-tab CRUD. | Low | Worst-case stale until reload; documented in README (no cross-tab `storage` event in v2 scope). |
| 5 | Palette refresh breaks v1 e2e snapshot baseline. | Medium | Regenerate baseline in D5 (Task 6.2); 5% diff threshold per `playwright.config.ts` convention. |
| 6 | New endpoints introduce security gaps (no auth on write). | Medium | Documented academic-only in README (Task 7.1); Security note + TODO link for any production use. |
| 7 | Cascading scope expansion: widening v1 components may force follow-on touches. | Low | Each v1 file touch documented as "Deviation" in `apply-progress.md` (v1 precedent: batch 4 `a20a6bd`). |
| 8 | `frontend/AGENTS.md` size budget (≤ 250 LoC per component) violated by `RemEditDialog.tsx`. | Low | Split into `.{tsx, Field.tsx, Confirm.tsx}` per design §11 if mid-implementation LoC > 250. |
| 9 | `@hookform/resolvers/zod` missing from `frontend/package.json`. | Low | **NEW**: verified during D0 Task 1.1; if missing, fall back to a 5-line manual resolver per RHF docs (no new package — hard constraint). |
| 10 | 9 endpoints × 9 tests = 81 new endpoint tests + D0..D4 unit tests = ~100 new tests; CI runtime impact. | Medium | Unit tests stay < 1s total (in-memory mocks); e2e gated behind `E2E=1` already (v1 pattern). |
| 11 | Astro 7 endpoint method dispatch (PUT + DELETE in `[uid].ts`) is unconventional — no v1 precedent. | Low | **NEW**: Astro 7 `APIRoute` exports `PUT`/`DELETE` natively per `astro.config.mjs:39`; if Astro rejects, split into `[uid]/index.ts` (PUT) + `[uid]/delete.ts` (DELETE) — small refactor, no contract change. |
| 12 | `serializeYamlSubset` round-trip exactness vs migration script comment shape. | Medium | **NEW**: serializer writes only `{uid}:` header + body; v1 parser skips `#` lines so read-after-write works. `serializeYamlSubset.test.ts` round-trips against 6 fixtures. |
| 13 | Write endpoint running in Astro dev SSR may lack `node:fs/promises` write permission in production Docker builds. | Low | Documented in README; production deployment already runs Astro `output: 'server'` per `astro.config.mjs:39-40` (v1 contract). |
| 14 | Auto-select newly-created item (design open question #7) blocks on user input. | Low | **NEW**: design locks "yes, scroll into view + `setSelectedUid`" matching proposal §10#4 recommendation. `setSelectedUid` is the only call site to change if reviewer pushes back. |

## 8. Open questions for sdd-apply

| # | Question | Blocks `sdd-apply`? |
|---|---|---|
| 1 | Schema multi-line fields (`autores`, `fuentes`, `dependencias`) — single-line only matches v1 parser's coverage. | **No**: design locks single-line in D0; multi-line deferred. |
| 2 | Soft vs hard delete — hard delete per user input. | **No**: D3 implements hard delete; user-resolved. |
| 3 | DP1 (persistence target) — YAML files via extended hand-rolled parser. | **No**: design locks YAML; user-resolved. |
| 4 | DP2 (sidebar persistence) — `localStorage` per browser. | **No**: design locks localStorage; user-resolved. |
| 5 | DP3 (form library) — `react-hook-form` + `zod` (already in `package.json`). | **No**: design locks RHF + Zod; user-resolved. |
| 6 | Should the kebab menu include a third "Duplicate" action? | **No (non-blocking)**: v2 ships 2 actions (Editar / Eliminar); adding "Duplicar" later is a 1-component change. |
| 7 | Should `RemEditDialog` auto-select the newly-created leaf in the sidebar (DD7 implementation detail)? | **No (non-blocking)**: D4 Task 5.2 locks "yes, scroll into view + `setSelectedUid`" — matches the proposal §10#4 recommendation. If reviewer pushes back, `setSelectedUid` is the single call site. |
| 8 | Should the resize handle use `mouseup` or `pointerup` for persistence? | **No (non-blocking)**: CSS `resize: horizontal` fires `resize` event after pointerup; we listen to `resize`. Equivalents. |
| 9 | Should `RemEditDialog` be one component or split now (preempt the AGENTS.md §1 250-LoC budget)? | **No (non-blocking)**: ship as one (~150 LoC estimated in D2 Task 3.2); split into `.{tsx,Field.tsx,Confirm.tsx}` if LoC > 250 mid-implementation per design §11. |
| 10 | Does the `@hookform/resolvers/zod` package exist in `frontend/package.json` deps? | **Possibly blocking**: verify in D0 Task 1.1 before implementation. If missing, fall back to manual resolver (no new package — hard constraint). |
| 11 | Does Astro 7 `APIRoute` export both `PUT` and `DELETE` from a single `[uid].ts`? | **Possibly blocking**: verify in D3 Task 4.1 with a minimal smoke test. If Astro rejects, split endpoints — small refactor, no contract change. |

---

**Total LOC budget per PR** (recheck at PR creation):

- PR1: D0 (580) + D1 (430) = ~1010 LoC → `size:exception` required
- PR2: D2 (430) + D3 (1080) + D4 (510) = ~2020 LoC → `size:exception` required (largest)
- PR3: D5 (330) + D6 (150) = ~480 LoC → `size:exception` borderline; monitor diff

> **Review Workload Forecast**
>
> | Field | Value |
> |-------|-------|
> | Estimated changed lines | ~1850 (impl) + ~1660 (tests + fixtures) = ~3510 total |
> | 400-line budget risk | High |
> | Chained PRs recommended | Yes |
> | Suggested split | PR1 (D0+D1) → PR2 (D2+D3+D4) → PR3 (D5+D6) |
> | Delivery strategy | auto-chain (preflight locked) |
> | Chain strategy | feature-branch-chain |
