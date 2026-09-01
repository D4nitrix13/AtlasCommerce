/**
 * `pages/[locale]/rem/index.astro` — unit tests (Task 2.12).
 *
 * Covers:
 *   - getStaticPaths returns both 'es' and 'en' paths (REQ-08#1).
 *   - resolveSelectionFromQuery falls back to the default UID when
 *     the requested `?id=<uid>` is not in the dataset (REQ-12#3).
 *
 * The page's frontmatter delegates to `src/lib/rem/page-route.ts`
 * for both pieces of logic, so the test targets that helper module
 * directly. The `.astro` file re-exports the helpers (`getStaticPaths
 * = getRemStaticPaths`) so the test is asserting on the same source
 * of truth the page ships.
 */

import { describe, expect, it } from 'vitest';

import { SEED_MINI } from '../rem/fixtures/seed-mini';
import {
  getRemStaticPaths,
  pickDefaultUid,
  resolveSelectionFromQuery,
} from '../../../src/lib/rem/page-route';

describe('pages/[locale]/rem/index.astro — getStaticPaths (REQ-08#1)', () => {
  it('returns a path entry for every supported locale (es + en)', () => {
    const paths = getRemStaticPaths();
    const locales = paths.map((p) => p.params.locale).sort();
    expect(locales).toEqual(['en', 'es']);
    for (const entry of paths) {
      expect(entry).toEqual({ params: { locale: expect.any(String) } });
    }
  });

  it('emits exactly the locales declared in i18n/config.ts (no drift)', () => {
    expect(getRemStaticPaths()).toHaveLength(2);
  });
});

describe('pages/[locale]/rem/index.astro — resolveSelectionFromQuery (REQ-12#3)', () => {
  it('returns the first stakeholder UID when queryId is null', () => {
    const result = resolveSelectionFromQuery(null, SEED_MINI);
    const expected = pickDefaultUid(SEED_MINI);
    expect(expected).not.toBeNull();
    expect(result.selectedUid).toBe(expected);
    expect(result.fallbackApplied).toBe(false);
  });

  it('falls back to first UID when the requested id is invalid (REQ-12#3)', () => {
    const result = resolveSelectionFromQuery('rf_does_not_exist', SEED_MINI);
    const expected = pickDefaultUid(SEED_MINI);
    expect(expected).not.toBeNull();
    expect(result.selectedUid).toBe(expected);
    expect(result.fallbackApplied).toBe(true);
  });

  it('preserves the requested UID when it resolves to a real dataset item', () => {
    const result = resolveSelectionFromQuery('rf_001', SEED_MINI);
    expect(result.selectedUid).toBe('rf_001');
    expect(result.fallbackApplied).toBe(false);
  });

  it('returns empty selectedUid when the dataset is empty (no fallback possible)', () => {
    const empty = { rf: [], rnf: [], stakeholders: [] };
    const result = resolveSelectionFromQuery(null, empty);
    expect(result.selectedUid).toBe('');
    expect(result.fallbackApplied).toBe(false);
  });
});
