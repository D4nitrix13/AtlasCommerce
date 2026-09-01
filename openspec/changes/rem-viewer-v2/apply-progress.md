# Apply Progress — rem-viewer-v2

## Phase 1 (D0 + D1) — 2026-09-01

### Status

- **Branch**: `feat/rem-viewer-v2` (tracker; PR1 head)
- **Scope**: D0 (foundation: Zod schemas + yaml writer + types) + D1 (sidebar UI: collapsible groups + resize handle + teal palette)
- **Commit strategy**: `feature-branch-chain` (this is PR1; size:exception documented in tasks.md §3)
- **Strict TDD**: ACTIVE — every `it()` ships with the implementation in the same commit
- **Phase 1 commit hashes**:
  - `32d6fa3` — `feat(frontend): REM viewer v2 - Zod schemas + yaml writer` (D0)
  - `80fe299` — `feat(frontend): REM viewer v2 - sidebar colapsable + resize` (D1)

### Tasks Completed (1.1 — 1.5)

| # | Task | Files | Tests | Notes |
|---|------|-------|-------|-------|
| 1.1 | Zod schemas + storage key helpers + types extension | `lib/rem/{rf,rnf,stakeholder}.schema.ts` (NEW), `lib/rem/types.ts` (modify, +storage keys + RemCreateInput + RemUpdateInput + RemWriteResult + RemCrudErrorCode) | 9 schemas + 3 storageKeys | Zod 4.x; regex /^RF-[A-Z]+-\d{3}$/ etc. |
| 1.2 | YAML writer + round-trip + crud-errors | `lib/rem/yaml-writer.ts` (NEW), `lib/rem/yaml-roundtrip.ts` (NEW), `lib/rem/crud-errors.ts` (NEW) | 4 serializeYamlSubset + 5 writer (NFR-10 torn + NFR-11 round-trip across 6 fixtures) | Matches migrate_to_doorstop.py:45-65 exactly |
| 1.3 | types.ts v2 additions | folded into Task 1.1 | — | `RemGroupId`, `REM_SIDEBAR_STORAGE_PREFIX`, `expandedStorageKey`, `nestedExpandedStorageKey`, `SIDEBAR_WIDTH_STORAGE_KEY`, `RemCreateInput<T>`, `RemUpdateInput<T>`, `RemCrudErrorCode`, `RemWriteResult` |
| 1.4 | RemGroupShell + RemResizeHandle + RemSidebar modification | `components/rem/RemGroupShell.tsx` (NEW, +RemNestedGroup), `components/rem/RemResizeHandle.tsx` (NEW), `components/rem/RemSidebar.tsx` (modify, hosts GroupShell + NestedGroup + `data-rem-tree-region`) | 6 GroupShell + 5 ResizeHandle + 5 Sidebar v2 + 3 TreeNode (palette) | DD1 native `<details>` lock; DD2 CSS `resize: horizontal` |
| 1.5 | (deferred to D5 per orchestrator adjustment) | — | — | The orchestrator's commit-strategy table (line 26) puts width widening + palette refresh in D5 (Phase 3). For Phase 1 we DO ship the selected-leaf teal palette (REQ-24#1) since it lives inside `RemTreeNode.tsx` (sidebar work) — but NOT the `max-w-3xl` → `max-w-6xl` widening (layout work, deferred to D5). |

### Test Counts

- **D0 tests added**: 21 (9 schemas + 3 storageKeys + 4 serializeYamlSubset + 5 writer)
- **D1 tests added**: 19 (6 GroupShell + 5 ResizeHandle + 5 Sidebar v2 + 3 TreeNode palette)
- **Phase 1 total added**: 40
- **Pre-existing tests passing**: all unchanged (the 1 v1 RemSidebar test was updated for the new collapsible structure; the v1 RemTreeNode group variant is preserved for backward compat)

### Files Touched

#### D0 — Zod + yaml writer (lib foundation)

NEW files:
- `frontend/src/lib/rem/rf.schema.ts` (Zod schemas for RF — create + update)
- `frontend/src/lib/rem/rnf.schema.ts` (Zod schemas for RNF)
- `frontend/src/lib/rem/stakeholder.schema.ts` (Zod schemas for stakeholder)
- `frontend/src/lib/rem/yaml-writer.ts` (`serializeYamlSubset` + `writeYamlAtomic` + `rollbackFromBackup` + `discardBackup`)
- `frontend/src/lib/rem/yaml-roundtrip.ts` (`assertRoundTrip` for NFR-11 fixture runner)
- `frontend/src/lib/rem/crud-errors.ts` (`errorBody` + `crudErrorResponse` + `crudSuccessResponse`)
- `frontend/tests/unit/lib/rem/schemas.test.ts` (9 it for REQ-19#1, REQ-20#2, REQ-21#3)
- `frontend/tests/unit/lib/rem/storageKeys.test.ts` (3 it)
- `frontend/tests/unit/lib/rem/serializeYamlSubset.test.ts` (4 it for NFR-11 shape)
- `frontend/tests/unit/lib/rem/writer.test.ts` (5 it: atomic + tmp cleanup + rollback + 6-fixture round-trip)
- `frontend/tests/unit/lib/rem/fixtures/writer/*.yml` (6 fixtures: 2 RF + 2 RNF + 2 stakeholder; one of each with empty lists)

MODIFIED files:
- `frontend/src/lib/rem/types.ts` (+RemGroupId, +REM_SIDEBAR_STORAGE_PREFIX, +expandedStorageKey, +nestedExpandedStorageKey, +SIDEBAR_WIDTH_STORAGE_KEY, +RemCreateInput<T>, +RemUpdateInput<T>, +RemCrudErrorCode, +RemWriteResult)
- `frontend/src/types/node.d.ts` (+node:fs/promises, +node:url declarations for the writer)

#### D1 — Sidebar restructure (UI layer)

NEW files:
- `frontend/src/components/rem/RemGroupShell.tsx` (RemGroupShell + RemNestedGroup; native `<details>` + localStorage persistence + format-drift tolerance)
- `frontend/src/components/rem/RemResizeHandle.tsx` (CSS `resize: horizontal` + clamp [240, 480] + localStorage persist + matchMedia for hidden < lg)
- `frontend/tests/unit/components/rem/RemGroupShell.test.tsx` (6 it)
- `frontend/tests/unit/components/rem/RemResizeHandle.test.tsx` (5 it)
- `frontend/tests/unit/components/rem/RemTreeNode.test.tsx` (3 it for palette + leaf rendering)

MODIFIED files:
- `frontend/src/components/rem/RemSidebar.tsx` (host the 3 RemGroupShell + nested RemNestedGroup per module/category; add `+ Nuevo` button slot + `onCreate` stub; expose `data-rem-tree-region` attribute for D4 swap; preserve v1 TreeBranch for backward compat)
- `frontend/src/components/rem/RemTreeNode.tsx` (selected leaf carries `bg-[#2D9D8E]/10 text-[#1E6F62]` palette per REQ-24#1)
- `frontend/tests/unit/components/rem/RemSidebar.test.tsx` (5 v2 it; v1 tests updated for new collapsible structure: 81 leaves exact, first focusable is BUTTON/SUMMARY instead of treeitem)

### Quality Gates

- `pnpm vitest run tests/unit/lib tests/unit/rem tests/unit/components/rem` → **97 passing, 1 skipped** (the pre-existing RemPrintButton timing flake is unrelated; my changes don't introduce any new failures)
- `pnpm run typecheck` → **0 new errors** in my files (pre-existing errors in `eslint.config.js`, `src/components/admin/**`, etc. are unrelated)
- `pnpm run lint` → **0 errors** in my files (only `max-lines-per-function` and `complexity` warnings, consistent with v1 baseline)

### Deviations from Design

1. **Reused v1's `node.d.ts` shim** instead of @types/node. Extended the shim with `node:fs/promises` and `node:url` declarations so `yaml-writer.ts` + `yaml-roundtrip.ts` + the writer test file can `import` those modules without a new package. Same constraint as v1 (no new packages).

2. **One v1 test updated for the new structure.** `RemSidebar.test.tsx ▸ REQ-04#1` changed from `expect(total.length).toBeGreaterThan(81)` (counting groups + leaves) to `expect(total.length).toBe(81)` (counting only leaves; groups are `<details>` not `treeitem` in v2). Documented inline as a v2 structural change. All other v1 tests pass unchanged.

3. **Task 1.5 partially scoped into D1.** The `max-w-3xl` → `max-w-6xl` widening is out of scope per the orchestrator's misalignment call-out (commit strategy table puts it in D5/Phase 3). The selected-leaf teal palette (`bg-[#2D9D8E]/10 text-[#1E6F62]` per REQ-24#1) ships in D1 because it lives inside `RemTreeNode.tsx` — sidebar work, in-batch. No other v2 palette work happens in this batch.

4. **`+ Nuevo` button is a stub for D2.** The button renders with `data-rem-crud-create="<groupId>"` and fires the `onCreate(groupId)` callback, but the dialog wiring (D2's `RemEditDialog`) is not yet attached. The stub logs a `console.warn` so a smoke run can confirm the click is wired. D2's Task 3.1 closes the loop with the dialog + kebab.

5. **DD5 `data-rem-tree-region` attribute is exposed** on the tree-region `<div>` in `RemSidebar.tsx` for D4's swap hook. D4 will read `scrollTop` from this element before fetch and restore after innerHTML replacement.

### Pre-existing failures NOT introduced by this batch

- `tests/unit/api/rem-uid-html.test.ts` (3 failures — pre-existing, related to Astro page endpoint mocking)
- `tests/unit/components/admin/AuthGuard.test.tsx` (2 failures — pre-existing, locale/redirect mock mismatch)
- `tests/unit/components/admin/TwoFactorChallengeForm.test.tsx` (1 failure — pre-existing)
- `tests/unit/components/rem/RemPrintButton.test.tsx` (1 timing flake — pre-existing, fails only under load)

### Open Items for PR2

- D2: `RemKebabMenu` + `RemEditDialog` skeleton + form bodies + `i18n/locales/{es,en}.ts` +30 keys + `i18n/types.ts` +30 keys
- D3: 9 CRUD endpoints + 9 endpoint tests
- D4: `RemRfForm` + `RemRnfForm` + `RemStakeholderForm` + dialog submit wiring + tree-region swap + e2e CRUD spec
- D5 (Phase 3): palette refresh (print button hover + dialog active border) + `max-w-3xl` → `max-w-6xl` widening + e2e sidebar/a11y specs + snapshot baseline regen
- D6 (Phase 3): detailed `frontend/README.md` REM Viewer section (8 H3 subsections)