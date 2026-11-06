# RFQ Page Redesign — Full Width + Design System

## Objective

Redesign `/es/rfq` (Request For Quote) to use full page width and apply
the `.interface-design/system.md` design system consistently. The current
page uses `max-w-6xl` (narrow) and hardcoded slate colors instead of the
established token system.

## Problem

- Layout constrained to `max-w-6xl` while all B2B components use full-bleed
  `w-full px-4 sm:px-6 lg:px-10`.
- Hardcoded `slate-*` colors instead of `--ink` / `--edge` / `--paper-raised`
  tokens.
- Generic card summary instead of the receipt-style panel pattern.
- Typography doesn't use the Fraunces display font hierarchy.
- No volume discount visibility (core B2B value prop).

## Scope

- `frontend/src/pages/[locale]/rfq/index.astro` — main redesign.
- `frontend/src/i18n/types.ts` + `locales/es.ts` + `locales/en.ts` — new keys.
- `frontend/src/components/storefront/b2b/RFQStepper.astro` — token alignment.

Out of scope: backend, confirmation page, bulk-order page.

## Constraints

- Multi-tenant: all colors via `var(--primary)` / token system, no hardcoded
  tenant hex.
- Accessibility: fieldset/legend, label-for, aria-current on stepper,
  data-testid hooks preserved.
- JS behavior (live summary, mock submit) preserved.
- File ≤ 400 lines (AGENTS §1) — extract if needed.

## Design direction (from .interface-design/system.md)

Mostrador boutique: paper canvas `#f3efe8`, ink text, one accent (tenant),
receipt-style totals panel. Hairlines + whisper shadows. Fraunces for
h1/hero numbers, Figtree for everything. Full-width without narrow max-w.

## Tasks

- [x] T1: Full-width layout + design tokens in RFQ page template
- [x] T2: Receipt-style summary panel + volume discount indicator
- [x] T3: i18n keys for new labels (types + es + en)
- [x] T4: Verify — typecheck + lint pass

## Route

T1-T2: inline (single non-trivial file, design decisions resolved).
T3: inline (mechanical edits across 3 files).
T4: inline (bounded check).
Component extraction (RfqSummaryPanel + rfq-form.ts): inline mechanical
refactoring to meet the 400-line file limit (AGENTS §1).

## TDD mode

Strict TDD enabled globally, but this is a visual/template redesign in
`.astro` — no unit-testable logic changes. Verification = typecheck + lint
+ structural readback. JS behavior preserved in `rfq-form.ts`.

## Acceptance criteria

- [x] Page uses full width (`w-full` + symmetric padding, no `max-w-6xl`).
- [x] All colors via design tokens (`--ink`, `--edge`, `--paper-raised`,
      `--primary`, `--primary-soft`) — no hardcoded `slate-*` in code.
- [x] Summary panel follows receipt pattern (dashed divider, Fraunces total).
- [x] Volume discount tier visible in summary.
- [x] `bun run typecheck` passes (0 errors).
- [x] `bun run lint` passes on modified files (0 errors, 1 complexity warning).
- [x] All data-testid hooks preserved (25 total: 15 page + 10 summary).
- [x] Multi-tenant safe (CSS vars only).

## Progress

All 4 tasks complete.

## Verification evidence

- `bun run typecheck`: 0 errors, 0 warnings (615 files).
- `bunx eslint` on modified files: 0 errors, 1 warning (complexity in
  `rfq-form.ts` — inherent to DOM queries with instanceof guards).
- Hardcoded `slate-*`: 0 in code (only in docblock comments).
- data-testid preservation: 25/25 hooks verified via grep.
- File sizes: index.astro 338 lines, RfqSummaryPanel.astro 141 lines,
  rfq-form.ts 116 lines, RFQStepper.astro 106 lines — all under 400.

## Files changed

| File | Change |
|---|---|
| `frontend/src/pages/[locale]/rfq/index.astro` | Redesign: full-width, tokens, receipt summary via component |
| `frontend/src/pages/[locale]/rfq/rfq-form.ts` | Extracted: live summary + submit script |
| `frontend/src/components/storefront/b2b/RfqSummaryPanel.astro` | New: receipt-style summary panel |
| `frontend/src/components/storefront/b2b/RFQStepper.astro` | Token alignment (slate → --edge/--ink) |
| `frontend/src/i18n/types.ts` | +2 keys (discountLabel, unitPriceLabel) |
| `frontend/src/i18n/locales/es.ts` | +2 keys |
| `frontend/src/i18n/locales/en.ts` | +2 keys |
