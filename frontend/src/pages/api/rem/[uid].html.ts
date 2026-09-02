/**
 * GET /api/rem/[uid].html
 *
 * Server-renders the detail panel for a single UID and returns it as
 * a `text/html` fragment — design §4 step 8, F2-lock from
 * `RemSidebar.tsx`. The React island fetches this on every selection
 * and assigns the response body to `.rem-detail.innerHTML` so the
 * page never ships a parallel React subtree for the detail panel.
 *
 * Status codes:
 *   - 200 — UID found, HTML body contains the rendered `<section
 *           data-rem-detail-render>`.
 *   - 404 — UID does not resolve to any dataset item.
 *   - 500 — Loader threw (typically `RemParseError` on a malformed
 *           YAML). Body still renders the `RemError` markup so the
 *           React island can swap it in verbatim.
 *
 * Why render manually instead of importing `RemDetail.astro`:
 *   - `RemDetail.astro` is a .astro component; importing it from a
 *     .ts endpoint requires Astro's container API at runtime, which
 *     is not ergonomic in Vitest's jsdom harness.
 *   - Both the endpoint and `RemDetail.astro` call the same source of
 *     truth (`buildDetailRows`) so the markup stays byte-identical
 *     for the same dataset row. The RemError markup is the same
 *     `RemError.astro` produces (data attribute + heading + code).
 */

import type { APIRoute } from 'astro';

import { clearLoaderCache, loadAllRequirements } from '../../../lib/rem/loader';
import { buildDetailRows, getFieldRowVariant, type RowSpec } from '../../../lib/rem/format';
import { buildCodeToUidMap, codeToHref } from '../../../lib/rem/links';
import { getServerDictionary } from '../../../i18n/server';
import { SUPPORTED_LOCALES, type Locale } from '../../../i18n/config';
import type { Requirement, Stakeholder } from '../../../lib/rem/types';

export const prerender = false;

/**
 * HTML-escape a string for safe inlining into text nodes and
 * attribute values. Mirrors the basic `&`, `<`, `>`, `"`, `'`
 * replacement used by every other Astro render in the project.
 */
function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/**
 * Resolve a UID across the full `RemDataset` (stakeholders + RF +
 * RNF). Returns the payload + a discriminator the renderer needs to
 * pick the right header shape and row composition.
 */
function findByUid(
  uid: string,
  dataset: { rf: Requirement[]; rnf: Requirement[]; stakeholders: Stakeholder[] }
): { item: Requirement | Stakeholder; kind: 'rf' | 'rnf' | 'stakeholder' } | null {
  for (const s of dataset.stakeholders) {
    if (s.uid === uid) {
      return { item: s, kind: 'stakeholder' };
    }
  }
  for (const r of dataset.rf) {
    if (r.uid === uid) {
      return { item: r, kind: 'rf' };
    }
  }
  for (const r of dataset.rnf) {
    if (r.uid === uid) {
      return { item: r, kind: 'rnf' };
    }
  }
  return null;
}

/**
 * Resolve a row's value to the 3-shape subset `getFieldRowVariant`
 * expects, mirroring `RemDetail.astro`'s server-side
 * `rowValueToString` resolution of the stakeholder shape.
 */
function resolveCellValue(
  value: RowSpec['value']
): string | string[] | null {
  if (value === null) return null;
  if (Array.isArray(value)) return value;
  if (typeof value === 'object' && value !== null && value.kind === 'stakeholders') {
    return value.names.join(', ');
  }
  if (typeof value === 'string') return value;
  return null;
}

/**
 * Render the `<td>` body for the `plain` variant — the resolved
 * shape from `resolveCellValue`. Caller must guarantee the variant is
 * `'plain'` before invoking.
 */
function renderPlainCell(value: string | string[]): string {
  return Array.isArray(value) ? escapeHtml(value.join(', ')) : escapeHtml(value);
}

/**
 * Render one row from `buildDetailRows` as the HTML string the
 * browser would get from `RemFieldRow.astro`. The 3-variant contract
 * (`pd`, `no-links`, `plain`) matches `getFieldRowVariant` exactly.
 */
function renderRow(row: RowSpec, accentIndex: number): string {
  const isLinksRow = row.field === 'fuentes';
  const resolved = resolveCellValue(row.value);
  const variant = getFieldRowVariant(resolved, { isLinksRow });

  let html: string;
  if (variant === 'pd') {
    html = `<span class="rem-pd-badge inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900 ring-1 ring-inset ring-amber-300"><svg class="mr-1 h-3 w-3" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true"><circle cx="6" cy="6" r="3"/></svg>PD</span>`;
  } else if (variant === 'no-links') {
    html = `<span aria-label="Sin fuentes" class="rem-no-links inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-100 text-base font-bold text-red-700 ring-1 ring-inset ring-red-300">?</span>`;
  } else if (Array.isArray(resolved)) {
    const chips = resolved.map(
      (entry) =>
        `<span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700">${escapeHtml(entry)}</span>`,
    ).join('');
    html = `<span class="flex flex-wrap gap-1.5">${chips}</span>`;
  } else {
    html = `<span>${escapeHtml(resolved)}</span>`;
  }

  const rowBg = accentIndex % 2 === 0 ? 'bg-white' : 'bg-slate-50/40';
  const labelClass =
    'rem-detail-row__label w-1/4 border-b border-slate-200 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 align-top';
  const valueClass =
    `rem-detail-row__value w-3/4 border-b border-slate-200 px-4 py-3 text-sm text-slate-900 align-top whitespace-pre-wrap break-words ${rowBg}`;

  return (
    `<tr role="row" data-rem-row data-rem-row-variant="${variant}" class="group transition-colors hover:bg-[#2D9D8E]/5">` +
    `<th scope="row" class="${labelClass}">${escapeHtml(row.labelKey)}</th>` +
    `<td class="${valueClass}">${html}</td>` +
    `</tr>`
  );
}

/**
 * Build a locale-aware translator for the endpoint. The middleware
 * sets `Astro.locals.locale`, so the endpoint reads that and falls back
 * to the `?locale=` query string only if needed. Honors both 'es' and
 * 'en' (project `SUPPORTED_LOCALES`); anything else falls back to 'es'.
 */
function translatorFor(localeRaw: string | undefined): (key: string) => string {
  const locale: Locale =
    localeRaw !== undefined && (SUPPORTED_LOCALES as readonly string[]).includes(localeRaw)
      ? (localeRaw as Locale)
      : 'es';
  const dictionary = getServerDictionary(locale);
  return (key: string): string =>
    (dictionary as Record<string, string>)[key] ?? key;
}

/**
 * Render the full detail panel — same outline as `RemDetail.astro`:
 *   <section data-rem-detail-render data-rem-detail-kind="...">
 *     <header><h2>code — title</h2></header>
 *     <table>
 *       <tbody>{row rows}</tbody>
 *     </table>
 *   </section>
 *
 * `t` is the locale-aware translator so labels render with the
 * actual UI string, not the i18n key literal.
 */
function renderDetailPanel(
  item: Requirement | Stakeholder,
  kind: 'rf' | 'rnf' | 'stakeholder',
  dataset: { rf: Requirement[]; rnf: Requirement[]; stakeholders: Stakeholder[] },
  localeRaw: string | undefined
): string {
  const t = translatorFor(localeRaw);
  const rows = buildDetailRows(item, t, dataset.stakeholders);
  const code = 'kind' in item ? item.code || null : item.uid;
  const title = 'kind' in item ? item.title || null : item.name || null;
  const headerTitle = `${code ?? '?'} — ${title ?? 'Sin título'}`;
  const uidAttr = 'uid' in item ? escapeHtml(item.uid) : '';

  const sectionClass =
    'rem-detail__panel w-full rounded-lg border border-slate-200 bg-white p-8 shadow-sm';
  const headerClass = 'rem-detail__header mb-6 border-b border-slate-200 pb-4';
  const titleClass = 'rem-detail__header-title text-2xl font-semibold text-slate-900';
  const tableClass = 'rem-detail__table w-full border-collapse text-left';

  const body = rows.map((row, idx) => renderRow(row, idx)).join('');

  return (
    `<section data-rem-detail-render data-rem-detail-kind="${kind}" ` +
    `data-rem-detail-uid="${uidAttr}" class="${sectionClass}">` +
    `<header class="${headerClass}">` +
    `<h2 class="${titleClass}">${escapeHtml(headerTitle)}</h2>` +
    `</header>` +
    `<table class="${tableClass}" role="table">` +
    `<tbody>${body}</tbody>` +
    `</table>` +
    `</section>`
  );
}

/**
 * Render the loader-error markup matching `RemError.astro`. The React
 * island can swap this into `.rem-detail` just like a 200 body — the
 * client doesn't branch on status.
 */
function renderErrorBody(code: string): string {
  const variant = code.startsWith('load:')
    ? 'load'
    : code.startsWith('notfound:')
      ? 'notfound'
      : 'generic';
  const sectionClass =
    'rem-error mx-auto my-12 max-w-2xl rounded-lg border border-red-300 bg-red-50 p-6 text-red-900 shadow-sm';
  return (
    `<section role="alert" data-rem-error data-rem-error-code="${escapeHtml(code)}" ` +
    `data-rem-error-variant="${variant}" class="${sectionClass}" aria-live="polite">` +
    `<h2 class="rem-error__title text-lg font-semibold">${escapeHtml('No se pudieron cargar los requisitos')}</h2>` +
    `<p class="rem-error__body mt-2 text-sm">${escapeHtml('Reintentá en unos segundos.')}</p>` +
    `<p class="rem-error__code mt-4 font-mono text-xs text-red-700">Código: <span class="rem-error__code-value">${escapeHtml(code)}</span></p>` +
    `</section>`
  );
}

const htmlResponse = (body: string, status = 200): Response =>
  new Response(body, {
    status,
    headers: {
      'Content-Type': 'text/html; charset=utf-8',
      'Cache-Control': 'no-store',
    },
  });

export const GET: APIRoute = ({ params, url, locals }) => {
  const uid = params.uid ?? '';
  if (uid === '') {
    return htmlResponse(renderErrorBody('notfound:empty'), 404);
  }

  // Locale resolution: middleware sets `locals.locale`; the React
  // island can also pass `?locale=es|en` to override (useful when the
  // user switches via `LanguageSwitcher` and the Astro URL has not yet
  // been refreshed). `locals` may be `undefined` in unit-test paths
  // where the endpoint is invoked without the Astro middleware.
  const localeRaw =
    url.searchParams.get('locale') ??
    (locals?.locale as string | undefined);

  let dataset: ReturnType<typeof loadAllRequirements>;
  try {
    dataset = loadAllRequirements();
  } catch (cause) {
    // Loader failed — surface the error markup at 500 so the React
    // island can swap it in verbatim (REQ-10#3 + task 2.11 spec).
    const code = cause instanceof Error && cause.name === 'RemParseError'
      ? `load:parse-error:${(cause as { path?: string }).path ?? 'unknown'}`
      : 'load:unknown';
    // Clear the cache so the next request gets a fresh attempt.
    clearLoaderCache();
    return htmlResponse(renderErrorBody(code), 500);
  }

  const found = findByUid(uid, dataset);
  if (found === null) {
    return htmlResponse(renderErrorBody(`notfound:${uid}`), 404);
  }

  return htmlResponse(renderDetailPanel(found.item, found.kind, dataset, localeRaw), 200);
};
