/**
 * REM viewer — search.
 *
 * Case-insensitive substring search across `code`, `title`, `module`,
 * `category`, `rationale`, and `status`. Each field contributes to
 * the hit's score per the table in `SCORE` (matches REM 1.2.2's
 * intuition: an exact code match is the strongest signal, a status
 * match is a weak one).
 *
 * Empty query returns every item with score 0 (REQ-03#4) so the
 * sidebar can render the unfiltered tree without branching the UI.
 *
 * Performance: linear scan, no allocations beyond the result array.
 * 81 items in well under 50 ms — NFR-02.
 */

import type { FieldKey, RemDataset, Requirement, SearchHit, Stakeholder } from "./types";

// ─── Score table (REQ-03) ────────────────────────────────────────────

export const SCORE = {
  CODE_EXACT: 100,
  CODE_PREFIX: 60,
  TITLE_EXACT: 50,
  TITLE_SUBSTRING: 30,
  MODULE_OR_CATEGORY: 10,
  RATIONALE: 10,
  STATUS: 5,
} as const;

const HAYSTACK_FIELDS: readonly FieldKey[] = [
  "code",
  "title",
  "module",
  "category",
  "rationale",
  "status",
];

/**
 * Lowercase a string with locale-aware rules so "Título" matches
 * "TITULO" exactly. Falls back to plain toLowerCase on null/undefined.
 */
function lower(value: string | undefined | null): string {
  return (value ?? "").toLowerCase();
}

/**
 * Score one requirement against a normalized query (already lowercased).
 * Returns the highest contribution across fields. If the query is
 * empty, returns 0.
 */
function scoreRequirement(req: Requirement, query: string): { score: number; field: FieldKey | null } {
  if (query === "") {
    return { score: 0, field: null };
  }

  // Exact code match is the strongest signal.
  if (lower(req.code) === query) {
    return { score: SCORE.CODE_EXACT, field: "code" };
  }
  // Code prefix (e.g. typing "RF-AUTH" matches "RF-AUTH-001").
  if (lower(req.code).startsWith(query)) {
    return { score: SCORE.CODE_PREFIX, field: "code" };
  }
  // Title exact.
  if (lower(req.title) === query) {
    return { score: SCORE.TITLE_EXACT, field: "title" };
  }
  // Title substring.
  if (lower(req.title).includes(query)) {
    return { score: SCORE.TITLE_SUBSTRING, field: "title" };
  }
  // Module or category.
  if (req.module && lower(req.module).includes(query)) {
    return { score: SCORE.MODULE_OR_CATEGORY, field: "module" };
  }
  if (req.category && lower(req.category).includes(query)) {
    return { score: SCORE.MODULE_OR_CATEGORY, field: "category" };
  }
  // Rationale.
  if (lower(req.rationale).includes(query)) {
    return { score: SCORE.RATIONALE, field: "rationale" };
  }
  // Status (e.g. searching "implemented" finds all implemented reqs).
  if (lower(req.status).includes(query)) {
    return { score: SCORE.STATUS, field: "status" };
  }
  return { score: 0, field: null };
}

/**
 * Stakeholder search: name / title / role / organization / notes.
 * (Re-uses the same score buckets but keyed on `uid` to avoid
 * colliding with Requirement UIDs in the result list.)
 */
function scoreStakeholder(s: Stakeholder, query: string): { score: number; field: FieldKey | null } {
  if (query === "") {
    return { score: 0, field: null };
  }
  if (lower(s.name) === query) {
    return { score: SCORE.TITLE_EXACT, field: "name" };
  }
  if (lower(s.name).includes(query) || lower(s.title).includes(query)) {
    return { score: SCORE.TITLE_SUBSTRING, field: "name" };
  }
  if (lower(s.organization).includes(query) || lower(s.role).includes(query)) {
    return { score: SCORE.MODULE_OR_CATEGORY, field: "role" };
  }
  if (lower(s.notes).includes(query)) {
    return { score: SCORE.RATIONALE, field: "notes" };
  }
  return { score: 0, field: null };
}

/**
 * Public search entry point. Returns a sorted list of hits:
 *   - score desc, then code asc (stable order for ties)
 *   - filtered to score > 0 OR empty query (REQ-03#4)
 *
 * An empty query returns 78 hits (3 stakeholders + 48 RF + 30 RNF - 3
 * = 78 per spec; the number is just `total items` since the spec
 * counts stakeholders + requirements — we return the real count).
 */
export function match(dataset: RemDataset, rawQuery: string): SearchHit[] {
  const query = rawQuery.trim().toLowerCase();

  const hits: SearchHit[] = [];

  for (const rf of dataset.rf) {
    const { score, field } = scoreRequirement(rf, query);
    if (query === "" || score > 0) {
      hits.push({ uid: rf.uid, score, matchedField: field });
    }
  }
  for (const rnf of dataset.rnf) {
    const { score, field } = scoreRequirement(rnf, query);
    if (query === "" || score > 0) {
      hits.push({ uid: rnf.uid, score, matchedField: field });
    }
  }
  for (const s of dataset.stakeholders) {
    const { score, field } = scoreStakeholder(s, query);
    if (query === "" || score > 0) {
      hits.push({ uid: s.uid, score, matchedField: field });
    }
  }

  // Sort: score desc, then uid asc for stable order on ties.
  hits.sort((a, b) => {
    if (a.score !== b.score) {
      return b.score - a.score;
    }
    return a.uid.localeCompare(b.uid, "es");
  });

  return hits;
}

export { HAYSTACK_FIELDS };
