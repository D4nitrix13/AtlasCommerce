/**
 * RemPrintButton.tsx
 *
 * React island: print-to-PDF trigger (REQ-06 + REQ-07).
 *
 * Two buttons:
 *   - "Imprimir PDF" → `body[data-rem-scope="single"]` (default) →
 *     `window.print()`. Used when the user wants the current selection
 *     only.
 *
 *   - "Informe completo" → flips `body[data-rem-scope="full"]` first
 *     so the print stylesheet (`styles/rem-print.css`, `@media print`)
 *     generates a cover page + TOC + every requirement paginated.
 *
 * DP2 locked: `window.print()` only. No new npm packages (no
 * `react-to-print`). Chromium primary per NFR-08; Firefox/Safari will
 * fall back to browser screenshots.
 *
 * The buttons are siblings inside the `<RemLayout>` header-actions
 * slot, so the layout (Batch 2b) wires them. For unit tests we render
 * them in isolation.
 */

import { useCallback, useState, type ReactElement } from 'react';

export type RemPrintScope = 'single' | 'full';

export interface RemPrintButtonProps {
  /** Optional translator (Batch 2b wires real i18n). */
  t?: (key: string) => string;
}

const PRINT_DEADLINE_MS = 100;

/**
 * Apply a `data-rem-scope` attribute on `<body>` so the print
 * stylesheet can branch on `@media print` rules. Reset on full
 * completion.
 */
function setScope(scope: RemPrintScope): void {
  if (typeof document !== 'undefined') {
    document.body.setAttribute('data-rem-scope', scope);
  }
}

export function RemPrintButton({ t = (k: string) => k }: RemPrintButtonProps): ReactElement {
  const [busy, setBusy] = useState(false);

  const triggerPrint = useCallback((scope: RemPrintScope) => {
    setScope(scope);
    const t0 = performance.now();
    try {
      window.print();
    } catch {
      // Native API may be unavailable in tests / SSR shadowing. The
      // business logic still ran (scope attribute + busy state).
    }
    const t1 = performance.now();
    // Reference the deadline so the linter keeps it (used by tests).
    if (t1 - t0 > PRINT_DEADLINE_MS) {
      console.warn(`[rem] window.print() took ${t1 - t0}ms (>${PRINT_DEADLINE_MS}ms budget)`);
    }
    setBusy(false);
  }, []);

  const handleSingle = useCallback(() => {
    setBusy(true);
    triggerPrint('single');
  }, [triggerPrint]);

  const handleFull = useCallback(() => {
    setBusy(true);
    triggerPrint('full');
  }, [triggerPrint]);

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
        className="rem-print-button__full inline-flex items-center justify-center rounded-md border border-[#2D9D8E] bg-[#2D9D8E] px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-[#24897B] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E] disabled:cursor-not-allowed disabled:opacity-60"
      >
        {t('rem.print.full')}
      </button>
    </div>
  );
}

export default RemPrintButton;
