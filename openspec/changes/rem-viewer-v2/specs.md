# Spec — `rem-viewer-v2`

## 1. Overview

Additive delta over the archived v1 viewer (`openspec/changes/rem-viewer/`,
sealed 2026-09-01, 65 tests PASS): restructures the sidebar into 3
collapsible top-level groups with nested collapsibles + a draggable
resize handle, ships full CRUD against the existing Doorstop YAMLs
(no backend, no new packages), widens the detail panel, and refreshes
the palette around `#2D9D8E`. All v1 behavior covered by REQ-01..12
and NFR-01..08 remains valid; this spec adds **12 new REQs (REQ-13..24)**
and **6 new NFRs (NFR-09..14)** with **45 new scenarios** mappable 1:1
to Vitest or Playwright test methods.

## 2. Data Model additions

The base `Requirement` / `Stakeholder` shapes from `lib/rem/types.ts`
are reused as-is. v2 adds the following types in the same file (or
imported by it):

```ts
// frontend/src/lib/rem/types.ts — v2 additions

/**
 * Identifier of a top-level sidebar group. Each maps to one branch of
 * the v1 `RemTree` (stakeholders / functional / nonFunctional). Used
 * as the suffix of the localStorage key that persists the collapse
 * state.
 */
export type RemGroupId = 'stakeholders' | 'rf' | 'rnf';

/**
 * Stable namespace prefix for every localStorage key v2 reads or
 * writes on the sidebar tree. Keeps the keys namespaced so a future
 * tenant accent / theme change does not collide.
 */
export const REM_SIDEBAR_STORAGE_PREFIX = 'rem.viewer.' as const;

/** Resolves to e.g. `'rem.viewer.expanded.stakeholders'`. */
export function expandedStorageKey(groupId: RemGroupId): string {
  return `${REM_SIDEBAR_STORAGE_PREFIX}expanded.${groupId}`;
}

/** Resolves to e.g. `'rem.viewer.expanded.rf.Autenticación'`. */
export function nestedExpandedStorageKey(
  groupId: 'rf' | 'rnf',
  bucketName: string,
): string {
  return `${REM_SIDEBAR_STORAGE_PREFIX}expanded.${groupId}.${bucketName}`;
}

/** localStorage key for the user-persisted sidebar width. */
export const SIDEBAR_WIDTH_STORAGE_KEY =
  `${REM_SIDEBAR_STORAGE_PREFIX}sidebar.width` as const;
```

Each YAML item type gets a paired `RemCreateInput<T>` / `RemUpdateInput<T>`
shape + Zod schema in `lib/rem/{rf,rnf,stakeholder}.schema.ts`:

```ts
/** All fields except `uid` (server-assigned). */
export type RemCreateInput<T> = Omit<T, 'uid'>;

/** All fields optional except `uid` (URL path). */
export type RemUpdateInput<T> = Partial<Omit<T, 'uid'>> & { uid: T['uid'] };

/** Discriminated union the new Astro endpoints return to the React island. */
export type RemWriteResult =
  | { ok: true; uid: string }
  | { ok: false; error: RemParseError };
```

The schemas live next to the types so `loader.ts` and the new
`write-back` parser can both import the same source of truth. The
hand-rolled YAML parser at `loader.ts:111-209` is extended for
write-back: a new `serializeYamlSubset(record: Record<string, unknown>): string`
emits the single-line `key: "value"` / `key: []` / indented-list shape
that `migrate_to_doorstop.py:130` produces today (round-trip covered by
NFR-11).

## 3. Functional Requirements

### REQ-13: Sidebar — collapsible top-level groups

**Statement.** The sidebar SHALL render the three dataset branches
(`Stakeholders` / `Requisitos Funcionales` / `Requisitos No
Funcionales`) as native `<details>`/`<summary>` elements; each group's
open/closed state SHALL persist in `localStorage` under the key
returned by `expandedStorageKey(groupId)` and SHALL survive a full page
reload.

**Rationale.** v1 stacked the three branches under a single flat tree
without visible grouping. REM 1.2.2 (the reference the professor
provided) groups them. v2 restores that mental model with the
accessibility win that `<details>` is keyboard-navigable and
announced by assistive tech out of the box.

#### Scenarios

| # | Given | When | Then |
|---|-------|------|------|
| 1 | `/es/rem` SSR with the full dataset | the page hydrates | the sidebar contains three `<details data-rem-group-top="<groupId>">` elements (one per branch) with `<summary>` headings `Stakeholders` / `Requisitos Funcionales` / `Requisitos No Funcionales` |
| 2 | all three groups open on mount | user clicks `Requisitos No Funcionales` summary | that group collapses; the two others stay open; `localStorage.getItem('rem.viewer.expanded.rnf')` === `'closed'` |
| 3 | user just collapsed `Requisitos Funcionales` | user reloads the page (F5) | that group is collapsed on first paint; the others are open |
| 4 | `localStorage['rem.viewer.expanded.rf']` === `'closed'` (legacy key, set by a v2 build earlier today) | page mounts | that branch renders collapsed; no console error |
| 5 | `localStorage.getItem('rem.viewer.expanded.stakeholders')` returns `null` (first visit) | page mounts | the stakeholders group defaults to `'open'`; only that branch has no stored state |

#### Acceptance

- Three `<details>` elements visible, each with `data-rem-group-top`.
- One `localStorage.setItem` per group toggle (verified via a spy in
  the Vitest suite — no scroll position change).
- After F5, the same groups in the same states are visible; default is
  all-open.
- Keystroke `Tab` lands on a group summary; `Enter` / `Space` toggles
  it (native `<details>` behavior).

### REQ-14: Sidebar — nested collapsibles for RF and RNF

**Statement.** Inside the `Requisitos Funcionales` group the system
SHALL nest one `<details>` per `module`; inside `Requisitos No
Funcionales` one `<details>` per `category`. The stakeholders group
SHALL stay flat. Each nested group's open/closed state SHALL persist
in `localStorage` under the key returned by
`nestedExpandedStorageKey(groupId, bucketName)` and SHALL survive
reload.

**Rationale.** v1 already grouped RF by `module` and RNF by `category`
in data, but rendered them as inline `<li>` lists with no per-group
toggle. v2 makes each group independently collapsible so the
professor can keep a few "open" and dismiss the rest — matching REM
1.2.2's two-level tree.

#### Scenarios

| # | Given | When | Then |
|---|-------|------|------|
| 1 | `Requisitos Funcionales` is open and contains `Autenticación`, `Carrito`, `Catálogo`, `Checkout` buckets | user clicks `Carrito` summary | only `Carrito` collapses; `localStorage['rem.viewer.expanded.rf.Carrito']` === `'closed'` |
| 2 | `Requisitos No Funcionales` is open | user collapses `Seguridad` then reloads | the `Seguridad` group is collapsed on first paint; `Rendimiento / Fiabilidad` and the rest stay open |
| 3 | `Requisitos Funcionales` contains exactly one `module` named `Autenticación` | page mounts | the bucket `<details data-rem-group-nest="rf.Autenticación">` renders with its items beneath; its summary text is `Autenticación` |

#### Acceptance

- Each RF `<module>` and each RNF `<category>` has its own `<details>`
  with `data-rem-group-nest="<groupId>.<bucket>"`.
- One `localStorage.setItem` per nested toggle; reload preserves state.
- The default for a never-toggled nested group is `open`.

### REQ-15: Sidebar — draggable resize handle

**Statement.** The sidebar SHALL expose a draggable divider on its
right edge that resizes the sidebar width between **240 px and 480 px**;
the default width SHALL be **320 px**. The current width SHALL persist
in `localStorage` under `SIDEBAR_WIDTH_STORAGE_KEY`; the write SHALL
happen on resize end (mouseup / pointerup), not on every pixel of
drag. The handle SHALL be hidden and the width SHALL fall back to
fixed (320 px) below the Tailwind `lg` breakpoint (`1024 px`).

**Rationale.** v1 hard-codes `lg:w-80` (320 px). On a 1280×800 laptop
that wastes ~640 px; on a 13" laptop it leaves the detail cramped.
v2 lets each viewer pick their own size and persists it per browser.

#### Scenarios

| # | Given | When | Then |
|---|-------|------|------|
| 1 | viewport is `1440 px` wide; no stored width | user mounts the page | the sidebar renders at exactly `320 px`; the resize handle is visible |
| 2 | viewport is `1440 px` wide; stored width is `200` | user mounts the page | the sidebar renders at `240 px` (clamped to min); `localStorage` is rewritten to `240` on first render |
| 3 | viewport is `1440 px` wide; stored width is `600` | user mounts the page | the sidebar renders at `480 px` (clamped to max); `localStorage` is rewritten to `480` |
| 4 | viewport is `1440 px` wide; stored width is `360` | user drags the handle from 320 → 380 px and releases | `localStorage['rem.viewer.sidebar.width']` === `'380'`; sidebar is `380 px` wide |
| 5 | viewport is `1023 px` wide (below `lg`) | user mounts the page | the resize handle is not rendered (`hidden lg:block`); sidebar is fixed at `320 px`; no localStorage write occurs |

#### Acceptance

- Resize handle is an element with `data-rem-resize-handle`, visible
  only at `≥lg`.
- Drag updates `--rem-sidebar-width` CSS variable continuously; the
  final value writes once on pointerup.
- Out-of-range stored values clamp to `[240, 480]` and overwrite the
  stored value on the first post-clamp render.
- A non-integer or non-numeric stored value (`'abc'`) is ignored and
  falls back to `320`.

### REQ-16: CRUD — kebab menu on every leaf

**Statement.** Every stakeholder, every RF row, and every RNF row
SHALL render a kebab `⋮` button (icon button) that opens a menu with
exactly two actions: `Editar` and `Eliminar`. The menu SHALL close
on Escape, on click-outside, and after either action.

**Rationale.** v1 has no edit/delete surface — users re-run
`migrate_to_doorstop.py` to change a YAML. v2 exposes the operations
directly so the professor can correct a field during the class
session.

#### Scenarios

| # | Given | When | Then |
|---|-------|------|------|
| 1 | page rendered at `/es/rem`; dataset has 3 stakeholders | user inspects any stakeholder row | the row contains one `button[aria-label="rem.crud.actions"]` (the kebab) with the `⋮` glyph inside |
| 2 | kebab menu is closed | user clicks the kebab on `RF-AUTH-001` | a popover renders with two buttons: `Editar` (testid `rem-crud-edit`) and `Eliminar` (testid `rem-crud-delete`) |
| 3 | kebab menu is open | user presses `Escape` | the menu closes; focus returns to the kebab button |
| 4 | kebab menu is open on `RNF-SEC-010` | user clicks anywhere outside the popover | the menu closes; the row is not selected |

#### Acceptance

- Every leaf has exactly one kebab.
- The kebab is `aria-haspopup="menu"` and `aria-expanded` toggles.
- The "Editar" / "Eliminar" labels come from `rem.crud.edit` and
  `rem.crud.delete`.

### REQ-17: CRUD — "Eliminar" with confirmation and row removal

**Statement.** Selecting `Eliminar` from the kebab menu SHALL trigger
a native `confirm()` dialog (`rem.crud.delete.confirm`). On `OK`,
the system SHALL `DELETE /api/rem/<kind>/<uid>`. On a `204` response
the system SHALL remove the matching leaf from the visible tree
without a page reload. On `Cancel` the system SHALL do nothing.

**Rationale.** Hard-delete matches Doorstop's model and was locked by
user input (the v2 proposal's DP3 → resolution note). Native
`confirm()` avoids a new dialog dependency and is announced by screen
readers.

#### Scenarios

| # | Given | When | Then |
|---|-------|------|------|
| 1 | `RF-AUTH-001` selected; user opens the kebab menu and clicks `Eliminar` | the page intercepts and **stubs** `window.confirm` to return `false` | no `fetch` is issued; the kebab closes; `RF-AUTH-001` is still visible |
| 2 | same as #1 but `window.confirm` returns `true` | the system fires `fetch('DELETE /api/rem/rf/rf_001')` | the request resolves 204; the `RF-AUTH-001` row is removed from the DOM without `window.location.reload`; `clearLoaderCache()` was called (verified via spy) |
| 3 | same as #2 but the request resolves 500 (YAML write error) | the system receives the 500 response | an `Alert` renders with the error code; the row is NOT removed; `window.location` is unchanged |
| 4 | user has just deleted `RF-AUTH-001` | the user navigates to `/es/rem?id=rf_001` | the page renders `RemError` with code `notfound:rf_001` (loader excludes the deleted file per the hard-delete contract) |

#### Acceptance

- `window.confirm` is called exactly once per `Eliminar` click.
- Successful DELETE removes the row in < 100 ms (no full reload).
- A failed DELETE keeps the row in place and surfaces the error
  payload via `rem.crud.delete.error` message.

### REQ-18: CRUD — "+ Nuevo" button per group

**Statement.** Each of the three top-level groups SHALL render a
`+ Nuevo` button (`rem.crud.create`) at the top of its content area.
Clicking the button SHALL open `RemEditDialog` with `mode='create'`
and the dialog's type (`'stakeholder' | 'rf' | 'rnf'`) pre-selected
to the originating group. The button SHALL be `disabled` while the
loader cache is empty (first paint).

**Rationale.** v1 has no creation surface. v2 makes "add a new RF"
a single click from the same group the user is browsing.

#### Scenarios

| # | Given | When | Then |
|---|-------|------|------|
| 1 | page rendered with the full dataset | user inspects `Requisitos Funcionales` | the group header contains one `<button data-rem-crud-create="rf">` whose label is `+ Nuevo` |
| 2 | user clicks the `+ Nuevo` button on `Stakeholders` | the page renders the dialog | the dialog is open with `mode='create'`, `type='stakeholder'`, and the 5 stakeholder fields rendered (name, title, role, organization, notes); the form has no `uid` field; the submit button label is `rem.crud.create.submit` |
| 3 | user clicks `+ Nuevo` on `Requisitos No Funcionales` | the page renders the dialog | the dialog fields are exactly the 8 RNF fields (`code`, `title`, `category`, `priority`, `status`, `rationale`, `fuentes`, `dependencias`, `verifiedBy`); type is `'rnf'` |

#### Acceptance

- One button per group with `data-rem-crud-create="<type>"`.
- The dialog receives the type via props; nothing else varies.
- The button is keyboard-focusable in the existing tab order
  (between the search input and the tree).

### REQ-19: CRUD — `RemEditDialog` form (create + edit)

**Statement.** `RemEditDialog` SHALL be a single modal component
shared by `mode='create' | 'edit'`, driven by `react-hook-form` with a
`zodResolver` from the matching Zod schema in
`lib/rem/{rf,rnf,stakeholder}.schema.ts`. On submit the dialog SHALL
`POST` (create) or `PUT` (edit), and on a `201` / `200` response
SHALL close itself, call `clearLoaderCache()`, fetch the affected
tree region (`fetch + innerHTML` pattern from `RemSidebar.tsx:235`),
and preserve the user's scroll position in the sidebar.

**Rationale.** v1's React island only owns read-side state. v2
extends the same `fetch + innerHTML` pattern the design locked for
detail-panel updates so we avoid loading a parallel React subtree
for the tree (Design F2). The scroll-preservation promise keeps the
UX coherent without a full reload.

#### Scenarios

| # | Given | When | Then |
|---|-------|------|------|
| 1 | dialog open in `mode='create'` for `type='rf'`; user types `code = "RF-NEW-999"` and submits | the system validates | the form rejects the submit and shows a Zod error `rem.form.code.invalid` while the dialog stays open |
| 2 | user fixes the code to `RF-NEW-099` and re-submits | the endpoint returns `201` | the dialog closes; `window.history.replaceState` is not called; `clearLoaderCache()` was called; the new leaf appears in the tree after the next `fetch`; sidebar scroll position is preserved |
| 3 | dialog open in `mode='edit'` for `RF-AUTH-001`; user changes the `title` field and submits | the endpoint returns `200` | the dialog closes; `clearLoaderCache()` was called; the detail panel re-renders via the existing `fetch + innerHTML` path and reflects the new title without page reload |
| 4 | dialog open with `mode='edit'`; user clicks the cancel button (or presses `Escape`) | the page intercepts | no `POST` or `PUT` fires; no `clearLoaderCache()` call; the dialog closes |
| 5 | submit fires but the endpoint returns 500 | the system receives the error | the dialog stays open, the submit button is re-enabled, and an `Alert` surfaces `rem.crud.write.error` with the response body verbatim |

#### Acceptance

- Single component (`RemEditDialog.tsx`), under 250 LoC per
  AGENTS.md §1 (split into `RemEditDialog.{tsx,Field.tsx,Confirm.tsx}`
  if it crosses the budget).
- All Zod errors surface in es + en via `useTranslations().t`.
- The `fetch + innerHTML` swap fires only on a 2xx, never on an
  error.

### REQ-20: CRUD — `POST /api/rem/{rf,rnf,stakeholder}` create endpoint

**Statement.** Each create endpoint SHALL validate the request body
against the corresponding Zod schema, write a new YAML file to
`scripts/requirements/req/` via the extended parser
(`serializer.serializeYamlSubset`), invalidate the loader cache via
`clearLoaderCache()`, and respond **`201 Created`** with
`{ ok: true, uid: <newUid> }`. Validation failure SHALL return
**`400`** with the Zod issues map; loader write failure SHALL return
**`500`** with the underlying error code.

**Rationale.** DP1 was locked by user input to "write to YAML". The
endpoints are the only write path so we can trace every change to a
commit + a YAML diff; the cache invalidation guarantees the next
`loadAllRequirements()` sees the new state.

#### Scenarios

| # | Given | When | Then |
|---|-------|------|------|
| 1 | empty body | client sends `POST /api/rem/stakeholder` with `Content-Type: application/json` and payload `{ "name": "Daniel", "title": "Líder", "role": "developer", "organization": "Panda", "notes": "" }` | the response is `201`; body is `{ "ok": true, "uid": "stakeholder_004" }`; a new YAML file exists at `req/stakeholder_004.yml`; the next `loadAllRequirements()` includes it |
| 2 | body with `priority: "X"` (not in `H \| M \| L`) | client sends the same request | the response is `400` with the Zod issues map; no YAML is written; `clearLoaderCache()` is NOT called |
| 3 | a parsed `serializeYamlSubset` write throws `Error` (fixture simulates ENOSPC) | the endpoint catches | the response is `500` with `{ "ok": false, "error": "<code>" }`; the existing YAMLs on disk are unchanged |
| 4 | the loader cache contains the pre-write dataset (`stakeholder_004` missing) | the request resolves 201 | the cache is invalidated via `clearLoaderCache()` before the response is sent |

#### Acceptance

- All three endpoints share the same `htmlResponse` (201) and
  `errorBody` (400 / 500) helpers used by `pages/api/rem/[uid].html.ts`.
- A successful write invokes `clearLoaderCache()` before `return`.
- No YAML files in `scripts/requirements/req/` are hand-edited during
  the spec (NFR-10 + NFR-11 cover this end-to-end).

### REQ-21: CRUD — `PUT /api/rem/{rf,rnf,stakeholder}/[uid]` update endpoint

**Statement.** Each update endpoint SHALL validate the request body
against the matching Zod schema (every field optional except `uid`
in the URL), rewrite the YAML file matching `[uid]` via
`serializeYamlSubset`, invalidate the loader cache, and respond
**`200 OK`** with `{ ok: true, uid, item }` where `item` is the
post-write `Requirement` or `Stakeholder`. A non-existent `uid`
SHALL return **`404`** with `notfound:<uid>`; a Zod failure SHALL
return **`400`**.

**Rationale.** Symmetric to REQ-20; the round-trip integrity property
(NFR-11) requires that the same parser produces the same YAML the
loader can re-read.

#### Scenarios

| # | Given | When | Then |
|---|-------|------|------|
| 1 | `stakeholder_001.yml` exists; payload is `{ "title": "Owner" }` | client sends `PUT /api/rem/stakeholder/stakeholder_001` | the response is `200`; body `{ "ok": true, "uid": "stakeholder_001", "item": { ... } }`; the file's `title` field is exactly `"Owner"` |
| 2 | `uid` `stakeholder_999` does not exist | client sends the request | the response is `404` with body `{ "ok": false, "error": "notfound:stakeholder_999" }`; no other YAML is modified |
| 3 | payload contains `priority: "X"` | client sends `PUT /api/rem/rf/rf_001` | the response is `400`; the YAML file is unchanged on disk |
| 4 | payload is `{}` (empty body) | client sends `PUT /api/rem/rf/rf_001` | the response is `200`; the YAML is rewritten byte-identical to its pre-write form (NFR-11 round-trip case) |

#### Acceptance

- The endpoint resolves `[uid]` against the loaded dataset (via
  `findByUid` from `[uid].html.ts:57`), not by filename guess.
- A 200 response body contains the full `item` so the React island
  can swap the detail without re-fetching `/api/rem/<uid>.html`.

### REQ-22: CRUD — `DELETE /api/rem/{rf,rnf,stakeholder}/[uid]`

**Statement.** Each delete endpoint SHALL remove the YAML file
matching `[uid]` from `scripts/requirements/req/` via
`node:fs/promises.unlink`, invalidate the loader cache, and respond
**`204 No Content`** with an empty body. A non-existent `uid` SHALL
return **`404`** with `notfound:<uid>`; a filesystem write failure
SHALL return **`500`**.

**Rationale.** Hard-delete was locked by user input. Removing the
file (vs adding `deleted_at` to the YAML) keeps the loader path
simple — `loader.ts` already silently skips unknown UIDs.

#### Scenarios

| # | Given | When | Then |
|---|-------|------|------|
| 1 | `stakeholder_001.yml` exists on disk and in the loader cache | client sends `DELETE /api/rem/stakeholder/stakeholder_001` | the response is `204` with empty body; the file is gone from disk; the next `loadAllRequirements()` does not include `stakeholder_001` |
| 2 | `uid` `stakeholder_999` does not exist | client sends the request | the response is `404` with body `{ "ok": false, "error": "notfound:stakeholder_999" }`; no file is touched |
| 3 | `node:fs.unlink` throws (fixture simulates EACCES) | the endpoint catches | the response is `500` with the underlying error code; the loader cache is NOT invalidated |
| 4 | user navigates to `/es/rem` after the 204 resolved | the page renders | the deleted row no longer appears in the sidebar; the tree renders the remaining items |

#### Acceptance

- 204 body is exactly empty (zero bytes), `Content-Length: 0`.
- `clearLoaderCache()` runs only when the unlink succeeds.

### REQ-23: UI — detail panel container widened

**Statement.** The detail panel container SHALL render at
`max-w-6xl` (≈ 1152 px) on viewports `≥lg`. On viewports `<lg` the
container SHALL collapse to `100%` width with the same horizontal
padding. The existing v1 `max-w-3xl` (≈ 768 px) is preserved on
print (`@media print`).

**Rationale.** v1's `max-w-3xl` left a 512-px empty right gutter on
the 1280×800 screenshot baseline. v2 widens to use that space while
still leaving 64 px of side padding on the screenshot baseline, and
keeps the print surface narrow (most A4 layouts look better at the
narrower width).

#### Scenarios

| # | Given | When | Then |
|---|-------|------|------|
| 1 | viewport is `1280×800`; user opens `/es/rem` | the user inspects the detail panel | the `<main>` wrapper has class `max-w-6xl` and the rendered panel is ≤ 1152 px wide |
| 2 | viewport is `800×600` | the user opens `/es/rem` | the `<main>` wrapper renders at full width; no horizontal scrollbar appears |

#### Acceptance

- Computed width of the `<main>` wrapper on a 1280-px viewport is
  between 1100 and 1180 px (screenshot baseline tolerance).
- Print stylesheet still constrains `.rem-detail` to a narrow column
  via `@media print { .rem-detail { max-width: 100%; } }` so REQ-06
  / REQ-07 (v1) keep their print fidelity.

### REQ-24: UI — palette anchored on `#2D9D8E`

**Statement.** The system SHALL use `#2D9D8E` (the project's
universal auth accent per `astro-themes-and-palette` skill) as the
anchor color for: selected leaf background
(`bg-[#2D9D8E]/10 text-[#1E6F62]`), active form input border, focus
ring (already inherited from v1), and the `Imprimir PDF` hover state.
The v1 `PD` badge (`#fef3c7` / `#92400e`) and red `?`
(`#fee2e2` / `#b91c1c`) contrast ratios SHALL remain ≥ 4.5:1.
Borders on cards and groups SHALL move from `border-slate-200` to
`border-slate-300`.

**Rationale.** v1 used a generic slate palette; v2 ties the viewer
back to the project's documented accent (`lib/auth-accent.ts:25`),
improving visual coherence with auth flows without using
`brand.accentColor` (which differs per tenant).

#### Scenarios

| # | Given | When | Then |
|---|-------|------|------|
| 1 | `RF-AUTH-001` is the selected leaf | the user inspects its `<li>` | computed `background-color` is `rgba(45, 157, 142, 0.10)` (Tailwind `#2D9D8E/10`); computed `color` is `rgb(30, 111, 98)` |
| 2 | the `PD` badge with `bg #fef3c7` and text `#92400e` is rendered | the user computes the contrast ratio | the ratio is ≥ 4.5:1 (WCAG 2.2 AA for normal text) |
| 3 | a card or group container is rendered | the user inspects its computed `border-color` | the value resolves to a Tailwind `slate-300` shade (`rgb(203, 213, 225)` or equivalent) |
| 4 | the user hovers `Imprimir PDF` button | the user inspects computed `background-color` on hover | the value resolves to `#2D9D8E` or its hover variant (`#258777`) |

#### Acceptance

- `RemSidebar.tsx`, `RemTreeNode.tsx`, `RemEditDialog.tsx`, and
  `RemPrintButton.tsx` use the v2 palette tokens; v1 slate-50/200
  greys are replaced except where the print stylesheet needs them.
- All assertion targets above match within a single CI run.

## 4. Non-Functional Requirements

| ID | Statement | Test |
|----|-----------|------|
| NFR-09 | A single-item CRUD call (`POST`, `PUT`, or `DELETE`) SHALL complete in ≤ 200 ms at the 95th percentile in `pnpm dev`. The contract is wall-clock from request bytes received to response bytes sent. | E2E — Vitest `performance.now()` around `fetch` against a mocked node adapter; CI gate |
| NFR-10 | A failed YAML write SHALL NOT corrupt any other file in `scripts/requirements/req/`. The endpoint catches, returns `500`, and the pre-write directory listing equals the post-write directory listing except for the file the operation was attempting to create or remove. | Unit — Vitest fixture with a torn write (`fs.writeFile` mocked to throw after the temp-file is created); assert `rename` was never called |
| NFR-11 | The extended parser SHALL round-trip: every YAML in the v1 dataset reads → writes → reads into a structurally equal object. Whitespace differences (the single-line shape) are acceptable; field order, key presence, and value equality are not. | Unit — Vitest reads the live `req/` folder, writes each YAML via `serializeYamlSubset`, re-reads, asserts deep-equal |
| NFR-12 | Coverage thresholds SHALL stay at ≥ 85% lines for `lib/rem/**`, `components/rem/**`, plus the new `lib/rem/{rf,rnf,stakeholder}.schema.ts`, `RemEditDialog.tsx`, and `pages/api/rem/{rf,rnf,stakeholder}/**`. ONE test per spec §3 scenario; no `skip` / `todo` / `xfail`. | CI step + grep rule on `tests/` |
| NFR-13 | WCAG 2.2 AA SHALL be preserved (v1 contract — REQ-11). The new `<details>` / `<summary>` groups SHALL be keyboard-navigable and SHALL announce `expanded` / `collapsed` to assistive tech (native semantics). The kebab menu SHALL be reachable via `Tab` and closeable via `Escape`. | E2E — Playwright a11y: `axe-core` run against the rendered DOM + `aria-expanded` assertion |
| NFR-14 | `frontend/README.md` SHALL have a `REM Viewer` subsection with at minimum: **Architecture**, **Endpoints** (1 GET + 9 CRUD = 10+ entries), **Data flow**, **Running**, **Testing**, **Accessibility**, **i18n**, **Migration**. | Lint — `markdownlint` passes + grep rule for the 8 required H3 headings |

## 5. Out of Scope

These echo the v2 proposal and add the spec-time additions:

- No backend (`backend/**`) changes — write-back is local to
  `scripts/requirements/req/*.yml`.
- No new npm packages — `react-hook-form@^7.80` and `zod@^4.4` are
  already direct deps (`frontend/package.json:45,47`); `confirm()` is
  native; no new dialog dep.
- No soft-delete — hard-delete only (user input).
- No migration of Doorstop → real database.
- No auth / RBAC on the write endpoints (academic-only; documented
  in `frontend/README.md`).
- No print-to-PDF changes — v1 REQ-06 / REQ-07 are unchanged.
- No real-time collaboration, comments, annotations, or audit log.
- No v1 component refactor — additive: v1 paths and class names are
  preserved where they don't collide with v2.
- No `scripts/requirements/req/*.yml` hand-edits in v2 — every change
  goes through the new endpoints or `migrate_to_doorstop.py`.
- No new e2e tests beyond what REQ-13..24 and NFR-09..14 mandate.
- No new locale — es + en remain the only two; v2 form labels reuse
  the same namespace.
- No cross-tab `storage` event wiring in v2 — worst-case a stale
  read on a different tab, refresh fixes it (documented in
  `frontend/README.md`).

## 6. Open Questions

> Only questions that **block** the next phase. DP1, DP2, DP3, and
> the soft-vs-hard-delete decision from the v2 proposal are now
> resolved by user input — removed from this list.

1. **Schema multi-line fields.** Should `autores`, `fuentes`, and
   `dependencias` accept multi-line strings, or are they single-line
   (the v1 parser's current shape)? The placeholder RFC recommends
   single-line to match `migrate_to_doorstop.py:130`. **Non-blocking**:
   the design can lock single-line in phase 4.
2. **"Eliminar" destructive style.** Should the kebab menu's
   `Eliminar` action render with a destructive style (red text, e.g.
   `text-red-600 hover:bg-red-50`)? The accessibility win of explicit
   affordance argues yes; the visual cleanliness of neutral argues
   no. **Non-blocking**: design can pick either; tests cover both
   shapes via a renderer prop.
3. **Auto-select newly-created item.** After `POST` returns 201, do
   we auto-select the new leaf in the sidebar (locking the success
   visible)? The proposal recommends yes. **Non-blocking**: design
   can confirm or override.
4. **`<details>` vs `aria-disclosure` button.** For the collapsible
   groups, `<details>` / `<summary>` is the native accessible
   pattern. Some screen reader / browser pairs (Safari + VoiceOver at
   older versions) handle `<details>` inconsistently. **Non-blocking**:
   design chooses one; we keep AA either way (NFR-13 is the gate).

## Test Plan Summary

One `it` per spec §3 scenario where testable. v2 deltas only; v1
42-test plan remains valid and unchanged.

| File (new or v2-modified) | `it` count | REQs / NFRs covered |
|---|---:|---|
| `tests/unit/lib/rem/schemas.test.ts` | 9 | REQ-19#1, REQ-20#2, REQ-21#3 — Zod schemas for rf / rnf / stakeholder (create + edit) |
| `tests/unit/lib/rem/writer.test.ts` | 5 | NFR-10, NFR-11 — round-trip + torn-write safety |
| `tests/unit/lib/rem/serializeYamlSubset.test.ts` | 4 | NFR-11 — single-line shape matches `migrate_to_doorstop.py:130` |
| `tests/unit/components/rem/RemEditDialog.test.tsx` | 5 | REQ-19#1..#5 |
| `tests/unit/components/rem/RemSidebar.test.tsx` | 11 | REQ-13#1..#5, REQ-14#1..#3, REQ-15#1..#5, REQ-16#1..#4, REQ-17#1..#4, REQ-18#1..#3 — extends v1's 10-test file |
| `tests/unit/components/rem/RemTreeNode.test.tsx` | 3 | REQ-13#4 (legacy key fallback), REQ-14#1 (collapsed state), REQ-17#2 (DELETE removes row) |
| `tests/unit/pages/api/rem/stakeholder.test.ts` | 4 | REQ-20#1..#4 — POST 201 / 400 / 500 / cache invalidated |
| `tests/unit/pages/api/rem/stakeholder_uid.test.ts` | 3 | REQ-21#1, #3, REQ-22#2 — PUT 200 / 400 / 404 |
| `tests/unit/pages/api/rem/stakeholder_delete.test.ts` | 4 | REQ-22#1..#4 — DELETE 204 / 404 / 500 / cache invalidated |
| `tests/unit/pages/api/rem/rf.test.ts` | 4 | REQ-20#1..#4 — POST for RF |
| `tests/unit/pages/api/rem/rf_uid.test.ts` | 4 | REQ-21#1..#4 |
| `tests/unit/pages/api/rem/rf_delete.test.ts` | 4 | REQ-22#1..#4 |
| `tests/unit/pages/api/rem/rnf.test.ts` | 4 | REQ-20#1..#4 |
| `tests/unit/pages/api/rem/rnf_uid.test.ts` | 4 | REQ-21#1..#4 |
| `tests/unit/pages/api/rem/rnf_delete.test.ts` | 4 | REQ-22#1..#4 |
| `tests/e2e/rem/rem-crud.spec.ts` | 8 | REQ-18#1, REQ-19#2, #3, REQ-20#1, REQ-21#1, REQ-22#1, REQ-23#1, REQ-24#1 — e2e flow at `/es/rem` |
| `tests/e2e/rem/rem-sidebar.spec.ts` | 6 | REQ-13#2, REQ-14#2, REQ-15#1, #4, REQ-23#1, REQ-24#2 — e2e sidebar persistence |
| `tests/e2e/rem/rem-a11y.spec.ts` | 3 | REQ-13#1, REQ-16#3, NFR-13 — `<details>` + kebab menu a11y |
| **Total v2** | **85** | All 45 §3 scenarios + NFR-09..14 |

**Auxiliary** (outside the strict-TDD 1:1 ratio; counted in coverage):

- `tests/unit/lib/rem/storageKeys.test.ts` (3 it) — `expandedStorageKey`,
  `nestedExpandedStorageKey`, `SIDEBAR_WIDTH_STORAGE_KEY` constants.
- `frontend/README.md` heading audit (1 it, scripted) — NFR-14.

**Coverage thresholds** (extend `vitest.config.ts`):

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

**Fixtures:**

- `tests/unit/lib/rem/fixtures/writer/*.yml` — copies of two v1 RF
  YAMLs + two v1 RNF YAMLs + two v1 stakeholder YAMLs (one with
  empty lists, one with non-empty), used by `writer.test.ts` and
  `serializeYamlSubset.test.ts`.

**Mocking strategy:**

- `vi.mock('node:fs/promises', ...)` for write-side tests, mirroring
  the v1 read-side mocks at `loader.test.ts`.
- `vi.mock('../../../src/i18n', ...)` identity mock for the kebab /
  dialog tests (key → key).
- `vi.spyOn(window, 'confirm')` for `Eliminar` tests.
- `vi.spyOn(window.localStorage, 'setItem')` for the persistence
  scenarios.

**E2E pre-requisites** (unchanged from v1): backend, frontend,
Caddy running locally per `playwright-e2e-patterns`. The e2e suite
gates on `process.env.E2E=1` to keep `pnpm vitest run` headless-only
in local dev.

**Lighthouse a11y** (NFR-13): unchanged v1 gate. The e2e
`rem-a11y.spec.ts` adds `<details>` expand / collapse assertion +
`axe-core` scan that fails on AA violations introduced by v2.

---

**Total LoC ceiling** for spec commit (per `work-unit-commits`):
≤ 700 lines per file (this spec); ≤ 250 LoC per component per
AGENTS.md §1; no `any` in production code per AGENTS.md §14.
