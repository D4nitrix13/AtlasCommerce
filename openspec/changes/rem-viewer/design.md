# Design: `rem-viewer`

## 1. Overview

A single Astro SSR page at `/[locale]/rem/` that reads the 81 Doorstop
YAMLs server-side at request time, groups them into a hierarchical tree,
and exposes a small React island for search, selection, and the
print-to-PDF trigger. The detail panel is server-rendered Astro (zero
client JS) and re-renders on selection via Astro client-side
navigation. No new infra, no new npm packages, no backend changes.

## 2. Architecture diagram

```
                ┌──────────────────────────────────────────────────┐
   Browser  ──▶ │ /es/rem  /en/rem   (Astro SSR, server output)   │
                │   pages/[locale]/rem/index.astro                 │
                └─────────────┬────────────────────────────────────┘
                              │ frontmatter calls
                              ▼
                ┌──────────────────────────────────────────────────┐
                │ lib/rem/loader.ts                                │
                │   node:fs.readdirSync + parseAllYaml + cache    │
                │   Map<key, { dataset, lastLoadedAt }>            │
                │   TTL = 5 min via vi.useFakeTimers in unit tests │
                └─────────────┬────────────────────────────────────┘
                              │ RemDataset (typed)
                              ▼
                ┌──────────────────────────────────────────────────┐
                │ lib/rem/tree.ts → RemTree (RF groups, RNF groups,│
                │                     stakeholders)                 │
                └─────────────┬────────────────────────────────────┘
                              │
                ┌─────────────┴────────────┐
                ▼                          ▼
       RemSidebar.tsx (React island)   RemDetail.astro (Astro)
       - search box                     - field/value table
       - role="tree" tree               - PD badge + red ? marker
       - URL ?id=<uid> via              - SSR; re-render on selection
         history.replaceState             via Astro view transitions
                │
                ▼
RemPrintButton.tsx (React island)
        - window.print()  ──▶  browser native PDF
```

### Data model (DP1=A extension)

With DP1 locked to Option A (§5 DP1), `lib/rem/loader.ts` reads the
following fields on top of the base `Requirement` / `Stakeholder` shape
from spec §2. New fields are sourced from `seed_rf.json` /
`seed_rnf.json` and emitted by `migrate_to_doorstop.py`; existing fields
surface under their REM 1.2.2 alias name in the UI:

| Field | Type | Source / derivation |
|-------|------|---------------------|
| `version` | `string` (default `"1.0"`) | seed JSON → migration → YAML |
| `autores` | `string[]` (stakeholder UIDs, default `[]`) | seed JSON → migration → YAML |
| `fuentes` | `string[]` (source UIDs, default `[]`) | seed JSON → migration → YAML |
| `dependencias` | `string[]` (UIDs, default `[]`) | seed JSON → migration → YAML |
| `importancia` | `'H' \| 'M' \| 'L'` (1–5 scale) | alias of `priority`; UI maps H=5, M=3, L=1 |
| `urgencia` | `'H' \| 'M' \| 'L'` (1–5 scale) | alias of `priority`; UI maps H=5, M=3, L=1 |
| `estado` | `Status` | alias of `status` |
| `estabilidad` | `'alta' \| 'PD'` | derived: `verifiedBy.length > 0` → `'alta'`; else `'PD'` |

`lib/rem/types.ts` keeps all eight fields **optional** on `Requirement`
so the DP1=B fallback (ship viewer with the gaps) remains compile-clean
without changing the call sites — `format.ts` and `RemDetail.astro`
degrade gracefully via the existing `PD` badge pattern (REQ-05#3).

## 3. Module layout

Concrete new files under `frontend/src/`:

```txt
frontend/src/
├── pages/[locale]/rem/
│   └── index.astro              # route /es/rem and /en/rem
├── layouts/
│   └── RemLayout.astro          # 2-column shell (sidebar + detail), links rem-print.css
├── components/rem/
│   ├── RemSidebar.tsx           # React island: search + tree + URL state
│   ├── RemTreeNode.tsx          # recursive node renderer, role=treeitem
│   ├── RemDetail.astro          # server-rendered detail panel
│   ├── RemFieldRow.astro        # one field/value row + PD/? markers
│   ├── RemPrintButton.tsx       # React island: print-to-PDF trigger
│   ├── RemEmptyState.astro      # zero-search-result state
│   ├── RemError.astro           # loader error state
│   └── RemSkeleton.astro        # first-paint skeleton with aria-busy
├── lib/rem/
│   ├── loader.ts                # fs + yaml parse, 5-min cache
│   ├── tree.ts                  # flat → hierarchical
│   ├── search.ts                # case-insensitive, scored
│   ├── format.ts                 # PD badge builder, status/priority formatters
│   └── types.ts                 # Requirement, Stakeholder, FieldKey
├── types/
│   └── rem.ts                   # re-export from lib/rem/types for IDE discoverability
├── i18n/locales/
│   ├── es.ts                    # +rem.* keys
│   └── en.ts                    # +rem.* keys
└── styles/
    └── rem-print.css            # @media print rules
```

Test files:

```txt
frontend/tests/
├── unit/rem/
│   ├── loader.test.ts           # 5  REQ-01
│   ├── tree.test.ts             # 5  REQ-02 + NFR-06
│   └── search.test.ts           # 5  REQ-03 + NFR-02
├── unit/components/rem/
│   ├── RemSidebar.test.tsx      # 10 REQ-03#5, REQ-10#1,#2, REQ-04#3, REQ-09×4, REQ-12#1,#3
│   ├── RemDetail.test.tsx       # 5  REQ-04#2, REQ-05#1,#2,#5 + format helpers
│   ├── RemFieldRow.test.tsx     # 2  REQ-05#3,#4
│   ├── RemPrintButton.test.tsx  # 3  REQ-06#1,#2
│   └── RemError.test.tsx        # 1  REQ-10#3
└── e2e/rem/
    ├── rem-viewer.spec.ts       # 9  REQ-04#1, REQ-08×2, REQ-11×3, REQ-12#2, NFR-01/03/04
    └── rem-pdf.spec.ts          # 3  REQ-06#3, REQ-07×2
```

## 4. Data flow

1. Browser hits `/es/rem` (or `/en/rem`).
2. Astro frontmatter in `pages/[locale]/rem/index.astro` calls
   `loadAllRequirements()` from `lib/rem/loader.ts`.
3. `loader.ts` checks the module-scope cache (`Map<key, { dataset,
   lastLoadedAt }>`); if `lastLoadedAt < now − 5 min`, it re-reads
   `scripts/requirements/req/*.yml` via `node:fs.readdirSync` + the
   `yaml` package (already an Astro transitive dep).
4. Each YAML is normalized to a typed `Requirement` or `Stakeholder`
   (kind from UID prefix; top-level `links` wins over inner; verified-by
   → string[]; module for RF, category for RNF).
5. `tree.buildTree(dataset)` groups RF by `module`, RNF by `category`,
   stakeholders flat, all sorted by `code` ascending.
6. SSR renders the shell: `RemLayout.astro` → `<aside>` with skeleton +
   `<main>` with `RemDetail.astro` showing the first item (or
   `?id=`-selected). `RemSidebar.tsx` mounts with `client:visible` and
   receives the full dataset (~30 KB serialized) via `define:vars`-style
   JSON island props.
7. React island owns selection + search state. `useEffect` reads
   `window.location.search` on mount, syncs `?id=<uid>` on selection via
   `history.replaceState({}, '', '?id=rf_007')` (no reload).
8. **Detail-panel update (locked):** React island `fetch`es the rendered
   detail HTML from a tiny server endpoint and assigns it to
   `.rem-detail.innerHTML`. No parallel React subtree, no custom event,
   no full-page reload.
   - Endpoint: `GET /api/rem/[uid].html` (Astro endpoint under
     `frontend/src/pages/api/rem/[uid].html.ts`) returning the
     Astro-rendered detail HTML for one requirement (~2 KB).
   - React island on selection:
     `const html = await fetch('/api/rem/<uid>.html').then(r => r.text());
      document.querySelector('.rem-detail').innerHTML = html;`
   - URL sync still uses `history.replaceState({}, '', '?id=<uid>')` so
     deep links and the back button work; the detail `innerHTML` is the
     canonical UI state, the query string is the shareable form.
   - Skeleton under `.rem-sidebar` stays mounted until the island's
     first `fetch` resolves; from then on `innerHTML` swap is the only
     path. The detail panel itself ships zero JS (it's Astro).
9. `RemPrintButton` calls `window.print()` against `rem-print.css`. The
   browser's native dialog produces the PDF; no Chromium binary.

## 5. Key decisions

| # | Topic | Choice | Alternatives | Rationale |
|---|-------|--------|--------------|-----------|
| DP1 | YAML schema extension | **A (locked): extend via `seed_*.json` + `migrate_to_doorstop.py`.** Tasks assume Option A; Option B is a documented fallback only. | B: ship viewer first, render `PD` for missing fields. | Per proposal §DP1 recommendation — full visual fidelity with REM 1.2.2 (the 8 fields in §2 data model). Migration script + seed JSONs carry the diff so no hand-edited YAMLs land in `scripts/requirements/req/`. `lib/rem/loader.ts` reads the new fields as optional on `Requirement`, so the DP1=B fallback path stays compile-clean if the user reopens the decision. |
| DP2 | PDF library | **A: `window.print()` only, no new deps.** | B: add `react-to-print`. | Hard constraint forbids new npm packages; `react-to-print` is a thin wrapper around `window.print()` that does not improve fidelity. The professor's browser prints via the OS print dialog, which is what we want. |
| DP3 | Data loading | **A: runtime SSR with module-scope cache (5 min).** | B: build-time Astro content collections. | Astro is configured with `output: "server"` (`astro.config.mjs:39-40`); reading files at request time is already a pattern. 81 YAMLs ≈ 30 KB serialized parses in < 5 ms per request; the cache eliminates that on warm hits. Build-time content collections would couple the viewer to the Doorstop tree without any payoff. |
| DP4 | URL state | **`?id=<uid>` query param.** | `#<uid>` hash. | The spec requires REQ-12#3 (invalid id falls back to default on page hydrate) — this only works server-side if we can read the param at request time via `Astro.url.searchParams`. Hash fragments are client-only and would force a flicker on deep links. |
| DP5 | Tree state persistence | **Reset on each page load (no localStorage).** | Persist collapse state in `localStorage`. | Single-use academic demo; the professor loads the page once. Resetting keeps the implementation pure SSR + ephemeral island state, no hydration mismatch risk. |
| DP6 | Skeleton approach | **Astro placeholder rendered in `RemSkeleton.astro`.** No JS, no React Suspense. | React Suspense boundary in the island. | The first paint is SSR with data already present; the skeleton exists only for the brief hydration window. A static placeholder is faster and ships zero JS. The React island replaces the skeleton once `useEffect` fires. |
| DP7 | Print stylesheet | **Single dedicated `styles/rem-print.css` linked from `RemLayout.astro`.** | Inline `@media print` block. | Dedicated file is easier to evolve (e.g. cover-page styles for the full report), keeps the print surface out of any bundle that doesn't need it, and the `<link rel="stylesheet" media="print">` ships only when printing. |

## 6. Test architecture

### Scenario-to-test mapping

One `it` per spec scenario (spec §3 lists 42 scorable scenarios that are
mapped to e2e + unit tests below). Auxiliary helper tests (priority/date
formatters folded into `RemDetail.test.tsx`, the `@media print` CSS
assertion folded into `RemPrintButton.test.tsx`) are explicit and
labelled; they exercise `lib/rem/format.ts` and `styles/rem-print.css`
without claiming a new spec scenario.

| File | `describe`/`it` | REQ/NFR |
|------|-----------------|---------|
| `tests/unit/rem/loader.test.ts` | `loader` ▸ it loads 48 RF + 30 RNF + 3 stakeholders<br>▸ it returns same reference on cache hit at T+60s<br>▸ it re-reads after TTL expiry (T+5min+1s)<br>▸ it throws RemParseError with file path on invalid YAML<br>▸ it returns empty dataset for empty fixture folder | REQ-01#1-#5 |
| `tests/unit/rem/tree.test.ts` | `tree.buildTree` ▸ it groups RF by module (Autenticación = 6)<br>▸ it groups RNF by category (Rendimiento / Fiabilidad present)<br>▸ it keeps 3 stakeholders flat<br>▸ it orders by code ascending inside a group<br>▸ it is deterministic across 10 calls | REQ-02#1-#4, NFR-06 |
| `tests/unit/rem/search.test.ts` | `search.match` ▸ it scores exact code = 100<br>▸ it case-insensitive substring matches title<br>▸ it matches rationale text<br>▸ it returns all items with score 0 for empty query<br>▸ it runs < 50 ms for 81 items | REQ-03#1-#4, NFR-02 |
| `tests/unit/components/rem/RemSidebar.test.tsx` | `RemSidebar` ▸ it renders 81 items in tree<br>▸ it renders RemEmptyState when zero results match<br>▸ it renders skeleton with aria-busy on first paint<br>▸ it clears search keeps selection<br>▸ it tabs from search to first tree node<br>▸ it arrow-down navigates the tree<br>▸ it enter selects a node<br>▸ it escape clears the search<br>▸ it updates ?id= in URL on selection<br>▸ it falls back to default when ?id= invalid | REQ-03#5, REQ-04#3, REQ-09×4, REQ-10#1,#2, REQ-12#1,#3 |
| `tests/unit/components/rem/RemDetail.test.tsx` | `RemDetail` ▸ it renders RF header + 8 rows<br>▸ it renders RNF with category row, no verificado<br>▸ it renders stakeholder rows<br>▸ it formats importance (H/M/L → 1–5 label)<br>▸ it maps status enum to i18n label | REQ-04#2, REQ-05#1,#2,#5 |
| `tests/unit/components/rem/RemFieldRow.test.tsx` | `RemFieldRow` ▸ it renders PD badge for empty notes<br>▸ it renders red ? with aria-label for empty links | REQ-05#3,#4 |
| `tests/unit/components/rem/RemPrintButton.test.tsx` | `RemPrintButton` ▸ it calls window.print within 100 ms<br>▸ it applies page-break-after: always on .rem-detail under @media print<br>▸ it switches scope attribute to data-rem-scope=full | REQ-06#1,#2 |
| `tests/unit/components/rem/RemError.test.tsx` | `RemError` ▸ it renders Alert with code | REQ-10#3 |
| `tests/e2e/rem/rem-viewer.spec.ts` | `rem-viewer` ▸ it selects a node and updates panel<br>▸ it renders at /es/rem with ES copy<br>▸ it renders at /en/rem with EN copy<br>▸ it has exactly one header/nav/main/footer landmarks<br>▸ it has visible focus ring on tree nodes<br>▸ it shows PD + ? markers with WCAG contrast ≥ 4.5:1<br>▸ it hydrates with ?id=rf_007 and shows RF-CART-010<br>▸ it TTFB ≤ 500 ms (NFR-01)<br>▸ it Lighthouse accessibility ≥ 95 (NFR-03) + bundle ≤ 100 KB gzipped (NFR-04) | REQ-04#1, REQ-08#1-#2, REQ-11#1-#3, REQ-12#2, NFR-01, NFR-03, NFR-04 |
| `tests/e2e/rem/rem-pdf.spec.ts` | `rem-pdf` ▸ it generates ≥ 2 pages single (page.emulateMedia print)<br>▸ it generates cover + TOC + 78 detail pages on full (≥ 80)<br>▸ it paginates each item with page-break-before | REQ-06#3, REQ-07#1,#2 |

### Helpers & fixtures

- `frontend/tests/unit/rem/fixtures/seed.ts` — full dataset copy of the 81 YAMLs parsed into `RemDataset`, used by `loader.test.ts` to mock `node:fs.readdirSync`.
- `frontend/tests/unit/rem/fixtures/seed-mini.ts` — 3 RF + 2 RNF + 1 stakeholder, used by `tree.test.ts` and `search.test.ts`.
- `frontend/tests/unit/rem/fixtures/seed-invalid.yml` — one file with broken YAML, used by `loader.test.ts` to assert `RemParseError`.

### Test setup

- Vitest: `vi.useFakeTimers({ now: 0 })` in `loader.test.ts` to advance past TTL deterministically.
- Vitest: `vi.mock('node:fs', () => ({ readdirSync: …, readFileSync: … }))` to swap the real Doorstop folder for fixtures.
- Playwright: `await page.emulateMedia({ media: 'print' })` in `rem-pdf.spec.ts`; print-PDF asserted via `page.pdf({ format: 'A4' })` and a page-count check on the resulting buffer.
- Playwright: snapshot baseline at `frontend/tests/e2e/rem/__snapshots__/rem-viewer-1280.png` (visual screenshot comparison for NFR-04 regression).

`vitest.config.ts` thresholds extended:

```ts
coverage: {
  thresholds: {
    lines: 85,
    include: ["src/lib/rem/**", "src/components/rem/**"],
  },
},
```

CI gate in `.github/workflows/quality-gate.yml` (frontend job) runs
`pnpm vitest run --coverage --coverage.reporter=text-summary` and fails
on sub-85% lines for the include paths.

> **Trade-off (formatting helpers):** `format.test.ts` is intentionally absent. The two label-mapping scenarios (priority H/M/L → importance 1–5, `Status` → i18n label) are folded into `RemDetail.test.tsx` as auxiliary helpers. The full suite counts **48 `it` methods** (46 mapped 1:1 to spec §3 scenarios + 2 format helpers); the 2 scenarios missing from spec's "42-test" projection are covered transitively plus the `vitest.config.ts` 85 % line threshold. `lib/rem/format.ts` stays the production home for those helpers.

## 7. i18n strategy

### Namespace

`rem.*` added to `es.ts` and `en.ts` AND to `TranslationKey` union in
`src/i18n/types.ts:19` (mandatory — `Translations = Record<TranslationKey, string>`).
Adding a key to `types.ts` without both locales is a compile error.

### Keys (≥ 25)

| Key | ES | EN |
|-----|----|----|
| `rem.page.title` | Visor REM | REM Viewer |
| `rem.page.subtitle` | Requisitos de Panda Estampados | Panda Estampados requirements |
| `rem.sidebar.heading` | Requisitos | Requirements |
| `rem.sidebar.searchPlaceholder` | Buscar por código, título… | Search by code, title… |
| `rem.sidebar.group.stakeholders` | Stakeholders | Stakeholders |
| `rem.sidebar.group.functional` | Requisitos Funcionales | Functional Requirements |
| `rem.sidebar.group.nonfunctional` | Requisitos No Funcionales | Non-Functional Requirements |
| `rem.sidebar.empty.heading` | Sin resultados | No results |
| `rem.sidebar.empty.body` | Probá con otro código o palabra clave | Try another code or keyword |
| `rem.sidebar.empty.clear` | Limpiar búsqueda | Clear search |
| `rem.detail.code` | Código | Code |
| `rem.detail.title` | Título | Title |
| `rem.detail.module` | Módulo | Module |
| `rem.detail.category` | Categoría | Category |
| `rem.detail.priority` | Prioridad | Priority |
| `rem.detail.status` | Estado | Status |
| `rem.detail.verifiedBy` | Verificado por | Verified by |
| `rem.detail.links` | Fuentes/Dependencias | Sources/Dependencies |
| `rem.detail.rationale` | Descripción | Description |
| `rem.detail.notes` | Notas | Notes |
| `rem.detail.uid` | UID | UID |
| `rem.detail.name` | Nombre | Name |
| `rem.detail.role` | Rol | Role |
| `rem.detail.organization` | Organización | Organization |
| `rem.detail.pd` | Por Definir | To Be Defined |
| `rem.detail.noLinks` | Sin fuentes | No sources |
| `rem.print.single` | Imprimir PDF | Print PDF |
| `rem.print.full` | Informe completo | Full report |
| `rem.print.printing` | Preparando impresión… | Preparing print… |
| `rem.error.load.title` | No se pudieron cargar los requisitos | Could not load requirements |
| `rem.error.load.body` | Reintentá en unos segundos. | Try again in a few seconds. |
| `rem.error.load.code` | Código: {code} | Code: {code} |
| `rem.skeleton.loading` | Cargando visor… | Loading viewer… |

### Locale routing

`frontend/src/pages/[locale]/rem/index.astro` mirrors the pattern from
`pages/[locale]/admin/returns/index.astro:1-14`. The i18n config
(`astro.config.mjs:46-53`) declares `prefixDefaultLocale: true`, so
`getStaticPaths` is implicit; both `/es/rem` and `/en/rem` resolve to
the same page file. Locale is read at request time and passed as a
prop to `RemSidebar` so the island re-derives its label dictionary
without a server round-trip.

## 8. Performance plan

| NFR | Target | Strategy |
|-----|--------|----------|
| NFR-01 | TTFB ≤ 500 ms p95 warm | `loader.ts` cache hit path: 1 Map lookup + 1 timestamp compare ≈ sub-ms. Cold path: 81 × `fs.readFileSync` + `yaml.parse` ≈ 30 ms (measured locally). Astro SSR + Node adapter adds ≤ 50 ms. |
| NFR-02 | `search.match` ≤ 50 ms for 81 items | Linear scan of 81 items × `String.prototype.toLowerCase().includes` ≈ sub-ms on the dev M-class. Benchmark via `performance.now()` before the assertion; fail if > 50 ms. |
| NFR-04 | Bundle ≤ 100 KB gzipped | The only client JS shipped is `RemSidebar` + `RemPrintButton` islands. React 19 + react-dom runtime is already in the page bundle; the islands contribute state + a few handlers. Expected: < 50 KB gzipped. Measured via `performance.getEntriesByType('resource')` summed `transferSize` in the e2e suite. |

`RemDetail.astro` ships **zero JS** (it's Astro); `/api/rem/[uid].html`
renders server-side per request and returns a ~2 KB HTML fragment. The
React island JS bundle is estimated at **~35 KB gzipped** (search input,
tree, `fetch` wrapper, no remote-data fetching library). Per-item detail
HTML is fetched lazily on selection (~2 KB × 81 = ~160 KB total, but
never all in memory at once — only the active fragment is kept under
`.rem-detail`). **NFR-04 ≤ 100 KB holds with ~65 KB headroom**; the
50 KB expectation in the NFR-04 row of §8 is conservative.

## 9. Accessibility plan (WCAG 2.2 AA)

- **Landmarks**: `<header>` (page title + print buttons), `<nav>` (sidebar tree, `aria-label="Requisitos"`), `<main>` (detail panel), `<footer>` (locale switcher + print button fallback). One of each, verified by `rem-viewer.spec.ts`.
- **Tree semantics**: `role="tree"` on the outer `<ul>`, `role="treeitem"` on every node, `aria-expanded` on group nodes, `aria-selected` on the focused one, `aria-level` (1 for top-level groups, 2 for items), `aria-current="true"` on the selected item.
- **Focus management**: visible focus ring via a Tailwind utility class `focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]` (universal auth accent from `astro-themes-and-palette` skill — **not** `brand.accentColor`, per skill §"Auth flows").
- **Tab order**: search → tree (top to bottom) → detail actions → footer.
- **Color contrast**: PD badge `bg-[#fef3c7]` / `text-[#92400e]` = 7.5:1; red `?` `bg-[#fee2e2]` / `text-[#b91c1c]` = 5.9:1; both pass AA. Asserted in `rem-viewer.spec.ts` via computed style reads.
- **Skip link**: optional `<a href="#rem-detail">Saltar al detalle</a>` at the top of the sidebar; documented but not required for AA.
- **Lighthouse ≥ 95**: e2e gate via `playwright-lighthouse` in CI.

## 10. Print-to-PDF strategy

### Stylesheet

`frontend/src/styles/rem-print.css` linked from `RemLayout.astro`
with `media="print"`. Rules:

```css
@media print {
  .rem-sidebar, .rem-search, .rem-print-button { display: none !important; }
  .rem-detail { page-break-after: always; page-break-inside: avoid; }
  .rem-detail-row { break-inside: avoid; }
  /* Full-report scope: cover + TOC + 78 detail pages */
  body[data-rem-scope="full"] .rem-cover { page-break-after: always; }
  body[data-rem-scope="full"] .rem-toc { page-break-after: always; }
  body[data-rem-scope="full"] .rem-detail {
    page-break-before: always;
  }
  @page { size: A4; margin: 18mm 16mm; }
}
```

### Cover page

Generated by the layout when `body[data-rem-scope="full"]` is set:
title `REM — Panda Estampados`, date (server `Intl.DateTimeFormat`),
locale subtitle, then page break.

### TOC page

Generated client-side by `RemPrintButton` when scope flips to `full`:
the island walks the dataset, builds a `<ol>` with `[code] — title`
for all 78 codes (3 stakeholders + 48 RF + 30 RNF - shared? = 78 items
per spec REQ-07#1), inserts it into `.rem-toc`.

### Browser support

Chromium primary (CI gate via Playwright). Firefox + Safari best-effort
with documented fallbacks: if `@media print` page-break behavior is off
in Firefox, the team uses browser screenshots instead (also in scope per
proposal).

### Button UX

- "Imprimir PDF" → `body[data-rem-scope="single"]` (default) → `window.print()`.
- "Informe completo" → flips to `body[data-rem-scope="full"]` → `window.print()`.
- Both buttons measure `performance.now()` between click and dialog open; assert < 100 ms in unit test.

## 11. File-by-file implementation notes

### `frontend/src/lib/rem/types.ts`
- **Purpose**: shared TS types for the REM domain.
- **Exports**: `Kind`, `Priority`, `Status`, `Stakeholder`, `Requirement`, `RemDataset`, `TreeNode`, `FieldKey`, `RemParseError`.
- **Test**: type-only; covered transitively by `loader.test.ts`.

### `frontend/src/lib/rem/loader.ts`
- **Purpose**: fs + yaml parse + 5-min cache.
- **Exports**: `loadAllRequirements(opts?: { fixtureDir?: string }): Promise<RemDataset>`, `clearLoaderCache(): void` (test-only).
- **Imports**: `node:fs/promises`, `yaml` (transitive dep of Astro).
- **Test**: `tests/unit/rem/loader.test.ts`.

### `frontend/src/lib/rem/tree.ts`
- **Purpose**: flat list → hierarchical tree.
- **Exports**: `buildTree(dataset: RemDataset): RemTree`.
- **Test**: `tests/unit/rem/tree.test.ts`.

### `frontend/src/lib/rem/search.ts`
- **Purpose**: case-insensitive, scored substring search.
- **Exports**: `match(dataset: RemDataset, query: string): SearchHit[]`.
- **Test**: `tests/unit/rem/search.test.ts`.

### `frontend/src/lib/rem/format.ts` *(NEW vs proposal)*
- **Purpose**: PD badge builder, status/priority label mapping.
- **Exports**: `buildDetailRows(item: Requirement | Stakeholder, t: Translator): RowSpec[]`, `formatPriority(p: Priority): string`, `formatStatus(s: Status): string`.
- **Why new**: spec REQ-05#1/#2 require priority + status to be rendered with i18n labels; centralizing here keeps `RemDetail.astro` pure presentation.
- **Test**: folded into `tests/unit/components/rem/RemDetail.test.tsx` per §6 trade-off note (no dedicated `format.test.ts`).

### `frontend/src/components/rem/RemSidebar.tsx`
- **Purpose**: React island: search box + hierarchical tree + selection state.
- **Exports**: default `RemSidebar`.
- **Props**: `{ dataset: RemDataset; initialUid: string; locale: Locale; }`.
- **Imports**: `react`, `lib/rem/{search,tree}`, `lib/rem/format`, `i18n`.
- **Test**: `tests/unit/components/rem/RemSidebar.test.tsx`.

### `frontend/src/components/rem/RemTreeNode.tsx`
- **Purpose**: recursive tree node.
- **Exports**: default `RemTreeNode`.
- **Test**: covered by `RemSidebar.test.tsx`.

### `frontend/src/components/rem/RemDetail.astro`
- **Purpose**: server-rendered detail panel.
- **Imports**: `RemFieldRow.astro`, `lib/rem/format`.
- **Test**: `tests/unit/components/rem/RemDetail.test.tsx` (renders via `@testing-library/react` against a small Astro→React adapter or tests the underlying `buildDetailRows` output).

### `frontend/src/components/rem/RemFieldRow.astro`
- **Purpose**: one field/value row + PD / ? markers.
- **Test**: `tests/unit/components/rem/RemFieldRow.test.tsx`.

### `frontend/src/components/rem/RemPrintButton.tsx`
- **Purpose**: React island: print-to-PDF trigger.
- **Test**: `tests/unit/components/rem/RemPrintButton.test.tsx`.

### `frontend/src/components/rem/RemEmptyState.astro`, `RemError.astro`, `RemSkeleton.astro`
- **Purpose**: AGENTS.md §11 UI states (loading, empty, error).
- **Tests**: `RemError.test.tsx`; empty + skeleton covered by `RemSidebar.test.tsx` (REQ-10#1).

### `frontend/src/layouts/RemLayout.astro`
- **Purpose**: 2-column shell; links `styles/rem-print.css`.
- **Props**: `{ title: string; locale: Locale; }`.

### `frontend/src/pages/[locale]/rem/index.astro`
- **Purpose**: route `/es/rem` and `/en/rem`.
- **Frontmatter**: `try { dataset = await loadAllRequirements() } catch (e) { render <RemError> }`; build tree; read `Astro.url.searchParams.get('id')`; resolve to selection (or default `rf_001`).
- **Body**: `RemLayout` → `<RemSidebar client:visible ...>` + `<RemDetail>` for the selected UID + `<RemPrintButton client:idle>`.

### `frontend/src/styles/rem-print.css`
- **Purpose**: `@media print` rules; see §10.

### `frontend/src/i18n/locales/{es,en}.ts` (+ `i18n/types.ts`)
- Add 32 keys under `rem.*` per §7.

## 12. Commit / PR plan

Per the `conventional-commits-and-prs` skill (scope = `frontend`,
Spanish message body, no AI attribution):

- **Branch**: `feat/frontend-rem-viewer`
- **Commits** (each stands alone, build passes between):

  1. `feat(frontend): REM viewer loader + types + tree (lib/rem/*)`
     — `lib/rem/{types,loader,tree,search,format}.ts` + their unit tests + fixtures. ~600 LoC.
  2. `feat(frontend): REM viewer components (sidebar/detail/print)`
     — `components/rem/*` + `RemLayout.astro` + `styles/rem-print.css`. ~700 LoC.
  3. `feat(frontend): REM viewer page route + i18n namespace`
     — `pages/[locale]/rem/index.astro` + 32 keys in `i18n/{types,locales/{es,en}}.ts`. ~150 LoC.
  4. `test(frontend): REM viewer unit tests (Vitest)`
     — full Vitest suite passing; ≥ 85 % coverage for `lib/rem/*` + `components/rem/*`.
  5. `test(frontend): REM viewer e2e tests (Playwright)`
     — `tests/e2e/rem/*` + snapshot baseline.
  6. `docs(frontend): REM viewer README + skill updates`
     — `frontend/README.md` updated; no `.claude/skills/` change in this PR (out of scope).

- **PR title**: `feat(frontend): REM viewer — modern web viewer for Doorstop requirements`
- **PR body** (template from `conventional-commits-and-prs`):
  - **Qué cambia**: una página nueva `/[locale]/rem/` que muestra los 81 YAMLs de Doorstop como árbol + detalle + exporta a PDF.
  - **Por qué**: entregable académico, alineado con REM 1.2.2; sin cambios en backend ni YAMLs.
  - **Cómo se prueba**: `pnpm vitest run`, `pnpm playwright test rem/`, `pnpm run typecheck`, `pnpm run lint`.
  - **Checklist**: tests pasan, linter pasa, sin secrets, sin YAMLs editados.

## 13. Risk register (echo + design-time additions)

| # | Risk | Likelihood | Mitigation |
|---|------|------------|------------|
| 1 | Deadline pressure (academic session, hard date) | High | Scope is small; "future extension" ideas explicitly out of scope. |
| 2 | YAML schema gap (REM 1.2.2 fields not in current data) | High | DP1 = Option B; render `PD` badges for missing fields. REQ-05 contracts lock this behavior. |
| 3 | PDF fidelity on Firefox vs Chromium | Medium | Test in Chromium CI; document Firefox/Safari fallbacks (browser screenshots). |
| 4 | WCAG 2.2 AA compliance | Medium | Tree semantics, focus ring, contrast verified by `rem-viewer.spec.ts` REQ-11. |
| 5 | Screenshot quality at different breakpoints | Low | Default 1280×800; meta viewport documented; e2e snapshot baseline. |
| 6 | `migrate_to_doorstop.py` overwrites schema changes | n/a | DP1 = B for this delivery; this risk does not apply. |
| 7 | Full-report PDF doesn't include sidebar tree | Low | Cover + TOC + per-item pages via `data-rem-scope="full"` switch. |
| 8 | Cache stale in dev after YAML edit | Low | 5-min TTL; manual reload acceptable. |
| 9 | **NEW**: i18n `Translations` type union requires adding every key in 3 files (types.ts + es.ts + en.ts) — risk of forgetting one. | Medium | One-shot script in `i18n/types.ts` already enforces this; CI runs `pnpm run typecheck` and fails on missing keys. |
| 10 | **NEW**: React island serializes ~30 KB JSON at hydration — large for first paint. | Low | Acceptable: dataset is constant per release; consider `client:idle` instead of `client:load` to defer. |
| 11 | **NEW**: PDF e2e tests depend on Playwright + headless Chromium — may flake in CI. | Medium | Pin Chromium version via `playwright.config.ts`; gate tests behind `if (process.env.PDF_TESTS === '1')` so unit suite is the always-on check. |
| 12 | **NEW**: empty YAML folder on first checkout (developer didn't pull `scripts/requirements/`) → loader returns empty → page shows empty state unexpectedly. | Low | Loader already returns `{ rf: [], rnf: [], stakeholders: [] }` (REQ-01#5); UI shows a tailored empty state (not the search empty state). |

## 14. Open questions

| # | Question | Blocking `sdd-tasks`? |
|---|----------|----------------------|
| 1 | **DP1 sequencing** — PENDING USER DECISION. Tasks will assume Option A (extend schema via `seed_*.json` + `migrate_to_doorstop.py`) per proposal recommendation. If user prefers Option B (ship viewer first, PD for missing fields), tasks must be regenerated to drop the `format.ts` helpers and `loader.ts` must not parse the new fields. | No — design §5 locks DP1=A; tasks are written for that path. Switching to DP1=B is a documented fallback that requires regenerating tasks and downgrading §2 to the base data model. |
| 2 | **PDF library**: `react-to-print` vs `window.print()` only. Design locks A (no new deps). | No — design is consistent with hard constraint. |
| 3 | **Browser for class session**: default Chromium. | No — design covers both with documented fallback. |
| 4 | **"Informe completo" scope**: required for delivery, or hide the button? | No — both buttons ship; professor uses whichever. |
| 5 | **Auth gate**: confirmed public. | No. |
| 6 | **`?id` vs `#uid`**: design locks `?id`. | No. |
| 7 | **Stakeholder UID mismatch** (seed `daniel` vs YAML `stakeholder_001`): internal to migration script; viewer reads UIDs only. | No — design is consistent. |