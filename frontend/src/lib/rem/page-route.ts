/**
 * REM viewer — page-route helpers.
 *
 * Pure functions extracted from the `pages/[locale]/rem/index.astro`
 * frontmatter so the unit test can exercise them without rendering
 * Astro. The page re-exports `getRemStaticPaths` as
 * `getStaticPaths` (Astro's required entry point), and uses
 * `pickDefaultUid` + `resolveSelectionFromQuery` for the SSR-side
 * selection logic that backs REQ-04#2 / REQ-12#2 / REQ-12#3.
 *
 * No `any`. No I/O. The dataset comes from `loader.ts` (which is the
 * single source of truth for what counts as a valid UID); the page
 * wraps the loader call in try/catch and falls back to
 * `<RemError>` rendering on failure.
 */

import { SUPPORTED_LOCALES } from '../../i18n/config';
import type { RemDataset, Requirement, Stakeholder } from './types';

/**
 * `getStaticPaths` payload for `[locale]/rem`. Astro uses this to
 * enumerate the supported locales; the page re-exports the same
 * function as `getStaticPaths` so `astro build` and the dev server
 * generate both `/es/rem` and `/en/rem` (REQ-08#1).
 *
 * The locale list is single-sourced from `SUPPORTED_LOCALES` so
 * adding `fr` later touches one place.
 */
export function getRemStaticPaths(): { params: { locale: string } }[] {
  return SUPPORTED_LOCALES.map((locale) => ({ params: { locale } }));
}

/**
 * Pick the first dataset item as the default selection when the URL
 * has no `?id=` or carries an invalid UID. Stakeholders sort first
 * by UID asc, then RF, then RNF — matches the sidebar's render order
 * so the fallback is always a visible node.
 *
 * Returns `null` when the dataset is empty (the page then renders
 * the empty state with no selection).
 */
export function pickDefaultUid(dataset: RemDataset): string | null {
  const sortedSh = [...dataset.stakeholders].sort((a, b) =>
    a.uid.localeCompare(b.uid, 'es')
  );
  if (sortedSh[0] !== undefined) return sortedSh[0].uid;
  const sortedRf = [...dataset.rf].sort((a, b) =>
    a.uid.localeCompare(b.uid, 'es')
  );
  if (sortedRf[0] !== undefined) return sortedRf[0].uid;
  const sortedRnf = [...dataset.rnf].sort((a, b) =>
    a.uid.localeCompare(b.uid, 'es')
  );
  if (sortedRnf[0] !== undefined) return sortedRnf[0].uid;
  return null;
}

/**
 * Find a dataset item by UID across stakeholders + RF + RNF.
 * Returns the item (typed) when found, `null` otherwise. Kept
 * separate from the React-side `findItemByUid` in `RemSidebar.tsx`
 * so the page can stay React-free in its frontmatter.
 */
export function findItemByUid(
  dataset: RemDataset,
  uid: string
): Requirement | Stakeholder | null {
  for (const s of dataset.stakeholders) {
    if (s.uid === uid) return s;
  }
  for (const r of dataset.rf) {
    if (r.uid === uid) return r;
  }
  for (const r of dataset.rnf) {
    if (r.uid === uid) return r;
  }
  return null;
}

/**
 * Resolve `?id=<uid>` against the dataset, applying the REQ-12#3
 * fallback contract:
 *   - valid UID → preselect that item, no fallback applied.
 *   - invalid UID → fall back to `pickDefaultUid`, mark applied.
 *   - missing/empty `?id=` → use `pickDefaultUid`, no fallback applied.
 *
 * `queryId` accepts `string | null | undefined` so the page can pass
 * `Astro.url.searchParams.get('id')` directly without an extra
 * null check.
 *
 * Returns `{ selectedUid, fallbackApplied }`. `selectedUid` is the
 * empty string when the dataset is empty — callers should treat that
 * as "no selection" and render the empty state.
 */
export function resolveSelectionFromQuery(
  queryId: string | null | undefined,
  dataset: RemDataset
): { selectedUid: string; fallbackApplied: boolean } {
  const fallbackUid = pickDefaultUid(dataset);
  if (fallbackUid === null) {
    return { selectedUid: '', fallbackApplied: false };
  }
  if (queryId === null || queryId === undefined || queryId === '') {
    return { selectedUid: fallbackUid, fallbackApplied: false };
  }
  if (findItemByUid(dataset, queryId) !== null) {
    return { selectedUid: queryId, fallbackApplied: false };
  }
  return { selectedUid: fallbackUid, fallbackApplied: true };
}
