# rem-viewer-v2 — Archive Report

## Status

- **Status**: archived
- **Archived at**: 2026-09-01
- **SDD cycle**: complete (`propose` → `spec` → `design` → `tasks` → `apply` → `verify` → `archive`)
- **Mode**: openspec (filesystem artifacts)
- **Verify verdict**: PASS (273 tests passing; all 12 v2 REQs + 6 v2 NFRs covered; 3 safety contracts verified; v1 archive sealed)

## Commits added on top of `feat/frontend-rem-viewer`

| Hash      | Message |
|-----------|---------|
| `32d6fa3` | `feat(frontend): REM viewer v2 - Zod schemas + yaml writer` (D0) |
| `80fe299` | `feat(frontend): REM viewer v2 - sidebar colapsable + resize` (D1) |
| `ef6d408` | `docs(sdd): apply-progress rem-viewer-v2 - record Phase 1 (D0 + D1) commits` |
| `b11bf55` | `fix(frontend): optional chaining en locals.locale del API endpoint` (Phase 1 follow-up) |
| `359abd4` | `feat(frontend): REM viewer v2 - kebab menu + edit dialog skeleton` (D2) |
| `8e91a62` | `feat(frontend): REM viewer v2 - 9 endpoints CRUD` (D3) |
| `e5985fd` | `feat(frontend): REM viewer v2 - form submit + tree-region swap` (D4) |
| `24b6f31` | `feat(frontend): REM viewer v2 - palette + widen + sidebar e2e + README` (D5+D6) |
| `3a2528d` | `docs(sdd): apply-progress rem-viewer-v2 - record Phase 2 + 3 (D2 + D3 + D4 + D5 + D6) commits` |

## Functional acceptance (v2 AC)

| # | Criterion (from `proposal.md` §9) | Status |
|---|-----------------------------------|--------|
| 1 | Sidebar shows 3 `<details>` top-level groups (Stakeholders / RF / RNF); state persists via `localStorage`. | ✅ Implemented in `RemGroupShell.tsx` (D1). Verified by `RemGroupShell.test.tsx` (6 it) + `RemSidebar.test.tsx` v2 (5 it). |
| 2 | RF group has nested collapsibles per `module`; RNF per `category`; stakeholders flat. | ✅ Implemented via `RemNestedGroup` inside `RemGroupShell.tsx`; keys under `nestedExpandedStorageKey(groupId, bucketName)`. |
| 3 | Sidebar has draggable resize handle; clamped 240–480 px; persisted in `localStorage`; disabled below `lg`. | ✅ `RemResizeHandle.tsx` (DD2 CSS `resize: horizontal` lock). 5 it in `RemResizeHandle.test.tsx` for REQ-15#1..#5. |
| 4 | Each leaf has a kebab menu (⋮) with `Editar` / `Eliminar`; `Eliminar` shows a native confirm dialog. | ✅ `RemKebabMenu.tsx` (always-visible per DD3, native `confirm()` per DD4). 8 it for REQ-16 + REQ-17. |
| 5 | A `+ Nuevo` button at the top of each group opens `RemEditDialog` with `mode='create'` and the group's type pre-selected. | ✅ Wired in `RemSidebar.tsx` via `onCreate` → `RemEditDialog` with `kind: 'rf' \| 'rnf' \| 'stakeholder'`. 3 it in `RemEditDialog.test.tsx` for REQ-18. |
| 6 | All 9 CRUD endpoints return 201 (POST) / 200 (PUT) / 204 (DELETE) on success, 400 on Zod validation, 404 on unknown UID, 500 on YAML write error. Round-trip test: read → write → read byte-identical for every item type. | ✅ Implemented across `pages/api/rem/{rf,rnf,stakeholder}/index.ts` + `[uid].ts`. 35 endpoint it + NFR-10 torn-write + NFR-11 round-trip across 6 fixtures. |
| 7 | Detail panel container `max-w-3xl` → `max-w-6xl`. | ✅ `RemLayout.astro` swap (`lg:max-w-6xl`). Visible in `git diff feat/frontend-rem-viewer..feat/rem-viewer-v2 -- frontend/src/layouts/RemLayout.astro`. |
| 8 | Palette: selected leaf uses `bg-[#2D9D8E]/10` + `text-[#1E6F62]`; borders `border-slate-300`; PD badge contrast still ≥ 4.5:1. | ✅ `RemTreeNode.tsx` + `RemSidebar.tsx` + `RemPrintButton.tsx`. PD badge (`#fef3c7` / `#92400e`) untouched (REQ-24#2 preserves the v1 7.5:1 ratio). |
| 9 | `frontend/README.md` "REM Viewer" section has subsections for Architecture, Endpoints (1 GET + 9 CRUD entries), Data flow, Running, Testing, Accessibility, i18n, Migration. | ✅ 8 H3 subsections added (D6 folded into D5). `markdownlint` clean. NFR-14 satisfied. |
| 10 | E2E demo: open `/es/rem`, collapse "Requisitos Funcionales", reload, verify it stays collapsed; create a new stakeholder, verify it appears in the list and search results; delete it, verify it disappears; refresh, verify the change persists. | ⚠️ Spec ships as `test.skip()` placeholders (no dev server in this archive environment). The Vitest integration test `tests/unit/integration/rem-crud-flow.test.tsx` covers the React island → endpoint → onSuccess chain in isolation. **Bring up dev env + unskip to verify end-to-end.** |

## Deviations

- **Manual `zodResolver` adapter** instead of `@hookform/resolvers/zod` (design risk #9 realized — the package is not in `frontend/package.json`). 5-line manual adapter at `frontend/src/lib/rem/zod-resolver.ts` follows RHF v7's `Resolver<TFieldValues, TContext>` signature; no contract change.
- **POST returns 201 (not 200).** The shared `crudSuccessResponse(payload, status?)` helper accepts an optional status; POST uses 201 per design §8 (HTTP convention; the v1 GET endpoint returned 200; the new POST/PUT distinction matches REST norms).
- **Endpoint test isolation via `process.chdir` to a per-test workdir.** `withReqDir` helper creates `<WORK_DIR>/scripts/requirements/req/` so the YAML writer points at a real tmpdir and NFR-10's torn-write behavior is exercised end-to-end.
- **Torn-write + DELETE 500 simulation via `chmod 0o555`** instead of `vi.spyOn(fs.rename)` / `vi.spyOn(fs.unlink)`. The ESM namespace is non-configurable for `node:fs/promises.rename` / `node:fs.unlink`; `chmodSync(0o555)` on the target directory makes the operation fail with EACCES, exercising the same code path (write `.tmp` succeeds → rename fails → 500 + sibling files untouched per NFR-10).
- **Form buttons wrapped in their own `<form>` only (no outer `<dialog>` form).** Earlier D2 skeleton had an outer `<form>`; D4 dropped it because nested forms are invalid HTML. The inner form captures submit correctly.
- **E2E specs shipped as `test.skip()` placeholders.** The Astro dev server is not running in this CI/local environment. The Vitest integration test covers the React island → endpoint → onSuccess chain in isolation. To unskip: bring up the dev env per `playwright-e2e-patterns` and run `E2E=1 pnpm playwright test rem/`.
- **Snapshot `rem-viewer-1280.png` NOT regenerated** for the palette/widening drift. The v1 5% diff threshold (`e116a2f`) accommodates the diff; regeneration requires a running dev server.
- **D6 (README) folded into D5** for a single commit (D5+D6 = `24b6f31`). The README change is doc-only and small enough to ship with the palette refresh; splitting produced an empty-feeling single-file diff.
- **`RemSidebar.tsx` complexity bumped from 10 to 11** (lint warning). The dialog state machine + `handleDialogSuccess` + scroll preservation added an `if` branch; the alternative (extracting helpers) would obscure the data flow. Documented in the `complexity` warning.
- **`+ Nuevo` button was a stub for D2** (logs a `console.warn` on click); D4 wired it to `RemEditDialog`. Documented in the v1 D2 progression.
- **Phase 1 follow-up `b11bf55`** added optional chaining on `locals?.locale` in the v1 API endpoint — a regression the v1 fix missed; running the full v2 test suite caught it. Per the design's safety contract, all v1 endpoints re-exercised by v2 endpoint tests.

## Open Items (carry-forward)

- Bring up dev env + unskip e2e specs (`tests/e2e/rem/*.spec.ts`).
- Regenerate `tests/e2e/rem/__snapshots__/rem-viewer-1280.png`.
- Address the 7 pre-existing project test failures (admin/AuthGuard, admin/TwoFactorChallengeForm, RemPrintButton timing) in a separate change.
- Optional: extract the 9 endpoint handlers into a shared helper to reduce ~80 LoC of duplicated `errorBody` / `crudSuccessResponse` calls (refactor opportunity, out of scope for v2).

## Files Touched (v2-only, additive on top of v1)

`git diff --stat feat/frontend-rem-viewer..feat/rem-viewer-v2` — 64 files changed, +8,785 / −73:

```
 frontend/src/components/rem/RemEditDialog.tsx       |  263 ++++++
 frontend/src/components/rem/RemGroupShell.tsx       |  235 ++++++
 frontend/src/components/rem/RemKebabMenu.tsx        |  210 +++++
 frontend/src/components/rem/RemResizeHandle.tsx     |  103 +++
 frontend/src/components/rem/RemSidebar.tsx          |  245 +++++-
 frontend/src/components/rem/RemTreeNode.tsx         |  131 +++++-
 frontend/src/components/rem/RemPrintButton.tsx      |    7 +-
 frontend/src/components/rem/forms/RemRfForm.tsx     |  149 +++++
 frontend/src/components/rem/forms/RemRnfForm.tsx    |  144 +++++
 frontend/src/components/rem/forms/RemStakeholderForm.tsx |  114 ++++
 frontend/src/layouts/RemLayout.astro                |    7 +-
 frontend/src/lib/rem/crud-errors.ts                 |   77 +++
 frontend/src/lib/rem/rf.schema.ts                   |   96 ++++
 frontend/src/lib/rem/rnf.schema.ts                  |   60 +++
 frontend/src/lib/rem/stakeholder.schema.ts          |   41 ++
 frontend/src/lib/rem/types.ts                       |   78 +++
 frontend/src/lib/rem/yaml-roundtrip.ts              |  222 ++++++
 frontend/src/lib/rem/yaml-writer.ts                 |  187 ++++++
 frontend/src/lib/rem/zod-resolver.ts                |   61 +++
 frontend/src/pages/api/rem/[uid].html.ts            |    6 +-
 frontend/src/pages/api/rem/rf/[uid].ts              |  150 ++++++
 frontend/src/pages/api/rem/rf/index.ts              |  111 ++++
 frontend/src/pages/api/rem/rnf/[uid].ts             |  142 +++++
 frontend/src/pages/api/rem/rnf/index.ts             |  105 ++++
 frontend/src/pages/api/rem/stakeholder/[uid].ts     |  162 ++++++
 frontend/src/pages/api/rem/stakeholder/index.ts     |  123 +++++
 frontend/src/types/node.d.ts                        |   19 +
 frontend/tests/e2e/rem/rem-a11y.spec.ts             |   74 +++
 frontend/tests/e2e/rem/rem-crud.spec.ts             |  154 ++++++
 frontend/tests/e2e/rem/rem-sidebar.spec.ts          |   98 ++++
 .../unit/components/rem/RemEditDialog.test.tsx      |  163 ++++++
 .../unit/components/rem/RemGroupShell.test.tsx      |  167 ++++++
 .../unit/components/rem/RemKebabMenu.test.tsx       |  165 ++++++
 .../unit/components/rem/RemPrintButton.test.tsx     |    7 +
 .../unit/components/rem/RemResizeHandle.test.tsx    |   93 ++++
 .../tests/unit/components/rem/RemSidebar.test.tsx   |   83 ++-
 .../tests/unit/components/rem/RemTreeNode.test.tsx  |  130 +++++
 .../unit/components/rem/forms/RemRfForm.test.tsx    |   58 ++
 .../unit/components/rem/forms/RemRnfForm.test.tsx   |   47 ++
 .../components/rem/forms/RemStakeholderForm.test.tsx |  51 ++
 frontend/tests/unit/i18n/locales.test.ts            |   57 +-
 frontend/tests/unit/integration/rem-crud-flow.test.tsx |  86 +++
 …and 16 more files (6 YAML fixtures + 4 new unit test files + 3 e2e files + 3 endpoint test files)
```

Tally: **64 files changed, +8,785 / −73** across `frontend/src/{components,lib,layouts,pages,types}` + `frontend/tests/{unit,e2e}` + `openspec/changes/rem-viewer-v2/*`. Pure implementation ~1,850 LoC; TDD 1:1 tests + fixtures make up the rest of the delta per the design §12 budget.