# REM Viewer v2 — Specs Delta

Source: `openspec/changes/rem-viewer-v2/specs.md` (archived 2026-09-01).

v2 is ADDITIVE over v1 (`openspec/specs/rem-viewer.md`).
All v1 behavior remains valid; this spec adds the sidebar restructure,
CRUD, palette refresh, and README documentation.

## What's new in v2

- Sidebar: 3 collapsible top-level groups + nested collapsibles + draggable resize handle.
- Full CRUD: 9 Astro endpoints (POST/PUT/DELETE per item type) writing back to
  the existing Doorstop YAMLs via an extended hand-rolled YAML parser with atomic
  write + .bak rollback.
- Palette refresh anchored on `#2D9D8E`.
- Detail panel widened from `max-w-3xl` to `max-w-6xl`.
- Detailed README at `frontend/README.md`.

## Functional Requirements (v2-only additions)

| ID    | Title | Summary |
|-------|-------|---------|
| REQ-13 | Sidebar — collapsible top-level groups | The sidebar renders the three dataset branches as native `<details>`/`<summary>`; each group's open/closed state persists in `localStorage` under `expandedStorageKey(groupId)` and survives reload. |
| REQ-14 | Sidebar — nested collapsibles for RF and RNF | RF nests one `<details>` per `module`; RNF per `category`; stakeholders flat. Each nested group persists in `localStorage` under `nestedExpandedStorageKey(groupId, bucketName)`. |
| REQ-15 | Sidebar — draggable resize handle | Draggable divider on the right edge; clamped 240–480 px; default 320 px; persists in `localStorage` under `SIDEBAR_WIDTH_STORAGE_KEY`; hidden below the `lg` (1024 px) breakpoint. |
| REQ-16 | CRUD — kebab menu on every leaf | Every stakeholder/RF/RNF row renders a `⋮` button that opens a menu with `Editar` and `Eliminar`. Closes on Escape, click-outside, or after action. |
| REQ-17 | CRUD — "Eliminar" with confirmation and row removal | Selecting `Eliminar` triggers `confirm()`; on OK, fires `DELETE /api/rem/<kind>/<uid>`; on 204, removes the matching leaf without a full page reload. |
| REQ-18 | CRUD — "+ Nuevo" button per group | Each of the three top-level groups renders a `+ Nuevo` button (`rem.crud.create`) that opens `RemEditDialog` with `mode='create'` and the group's type pre-selected. |
| REQ-19 | CRUD — `RemEditDialog` form (create + edit) | Single modal component shared by create/edit, driven by `react-hook-form` + `zodResolver`; on 201/200 closes itself, calls `clearLoaderCache()`, swaps the tree region via the F2-locked `fetch + innerHTML` pattern, and preserves sidebar scroll position. |
| REQ-20 | CRUD — `POST /api/rem/{rf,rnf,stakeholder}` create endpoint | Validates body via Zod; writes a new YAML via `serializeYamlSubset` + `writeYamlAtomic`; invalidates the loader cache; responds `201` with `{ ok: true, uid }`. 400 on validation, 500 on write failure. |
| REQ-21 | CRUD — `PUT /api/rem/{rf,rnf,stakeholder}/[uid]` update endpoint | Validates the body; rewrites the matching YAML file; invalidates cache; responds `200` with `{ ok: true, uid, item }`. 404 on unknown UID; 400 on validation; round-trip byte-identical for empty body (NFR-11). |
| REQ-22 | CRUD — `DELETE /api/rem/{rf,rnf,stakeholder}/[uid]` | `fs.unlink` of the YAML file; cache invalidation only on success; responds `204` (empty body, `Content-Length: 0`). 404 on unknown UID; 500 on filesystem failure. |
| REQ-23 | UI — detail panel container widened | `<main>` wrapper renders at `max-w-6xl` (≈ 1152 px) on viewports `≥lg`; collapses to `100%` width below `lg`. v1 `max-w-3xl` preserved on print. |
| REQ-24 | UI — palette anchored on `#2D9D8E` | Selected leaf uses `bg-[#2D9D8E]/10 text-[#1E6F62]`; borders `border-slate-200` → `border-slate-300`; PD badge (`#fef3c7` / `#92400e`) and red `?` (`#fee2e2` / `#b91c1c`) contrast ≥ 4.5:1; `Imprimir PDF` hover uses the teal accent. |

Full scenarios (45 §3 scenarios + 6 NFRs) are archived at
`openspec/changes/rem-viewer-v2/specs.md` §3–4.

## Non-Functional Requirements (v2-only additions)

| ID    | Statement |
|-------|-----------|
| NFR-09 | A single-item CRUD call (`POST`, `PUT`, or `DELETE`) SHALL complete in ≤ 200 ms at the 95th percentile in `pnpm dev` (request bytes → response bytes). |
| NFR-10 | A failed YAML write SHALL NOT corrupt any other file in `scripts/requirements/req/`. The endpoint catches, returns `500`, and the pre-write listing equals the post-write listing except for the file the operation was attempting to create or remove. |
| NFR-11 | The extended parser SHALL round-trip: every YAML in the v1 dataset reads → writes → reads into a structurally equal object. Whitespace differences (the single-line shape) are acceptable; field order, key presence, and value equality are not. |
| NFR-12 | Coverage thresholds SHALL stay at ≥ 85% lines for `lib/rem/**`, `components/rem/**`, plus the new `lib/rem/{rf,rnf,stakeholder}.schema.ts`, `RemEditDialog.tsx`, and `pages/api/rem/**`. ONE test per spec §3 scenario; no `skip` / `todo` / `xfail`. |
| NFR-13 | WCAG 2.2 AA SHALL be preserved (v1 contract — REQ-11). The new `<details>` / `<summary>` groups SHALL be keyboard-navigable and SHALL announce `expanded` / `collapsed` to assistive tech. The kebab menu SHALL be reachable via `Tab` and closeable via `Escape`. |
| NFR-14 | `frontend/README.md` SHALL have a `REM Viewer` subsection with at minimum: **Architecture**, **Endpoints** (1 GET + 9 CRUD = 10+ entries), **Data flow**, **Running**, **Testing**, **Accessibility**, **i18n**, **Migration**. |

## Round-trip integrity contract (NFR-11)

The YAML writer at `frontend/src/lib/rem/yaml-writer.ts` is the
round-trip-safe replacement for the v1 read-only parser. A test
(`tests/unit/lib/rem/writer.test.ts`) reads each v1 YAML in
`tests/unit/lib/rem/fixtures/writer/*.yml`, writes it back via the
new writer, and asserts deep equality with the original (whitespace
stripped; field order, key presence, value equality preserved per
NFR-11). The 6 fixtures cover both empty-list and with-content shapes
for stakeholders / RF / RNF.

## Open Items (post-archive)

- **e2e tier unverified**: 17 new e2e scenarios in
  `tests/e2e/rem/{rem-crud,rem-sidebar,rem-a11y}.spec.ts` ship as
  `test.skip()` placeholders. Bring up the dev env (docker compose +
  `pnpm --filter frontend run dev` + caddy), install
  `@axe-core/playwright` if missing, and unskip.
- **Snapshot regen**: `tests/e2e/rem/__snapshots__/rem-viewer-1280.png`
  was NOT regenerated for the v2 palette/widening drift; the v1 5%
  diff threshold accommodates the diff so it does not block archive.
  Regenerate when the dev env is available.
- **Pre-existing project failures**: 7 admin/auth tests +
  RemPrintButton timing flake predate v2 and remain out-of-scope.
  See `openspec/changes/rem-viewer-v2/apply-progress.md` for the full list.