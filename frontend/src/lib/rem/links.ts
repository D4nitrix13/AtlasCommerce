/**
 * lib/rem/links.ts
 *
 * Helpers for the internal link layer of the REM viewer. A "code" in
 * the detail panel (`RF-AUTH-001`, `RNF-SEC-010`, etc.) is the public
 * identifier of a requirement; we need the internal `uid` (`rf_NNN`,
 * `rnf_NNN`) to construct a deep link to its detail panel.
 *
 * The viewer uses the `?id=<uid>` query parameter for selection
 * (REQ-12). External code like "RNF-SEC-010" is the user-facing
 * label inside the Fuentes/Dependencias chips, so any click on those
 * chips has to translate the code to a uid before constructing the
 * href.
 */

import type { RemDataset } from './types';

/**
 * Build a `code → uid` lookup once per page render. Linear in
 * dataset size; cheap (sub-millisecond) even for the full 81-item
 * dataset.
 *
 * Stakeholder rows don't carry a `code` (only a `uid`), so they're
 * omitted — links are meaningful only when the destination has a code.
 *
 * When a code collides (shouldn't happen given migration shape but
 * we guard), the first occurrence wins — keeps the function total.
 */
export function buildCodeToUidMap(dataset: RemDataset): Map<string, string> {
  const map = new Map<string, string>();
  for (const rf of dataset.rf) {
    if (rf.code !== undefined && rf.code !== '' && !map.has(rf.code)) {
      map.set(rf.code, rf.uid);
    }
  }
  for (const rnf of dataset.rnf) {
    if (rnf.code !== undefined && rnf.code !== '' && !map.has(rnf.code)) {
      map.set(rnf.code, rnf.uid);
    }
  }
  return map;
}

/**
 * Build a `<a>` href that deep-links to the requirement with the
 * given code under the active locale. Returns `null` if the code is
 * not in the map (caller falls back to a non-link chip).
 *
 * Path shape: `/${locale}/rem?id=${uid}` matches the page route
 * `pages/[locale]/rem/index.astro` plus the `?id=` query that the
 * sidebar reads (REQ-12).
 */
export function codeToHref(
  code: string,
  codeToUid: ReadonlyMap<string, string>,
  locale: string,
): string | null {
  const uid = codeToUid.get(code);
  if (uid === undefined) {
    return null;
  }
  return `/${locale}/rem?id=${uid}`;
}
