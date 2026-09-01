# Design: `rem-viewer-v2`

## 1. Overview

v2 is an **additive** layer over the archived v1 viewer
(`openspec/changes/rem-viewer/`, sealed 2026-09-01, 65 tests PASS).
v1's 12 REQs + 8 NFRs remain valid; v2 adds **12 new REQs (REQ-13..24)**
+ **6 new NFRs (NFR-09..14)** + **45 new scenarios**. The deltas are:
sidebar restructure (3 collapsible top-level groups + nested groups +
draggable resize handle + `localStorage`), full CRUD against the existing
Doorstop YAMLs (9 new Astro endpoints + Zod schemas + extended YAML
writer + atomic `.bak` safety), detail panel widening
(`max-w-3xl` → `max-w-6xl`), palette anchored on `#2D9D8E`, and an
extended `frontend/README.md`. No backend (`backend/**`) changes,
no new npm packages.

## 2. Architecture delta diagram

```
                ┌──────────────────────────────────────────────────────┐
   Browser  ──▶ │ /es/rem  /en/rem   (Astro SSR, server output)       │
                │   pages/[locale]/rem/index.astro                     │
                └─────────────┬────────────────────────────────────────┘
                              │ frontmatter: loadAllRequirements()
                              ▼
                ┌──────────────────────────────────────────────────────┐
                │ lib/rem/loader.ts  (5-min cache + clearLoaderCache)  │
                └─────────────┬────────────────────────────────────────┘
                              │ RemTemplate
                              ▼
                ┌──────────────────────────────────────────────────────┐
                │ RemLayout.astro (palette: #2D9D8E accents, slate-300)│
                └─────────────┬────────────────────────────────────────┘
                              │
        ┌─────────────────────┼────────────────────────────────────────┐
        ▼                     ▼                                        ▼
   <nav class="rem-shell__nav">        <main> (max-w-6xl ≥ lg)         <footer>
        │                                   │
        ▼                                   ▼
   RemGroupShell.tsx  (NEW)            RemDetail / RemError
        │                                   ▲
        ▼                                   │  fetch + innerHTML (F2 lock)
   RemSidebar.tsx (modified)  ──────────────┘  reuses RemSidebar.tsx:235
        │
        ├─ <details data-rem-group-top="<groupId>">  (REQ-13)
        │   └─ <details data-rem-group-nest="<g>.<bucket>">  (REQ-14)
        │       └─ RemTreeNode + RemKebabMenu (NEW, REQ-16)
        │           ├─ "+ Nuevo" → RemEditDialog (NEW, REQ-18/19)
        │           │       └─ forms/{RemRfForm,RemRnfForm,RemStakeholderForm}
        │           │           (Zod resolver, POST/PUT)
        │           └─ "Eliminar" → window.confirm() → DELETE (REQ-17)
        └─ RemResizeHandle.tsx (NEW, REQ-15) — CSS resize: horizontal
            │              clamped [240, 480] px, persisted on mouseup
            └─→ --rem-sidebar-width CSS variable

   On dialog submit  ──▶  POST|PUT  /api/rem/{stakeholder,rf,rnf}[/uid]
                              │
                              ▼
                ┌──────────────────────────────────────────────────────┐
                │ pages/api/rem/{rf,rnf,stakeholder}/{index,[uid]}.ts │
                │   - Zod validation → 400                            │
                │   - serializeYamlSubset(record) → fs.writeFile      │
                │   - .bak.<ts> snapshot; rename atomic; restore 500  │
                │   - clearLoaderCache() on success (REQ-20#4)        │
                └─────────────┬────────────────────────────────────────┘
                              ▼
                scripts/requirements/req/<uid>_<slug>.yml
                (single-line shape: `key: "value"` / `key: []` / `  - item`)

   On dialog success  ──▶  fetch('/api/rem/<uid>.html')  (F2 lock)
        +  clearLoaderCache()
        +  swap `.rem-detail` innerHTML
        +  preserve sidebar scroll position (REQ-19#2)
```

## 3. v1 vs v2 file layout delta

NEW files only. v1 paths/components preserved as-is:

```txt
frontend/src/
├── lib/rem/
│   ├── rf.schema.ts                  ← Zod schema (create + edit)
│   ├── rnf.schema.ts                 ← Zod schema (create + edit)
│   ├── stakeholder.schema.ts         ← Zod schema (create + edit)
│   ├── yaml-writer.ts                ← serializeYamlSubset(record)
│   ├── yaml-roundtrip.ts             ← read → write → read helper
│   └── crud-errors.ts                ← error code helpers (shared)
├── components/rem/
│   ├── RemGroupShell.tsx             ← <details> wrapper + persistence
│   ├── RemResizeHandle.tsx           ← CSS resize: horizontal divider
│   ├── RemKebabMenu.tsx              ← per-row ⋮ button + popover
│   ├── RemEditDialog.tsx             ← modal shell (≤ 150 LoC)
│   ├── RemEditDialog.Field.tsx       ← split if parent > 250 LoC
│   ├── RemEditDialog.Confirm.tsx     ← split if parent > 250 LoC
│   └── forms/
│       ├── RemRfForm.tsx             ← react-hook-form + Zod
│       ├── RemRnfForm.tsx
│       └── RemStakeholderForm.tsx
└── pages/api/rem/
    ├── stakeholder/
    │   ├── index.ts                  ← POST
    │   └── [uid].ts                  ← PUT + DELETE (method switch)
    ├── rf/
    │   ├── index.ts                  ← POST
    │   └── [uid].ts                  ← PUT + DELETE
    └── rnf/
        ├── index.ts                  ← POST
        └── [uid].ts                  ← PUT + DELETE

frontend/tests/
├── unit/lib/rem/
│   ├── storageKeys.test.ts           ← REQ-13/14/15 key helpers
│   ├── schemas.test.ts               ← Zod 3-schema fixtures
│   ├── writer.test.ts                ← NFR-10 torn write
│   └── serializeYamlSubset.test.ts   ← NFR-11 round-trip
├── unit/components/rem/
│   ├── RemSidebar.test.tsx           ← +11 v2 it (extends v1's 10)
│   ├── RemTreeNode.test.tsx          ← +3 v2 it
│   ├── RemGroupShell.test.tsx        ← <details> a11y + persistence
│   ├── RemResizeHandle.test.tsx      ← clamp + persist on mouseup
│   ├── RemKebabMenu.test.tsx         ← Escape + click-outside
│   ├── RemEditDialog.test.tsx        ← 5 it (REQ-19#1..#5)
│   ├── forms/
│   │   ├── RemRfForm.test.tsx
│   │   ├── RemRnfForm.test.tsx
│   │   └── RemStakeholderForm.test.tsx
│   └── pages/api/rem/
│       ├── stakeholder.test.ts       ← POST (4 it)
│       ├── stakeholder_uid.test.ts   ← PUT (3 it)
│       ├── stakeholder_delete.test.ts← DELETE (4 it)
│       ├── rf.test.ts                ← POST (4 it)
│       ├── rf_uid.test.ts            ← PUT (4 it)
│       ├── rf_delete.test.ts         ← DELETE (4 it)
│       ├── rnf.test.ts               ← POST (4 it)
│       ├── rnf_uid.test.ts           ← PUT (4 it)
│       └── rnf_delete.test.ts        ← DELETE (4 it)
└── e2e/rem/
    ├── rem-crud.spec.ts              ← 8 it (REQ-18#1, REQ-19#2..#3,
    │                                    REQ-20#1, REQ-21#1, REQ-22#1,
    │                                    REQ-23#1, REQ-24#1)
    ├── rem-sidebar.spec.ts           ← 6 it (REQ-13#2, REQ-14#2,
    │                                    REQ-15#1/#4, REQ-23#1, REQ-24#2)
    └── rem-a11y.spec.ts              ← 3 it (REQ-13#1, REQ-16#3, NFR-13)
```



## 4. Module layout for new files

| File | Purpose | Key exports | Key types | Deps | Test |
|------|---------|-------------|-----------|------|------|
| `lib/rem/{rf,rnf,stakeholder}.schema.ts` | Zod source of truth (create + edit) | `rfCreateSchema`, `rfUpdateSchema`, `rnfCreateSchema`, `rnfUpdateSchema`, `stakeholderCreateSchema`, `stakeholderUpdateSchema` | `RemCreateInput<T>`/`RemUpdateInput<T>` (from spec §2) | `zod@^4.4` | `schemas.test.ts` |
| `lib/rem/yaml-writer.ts` | `serializeYamlSubset(record)` matching `migrate_to_doorstop.py:45-65`; pre-write `.bak.<ts>` in tmp + atomic `rename` | `serializeYamlSubset`, `writeYamlAtomic`, `rollbackFromBackup` | — | `node:fs/promises`, `node:os`, `node:path` | `serializeYamlSubset.test.ts`, `writer.test.ts` |
| `lib/rem/yaml-roundtrip.ts` | NFR-11 fixture runner: read → write → re-read → deep-equal (whitespace-agnostic) | `assertRoundTrip(uid, fixtureDir)` | — | `loader`, `yaml-writer` | `writer.test.ts` |
| `lib/rem/crud-errors.ts` | Shared `errorBody(code, status)` + status mapping (400/404/500) | `errorBody`, `crudErrorResponse` | — | — | co-tested via endpoint tests |
| `components/rem/RemGroupShell.tsx` | 3 native `<details>` top-level groups + nested groups; reads/writes `expandedStorageKey`/`nestedExpandedStorageKey`; owns the `<button data-rem-crud-create="<type>">` at the top of each group's content area (REQ-18#1) | default | props `{groupId, defaultOpen, label, level, children, onCreate}` | React, `lib/rem/types` | `RemGroupShell.test.tsx` |
| `components/rem/RemResizeHandle.tsx` | `data-rem-resize-handle` divider with CSS `resize: horizontal`; reads/writes `SIDEBAR_WIDTH_STORAGE_KEY` on `mouseup`/pointerup | default | props `{minPx, maxPx, defaultPx}` | React | `RemResizeHandle.test.tsx` |
| `components/rem/RemKebabMenu.tsx` | Per-row `⋮` icon button + `Editar`/`Eliminar` popover; Escape + click-outside close | default | props `{uid, kind, onEdit, onDelete, t}` | React | `RemKebabMenu.test.tsx` |
| `components/rem/RemEditDialog.tsx` | Modal shell, switches on `mode`/`kind`; embeds one of 3 forms | default | props `{mode, kind, uid?, defaultValues?, onClose}` | React, forms | `RemEditDialog.test.tsx` |
| `components/rem/RemEditDialog.{Field,Confirm}.tsx` | Optional split (AGENTS.md §1 — ≤ 250 LoC) | same as parent | — | — | included above |
| `components/rem/forms/RemRfForm.tsx` | `useForm({ resolver: zodResolver(rfCreateSchema\|rfUpdateSchema) })`; renders RF fields | default | props `{mode, defaultValues, onSubmit, onCancel}` | react-hook-form, `zod`, schema | `forms/RemRfForm.test.tsx` |
| `components/rem/forms/RemRnfForm.tsx` | Same shape for RNF | default | same | same | `forms/RemRnfForm.test.tsx` |
| `components/rem/forms/RemStakeholderForm.tsx` | Same shape for stakeholder | default | same | same | `forms/RemStakeholderForm.test.tsx` |
| `pages/api/rem/{rf,rnf,stakeholder}/index.ts` | `POST` create; Zod validate → find next UID slot → `writeYamlAtomic` → `clearLoaderCache()` → 201 | `POST` (Astro endpoint) | — | zod, loader, yaml-writer | endpoint test |
| `pages/api/rem/{rf,rnf,stakeholder}/[uid].ts` | Method switch: `PUT` updates field-by-field, `DELETE` `fs.unlink`; 404 on unknown; 200/204 on hit | `PUT`, `DELETE` | — | zod, loader, yaml-writer | endpoint test |
| `tests/unit/lib/rem/storageKeys.test.ts` | Asserts `expandedStorageKey`/`nestedExpandedStorageKey`/`SIDEBAR_WIDTH_STORAGE_KEY` strings (NFR-13 stability) | — | — | — | itself |
| `tests/unit/lib/rem/fixtures/writer/*.yml` | 6 v1-shaped fixtures (2 RF, 2 RNF, 2 stakeholder; one of each with empty lists) | — | — | — | used by writer tests |
| `tests/e2e/rem/rem-crud.spec.ts` | 8 e2e flow tests | — | — | — | itself |

## 5. Key decisions (7 design-time locks)

### DD1 — Collapsible group semantics: native `<details>` vs custom button + aria-expanded

**Choice**: native `<details>`/`<summary>`. **Rationale**: native element
delivers keyboard nav (Tab → summary, Space/Enter toggle), screen-reader
announcement (`expanded`/`collapsed`), and a free `name=` attribute for
single-open groups. The alternative (custom `<button aria-expanded>`)
costs ~30 LoC per group for a11y parity and risks subtle regressions with
assistive tech. Trade-off accepted: Safari < 16 mis-announces
`<details>` in rare edge cases — covered by NFR-13 `axe-core` scan.

### DD2 — Sidebar resize: CSS `resize: horizontal` vs pointer events

**Choice**: CSS `resize: horizontal` on the inner `<aside>` content with
the handle element providing the resize affordance (`cursor: ew-resize`).
**Rationale**: the browser handles `mousemove`/`mouseup` and emits a
`resize` event on the element — zero JS drag logic, ~15 LoC total vs
~80 LoC for pointer-events, and free `min/max-width` enforcement via
CSS. We persist on `resize` event (which fires only on pointerup in
practice) — not on every pixel — to match REQ-15#4. Trade-off accepted:
no snap-to-defaults on double-click; we rely on the `resize` event
listener for clamping.

### DD3 — Kebab menu trigger: always-visible vs hover-only

**Choice**: always-visible. **Rationale**: touch devices have no hover;
hover-only menus are inaccessible on tablets / kiosks (the professor's
demo may use either). The 24×24 px icon button adds ≤ 32 px of width
per row — acceptable. Trade-off accepted: minor visual noise; mitigated
by `aria-hidden` on the SVG and `opacity-0 group-hover:opacity-100`
fallback styling if it becomes a complaint.

### DD4 — Delete confirmation: `window.confirm()` vs `<dialog>`

**Choice**: `window.confirm()`. **Rationale**: native, screen-reader
announced, zero component overhead, and the spec already accepts
`Eliminar`'s confirm semantics (REQ-17#1). The custom `<dialog>` route
would add ~60 LoC of overlay/escape/backdrop logic for no functional
gain. Hard-delete contract (locked by user input) means the dialog is
informational — a native `confirm()` matches the gravitas of the
operation. Trade-off accepted: cannot style; kept simple.

### DD5 — Tree-region swap after CRUD: `fetch + innerHTML` vs JSON + re-render

**Choice**: reuse v1's F2-locked `fetch + innerHTML` against
`/api/rem/<uid>.html`. **Rationale**: keeps the React island small (no second client-side data path) and matches v1's precedent for the detail panel. Decision: re-fetch the WHOLE sidebar slot via the existing SSR HTML (no new GET endpoint needed; the page is `output: 'server'` and renders fresh sidebar HTML on every request). Swap `aside.rem-shell__nav` innerHTML atomically.

### DD6 — Write-back parser: extend v1 hand-rolled parser vs add `yaml` package

**Choice**: extend the v1 hand-rolled parser in `loader.ts:111-209` with
a paired `serializeYamlSubset(record)` in `yaml-writer.ts`. **Rationale**:
v1 design §3 documents that `yaml` is a transitive Astro dep but cannot
be imported without listing it in `frontend/package.json`'s
`dependencies`; v2's hard constraint forbids new packages. The v1 parser
covers the only shapes `migrate_to_doorstop.py:45-65` emits — adding
the matching serializer keeps the round-trip closed. Trade-off
accepted: ~80 LoC of manual escaping/joining; the test matrix is small
and exercised by `serializeYamlSubset.test.ts` (NFR-11) + 6 fixtures.

### DD7 — Palette anchor: `#2D9D8E` (auth accent) vs new v2 palette

**Choice**: `#2D9D8E` from `lib/auth-accent.ts` (`AUTH_ACCENT.primary`).
**Rationale**: per `astro-themes-and-palette` skill, the auth accent is
the project's *universal* identity color — used in focus rings,
selected-leaf highlight, print-button hover, and form input border.
Using `brand.accentColor` would differ per tenant (Panda = `#0066CC`,
Kitsune = `#FF6B6B`); the REM viewer is single-tenant for now but we
pick the universal color so adding the viewer to another store is
zero-cost. PD badge contrast (7.5:1) preserved per REQ-24#2. Trade-off
accepted: subtle departure from the storefront palette; documented in
`frontend/README.md` as the project's deliberate "auth-aligned" choice.

## 6. Test architecture

**Coverage target** (NFR-12): 85% lines for `lib/rem/**`, `components/rem/**`, `pages/api/rem/**`. Per-file thresholds extend v1 `vitest.config.ts` paths.

**Scenario-to-test mapping** (45 spec §3 scenarios + 6 NFRs):

| Spec | Scenario | Test file / method |
|------|---------|--------------------|
| REQ-13#1 | 3 `<details data-rem-group-top>` | `RemGroupShell.test.tsx ▸ renders 3 top-level groups` |
| REQ-13#2 | click summary → `localStorage[expanded.rnf] = 'closed'` | `RemGroupShell.test.tsx ▸ writes expanded key on toggle` |
| REQ-13#3 | reload preserves collapse state | `RemGroupShell.test.tsx ▸ reads expanded key on mount` |
| REQ-13#4 | legacy `expanded.rf` fallback | `RemTreeNode.test.tsx ▸ accepts same-version key written by an earlier v2 build (e.g. value='open' vs value='closed' format drift)` |
| REQ-13#5 | first-visit default-open | `RemGroupShell.test.tsx ▸ defaults to open when storage empty` |
| REQ-14#1 | nested `<details>` per module | `RemGroupShell.test.tsx ▸ renders nested <details> per rf.module` |
| REQ-14#2 | nested collapse + reload | `RemGroupShell.test.tsx ▸ writes nested expanded key` |
| REQ-14#3 | single-module bucket rendering | `RemGroupShell.test.tsx ▸ renders bucket with items` |
| REQ-15#1 | 320 px default + handle visible | `RemResizeHandle.test.tsx ▸ defaults to 320 with handle` |
| REQ-15#2 | clamp 200 → 240 | `RemResizeHandle.test.tsx ▸ clamps under min` |
| REQ-15#3 | clamp 600 → 480 | `RemResizeHandle.test.tsx ▸ clamps over max` |
| REQ-15#4 | drag → persist on pointerup | `RemResizeHandle.test.tsx ▸ persists on mouseup only` |
| REQ-15#5 | hidden < lg | `RemResizeHandle.test.tsx ▸ hidden below lg` |
| REQ-16#1 | kebab `⋮` per row | `RemKebabMenu.test.tsx ▸ renders kebab per row` |
| REQ-16#2 | popover Editar/Eliminar | `RemKebabMenu.test.tsx ▸ opens with two actions` |
| REQ-16#3 | Escape closes | `RemKebabMenu.test.tsx ▸ Escape closes + returns focus` |
| REQ-16#4 | click-outside closes | `RemKebabMenu.test.tsx ▸ click outside closes` |
| REQ-17#1 | confirm() returns false → no fetch | `RemKebabMenu.test.tsx ▸ confirm false aborts delete` |
| REQ-17#2 | confirm true → 204 → remove row | `RemKebabMenu.test.tsx ▸ confirm true fires DELETE + removes` |
| REQ-17#3 | 500 → error alert | `RemKebabMenu.test.tsx ▸ DELETE 500 keeps row + shows error` |
| REQ-17#4 | deleted uid → notfound on `/es/rem?id=…` | `e2e/rem-crud.spec.ts ▸ deleted UID renders RemError notfound` |
| REQ-18#1 | `+ Nuevo` button per group | `RemSidebar.test.tsx ▸ renders create button per group` |
| REQ-18#2 | open dialog with `type='stakeholder'` | `RemEditDialog.test.tsx ▸ create stakeholder mode opens 5 fields` |
| REQ-18#3 | open dialog with `type='rnf'` | `RemEditDialog.test.tsx ▸ create rnf mode opens 8 fields` |
| REQ-19#1 | invalid code → Zod error | `RemEditDialog.test.tsx ▸ invalid code blocks submit` |
| REQ-19#2 | 201 → close + clear cache + fetch + scroll preserved | `RemEditDialog.test.tsx ▸ create success closes + refetches tree` |
| REQ-19#3 | 200 → detail swap | `RemEditDialog.test.tsx ▸ edit success swaps detail panel` |
| REQ-19#4 | Cancel / Escape → no fetch | `RemEditDialog.test.tsx ▸ cancel does not fire POST` |
| REQ-19#5 | 500 → stays open + error alert | `RemEditDialog.test.tsx ▸ submit 500 stays open + error` |
| REQ-20#1 | POST stakeholder → 201 + new file | `pages/api/rem/stakeholder.test.ts ▸ create 201` |
| REQ-20#2 | invalid `priority: X` → 400 | `pages/api/rem/stakeholder.test.ts ▸ invalid priority 400` |
| REQ-20#3 | serializer throws → 500 + others untouched | `pages/api/rem/stakeholder.test.ts ▸ write error 500 + sibling untouched` (NFR-10) |
| REQ-20#4 | success → `clearLoaderCache()` called | `pages/api/rem/stakeholder.test.ts ▸ clears loader cache` |
| REQ-21#1 | PUT 200 + file rewritten | `pages/api/rem/stakeholder_uid.test.ts ▸ update 200` |
| REQ-21#2 | unknown uid → 404 | `pages/api/rem/stakeholder_uid.test.ts ▸ update notfound 404` |
| REQ-21#3 | invalid → 400 + untouched | `pages/api/rem/stakeholder_uid.test.ts ▸ invalid 400` |
| REQ-21#4 | empty `{}` → byte-identical (NFR-11) | `tests/unit/lib/rem/writer.test.ts ▸ empty payload round-trips byte-identical` (NFR-11 covered here; stakeholder_uid stays at 3 it per §3) |
| REQ-22#1 | DELETE 204 + file gone | `pages/api/rem/stakeholder_delete.test.ts ▸ delete 204` |
| REQ-22#2 | unknown → 404 | `pages/api/rem/stakeholder_delete.test.ts ▸ delete notfound 404` |
| REQ-22#3 | unlink throws → 500 + cache NOT cleared | `pages/api/rem/stakeholder_delete.test.ts ▸ delete 500` |
| REQ-22#4 | deleted uid no longer in tree | `pages/api/rem/stakeholder_delete.test.ts ▸ loader excludes deleted uid` |
| REQ-23#1 | `<main>` `max-w-6xl` on ≥ lg | `e2e/rem-crud.spec.ts ▸ detail panel max-w-6xl at 1280x800` |
| REQ-23#2 | full width < lg | `e2e/rem-crud.spec.ts ▸ detail full width at 800x600` |
| REQ-24#1 | selected leaf = `rgba(45,157,142,0.10)` | `e2e/rem-sidebar.spec.ts ▸ selected leaf uses #2D9D8E/10` |
| REQ-24#2 | PD badge contrast ≥ 4.5:1 | `e2e/rem-sidebar.spec.ts ▸ PD badge contrast preserved` |
| REQ-24#3 | cards use `border-slate-300` | `RemSidebar.test.tsx ▸ group container border-slate-300` |
| REQ-24#4 | Imprimir PDF hover = `#2D9D8E`/`#258777` | `RemPrintButton.test.tsx ▸ full report button hover bg` (existing test) |
| NFR-09 | CRUD ≤ 200 ms p95 | `e2e/rem-crud.spec.ts ▸ CRUD ops under 200 ms` (perf.now) |
| NFR-10 | torn write safety | `writer.test.ts ▸ torn write restores .bak` |
| NFR-11 | round-trip deep-equal | `serializeYamlSubset.test.ts ▸ 6 fixtures round-trip` |
| NFR-12 | 85% coverage on new paths | `vitest.config.ts` threshold + CI grep |
| NFR-13 | `<details>` + kebab a11y | `e2e/rem-a11y.spec.ts ▸ axe-core scan + aria-expanded` |
| NFR-14 | README 8 H3 sections | scripted grep test + `markdownlint` |

**Total v2**: 85 `it` (matches spec §"Test Plan Summary"). Co-tested in
`loader.test.ts` (read-side mocks stay untouched); `RemSidebar.test.tsx`
extends v1's 10-test file with +11 v2 `it`.

**Mocking strategy**:
- `vi.mock('node:fs/promises', ...)` for endpoint + writer tests.
- `vi.mock('../../../src/i18n', ...)` identity mock (`t: key => key`).
- `vi.spyOn(window, 'confirm')` for `Eliminar` tests.
- `vi.spyOn(window.localStorage, 'setItem')` for persistence scenarios.

## 7. i18n strategy

New keys (~30) added to both `es.ts` and `en.ts` and to the
`TranslationKey` union in `i18n/types.ts:19`:

```txt
rem.group.{stakeholders,rf,rnf}.{heading,new}    "Stakeholders" / "Requisitos funcionales" / "Requisitos no funcionales" / "+ Nuevo …"
rem.crud.{edit,delete,create,create.submit,edit.submit,cancel}
rem.crud.delete.confirm                          "¿Eliminar este requisito? Esta acción no se puede deshacer."
rem.crud.{delete,write}.error                    "No se pudo {eliminar,guardar el cambio}. Reintentá en unos segundos."
rem.form.{code,title,priority,status,module,category,rationale,verifiedBy,fuentes,dependencias}.{label,invalid,required,hint}
rem.form.stakeholder.{name,title,role,organization,notes}.label
```

Naming follows the v1 namespace (`rem.*`); Zod error keys map 1:1 to
`rem.form.*.invalid` / `*.required` translations surfaced via
`useTranslations().t` (per REQ-19).

## 8. CRUD endpoints contract

| Method + path | Body (Zod schema) | Success | Errors | YAML write path | Cache strategy |
|---|---|---|---|---|---|
| `POST /api/rem/stakeholder` | `stakeholderCreateSchema` (all fields except `uid`) | `201 {ok:true, uid: "stakeholder_004"}` | `400 crud:validation:<field>`, `500 crud:write:<code>` | `scripts/requirements/req/stakeholder_004_<slug>.yml` | `clearLoaderCache()` before `return` |
| `PUT /api/rem/stakeholder/[uid]` | `stakeholderUpdateSchema` (all fields optional + `uid`) | `200 {ok:true, uid, item: Stakeholder}` | `400`, `404 crud:notfound:<uid>`, `500` | overwrite `req/<existingFile>.yml` | `clearLoaderCache()` on 200 |
| `DELETE /api/rem/stakeholder/[uid]` | — | `204` (empty body, `Content-Length: 0`) | `404`, `500` | `fs.unlink(req/<existingFile>.yml)` | `clearLoaderCache()` only on unlink success |
| `POST /api/rem/rf` | `rfCreateSchema` | `201 {ok:true, uid: "rf_049"}` | `400`, `500` | `req/rf_049_<slug>.yml` | `clearLoaderCache()` |
| `PUT /api/rem/rf/[uid]` | `rfUpdateSchema` | `200` | `400`, `404`, `500` | overwrite | `clearLoaderCache()` |
| `DELETE /api/rem/rf/[uid]` | — | `204` | `404`, `500` | `fs.unlink` | `clearLoaderCache()` only on success |
| `POST /api/rem/rnf` | `rnfCreateSchema` | `201 {ok:true, uid: "rnf_031"}` | `400`, `500` | `req/rnf_031_<slug>.yml` | `clearLoaderCache()` |
| `PUT /api/rem/rnf/[uid]` | `rnfUpdateSchema` | `200` | `400`, `404`, `500` | overwrite | `clearLoaderCache()` |
| `DELETE /api/rem/rnf/[uid]` | — | `204` | `404`, `500` | `fs.unlink` | `clearLoaderCache()` only on success |

**UID slot allocation** (POST): scan `readdirSync(req)` for `^(rf|rnf|stakeholder)_\d{3}_` and pick `max + 1`, zero-padded to 3 digits. Filename slug from `name` (stakeholder) or `code` (RF/RNF), lowercased, ASCII-only — fallback `<uid>.yml` if empty.

**Content-Type**: `application/json; charset=utf-8`. Response body shape
`{ ok: true, ... }` / `{ ok: false, error: "code" }` (per spec
`RemWriteResult`).

**Astro endpoint method dispatch**: `[uid].ts` exports both `PUT` and `DELETE`; shared `errorBody` helper in `lib/rem/crud-errors.ts`.

## 9. Write-back safety

Per NFR-10 (torn write) and NFR-11 (round-trip):

1. **Snapshot** — before `writeYamlAtomic(targetPath, content)`:
   `fs.copyFile(targetPath, os.tmpdir() + '/<uid>.bak.<Date.now()>')`.
   Skip if `targetPath` doesn't exist (create flow).
2. **Write** — `fs.writeFile(targetPath + '.tmp', content)` then
   `fs.rename(targetPath + '.tmp', targetPath)` for atomic swap.
3. **Success** — `fs.unlink` the `.bak` snapshot; respond 2xx.
4. **Failure** (any throw in steps 1-3) — restore `.bak` over
   `targetPath` via the same atomic `writeFile + rename`, delete the
   `.tmp` if it exists, respond `500 { ok: false, error: 'crud:write:<code>' }`.
5. **Directory listing invariant** (NFR-10 assertion) — pre and post
   `readdirSync(req)` produce the same file set except for the file the
   operation was attempting to create/remove. Verified by
   `writer.test.ts` with a torn-write fixture.

`.bak` files are stored in `os.tmpdir()` (e.g. `/tmp`), never in
`scripts/requirements/req/`, so they never appear in git diffs.

## 10. Sidebar resize safety

Per REQ-15:

- **Clamp** — `min-width: 240px; max-width: 480px;` on the inner
  `<aside>` (CSS `resize: horizontal` honors these).
- **Default** — `320 px`. Read on mount from `localStorage.getItem(SIDEBAR_WIDTH_STORAGE_KEY)`.
  - On parse error / NaN / missing → fall back to `320`.
  - On out-of-range → clamp and overwrite `localStorage` with the
    clamped value on first render (REQ-15#2/#3).
- **Persist** — listen for `resize` event on the inner `<aside>`; write
  the current `offsetWidth` to `localStorage` exactly once per event
  (event fires on pointerup in practice; debounce 0 ms is fine).
- **Below `lg`** (1024 px) — `hidden lg:block` on the handle; the inner
  `<aside>` keeps its fixed `lg:w-80` width (REQ-15#5). No localStorage
  write occurs in this mode.

## 11. File-by-file implementation notes

> Cross-refs to v1 modules the new files reuse (no copy):
> `buildDetailRows` (`lib/rem/format.ts`), `loadAllRequirements` +
> `clearLoaderCache` (`lib/rem/loader.ts`), `findItemByUid` (`pages/api/rem/[uid].html.ts:57` — lifted into a shared `lib/rem/crud-errors.ts` for the new endpoints), the i18n
> `useTranslations` hook + `TranslationKey` union (`src/i18n/types.ts`),
> the v1 `RemSidebar.tsx:235` `fetch + innerHTML` pattern (DD5 reuse).

**Strict-TDD contract**: each new component ships with a co-located
test file in the same commit (per `work-unit-commits` skill).

### `frontend/src/lib/rem/rf.schema.ts` (NEW, ~80 LoC)
- Exports `rfCreateSchema`, `rfUpdateSchema`.
- Fields: `code` (`/^RF-[A-Z]+-\d{3}$/`), `title` (min 1),
  `rationale` (default `''`), `priority` (`'H'|'M'|'L'`),
  `status` (`Status` enum), `module` (min 1), `verifiedBy` (`string[]`,
  default `[]`), `fuentes` (`string[]`, default `[]`),
  `dependencias` (`string[]`, default `[]`).
- Update variant makes every field optional.
- Test: `tests/unit/lib/rem/schemas.test.ts ▸ rf schema validates fixture + rejects bad code/priority`.

### `frontend/src/lib/rem/rnf.schema.ts` (NEW, ~80 LoC)
- Mirror of `rf.schema.ts`; `category` instead of `module`;
  no `verifiedBy`.

### `frontend/src/lib/rem/stakeholder.schema.ts` (NEW, ~70 LoC)
- Fields: `name` (min 1), `title`, `role`, `organization`, `notes`.
- No `code` field.

### `frontend/src/lib/rem/yaml-writer.ts` (NEW, ~140 LoC)
- `serializeYamlSubset(record: Record<string, unknown>): string` —
  emits single-line shape exactly matching `migrate_to_doorstop.py:45-65`:
  comment lines, `{uid}:` header, then for each `k, v`: list → `key: []`
  or indented `- item`; bool → `true`/`false`; everything else →
  `key: "value"` (escapes `|` and `"`).
- `writeYamlAtomic(filePath, content): Promise<void>` — `.bak.<ts>`
  snapshot + temp file + atomic `rename`.
- `rollbackFromBackup(filePath, backupPath): Promise<void>` — restore.
- Test: `tests/unit/lib/rem/serializeYamlSubset.test.ts` (4 it:
  stakeholder empty lists, stakeholder with notes, RF with deps, RNF
  with category); `tests/unit/lib/rem/writer.test.ts` (5 it: torn
  write, atomic rename, rollback, NFR-10 sibling untouched, NFR-11
  round-trip deep-equal via `yaml-roundtrip.ts`).

### `frontend/src/lib/rem/yaml-roundtrip.ts` (NEW, ~30 LoC)
- `assertRoundTrip(uid, fixtureDir): void` — Vitest helper that reads,
  writes, re-reads and `expect`s deep equality with whitespace-stripped
  comparison (treats empty `[]` and `[\n  - a\n]` as equal).

### `frontend/src/lib/rem/crud-errors.ts` (NEW, ~40 LoC)
- `errorBody(code: string, status: number): Response` —
  `Response(JSON.stringify({ok: false, error: code}), {status, headers})`.
- Shared between all 9 endpoints.

### `frontend/src/components/rem/RemGroupShell.tsx` (NEW, ~120 LoC)
- `useEffect` reads/writes `expandedStorageKey(groupId)` /
  `nestedExpandedStorageKey(groupId, bucketName)` on toggle.
- Renders `<details data-rem-group-top={groupId}>` with `<summary>`
  containing the kebab/label slot.
- Children = nested shells or leaves.
- Defaults to `open` if storage empty (REQ-13#5).
- Guards against format drift in `expanded.*` key values: a prior v2 build may have written `'open'` / `'closed'` literals while the current build normalizes to boolean strings — REQ-13#4 test reads both shapes without throwing, so a re-deploy with a value-format change does not blank the sidebar.
- Test: `RemGroupShell.test.tsx` (6 it covering REQ-13#1..#5 + REQ-14#1).

### `frontend/src/components/rem/RemResizeHandle.tsx` (NEW, ~80 LoC)
- Renders `<div data-rem-resize-handle>` as a sibling of the sidebar
  inner `<aside>`. The inner `<aside>` carries `style={{ resize:
  'horizontal', minWidth: 240, maxWidth: 480 }}` and emits `onResize`.
- `useEffect` writes `localStorage` on each `resize` event.
- Hidden below `lg` via `hidden lg:block`.
- Test: `RemResizeHandle.test.tsx` (5 it for REQ-15#1..#5).

### `frontend/src/components/rem/RemKebabMenu.tsx` (NEW, ~140 LoC)
- One `<button aria-haspopup="menu" aria-expanded>` per row.
- Popover with two `<button data-testid="rem-crud-edit|rem-crud-delete">`.
- Escape / click-outside close + focus return.
- `Eliminar` calls `window.confirm(t('rem.crud.delete.confirm'))` →
  on true, fires `onDelete(uid)`; on false, closes.
- Test: `RemKebabMenu.test.tsx` (8 it: REQ-16#1..#4, REQ-17#1..#4).

### `frontend/src/components/rem/RemEditDialog.tsx` (NEW, ~150 LoC)
- `<dialog>` element (HTML5 native), `mode: 'create' | 'edit'`,
  `kind: 'rf' | 'rnf' | 'stakeholder'`, `uid?: string`,
  `defaultValues?: ...`.
- Embeds one of 3 forms; submit calls
  `POST /api/rem/{kind}` or `PUT /api/rem/{kind}/{uid}`.
- On 201/200: `clearLoaderCache()`, fire onSuccess → parent swaps tree
  region (DD5). Parent (RemSidebar) captures `aside.rem-shell__nav .overflow-y-auto` `scrollTop` BEFORE the fetch and writes it back after the innerHTML swap resolves. CSS hook: `[data-rem-tree-region]`. Closes dialog.
- On 400: shows Zod issues via the form's `formState.errors`.
- On 500: shows `<Alert>` with `rem.crud.write.error`.
- Escape / backdrop click → `onClose` (no fetch).
- Test: `RemEditDialog.test.tsx` (5 it for REQ-19#1..#5).
- Split into `RemEditDialog.{tsx,Field.tsx,Confirm.tsx}` if LoC > 250.

### `frontend/src/components/rem/forms/{RemRfForm,RemRnfForm,RemStakeholderForm}.tsx` (NEW, each ~120-160 LoC)
- `useForm({ resolver: zodResolver(schema), defaultValues, mode: 'onBlur' })`.
- Fields rendered as labeled inputs; errors under each field.
- Submit button label from i18n (`rem.crud.create.submit` /
  `rem.crud.edit.submit`).
- Tests: `forms/{RemRfForm,RemRnfForm,RemStakeholderForm}.test.tsx`
  (3 it each: invalid code/priority rejects, valid submits calls onSubmit,
  cancel button does not submit).

### `frontend/src/pages/api/rem/{rf,rnf,stakeholder}/index.ts` (NEW, ~80 LoC each)
- `export const prerender = false;`
- `POST` handler: validate → allocate slot → `serializeYamlSubset` →
  `writeYamlAtomic` → `clearLoaderCache()` → 201.
- Errors via `errorBody` helper.

### `frontend/src/pages/api/rem/{rf,rnf,stakeholder}/[uid].ts` (NEW, ~100 LoC each)
- `export const prerender = false;`
- `PUT` handler: validate → load YAML → merge fields → write → cache clear → 200.
- `DELETE` handler: resolve uid to path → `unlink` → cache clear → 204.
- 404 via `errorBody('crud:notfound:<uid>', 404)`.

### `frontend/tests/unit/lib/rem/fixtures/writer/*.yml` (NEW, 6 files)
- 6 v1-shaped fixtures (one empty-list + one with-content per kind).
- Copied from current `scripts/requirements/req/` via a small
  `tests/unit/lib/rem/fixtures/_generate.test.ts` (already exists for
  v1) so the fixtures stay in sync.

### `frontend/tests/e2e/rem/rem-crud.spec.ts` (NEW, 8 it)
- Real CRUD against the dev server (Caddy + Postgres + Redis per
  `playwright-e2e-patterns`). Each CRUD it does a full round-trip:
  POST → reload page → assert new row visible → DELETE → reload → assert
  gone. Each it gated on `process.env.E2E === '1'` so the unit suite
  stays headless.

### `frontend/tests/e2e/rem/rem-sidebar.spec.ts` (NEW, 6 it)
- E2E for collapse persistence (`localStorage` survives reload), resize
  handle, palette tokens via `getComputedStyle`.

### `frontend/tests/e2e/rem/rem-a11y.spec.ts` (NEW, 3 it)
- `axe-core` scan with zero AA violations; `aria-expanded` assertions
  on the 3 top-level `<details>` and the kebab; tab order across search
  → summary → leaves.

## 12. Commit / PR plan

Per `work-unit-commits` + `chained-pr` skills (total ≈ 1850 LOC,
> 400-line PR budget → chained PRs). Tracker branch inherits from
`feat/rem-viewer-v2` (the v2 of v1's `feat/frontend-rem-viewer`).

### Work-unit commits (D0..D6)

| # | Commit | Scope | Approx LOC |
|---|--------|-------|-----------|
| D0 | `feat(frontend): REM viewer v2 - esquemas Zod + yaml-writer` | `lib/rem/{rf,rnf,stakeholder}.schema.ts` + `yaml-writer.ts` + `yaml-roundtrip.ts` + `crud-errors.ts` + 4 unit tests + 6 fixtures | ~280 |
| D1 | `feat(frontend): REM viewer v2 - sidebar colapsable + resize handle` | `RemGroupShell.tsx` + `RemResizeHandle.tsx` + `RemSidebar.tsx` + `RemTreeNode.tsx` modific. + 2 unit tests + `types.ts` ext. | ~340 |
| D2 | `feat(frontend): REM viewer v2 - kebab + dialog (read-only)` | `RemKebabMenu.tsx` + `RemEditDialog.tsx` + `RemTreeNode` kebab slot + `RemSidebar` button + 1 unit test | ~280 |
| D3 | `feat(frontend): REM viewer v2 - 9 endpoints CRUD + i18n` | 9 endpoints + 9 endpoint tests + ~30 i18n keys (es + en + types) | ~420 |
| D4 | `feat(frontend): REM viewer v2 - submit + tree-region swap` | `forms/{RemRfForm,RemRnfForm,RemStakeholderForm}.tsx` + 3 form tests + `RemEditDialog` wiring + e2e CRUD spec | ~200 |
| D5 | `feat(frontend): REM viewer v2 - palette + detalle widen` | Tailwind token swaps across `RemSidebar`, `RemTreeNode`, `RemPrintButton`, `RemDetail` + `RemLayout` + `RemEditDialog`; `max-w-3xl` → `max-w-6xl`; snapshot baseline regen | ~180 |
| D6 | `docs(frontend): REM viewer v2 - README detallado` | `frontend/README.md` REM Viewer subsections (Architecture, Endpoints, Data flow, Running, Testing, Accessibility, i18n, Migration) | ~150 |

**Total**: ~1850 LOC across 7 work units.

### Chained PRs (per `auto-chain` preflight)

| PR | Commits | LOC | Notes |
|----|---------|-----|-------|
| PR1 — Foundation + sidebar UI | D0 + D1 | ~620 | Needs `size:exception` (≈ 400-line PR budget). Backend foundation (schemas + writer) + UI skeleton (collapsibles + resize). Each commit green individually. |
| PR2 — CRUD pipeline | D2 + D3 + D4 | ~900 | Needs `size:exception`. Dialog + 9 endpoints + form wiring. Largest PR — guarded by `size:exception` + 2 reviewers per `conventional-commits-and-prs` (> 400 lines). |
| PR3 — Polish + docs | D5 + D6 | ~330 | Within budget. Palette refresh (regenerate e2e snapshot baseline per v1 risk #5) + README. |

**PR template body** uses `conventional-commits-and-prs` Spanish template
(`Qué cambia` / `Por qué` / `Cómo se prueba` / `Checklist`). PR3's
"Cómo se prueba" includes the e2e `pnpm run test:e2e` and the snapshot
diff review.

## 13. Risk register (echo + design-time additions)

| # | Risk | Likelihood | Mitigation |
|---|------|------------|-----------|
| 1 | Deadline pressure (academic) | High | DD5 reuse (F2 pattern); D0..D6 each shippable independently; per AGENTS.md §8 work-unit-commits. |
| 2 | CRUD write corrupts YAML | Medium | `.bak.<ts>` snapshot + atomic `rename` + NFR-10 test. |
| 3 | Sidebar resize collides with scroll | Low | CSS `resize: horizontal` (DD2); handle hidden < `lg` (REQ-15#5). |
| 4 | `localStorage` desync across tabs | Low | Worst-case stale until reload; documented in README. No cross-tab `storage` event in v2 scope. |
| 5 | Palette refresh breaks e2e snapshot | Medium | Regenerate baseline in D5 commit; 5 % diff threshold per `playwright.config.ts` convention. |
| 6 | New endpoints introduce security gaps | Medium | Documented academic-only; no auth (REQ-08#1 v1). README section "Security" links to a TODO for production. |
| 7 | Cascading scope expansion | Low | Document each v1 file touch as "Deviation" in `apply-progress.md` (v1 precedent: batch 4 `a20a6bd`). |
| 8 | `frontend/AGENTS.md` size budget violations | Low | Split `RemEditDialog.tsx` into `.{tsx,Field.tsx,Confirm.tsx}` if > 250 LoC (per §11). |
| 9 | **NEW (design)**: Zod 4 vs Zod 3 signatures — `zodResolver` adapter may differ. `frontend/package.json:47` pins `zod@^4.4`; confirm `import { zodResolver } from '@hookform/resolvers/zod'` is in `dependencies` (or fall back to a manual validation adapter). | Low | Verify during D0; if missing, add a one-liner manual resolver (per RHF docs) — no new package. |
| 10 | **NEW (design)**: 9 endpoints × 9 tests = 81 new tests + D0-D4 unit tests = ~140 new tests; CI runtime impact. | Medium | Unit tests stay < 1 s total (in-memory mocks); e2e gated behind `E2E=1` already. |
| 11 | **NEW (design)**: Astro endpoint method dispatch (PUT + DELETE in `[uid].ts`) is unconventional — no precedent in the v1 codebase. Confirm Astro 7's `APIRoute` exports `PUT` / `DELETE` natively (`astro.config.mjs:35-39` uses `output: 'server'` + `@astrojs/node` standalone). | Low | Verified by spec; if Astro rejects, split into `[uid]/index.ts` (PUT) + `[uid]/delete.ts` (DELETE) — small refactor. |
| 12 | **NEW (design)**: `serializeYamlSubset` round-trip exactness vs migration script comment shape. The migration script prepends `# Stakeholder: <name>\n# Generado por migrate_to_doorstop.py` — the serializer must NOT add these comments on round-trip (NFR-11 allows whitespace diff, not content diff for header comments). | Medium | `serializeYamlSubset` writes only `{uid}:` header + body; the v1 parser skips `#` lines so read-after-write works. `serializeYamlSubset.test.ts` round-trips against 6 fixtures to assert. |
| 13 | **NEW (design)**: write endpoint running in Astro dev SSR may not have `node:fs/promises` write permission in production builds (Docker). | Low | Documented in README; production deployment must run Astro with `output: 'server'` already per `astro.config.mjs:39-40` (v1 contract). |

## 14. Open questions

| # | Question | Blocking `sdd-tasks`? |
|---|----------|-----------------------|
| 1 | Schema multi-line fields (`autores`, `fuentes`, `dependencias`) — **RESOLVED** by user: single-line only (matches v1 parser's coverage; multi-line deferred). | No. |
| 2 | Soft vs hard delete — **RESOLVED** by user: HARD delete. | No. |
| 3 | DP1 (persistence target) — **RESOLVED**: YAML files via extended hand-rolled parser. | No. |
| 4 | DP2 (sidebar persistence) — **RESOLVED**: localStorage per browser. | No. |
| 5 | DP3 (form library) — **RESOLVED**: `react-hook-form` + `zod` (already in `package.json`). | No. |
| 6 | Should the kebab menu include a third "Duplicate" action? | **DESIGN-TIME, NON-BLOCKING**: `sdd-tasks` can ship with 2 actions (Editar / Eliminar). Adding "Duplicar" later is a 1-component change. |
| 7 | Should `RemEditDialog` auto-select the newly-created leaf in the sidebar (DD5 implementation detail)? | **DESIGN-TIME, NON-BLOCKING**: `sdd-tasks` will lock "yes, scroll into view + `setSelectedUid`" — matches the proposal's §10#4 recommendation. If a reviewer pushes back, `setSelectedUid` is the only call site to change. |
| 8 | Should the resize handle use `mouseup` or `pointerup` for persistence? | **DESIGN-TIME, NON-BLOCKING**: CSS `resize: horizontal` fires `resize` event after `pointerup`; we listen to `resize`. Equivalents. |
| 9 | Should `RemEditDialog` be one component or split now (preempt the AGENTS.md §1 250-LoC budget)? | **DESIGN-TIME, NON-BLOCKING**: ship as one (~150 LoC estimated); split if it crosses 250 mid-implementation. |