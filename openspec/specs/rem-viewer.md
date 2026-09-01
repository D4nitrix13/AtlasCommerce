# REM Viewer — Specs Delta

Source: `openspec/changes/rem-viewer/specs.md` (archived 2026-09-01).

This document is the canonical spec for the REM Viewer feature. All
12 REQs and 8 NFRs are listed below for reference; the full scenarios
are archived at `openspec/changes/rem-viewer/specs.md` §3.

## Functional Requirements

| ID    | Title                       | Summary |
|-------|-----------------------------|---------|
| REQ-01 | Load YAML dataset            | Load 81 YAMLs at request time; return typed `RemDataset`; 5-minute module-scope cache; `RemParseError` on invalid YAML. |
| REQ-02 | Hierarchical tree            | Group RF by `module`, RNF by `category`, keep stakeholders flat; sort members by `code` ascending; deterministic output. |
| REQ-03 | Search across fields         | Case-insensitive substring search across `code`/`title`/`module`/`category`/`rationale`/`status`; scored and sorted. |
| REQ-04 | Select a requirement         | Sidebar selection owns React state, syncs to URL (REQ-12), updates panel synchronously on click or Enter. |
| REQ-05 | Render detail panel          | Header (`code` + title) + two-column field/value table; `PD` badge for unset fields; red `?` for empty `links`. |
| REQ-06 | Print single requirement     | Print current selection on one A4 page via `window.print()` + `styles/rem-print.css`. |
| REQ-07 | Print full report            | Print cover + TOC + every requirement paginated (`body[data-rem-scope="full"]`). |
| REQ-08 | i18n es / en                 | Route at `/es/rem/` and `/en/rem/`; UI strings under `rem.*` namespace; compile-time es/en parity. |
| REQ-09 | Keyboard navigation          | Tab → search; Arrow keys → tree; Enter → select; Escape → clear search. ARIA tree semantics. |
| REQ-10 | Empty / error / loading UI   | All five AGENTS.md §11 states (loading skeleton, empty, error, success, disabled). |
| REQ-11 | WCAG 2.2 AA                  | Semantic landmarks, visible focus ring, color contrast ≥ 4.5:1 for markers, Lighthouse ≥ 95. |
| REQ-12 | URL hash share               | Selection persists in `?id=<uid>`; deep links open same detail; invalid id falls back to default. |

## Non-Functional Requirements

| ID     | Statement |
|--------|-----------|
| NFR-01 | TTFB ≤ 500 ms (p95) for `GET /es/rem` warm cache. |
| NFR-02 | `search.match(dataset, query)` ≤ 50 ms for 81 items. |
| NFR-03 | Lighthouse accessibility ≥ 95 on `/es/rem` 1280×800. |
| NFR-04 | Viewer route JS bundle ≤ 100 KB gzipped. |
| NFR-05 | Vitest line coverage ≥ 85 % for `lib/rem/*` and `components/rem/*`; one test per §3 scenario; no `skip`/`todo`/`xfail`. |
| NFR-06 | Same dataset → byte-identical HTML (modulo cover date). |
| NFR-07 | Every UI string has es + en key. |
| NFR-08 | Chromium primary; Firefox + Safari best-effort (esp. print). |
