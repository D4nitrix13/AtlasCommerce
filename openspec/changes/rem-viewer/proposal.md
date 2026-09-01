# Proposal: rem-viewer

## Intent

Build a modern web viewer over the existing Doorstop YAML dataset
(`scripts/requirements/req/`) so the team can present a Requirements
Engineering Management artifact in class that matches the visual reference
of REM 1.2.2 (the legacy Java Swing tool the professor used as reference) but
runs in any modern browser, supports PDF export, and is the same data the
team already commits to git. This is an academic deliverable, not a
production product.

## Background

- The team migrated 48 functional requirements (RF), 30 non-functional
  requirements (RNF) and 3 stakeholders to Doorstop YAMLs under
  `scripts/requirements/req/` (single flat folder, filenames `rf_NNN_*.yml`,
  `rnf_NNN_*.yml`, `stakeholder_NNN_*.yml`; total 81 files).
- Doorstop is the source of truth and stays untouched: it is 100% scriptable
  and version-controlled, which the team values.
- The current Doorstop HTML output (`docs/requirements/index.html` and
  `traceability.html`) is functional but not presentation-grade. The team
  also has a Python reportlab pipeline (`scripts/requirements/generate_avance2_pdf.py`)
  that produces `Avance2.pdf` — another static channel with no
  interactivity.
- REM 1.2.2 (legacy Java Swing, 2006) is the professor's visual reference.
  Its layout: left JTree with requirement codes, right two-column
  field/value table per selected node. The professor will open a URL during
  class; the team also wants to take browser screenshots and embed them in
  the academic document.
- The current YAML schema is intentionally lean
  (`title`, `rationale`, `priority`, `status`, `module` / `category`,
  `code`, `links`, `verified-by`). REM 1.2.2 shows richer fields
  (`version`, `autores`, `fuentes`, `dependencias`, `descripcion`,
  `importancia`, `urgencia`, `estado`, `estabilidad`). Some map cleanly to
  what we have (`rationale` ≈ `descripcion`, `priority` ≈
  `importancia`/`urgencia`, `status` ≈ `estado`); others are missing
  (`version`, `autores`, `fuentes`, `dependencias`).

## Scope

### In Scope

- New public viewer page at `/[locale]/rem/` (Astro route, i18n-aware).
- Left sidebar with a hierarchical tree: Stakeholders /
  Requisitos Funcionales / Requisitos No Funcionales, grouped by
  `module` (RF) or `category` (RNF) inside each bucket.
- Right detail panel: header row with `code` + full `title`, two-column
  field/value table covering the fields described in the Approach section.
- Text search across `code`, `title`, `module`, `category`, `rationale`,
  `status`.
- Client-side PDF export of the currently selected requirement (one
  requirement per page) and of the full report (cover + all
  requirements paginated). Uses the browser's own print-to-PDF under the
  hood; no Chromium binary.
- Print-friendly CSS so the same view is screenshot-friendly (1280×800
  default, 1024×768 fallback).
- Spanish (es) as the primary locale, English (en) as secondary, wired
  through the existing `src/i18n/` setup.
- WCAG 2.2 AA: keyboard navigation in the tree, focus ring, semantic
  landmarks, color contrast for "PD" / "?" red markers.
- Full unit test coverage with Vitest + Testing Library and E2E coverage
  with Playwright (one test method per spec scenario).

### Out of Scope (explicit non-goals)

- **Do NOT replace Doorstop.** The viewer is a read-only browser over
  Doorstop's files. No write-back.
- **Do NOT edit any existing `.yml` file by hand** in this change. Any
  schema extension is routed through `seed_*.json` and
  `migrate_to_doorstop.py` (see DP1).
- **Do NOT touch the backend (Laravel).** This is a frontend-only change.
- **Do NOT add multi-tenant theming for this viewer.** The deliverable is
  single-brand academic. Future extension noted, not implemented.
- **Do NOT add authentication, RBAC, or rate limiting** — the page is
  intended to be opened by the professor without a login.
- **Do NOT add server-side PDF rendering** (Puppeteer / Playwright PDF
  service). See DP2.
- **Do NOT add real-time collaboration, comments, or annotations.**
- **Do NOT add traceability matrices or impact analysis views.** Doorstop
  already produces `traceability.html` / `traceability.csv`. The viewer
  stays scoped to per-requirement inspection.
- **Do NOT run or modify** the Python `migrate_to_doorstop.py` script in
  this change unless DP1 is accepted (in which case the script becomes
  the regeneration source — see DP1).

## Approach

Single Astro page that reads the 81 YAMLs server-side (the project uses
`output: "server"` + Node adapter, see `frontend/astro.config.mjs`),
groups them into a tree, and lets a small React island handle
selection, search, and the print-to-PDF trigger. No new infra, no
backend endpoints, no new dependencies beyond what is already in
`frontend/`.

### File layout (new)

```txt
frontend/src/
├── pages/[locale]/rem/
│   └── index.astro              # route /es/rem and /en/rem
├── layouts/
│   └── RemLayout.astro          # 2-column shell (sidebar + detail)
├── components/rem/
│   ├── RemSidebar.tsx           # React island: tree + search
│   ├── RemDetail.astro          # field/value table per requirement
│   ├── RemTreeNode.tsx          # recursive tree node
│   ├── RemFieldRow.astro        # one field/value row, supports "PD"/"?" markers
│   ├── RemPrintButton.tsx       # React island: print-to-PDF trigger
│   └── RemEmptyState.astro      # when search returns nothing
├── lib/rem/
│   ├── loader.ts                # fs + yaml parse, module-scope cache
│   ├── tree.ts                  # flat list → hierarchical tree
│   ├── search.ts                # case-insensitive multi-field search
│   └── types.ts                 # Requirement, Stakeholder, FieldKey types
├── types/rem.ts                 # re-export from lib/rem/types for IDE discoverability
├── i18n/locales/
│   ├── es.ts                    # add `rem.*` namespace
│   └── en.ts                    # add `rem.*` namespace
└── styles/
    └── rem-print.css            # @media print rules

frontend/tests/
├── unit/rem/
│   ├── loader.test.ts           # parse + cache behavior
│   ├── tree.test.ts             # grouping by module/category
│   ├── search.test.ts           # match scoring + empty state
│   ├── RemSidebar.test.tsx
│   ├── RemDetail.test.tsx (renders via @testing-library)
│   ├── RemFieldRow.test.tsx
│   └── RemPrintButton.test.tsx
└── e2e/rem/
    ├── rem-viewer.spec.ts       # load, navigate, search, screenshot
    └── rem-pdf.spec.ts          # print-to-PDF, verify > 1 page
```

### Data flow

1. `pages/[locale]/rem/index.astro` runs at request time (server mode).
2. Frontmatter imports `loadAllRequirements()` from `lib/rem/loader.ts`.
3. `loader.ts` reads `scripts/requirements/req/*.yml` once, parses with
   the `yaml` package (already a transitive dep via Astro), returns
   `{ rf: Requirement[]; rnf: Requirement[]; stakeholders: Stakeholder[] }`.
   Result cached in a module-level `Map<string, Result>` invalidated
   every 5 minutes via a `lastLoadedAt` timestamp, so editing YAMLs in
   dev reflects without a server restart.
4. `tree.ts` groups RF by `module`, RNF by `category`, stakeholders
   kept flat.
5. The page renders a server-side shell with the first item preselected
   and the React `RemSidebar` island mounted with the full dataset
   (serialized once, ~30 KB).
6. The React island owns selection state and the search query. Selecting
   a node updates `window.location.hash` so the URL is shareable, and
   the right panel re-renders the field/value table.
7. `RemPrintButton` calls `window.print()` against the print stylesheet.

### UI states (mandatory per `frontend/AGENTS.md §11`)

- **loading**: only relevant for client-side lazy chunks; the page is
  server-rendered so the first paint already has data. We render a
  skeleton tree for hydration.
- **empty**: `RemEmptyState.astro` when search has zero matches, with a
  "Limpiar búsqueda" button.
- **error**: if the YAML parse throws, the frontmatter `try/catch` logs
  the error and renders a `Alert.astro` with the "no se pudieron cargar
  los requisitos" copy.
- **success**: default state.
- **disabled**: search input gets `disabled` while the tree is hydrating.

## Decision Points

### DP1 — YAML schema: extend vs live with gaps

**Option A (recommended): extend the schema** through
`seed_*.json` + `migrate_to_doorstop.py`.

- New fields added: `version` (default `"1.0"`), `autores` (list of
  `stakeholder_NNN` UIDs, default empty), `fuentes` (list of source
  UIDs, default empty), `dependencias` (list of UIDs, default empty).
- Existing fields get renamed/aliased: `rationale` keeps its name but
  is also surfaced as `descripcion` in the UI; `priority` is
  displayed as both `importancia` (H/M/L → 1-5) and `urgencia` (H/M/L
  → 1-5); `status` is surfaced as `estado`; new `estabilidad` is
  derived from `verified-by` (present → "alta", absent → "PD").
- Implementation: edit `seed_rf.json` / `seed_rnf.json` to add the
  fields (with empty defaults), update `migrate_to_doorstop.py` to
  emit them, regenerate `req/`. Diff in the seed JSONs makes the
  schema extension auditable in PR review.
- **Why**: full visual fidelity with REM 1.2.2; team is the source of
  truth so backfilling `autores` is fast; no hand-edited YAMLs.

**Option B**: live with the current schema.

- Viewer shows "PD" ("Por Definir") for any missing field, mirroring
  REM 1.2.2's behavior for unset values.
- **Why**: zero YAML changes; the viewer is purely additive.
- **Downside**: less information, no version history per requirement,
  no author attribution, weaker "academic fidelity" story.

**Recommendation: Option A**, with the explicit constraint that no
existing YAML is hand-edited — only the seed JSONs and the migration
script change.

### DP2 — PDF export: server-side vs client-side

**Option A (recommended): client-side via `window.print()`** with a
print stylesheet that paginates one requirement per page.

- Zero new dependencies, no Chromium binary in CI, uses the same
  rendering engine as the screenshots, so what the professor sees in
  the browser is what gets exported.
- We can use the existing `@react-pdf/renderer` if we want a more
  deterministic layout, but the project already has `react-to-print`
  patterns in `components/ui/PasswordStrengthMeter.tsx` area, so we
  follow that precedent.
- **Downside**: PDF fidelity depends on the browser (Chromium handles
  `@page` and `page-break-inside` cleanly; Firefox and Safari have
  small quirks). The professor's browser is unknown; we recommend
  Chromium for the live class and document the fallback.

**Option B**: server-side (Puppeteer or Playwright PDF via an Astro
endpoint).

- High fidelity, deterministic output.
- **Downside**: requires a Chromium binary in the Docker image, adds
  ~150 MB to the image, slow on first render, needs cache headers
  for performance. Out of scope for an academic deliverable.

**Recommendation: Option A**. If the professor opens Firefox and the
print layout is off, the team can take browser screenshots instead
(also in scope).

### DP3 — Data loading: build-time vs runtime

**Option A (recommended): runtime SSR read** with a module-scope cache
in `lib/rem/loader.ts`.

- Reads YAMLs at request time using `node:fs` and the `yaml` package
  (already a transitive dep). Caches the parsed result for 5 minutes
  so dev hot-reload still works without server restart.
- **Why**: zero config changes to `astro.config.mjs`, no new
  `src/content/` folder, no symlinks. Pure frontend code that works
  because the project already uses `output: "server"`.

**Option B**: build-time via Astro content collections with a custom
loader pointing to `scripts/requirements/req/`.

- Type-safe via Zod schema in `src/content/config.ts`.
- **Downside**: requires Astro config to register a custom loader,
  couples the viewer to the build pipeline, and provides no benefit
  for a server-mode project that can already read files at request
  time.

**Recommendation: Option A**. The dataset is small (81 items, ~30 KB
serialized) and parsing is sub-millisecond per YAML, so a 5-minute
cache is more than enough.

## Risks

| # | Risk | Likelihood | Mitigation |
|---|------|------------|------------|
| 1 | **Deadline pressure** — academic class session, hard date. | High | Scope is small (one page, one tree, one detail panel). All "future extension" ideas (collaboration, traceability matrix, multi-tenant) are explicitly out of scope. |
| 2 | **YAML schema gap** — REM 1.2.2 fields not in current data. | High | DP1 recommends extending via `seed_*.json` + `migrate_to_doorstop.py` (no hand edits). Fallback: render "PD" badges. |
| 3 | **PDF fidelity on Firefox vs Chromium** — `@page` rules differ. | Medium | Test the print layout in both during the design phase; document the recommended browser for the class session. |
| 4 | **WCAG 2.2 AA compliance** — academic context may be graded on it. | Medium | Use `frontend/src/components/ui/Button.astro` and `Input.astro` patterns; keyboard arrow nav in the tree; semantic landmarks; visible focus ring; aria-current on the selected node. |
| 5 | **Screenshot quality at different breakpoints** — professor may view on projector vs laptop. | Low | Default canvas is 1280×800 with a documented `print` media query. Add a `<meta name="viewport" content="width=1280">` for predictable screenshots. |
| 6 | **`migrate_to_doorstop.py` overwrites schema changes** if someone re-runs it after DP1 is applied. | Medium | The DP1 change ships a test that asserts the new fields survive a re-run of the migration script. |
| 7 | **PDF doesn't include the sidebar tree** (since the tree is the React island). | Low | The "Full report" PDF generates a TOC page that lists every requirement with its code and short title before the detail pages. |
| 8 | **Cache stale in dev** after a YAML edit. | Low | 5-minute TTL is short enough; a manual reload in dev is acceptable. |

## Dependencies

- **Doorstop YAML as canonical data source** — read-only, no migrations
  in this change unless DP1 is accepted.
- **Astro 7 + React 19 + Tailwind 4** — already provisioned in
  `frontend/`, no new packages needed for the core implementation.
- **`yaml` package** — already a transitive dep of Astro; used
  directly in `lib/rem/loader.ts`.
- **`react-to-print`** (optional, only if Option A in DP2 is implemented
  with a non-`window.print()` approach) — add to `frontend/package.json`
  if needed; otherwise zero new deps.
- **No backend, no infra, no DB, no new Docker layers.**

## Acceptance Criteria

- [ ] `pnpm run typecheck` passes with no `any` in `lib/rem/` or
      `components/rem/`.
- [ ] `pnpm run lint` passes for all new files.
- [ ] `pnpm vitest run` covers: loader parse + cache, tree grouping,
      search scoring, sidebar selection, detail field rendering
      (including "PD" and "?" markers), print button. **One test
      method per spec scenario**, no skipped tests.
- [ ] `npx playwright test` covers: page loads at `/es/rem` and
      `/en/rem`, sidebar renders 81 items, selecting a node updates
      the detail panel, search filters live, "Imprimir PDF" triggers
      a print dialog, the "Informe completo" export produces more
      than 1 page.
- [ ] The viewer visually matches REM 1.2.2: left tree with
      `[code]` + short title, right field/value table with bold
      labels, header row with code + full title, "PD" markers for
      unset fields, red `?` for empty `fuentes` (or `links` after
      DP1).
- [ ] Keyboard navigation: Tab focuses the search, arrow keys move
      within the tree, Enter selects, Escape clears the search.
- [ ] Lighthouse accessibility score ≥ 95 on the `/es/rem` route.
- [ ] A 1280×800 browser screenshot of the viewer is generated
      during the E2E run and committed to
      `frontend/tests/e2e/rem/__snapshots__/rem-viewer-1280.png`
      as a regression baseline.
- [ ] No YAML under `scripts/requirements/req/` is hand-edited
      during this change (unless DP1 is accepted, in which case
      only `seed_*.json` and `migrate_to_doorstop.py` change).
- [ ] No file in `backend/` is touched. No new packages are added
      to `frontend/package.json` unless DP2 Option A picks
      `react-to-print`.

## Open Questions

1. **DP1: should the team extend the YAML schema in this change,
   or leave it as a follow-up?** The proposal recommends yes, but
   the team may prefer a "ship viewer first, extend schema later"
   path. Either is valid; the open question is sequencing.
2. **What is the professor's preferred browser for the class
   session?** Affects whether the print-to-PDF path needs extra
   fallback. Default assumption: Chromium.
3. **Does the team want the "Informe completo" PDF (cover + all
   81 requirements paginated), or only the per-requirement PDF
   shown in the current view?** The proposal includes both; the
   open question is whether the "Informe completo" is required
   for this delivery.
4. **Should the page be public (no auth) or behind a basic auth
   gate?** The proposal assumes public; the open question is
   whether the professor wants a private URL.
5. **Future extension (out of scope for this change, listed for
   awareness only):** multi-tenant theming for the viewer
   (would reuse the `frontend/packages/themes/` system), CSV
   export of the full set, a search-by-module quick filter in
   the sidebar.
