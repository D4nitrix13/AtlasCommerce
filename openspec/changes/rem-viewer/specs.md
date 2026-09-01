# Spec — `rem-viewer`

## 1. Overview

Read-only browser viewer over the 81 Doorstop YAMLs in
`scripts/requirements/req/`: left tree (Stakeholders / RF by `module` /
RNF by `category`) + right field/value detail panel. Search, keyboard
nav, single + full-report print-to-PDF, WCAG 2.2 AA, es/en i18n at
`/[locale]/rem/`. Server-rendered (`output: "server"`); no login.

## 2. Data Model

YAML files use Doorstop's outer schema: top-level metadata
(`active`/`derived`/`header`/`level`/`links`/`normative`/`ref`/
`reviewed`) plus an inner block keyed by UID `rf_NNN` / `rnf_NNN` /
`stakeholder_NNN`. Top-level `links` is the cross-reference list
(between requirements); may be empty.

```ts
// frontend/src/lib/rem/types.ts
export type Kind = 'rf' | 'rnf';
export type Priority = 'H' | 'M' | 'L';
export type Status = 'pending' | 'in-analysis' | 'approved' | 'implemented' | 'verified';

export type Stakeholder = { uid: string; name: string; title: string; role: string; organization: string; notes: string; };

export type Requirement = {
  uid: string; code: string; title: string; rationale: string;
  priority: Priority; status: Status;
  module?: string; category?: string;
  verifiedBy: string[]; links: string[]; kind: Kind;
};

export type RemDataset = { rf: Requirement[]; rnf: Requirement[]; stakeholders: Stakeholder[]; };
```

Loader rules: `kind` from UID prefix; RF MUST have `module`, RNF MUST
have `category` (structural discriminator); top-level YAML `links`
wins over inner; `verifiedBy` is `[]` for RNF; stakeholders have empty
`code` and no priority/status.

## 3. Functional Requirements

### REQ-01 — Load YAML dataset
The system SHALL load the 81 YAMLs at request time, return a typed `RemDataset`, and cache for 5 minutes (module-scope `Map` keyed by folder + `lastLoadedAt`).

| # | Given | When | Then |
|---|-------|------|------|
| 1 | real `req/` folder | `loadAllRequirements()` runs | 48 `rf` + 30 `rnf` + 3 `stakeholders`; every `rf` has `module`; every `rnf` has `category` |
| 2 | cache primed at T0 | call at T0 + 60 s | same object reference (no re-read) |
| 3 | TTL = 5 min, primed at T0 | call at T0 + 5 min + 1 s (fake timers) | re-reads YAMLs |
| 4 | one file has invalid YAML | `loadAllRequirements()` runs | throws `RemParseError` whose message includes the file path |
| 5 | empty fixture folder | `loadAllRequirements()` runs | returns `{ rf: [], rnf: [], stakeholders: [] }` |

### REQ-02 — Hierarchical tree
The system SHALL group RF by `module`, RNF by `category`, keep stakeholders flat; sort members by `code` ascending.

| # | Given | When | Then |
|---|-------|------|------|
| 1 | RF includes `rf_001` (module `Autenticación`) and `rf_007` (module `Carrito`) | `tree.buildTree(dataset)` | RF branch has one node per unique `module`; `Autenticación` has exactly 6 items |
| 2 | RNF includes `rnf_004` (category `Seguridad`) and `rnf_010` (category `Rendimiento / Fiabilidad`) | `tree.buildTree(dataset)` | RNF branch has one node per unique `category` |
| 3 | 3 stakeholders | `tree.buildTree(dataset)` | Stakeholders branch has 3 leaf nodes, no nesting |
| 4 | `Autenticación` group | `tree.buildTree(dataset)` | items ordered `RF-AUTH-001` … `RF-AUTH-006` |
| 5 | fixed dataset | `buildTree` called 10× | results deep-equal every time (deterministic) |

### REQ-03 — Search across fields
The system SHALL provide case-insensitive substring search across `code`/`title`/`module`/`category`/`rationale`/`status`, scored and sorted (score desc, code asc).

Score table: exact code = 100, code prefix = 60, title exact = 50, title substring = 30, module/category/rationale = 10, status = 5.

| # | Given | When | Then |
|---|-------|------|------|
| 1 | dataset contains `RF-AUTH-001` | query `RF-AUTH-001` | `rf_001` first with score 100 |
| 2 | `rnf_010` titled "Sesiones en Redis" | query `sesiones en redis` (lowercase) | `rnf_010` in result list |
| 3 | `rf_001.rationale` mentions `AuthController` | query `AuthController` | `rf_001` in result list |
| 4 | empty query | `search.match(dataset, '')` | 78 items, each score 0 |
| 5 | query `zzzzzz-no-match` | sidebar receives empty list | renders `RemEmptyState` with copy "Sin resultados" + "Limpiar búsqueda" button |

### REQ-04 — Select a requirement
Sidebar selection owns React state, syncs to URL (REQ-12), updates panel synchronously on click or Enter.

| # | Given | When | Then |
|---|-------|------|------|
| 1 | page loaded at `/es/rem` with `rf_001` preselected | user clicks `Carrito > RF-CART-010` | panel header shows `RF-CART-010`; title "Agregar items al carrito" |
| 2 | fresh page load | panel renders | shows `RF-AUTH-001` ("Login con email y contraseña") with status `implemented` |
| 3 | user typed a query and selected `rnf_010` | user clears the search box | tree re-expands to full dataset; panel still shows `rnf_010` |

### REQ-05 — Render detail panel
Header row (`code` + full `title`) + two-column field/value table with `PD` ("Por Definir") badge for unset fields and red `?` for empty `links` (RF only).

| # | Given | When | Then |
|---|-------|------|------|
| 1 | `rf_007` selected (code `RF-CART-010`, module `Carrito`, priority `H`, status `implemented`, verifiedBy `[stakeholder_001, stakeholder_002]`) | panel renders | header `RF-CART-010 — Agregar items al carrito`; rows: Código, Título, Módulo, Prioridad, Estado, Verificado por, Fuentes/Dependencias, Descripción; "Verificado por" shows "Daniel Benjamin Perez Morales, Andy Joe Sanchez Calero" |
| 2 | `rnf_004` selected (code `RNF-SEC-010`, category `Seguridad`, priority `H`, status `verified`) | panel renders | "Categoría" row shows `Seguridad`; no "Verificado por" row |
| 3 | stakeholder with `notes: ""` (fixture) | panel renders | "Notas" row shows `PD` badge |
| 4 | `rf_001` selected (its `links` is `[]`) | panel renders | "Fuentes/Dependencias" row shows red `?` with `aria-label="Sin fuentes"` |
| 5 | `stakeholder_001` selected | panel renders | rows: UID, Nombre, Título, Rol, Organización, Notas; no "Prioridad" / "Estado" |

### REQ-06 — Print single requirement
Print current selection on one A4 page via `window.print()` + print stylesheet (`styles/rem-print.css`).

| # | Given | When | Then |
|---|-------|------|------|
| 1 | `rf_001` selected | click "Imprimir PDF" | `window.print()` called within 100 ms |
| 2 | `@media print` active | stylesheet evaluated | `.rem-sidebar` is `display: none`; `.rem-detail` has `page-break-after: always` |
| 3 | page loaded | `page.emulateMedia({ media: 'print' })` then save PDF | PDF has ≥ 2 pages |

### REQ-07 — Print full report
Print cover + TOC + every requirement paginated.

| # | Given | When | Then |
|---|-------|------|------|
| 1 | "Informe completo" clicked | `@media print` with `body[data-rem-scope="full"]` | page 1 = cover ("REM — Panda Estampados" + date); page 2 = TOC listing all 78 codes |
| 2 | full-report stylesheet active | PDF renders | each item preceded by `page-break-before: always`; PDF ≥ 80 pages (1 cover + 1 TOC + 78 details) |

### REQ-08 — i18n es / en
Route at `/es/rem/` and `/en/rem/`; UI strings under `rem.*` namespace; `Translations` type enforces es/en parity.

| # | Given | When | Then |
|---|-------|------|------|
| 1 | page mounted at `pages/[locale]/rem/index.astro` | navigate `/es/rem` and `/en/rem` | both render with locale-appropriate copy |
| 2 | keys under `rem.*` in `es.ts` and `en.ts` | `pnpm run typecheck` | missing key is a compile error |
| 3 | `rf_001` selected at `/es/rem` | navigate `/en/rem?id=rf_001` | panel still shows `rf_001` with English labels |

### REQ-09 — Keyboard navigation
Tab → search; Arrow keys → tree; Enter → select; Escape → clear search. Tree uses `role="tree"` / `role="treeitem"` / `aria-selected` / `aria-current="true"`.

| # | Given | When | Then |
|---|-------|------|------|
| 1 | search box is active element | Tab once | focus moves to first tree node (`Stakeholders` group header) |
| 2 | focus on `Stakeholders > stakeholder_001` | ArrowDown | focus moves to `Stakeholders > stakeholder_002` |
| 3 | focus on `RF > Carrito > RF-CART-010` | Enter | panel renders `rf_007`; focus stays in tree |
| 4 | search box has `redis` | Escape | value becomes empty; focus stays on input |

### REQ-10 — Empty / error / loading UI states
All five AGENTS.md §11 states covered (loading, empty, error, success, disabled).

| # | Given | When | Then |
|---|-------|------|------|
| 1 | page SSR with dataset, island not hydrated | first paint | sidebar shows skeleton inside `[data-rem-skeleton]` with `aria-busy="true"` |
| 2 | zero-match search | sidebar renders | shows `RemEmptyState`; tree hidden |
| 3 | loader threw `RemParseError` (fixture) | page frontmatter catches | renders `Alert.astro` with copy "No se pudieron cargar los requisitos" + error code; sidebar and detail NOT rendered |

### REQ-11 — WCAG 2.2 AA
Semantic landmarks, visible focus ring, color contrast ≥ 4.5:1 for markers, Lighthouse accessibility ≥ 95.

| # | Given | When | Then |
|---|-------|------|------|
| 1 | page at `/es/rem` | DOM inspected | exactly one `<header>`, `<nav>` (sidebar), `<main>` (detail), `<footer>`; all `aria-label`ed |
| 2 | focus on search box and first tree node | computed styles | `outline` non-`none`; contrast vs background ≥ 3:1 |
| 3 | "PD" badge (`bg #fef3c7`, text `#92400e`); "?" (`bg #fee2e2`, text `#b91c1c`) | contrast computed | both ≥ 4.5:1 against background |

### REQ-12 — URL hash share
Selection persists in `?id=<uid>` (or `#<uid>`) — deep links open the same detail; invalid id falls back to default (`RF-AUTH-001`).

| # | Given | When | Then |
|---|-------|------|------|
| 1 | user clicks `RF-CART-010` | React state commits | `window.location.search` becomes `?id=rf_007` (via `history.replaceState`, no reload) |
| 2 | page loaded with `?id=rf_007` | page hydrates | panel renders `RF-CART-010` without user clicking |
| 3 | page loaded with `?id=does-not-exist` | page hydrates | falls back to `RF-AUTH-001`; no crash |

## 4. Non-Functional Requirements

| ID | Statement | Test |
|----|-----------|------|
| NFR-01 | TTFB ≤ 500 ms (p95) for `GET /es/rem` warm cache | E2E `response.timing()` |
| NFR-02 | `search.match(dataset, query)` ≤ 50 ms for 81 items | Unit — `performance.now()` around call |
| NFR-03 | Lighthouse accessibility ≥ 95 on `/es/rem` 1280×800 | E2E via `playwright-lighthouse` |
| NFR-04 | Viewer route JS bundle ≤ 100 KB gzipped | E2E — sum `performance.getEntriesByType('resource')` transfer sizes |
| NFR-05 | Vitest line coverage ≥ 85 % for `lib/rem/*` and `components/rem/*`; one test per §3 scenario; no `skip`/`todo`/`xfail` | CI step + grep rule |
| NFR-06 | Same dataset → byte-identical HTML (modulo cover date) | Snapshot test on rendered tree |
| NFR-07 | Every UI string has es + en key | REQ-08 #2 |
| NFR-08 | Chromium primary; Firefox + Safari best-effort (esp. print) | Documented; Chromium CI gate |

## 5. Out of Scope

- No write-back to Doorstop; no hand-editing existing `.yml` files.
- No backend (Laravel) changes; no multi-tenant theming for viewer.
- No auth / RBAC / rate limiting.
- No server-side PDF rendering (no Chromium binary in CI).
- No real-time collaboration, comments, or annotations.
- No traceability matrix or impact-analysis view.
- No modifications to `migrate_to_doorstop.py` unless DP1 accepted.
- No new npm packages unless DP2 picks `react-to-print`.

## 6. Open Questions

1. **DP1 sequencing** (from proposal): extend YAML schema now or ship viewer first? Spec supports both (extra fields surface when present, `PD` when absent). **Blocked on user input.**
2. **PDF library**: `window.print()` only, or add `react-to-print`? Default: `window.print()` + print stylesheet. **Blocked on user input.**
3. **Browser for class session** (from proposal): default Chromium; affects Firefox/Safari fallback priority.
4. **"Informe completo" scope**: required for delivery, or hide the button? Spec covers both.
5. **Auth gate**: confirmed public per proposal; not blocking.
6. **`?id` vs `#uid`**: spec supports both; design phase picks one.
7. **Stakeholder UID mismatch** (seed `daniel` vs YAML `stakeholder_001`) is internal to the migration script; viewer reads UIDs only — not blocking.

## Test Plan Summary

| File | Tests | REQs |
|------|------:|------|
| `tests/unit/rem/loader.test.ts` | 5 | REQ-01 |
| `tests/unit/rem/tree.test.ts` | 5 | REQ-02, NFR-06 |
| `tests/unit/rem/search.test.ts` | 5 | REQ-03, NFR-02 |
| `tests/unit/components/rem/RemSidebar.test.tsx` | 7 | REQ-03#5, REQ-04#3, REQ-09×4, REQ-10#1, REQ-12#1,#3 |
| `tests/unit/components/rem/RemDetail.test.tsx` | 3 | REQ-04#2, REQ-05#1,#2,#5 |
| `tests/unit/components/rem/RemFieldRow.test.tsx` | 2 | REQ-05#3,#4 |
| `tests/unit/components/rem/RemPrintButton.test.tsx` | 2 | REQ-06#1,#2 |
| `tests/unit/components/rem/RemError.test.tsx` | 1 | REQ-10#3 |
| `tests/e2e/rem/rem-viewer.spec.ts` | 9 | REQ-04#1, REQ-08×2, REQ-11×3, REQ-12#2, NFR-01, NFR-03, NFR-04 |
| `tests/e2e/rem/rem-pdf.spec.ts` | 3 | REQ-06#3, REQ-07×2 |
| **Total** | **42** | |

Every §3 scenario maps 1:1 to one test method. No `skip`/`todo`/`xfail`.