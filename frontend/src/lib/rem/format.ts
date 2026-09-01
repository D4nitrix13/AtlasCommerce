/**
 * REM viewer — format helpers.
 *
 * Pure functions that the detail panel uses to render row specs and
 * label priority/status values for the i18n namespace.
 *
 * Design §6 trade-off: this module has no dedicated test file. The
 * assertions for `formatPriority` / `formatStatus` are folded into
 * `RemDetail.test.tsx` in PR2 (the file that actually exercises the
 * detail panel). For now we rely on `pnpm run typecheck` to enforce
 * the contract.
 *
 * Why a separate module: the Astro detail panel
 * (`src/components/rem/RemDetail.astro`) is server-rendered, so it
 * needs plain synchronous helpers — no React, no hooks. Keeping the
 * helpers in a `lib/rem/format.ts` module also means the
 * `RemFieldRow.astro` component can import them without dragging in
 * React.
 */

import type { FieldKey, Priority, Requirement, Stakeholder, Status } from './types';

/**
 * A single field/value row in the detail panel.
 *
 * `value` may be:
 *   - a string (rendered as-is)
 *   - `null` (rendered as the "PD" badge)
 *   - a `string[]` (joined with comma; empty array → red `?` marker
 *     for the "Fuentes/Dependencias" row per REQ-05#4)
 *   - `{ kind: "stakeholders"; uids: string[]; names: string[] }`
 *     for the "Verificado por" row
 */
export interface RowSpec {
  field: FieldKey;
  labelKey: string;
  value: string | string[] | { kind: 'stakeholders'; uids: string[]; names: string[] } | null;
}

/**
 * Translator shape: matches what the existing i18n hook returns.
 * Kept here as a local type so `format.ts` doesn't depend on the
 * React i18n hook.
 */
export type Translator = (key: string) => string;

/**
 * Maps a priority bucket (H / M / L) to the 1–5 scale REM 1.2.2
 * shows in the UI. Strings are returned (not numbers) so the detail
 * panel can interpolate them into labels via the translator.
 */
export function formatPriority(priority: Priority): string {
  switch (priority) {
    case 'H':
      return '5';
    case 'M':
      return '3';
    case 'L':
      return '1';
  }
}

/**
 * Maps a status enum to the i18n key the translator should resolve.
 * Each key is in the `rem.detail.*` namespace — PR2 adds the actual
 * translations.
 */
export function formatStatus(status: Status): string {
  return `rem.detail.status.${status}`;
}

// ─── Row variants (RemFieldRow.astro contract) ───────────────────────

/**
 * The three visual variants `RemFieldRow.astro` chooses between:
 *   - `pd`        → value is null → render the "PD" (Por Definir) badge.
 *   - `no-links`  → value is an empty array AND the row is the links row
 *                   (Fuentes/Dependencias) → render the red `?` with
 *                   `aria-label="Sin fuentes"`.
 *   - `plain`     → string or non-empty array → render the value as-is.
 *
 * Extracted into a pure helper so `RemFieldRow.test.tsx` can assert on
 * the branch without needing an Astro render container. The Astro file
 * calls this same helper and switches on the result.
 */
export type FieldRowVariant = 'pd' | 'no-links' | 'plain';

export interface FieldRowOptions {
  /** Set on the "Fuentes/Dependencias" row only. */
  isLinksRow?: boolean;
}

export function getFieldRowVariant(
  value: string | string[] | null,
  options: FieldRowOptions = {}
): FieldRowVariant {
  if (value === null) {
    return 'pd';
  }
  if (Array.isArray(value) && value.length === 0 && options.isLinksRow === true) {
    return 'no-links';
  }
  return 'plain';
}

// ─── Error variant (RemError.astro contract) ─────────────────────────

/**
 * Visual variant for `RemError.astro`. The RemError component renders
 * an `Alert` whose label is derived from this so unit tests can assert
 * without rendering Astro. The Astro file is responsible for the
 * actual Alert wrapper + i18n string interpolation.
 */
export type ErrorVariant = 'load' | 'notfound' | 'generic';

/**
 * Maps an error code prefix into a variant. Unknown prefixes fall back
 * to `generic` so the UI never crashes on a malformed error code.
 */
export function getErrorVariant(errorCode: string): ErrorVariant {
  if (errorCode.startsWith('load:')) {
    return 'load';
  }
  if (errorCode.startsWith('notfound:')) {
    return 'notfound';
  }
  return 'generic';
}

/**
 * Resolves a list of stakeholder UIDs against a stakeholder list and
 * returns the comma-joined display names. Unknown UIDs are kept
 * verbatim so the UI can show them as-is (a sign of stale data).
 */
function resolveStakeholderNames(
  uids: string[],
  stakeholders: Stakeholder[]
): { uids: string[]; names: string[] } {
  const byUid = new Map(stakeholders.map((s) => [s.uid, s.name] as const));
  return {
    uids,
    names: uids.map((uid) => byUid.get(uid) ?? uid),
  };
}

/**
 * Builds the row specs for the detail panel.
 *
 * For an RF: 8 rows (REQ-05#1):
 *   1. code     5. status
 *   2. title    6. verifiedBy
 *   3. module   7. links (fuentes/dependencias)
 *   4. priority 8. rationale (descripcion)
 *
 * For an RNF: 7 rows (REQ-05#2 — no "Verificado por"):
 *   1. code     5. status
 *   2. title    6. links
 *   3. category 7. rationale
 *   4. priority
 *
 * For a stakeholder: 6 rows (REQ-05#5 — no priority/status):
 *   1. uid, 2. name, 3. title, 4. role, 5. organization, 6. notes
 *
 * Missing optional fields surface as `null` so the row component
 * can render the "PD" badge.
 */
export function buildDetailRows(
  item: Requirement | Stakeholder,
  t: Translator,
  stakeholders: Stakeholder[] = []
): RowSpec[] {
  if ('kind' in item) {
    // Requirement (RF or RNF)
    const req = item;
    const rows: RowSpec[] = [
      { field: 'code', labelKey: t('rem.detail.code'), value: req.code || null },
      { field: 'title', labelKey: t('rem.detail.title'), value: req.title || null },
    ];
    if (req.kind === 'rf') {
      rows.push({ field: 'module', labelKey: t('rem.detail.module'), value: req.module ?? null });
    } else {
      rows.push({
        field: 'category',
        labelKey: t('rem.detail.category'),
        value: req.category ?? null,
      });
    }
    rows.push({
      field: 'priority',
      labelKey: t('rem.detail.priority'),
      value: req.priority ? formatPriority(req.priority) : null,
    });
    rows.push({
      field: 'status',
      labelKey: t('rem.detail.status'),
      // Batch 2c: wrap the dynamic key in t() so the row value is
      // translated instead of leaking the literal "rem.detail.status.<x>"
      // string into the UI. The static analysis still returns the key
      // when the caller passes the identity translator (see
      // `RemDetail.test.tsx`), but the production translator resolves
      // it to the locale string.
      value: req.status ? t(formatStatus(req.status)) : null,
    });

    // RF only: "Verificado por" row (REQ-05#2 — RNF has no verified-by)
    if (req.kind === 'rf') {
      const resolved = resolveStakeholderNames(req.verifiedBy, stakeholders);
      rows.push({
        field: 'verifiedBy',
        labelKey: t('rem.detail.verifiedBy'),
        value:
          resolved.uids.length === 0
            ? null
            : { kind: 'stakeholders', uids: resolved.uids, names: resolved.names },
      });
    }

    // "Fuentes/Dependencias" row — both lists together.
    // If both are empty, render the red `?` marker (REQ-05#4).
    const combined: string[] = [];
    for (const f of req.fuentes ?? []) {
      combined.push(f);
    }
    for (const d of req.dependencias ?? []) {
      combined.push(d);
    }
    rows.push({
      field: 'fuentes',
      // Batch 2c: align labelKey with the canonical namespace key
      // (`rem.detail.sources` = "Fuentes/Dependencias"); previously
      // `rem.detail.links` did not exist in the namespace.
      labelKey: t('rem.detail.sources'),
      value: combined.length === 0 ? [] : combined,
    });

    rows.push({
      field: 'rationale',
      labelKey: t('rem.detail.rationale'),
      value: req.rationale || null,
    });

    return rows;
  }

  // Stakeholder
  const s = item;
  return [
    { field: 'uid', labelKey: t('rem.detail.uid'), value: s.uid || null },
    { field: 'name', labelKey: t('rem.detail.name'), value: s.name || null },
    { field: 'title', labelKey: t('rem.detail.title'), value: s.title || null },
    { field: 'role', labelKey: t('rem.detail.role'), value: s.role || null },
    {
      field: 'organization',
      labelKey: t('rem.detail.organization'),
      value: s.organization || null,
    },
    { field: 'notes', labelKey: t('rem.detail.notes'), value: s.notes || null },
  ];
}
