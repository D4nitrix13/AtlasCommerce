/**
 * i18n parity — REM namespace (Task 2.14 / REQ-08#2 + v2 D2).
 *
 * The `TranslationKey` union in `src/i18n/types.ts` already enforces
 * that both `es.ts` and `en.ts` declare every key in the union (a
 * missing key in either locale is a compile error). This test is
 * the runtime safety net: it loads both locales and asserts that
 * the `rem.*` key sets are equal so any future drift between the
 * canonical union and the locale files is caught by CI.
 *
 * Batch 2c contract: exactly 45 keys under `rem.*` (32 + 13
 * corrective). v2 (D2) adds 33 new keys for kebab actions, dialog
 * chrome, form labels, and CRUD error alerts, bringing the total
 * to 78. Update this constant when adding/removing keys.
 */

import { describe, expect, it } from 'vitest';

import en from '../../../src/i18n/locales/en';
import es from '../../../src/i18n/locales/es';

// Expected key count per Batch 2c prompt — 32 (Batch 2b) + 13
// (Batch 2c corrective) = 45. v2 D2 adds 33 new keys (kebab +
// dialog + forms) → 78 total. Update this constant (and the test
// below) when adding/removing keys in the `rem.*` namespace.
const EXPECTED_REM_KEY_COUNT = 78;

const REM_KEYS_PREFIX = 'rem.';

/**
 * Keys that MUST differ between `es` and `en`. Intentionally-equal
 * keys are excluded: brand names (`rem.title`, `rem.print.coverTitle`),
 * proper nouns (`rem.tree.stakeholders` mirrors REM 1.2.2), and ARIA
 * tokens (`rem.tree.role`, `rem.treeitem.role`,
 * `rem.sources.empty.ariaLabel`) are consumed verbatim by the browser
 * and stay identical across locales.
 */
const MUST_DIFFER_KEYS: readonly string[] = [
  'rem.sidebar.heading',
  'rem.sidebar.search.placeholder',
  'rem.sidebar.search.clear',
  'rem.sidebar.empty.heading',
  'rem.sidebar.empty.body',
  'rem.skeleton.loading',
  'rem.tree.functional',
  'rem.tree.nonfunctional',
  'rem.detail.code',
  'rem.detail.title',
  'rem.detail.status',
  'rem.detail.status.pending',
  'rem.detail.status.in-analysis',
  'rem.detail.status.approved',
  'rem.detail.status.implemented',
  'rem.detail.status.verified',
  'rem.detail.verifiedBy',
  'rem.detail.sources',
  'rem.detail.rationale',
  'rem.detail.organization',
  'rem.print.single',
  'rem.print.full',
  'rem.empty.title',
  'rem.error.title',
  'rem.error.load.title',
  'rem.error.load.body',
  'rem.shell.header',
  'rem.shell.main',
  'rem.shell.footer',
  'rem.error.codeUnknown',
  'rem.pd.label',
  'rem.cover.dateFormat',
  // v2 (D2): kebab + dialog + form chrome copy — must differ
  'rem.crud.actions',
  'rem.crud.edit',
  'rem.crud.delete',
  'rem.crud.cancel',
  'rem.crud.create.submit',
  'rem.crud.edit.submit',
  'rem.crud.delete.confirm',
  'rem.crud.delete.error',
  'rem.crud.write.error',
  'rem.dialog.create.heading',
  'rem.dialog.edit.heading',
  'rem.form.code.label',
  'rem.form.code.invalid',
  'rem.form.code.required',
  'rem.form.title.label',
  'rem.form.title.required',
  'rem.form.priority.label',
  'rem.form.priority.required',
  'rem.form.status.label',
  'rem.form.module.label',
  'rem.form.module.required',
  'rem.form.category.label',
  'rem.form.category.required',
  'rem.form.rationale.label',
  'rem.form.verifiedBy.label',
  'rem.form.fuentes.label',
  'rem.form.dependencias.label',
  'rem.form.stakeholder.name.label',
  'rem.form.stakeholder.name.required',
  'rem.form.stakeholder.title.label',
  'rem.form.stakeholder.role.label',
  'rem.form.stakeholder.organization.label',
  'rem.form.stakeholder.notes.label',
];

function pickRemKeys(locale: Record<string, string>): string[] {
  return Object.keys(locale)
    .filter((k) => k.startsWith(REM_KEYS_PREFIX))
    .sort();
}

describe('i18n — rem namespace parity (REQ-08#2)', () => {
  it('es.ts has exactly the expected number of rem.* keys', () => {
    expect(pickRemKeys(es)).toHaveLength(EXPECTED_REM_KEY_COUNT);
  });

  it('en.ts has exactly the expected number of rem.* keys', () => {
    expect(pickRemKeys(en)).toHaveLength(EXPECTED_REM_KEY_COUNT);
  });

  it('es.ts and en.ts have IDENTICAL rem.* key sets (parity)', () => {
    expect(pickRemKeys(es)).toEqual(pickRemKeys(en));
  });

  it('no rem.* key in MUST_DIFFER_KEYS is identical between locales', () => {
    for (const key of MUST_DIFFER_KEYS) {
      const esVal = es[key as keyof typeof es];
      const enVal = en[key as keyof typeof en];
      expect(esVal, `es/en identical for ${key}`).not.toBe(enVal);
    }
  });

  it('keys appear in the same order in both locale files (grep-friendly)', () => {
    expect(pickRemKeys(es)).toEqual(pickRemKeys(en));
  });
});
