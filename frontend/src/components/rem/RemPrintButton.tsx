/**
 * RemPrintButton.tsx
 *
 * React island: print-to-PDF trigger (REQ-06 + REQ-07).
 *
 * Two buttons:
 *   - "Imprimir PDF" → sets `body[data-rem-scope="single"]` → calls
 *     `window.print()`. Prints the currently selected detail only.
 *
 *   - "Informe completo" → navigates (new tab) to
 *     `/${locale}/rem/full-report?auto_print=1`. The dedicated
 *     endpoint renders cover + TOC + all 78 items, and the query
 *     param auto-triggers `window.print()` after the document loads.
 *     This is a much cleaner UX than the previous approach (which
 *     only flipped a CSS scope and printed the current single item
 *     with "full" rules pointing at non-existent cover/TOC elements).
 *
 * DP2 locked: `window.print()` only. No new npm packages (no
 * `react-to-print`). Chromium primary per NFR-08; Firefox/Safari will
 * fall back to browser screenshots.
 *
 * The buttons are siblings inside the `<RemLayout>` header-actions
 * slot, so the layout wires them. For unit tests we render
 * them in isolation.
 */

import { useCallback, useState, type ReactElement } from 'react';
import { useTranslations } from '../../i18n';
import { SUPPORTED_LOCALES, type Locale } from '../../i18n/config';

/**
 * Resolve the active locale from `window.location.pathname` (the
 * middleware-prefixed URL is `/<locale>/rem/...`). Falls back to
 * `DEFAULT_LOCALE` on SSR or unparseable paths.
 */
function resolveActiveLocale(): Locale {
  if (typeof window === 'undefined') return 'es';
  const match = window.location.pathname.match(/^\/(es|en)(\/|$)/);
  if (match === null || match[1] === undefined) return 'es';
  const candidate = match[1];
  return (SUPPORTED_LOCALES as readonly string[]).includes(candidate)
    ? (candidate as Locale)
    : 'es';
}

export type RemPrintScope = 'single' | 'full';

// Component takes no props: the translator is sourced from the i18n
// hook so the React island stays serializable across the SSR → client
// boundary (the same reason `RemSidebar` no longer takes a `t` prop).
// The function signature is intentionally parameter-less — props are
// not part of the public API.

const PRINT_DEADLINE_MS = 100;

/**
 * Apply a `data-rem-scope` attribute on `<body>` so the (legacy)
 * print stylesheet can branch on `@media print` rules for the
 * single-mode print path. The full-report endpoint does NOT need
 * this because it uses the inline print stylesheet on the dedicated
 * `/rem/full-report` route.
 */
function setScope(scope: RemPrintScope): void {
  if (typeof document !== 'undefined') {
    document.body.setAttribute('data-rem-scope', scope);
  }
}

export function RemPrintButton(): ReactElement {
  // Serializable translator + locale URL builder.
  const { t } = useTranslations();
  const locale = resolveActiveLocale();
  const [busy, setBusy] = useState(false);

  const triggerSingle = useCallback(() => {
    setScope('single');
    const t0 = performance.now();
    setTimeout(() => {
      try {
        window.print();
      } catch {
        // Native API may be unavailable in tests / SSR shadowing.
      }
      const t1 = performance.now();
      if (t1 - t0 > PRINT_DEADLINE_MS) {
        console.warn(
          `[rem] window.print() took ${t1 - t0}ms (>${PRINT_DEADLINE_MS}ms budget)`,
        );
      }
      setBusy(false);
    }, 0);
  }, []);

  const triggerFull = useCallback(() => {
    setBusy(true);
    // Open in a new tab so the sidebar keeps its current selection.
    // The endpoint auto-triggers window.print() when ?auto_print=1.
    const url = `/${locale}/rem/full-report?auto_print=1`;
    const win = window.open(url, '_blank', 'noopener,noreferrer');
    if (win === null) {
      // Popup blocked — fall back to same-tab navigation.
      window.location.href = url;
    }
    setBusy(false);
  }, [locale]);

  const handleSingle = useCallback(() => {
    setBusy(true);
    triggerSingle();
  }, [triggerSingle]);

  const handleFull = useCallback(() => {
    setBusy(true);
    triggerFull();
  }, [triggerFull]);

  return (
    <div className="rem-print-button flex items-center gap-2" data-rem-print>
      <button
        type="button"
        data-testid="rem-print-single"
        data-rem-print-scope="single"
        aria-label={t('rem.print.single')}
        onClick={handleSingle}
        disabled={busy}
        className="rem-print-button__single inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E] disabled:cursor-not-allowed disabled:opacity-60"
      >
        {t('rem.print.single')}
      </button>
      <button
        type="button"
        data-testid="rem-print-full"
        data-rem-print-scope="full"
        aria-label={t('rem.print.full')}
        onClick={handleFull}
        disabled={busy}
        className="rem-print-button__full inline-flex items-center justify-center rounded-md border border-[#2D9D8E] bg-[#2D9D8E] px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-[#258777] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E] disabled:cursor-not-allowed disabled:opacity-60"
      >
        {t('rem.print.full')}
      </button>
    </div>
  );
}

export default RemPrintButton;
