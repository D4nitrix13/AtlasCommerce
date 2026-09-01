# Proposal: rem-viewer-v2

## 1. Intent

The v1 viewer (`openspec/changes/rem-viewer/`, archived 2026-09-01, PASS
with 65 tests) is functionally complete: tree, search, detail panel,
print-to-PDF, es/en i18n, WCAG 2.2 AA. But the user opened `/es/rem` in
dev and the sidebar UX doesn't match the professor's REM 1.2.2 reference:

- Stakeholders appear flat at the top of the sidebar (Daniel, Andy,
  Jeremy on individual lines) instead of grouped under a collapsible
  "Stakeholders" header.
- RF / RNF are not grouped under their own collapsible top-level headers.
- No CRUD — there is no way to create, edit, or delete a requirement or
  stakeholder from the UI.
- Sidebar has fixed width — no resize handle.
- Detail panel container is `max-w-3xl` — leaves a lot of empty space
  on widescreens.
- Colors look generic (`slate-50`, `slate-200` borders).

v2 restores the v1.2.2 mental model (collapsible top-level groups →
collapsible sub-groups → leaves), adds full CRUD against the existing
YAML data (no backend changes), widens the detail panel, refreshes the
palette around the project's universal auth accent, and ships a
detailed README. **Additive on top of v1** — v1 paths/components are
preserved where possible.

## 2. Background

### v1 — what works (all PASS, per `archive-report.md`)

| # | What | Evidence |
|---|------|----------|
| 1 | Tree (stakeholders flat, RF by `module`, RNF by `category`) | 12 REQs + 8 NFRs; 64 unit + 12 e2e tests |
| 2 | Detail panel with `PD` badge + red `?` marker | REQ-05, REQ-11 |
| 3 | Print-to-PDF (single + full report) | REQ-06, REQ-07; `rem-print.css` F3-locked |
| 4 | es/en i18n (45 keys under `rem.*`) | REQ-08, `i18n/locales.test.ts` parity |
| 5 | URL `?id=<uid>` deep links | REQ-12 |
| 6 | WCAG 2.2 AA (landmarks, focus ring, contrast) | REQ-11 |

### v1 — issues fixed in batch 4 (`a20a6bd`)

| # | Issue | Resolution |
|---|-------|------------|
| V1 | React island threw on hydration (`t is not a function`) | `useTranslations()` hook instead of `t` prop |
| V2 | Tailwind not loaded on `/[locale]/rem/` in dev | `RemLayout.astro` imports `styles/global.css` |
| V3 | `RemPrintButton` `window.print()` timing flake | `setTimeout(0, ...)` deferral |

### v2 — UX gaps the user called out (tied to each scope item)

| # | User callout (Rioplatense) | v2 deliverable |
|---|----------------------------|-----------------|
| 1 | "stakeholders flat, not under a collapsible header" | §4: 3 collapsible top-level groups |
| 2 | "RF/RNF should also have collapsible groups + items inside categorized" | §4: nested groups for RF (by `module`) and RNF (by `category`) |
| 3 | "no CRUD — add create / edit / delete" | §4: CRUD UI + 9 new endpoints |
| 4 | "divs narrower than they could be on big screens" | §4: detail panel `max-w-3xl` → `max-w-6xl` |
| 5 | "improve the colors" | §4: palette anchored on `#2D9D8E` |
| 6 | "detailed README documenting new endpoints" | §4: README section with subsections |

## 3. Scope

### In scope

1. **Sidebar restructure** — 3 COLLAPSIBLE top-level groups
   (`Stakeholders` / `Requisitos Funcionales` / `Requisitos No
   Funcionales`), each containing nested collapsibles (stakeholders
   flat; RF grouped by `module`; RNF grouped by `category`). Collapse
   state persisted in `localStorage`.
2. **Sidebar resize handle** — draggable divider on the right edge of
   `<nav class="rem-shell__nav">`. Width clamped 240–480px. Persisted
   in `localStorage`.
3. **CRUD UI** — kebab menu (⋮) per leaf: `Editar` / `Eliminar`
   (with confirm dialog). `+ Nuevo` button at the top of each group
   opens an empty form. Forms use `react-hook-form` + `zod` (already in
   `frontend/package.json:45,47`).
4. **CRUD endpoints** — 9 new Astro endpoints under
   `pages/api/rem/`:
   - `POST /api/rem/stakeholder` — create
   - `PUT  /api/rem/stakeholder/[uid]` — update
   - `DELETE /api/rem/stakeholder/[uid]` — delete
   - `POST /api/rem/rf` / `PUT /api/rem/rf/[uid]` / `DELETE /api/rem/rf/[uid]`
   - `POST /api/rem/rnf` / `PUT /api/rem/rnf/[uid]` / `DELETE /api/rem/rnf/[uid]`
   - All read/write via `node:fs/promises` + the hand-rolled YAML
     parser extended for write-back (currently read-only in `loader.ts`).
5. **Detail panel widened** — `max-w-3xl` → `max-w-6xl`.
6. **Palette refresh** — anchor `#2D9D8E` (the project's universal
   auth accent per `astro-themes-and-palette` skill + `lib/auth-accent.ts:25`)
   for selected-item highlight; border-tint `slate-200` → `slate-300`;
   PD badge stays at `#fef3c7` / `#92400e` (7.5:1 contrast verified in
   v1, REQ-11#3).
7. **Detailed README** — extend `frontend/README.md` "REM Viewer"
   section with subsections: Architecture / Endpoints (1 GET + 9 CRUD) /
   Data flow / Running / Testing / Accessibility / i18n / Migration
   (`migrate_to_doorstop.py`).

### Out of scope

- No backend (`backend/**`) changes — frontend-only.
- No new npm packages (already covered: `react-hook-form` 7.80, `zod`
  4.4 in `package.json`).
- No real-time collaboration, comments, annotations.
- No migration of Doorstop → real DB.
- No auth/RBAC — page stays public (REQ-08#1 from v1 carries over).
- No rem-viewer v1 refactor — additive; v1 paths/components preserved
  where possible.
- No print-to-PDF changes (v1 REQ-06/07 already pass).
- No `scripts/requirements/req/*.yml` hand-edits during v2 — only via
  the new endpoints OR `migrate_to_doorstop.py`.
- No new e2e tests beyond what the new REQs/NFRs mandate.

## 4. Approach

### Sidebar restructure (`RemSidebar.tsx` + new `RemGroupShell.tsx`)

- New wrapper component `RemGroupShell.tsx` renders 3 `<details>`-style
  collapsible groups (Stakeholders / RF / RNF). `<details>/<summary>`
  is native, accessible, zero-JS for state.
- Collapse state survives reload via `localStorage.setItem('rem-sidebar.<group>', 'open' | 'closed')` on each toggle. Read once on mount.
- The resize handle is a 6px-wide draggable divider inside
  `<nav class="rem-shell__nav">`. Pointer events on `mousedown` set a
  capture listener; `mousemove` updates `--rem-sidebar-width` CSS
  variable; `mouseup` persists to `localStorage`. Clamp 240–480px.
  Below `lg` breakpoint (1024px) the handle is hidden and width falls
  back to fixed.

### CRUD UI (`RemEditDialog.tsx` + Zod schemas)

- New component `RemEditDialog.tsx` — modal dialog used for both
  create and edit. Mode controlled by a `mode: 'create' | 'edit'` prop.
- 3 Zod schemas in `lib/rem/{rf,rnf,stakeholder}.schema.ts`. Each
  derives from the corresponding `types.ts` shape + Zod refinements
  (e.g. `code` matches `/^RF-[A-Z]+-\d{3}$/`, `priority` ∈ `H|M|L`).
- Form uses `react-hook-form`'s `useForm({ resolver: zodResolver(schema) })`.
- On submit: `POST`/`PUT` to the endpoint; on 2xx, call
  `clearLoaderCache()` then `fetch('/api/rem/<uid>.html')` to swap the
  detail panel (`fetch + innerHTML` pattern already in `RemSidebar.tsx:235`).
- Delete shows a native `confirm()` first (no modal dependency); on 204,
  clear cache and re-fetch the first item from the dataset.

### CRUD endpoints + YAML write-back

- All endpoints are Astro `.ts` files under `pages/api/rem/`.
- Each endpoint:
  - Validates the body against the matching Zod schema → 400 on failure.
  - Calls `loadAllRequirements()` to read current state (caches for
    ≤ 5 min, so post-write we call `clearLoaderCache()` to guarantee
    the next read sees the new state).
  - Reads the target YAML file via `readFile`, mutates the in-memory
    parsed object (or for create: picks the next free UID slot), and
    writes back via `writeFile` using the **same single-line shape as
    `migrate_to_doorstop.py:130`** (key: `"value"` / `key: []` /
    indented list items).
  - Returns 201 (POST), 200 (PUT), 204 (DELETE); 404 for unknown UID;
    500 for any YAML write error.
- Write-back uses `node:fs/promises.{readFile, writeFile, rename}` so
  the write is atomic (`writeFile` to a temp file then `rename`).

### Palette refresh

- Replace `bg-slate-50` body bg → `bg-[#f8fafc]` (still very light, but
  with a hint of the teal family per `astro-themes-and-palette` skill).
- Selected leaf in tree: `bg-[#2D9D8E]/10` with `text-[#1E6F62]` (the
  hover variant of the auth accent) instead of v1's
  `aria-current="true"` only — adds visible affordance.
- Borders: `border-slate-200` → `border-slate-300` on cards and
  groups. PD badge stays at `bg-[#fef3c7] text-[#92400e]` (v1
  contrast 7.5:1, REQ-11#3).

### README

- Append to existing `frontend/README.md` under the "REM Viewer"
  heading. New subsections: **Architecture** (file tree under
  `frontend/src/{lib,components,pages,styles,i18n}/rem`),
  **Endpoints** (1 GET `/api/rem/[uid].html` + 9 CRUD),
  **Data flow** (SSR read → 5-min cache → render → CRUD write-back),
  **Running** (`pnpm dev` → `/es/rem`), **Testing** (vitest +
  playwright commands), **Accessibility** (landmarks, contrast, AA),
  **i18n** (45 → ~50 `rem.*` keys with the new CRUD labels in both
  es + en), **Migration** (`migrate_to_doorstop.py`).

## 5. Capabilities

> Contract between this proposal and the sdd-spec phase. The sdd-spec
> agent reads this to know exactly which spec files to create or update.

### New Capabilities

- `rem-crud` — write-side surface: 9 Astro endpoints (`POST/PUT/DELETE`
  per item type), 3 Zod schemas, atomic YAML write-back, loader cache
  invalidation, error contracts (400/404/500).

### Modified Capabilities

- `rem-viewer` — sidebar collapsible groups (3 top-level + nested),
  resize handle + `localStorage` persistence, detail panel
  `max-w-3xl` → `max-w-6xl`, palette anchored on `#2D9D8E`. v1's 12
  REQs + 8 NFRs remain valid; new REQs add the new behaviors.

## 6. Decision Points

### DP1 — CRUD persistence backend

- **A (recommended):** write back to the existing YAML files via the
  hand-rolled YAML parser. Matches v1's commitment to YAML as source
  of truth; no new infra; academic-deliverable scope.
- **B:** parallel `state.json` and stop reading YAML. Adds divergence.
- **C:** require a backend Laravel API. Out of scope per §3 "No
  backend changes".

**Recommendation: A.**

### DP2 — Sidebar resize persistence

- **A (recommended):** `localStorage`. Per-browser, no server, simple.
- **B:** URL query param (`?w=320`). Per-tab, shareable. Adds URL
  noise and breaks REQ-12's deep-link contract.
- **C:** no persistence (resets on reload). Loses the v2 acceptance
  criterion.

**Recommendation: A** for solo-student academic use.

### DP3 — Form library

- **A (recommended):** `react-hook-form` + `Zod` (the project's existing
  pair per `frontend/package.json:45,47`).
- **B:** native `<form>` + manual validation. Smaller bundle but
  worse DX and no `mode: 'onBlur'` ergonomics.
- **C:** new third-party (`react-final-form`, `formik`). Forbidden by
  §3 "No new npm packages".

**Recommendation: A.**

## 7. Risks

| # | Risk | Likelihood | Mitigation |
|---|------|------------|------------|
| 1 | Deadline pressure (academic deadline, v1's own window). | High | Scope cuts: skip edit/delete per-item icons if time tight; bare CRUD via top-of-group menu is acceptable. |
| 2 | CRUD write-back corrupts YAML if parser sees an edge case. | Medium | Extend parser incrementally; add round-trip test (read → write → read identical) for every item type. |
| 3 | Sidebar resize handle collides with scroll on small screens. | Low | Disable handle below the `lg` (1024px) breakpoint; fall back to fixed width. |
| 4 | `localStorage` state desyncs after multi-tab CRUD. | Low | Listen to `storage` event and refetch on cross-tab writes; v1's loader cache is 5-min TTL so worst-case UX is a stale read until next reload. |
| 5 | Palette refresh changes Tailwind classes touched by v1 e2e snapshot (`rem-viewer-1280.png`). | Medium | Regenerate baseline in v2 commit; 5 % diff threshold from `e116a2f` allows visual drift. |
| 6 | New endpoints introduce security gaps (no auth on write). | Medium | Document academic-only constraint in `frontend/README.md`; recommend separate auth change for any production use. |
| 7 | Cascading scope expansion: removing/widening v1 components may force follow-on touches. | Low | Document each v1 file touch as a "Deviation" in `apply-progress.md`, same protocol as v1 batch 4 (`a20a6bd`). |
| 8 | `frontend/AGENTS.md` size budget (max 400/page, 250/component) violated by new dialog. | Low | Split `RemEditDialog.tsx` into `RemEditDialog.{tsx,Field.tsx,Confirm.tsx}` if it crosses 250 LoC. |

## 8. Dependencies

- v1 viewer at `feat/frontend-rem-viewer` (merged into this branch's
  history per `archive-report.md`).
- `react-hook-form@^7.80.0` + `zod@^4.4.3` — already in
  `frontend/package.json:45,47` (no new install).
- `lib/rem/loader.ts` `clearLoaderCache()` — exported per
  `loader.ts:72` (verified).
- `lib/rem/types.ts` `Requirement` / `Stakeholder` — read-only contract;
  no type changes required for CRUD.
- Hand-rolled YAML parser in `loader.ts:111-209` — must be extended
  for write-back. Same file; no new package.

## 9. Acceptance Criteria

1. Sidebar shows 3 `<details>` top-level groups (Stakeholders / RF /
   RNF), each collapsible; collapse state survives reload via
   `localStorage`.
2. RF group contains nested collapsibles per `module`; RNF per
   `category`. Stakeholders flat.
3. Sidebar has a draggable resize handle; width clamped 240–480px;
   persisted in `localStorage`; disabled below the `lg` breakpoint.
4. Each leaf has a kebab menu (⋮) with `Editar` / `Eliminar` actions;
   `Eliminar` shows a native confirm dialog.
5. A `+ Nuevo` button at the top of each group opens an empty
   `RemEditDialog` for that item type.
6. All 9 CRUD endpoints return 201 (POST) / 200 (PUT) / 204 (DELETE)
   on success, 400 on Zod validation error, 404 on unknown UID, 500
   on YAML write error. Round-trip test: read → write → read
   byte-identical for every item type.
7. Detail panel container `max-w-3xl` → `max-w-6xl`.
8. Palette: selected leaf uses `bg-[#2D9D8E]/10` + `text-[#1E6F62]`;
   borders `border-slate-300`; PD badge contrast still ≥ 4.5:1.
9. `frontend/README.md` "REM Viewer" section has subsections for
   Architecture, Endpoints (1 GET + 9 CRUD entries), Data flow,
   Running, Testing, Accessibility, i18n, Migration.
10. E2E demo: open `/es/rem`, collapse "Requisitos Funcionales",
    reload, verify it stays collapsed; create a new stakeholder,
    verify it appears in the list and search results; delete it,
    verify it disappears; refresh, verify the change persists.

## 10. Open Questions

1. **DP1 — write to YAML or parallel `state.json`?** Recommendation: YAML
   (matches v1). Block on user input only if the user wants to revisit.
2. **What locales should the form labels support?** v1 has es + en; the
   v2 forms ship es + en. Block only if a third locale is needed.
3. **Should `Eliminar` be soft-delete (`deleted_at` field) or hard-delete?**
   Hard-delete matches Doorstop's model; team can hand-edit YAML to
   undelete. Block on user input only if academic auditability requires
   soft-delete.
4. **Auto-select newly-created item?** UX nicety — after `POST` returns
   201, do we auto-select the new item in the sidebar? Recommendation:
   yes, to make the success visible. Block on user input only if the
   team wants a different default.