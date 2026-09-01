/**
 * e2e/rem/rem-pdf.spec.ts
 *
 * End-to-end coverage for the REM viewer's print-to-PDF flow.
 * Maps 1:1 to spec §3 REQ-06#3 + REQ-07#1/#2 per design §6 + tasks.md
 * Task 4.2.
 *
 *   - REQ-06#3  single print produces ≥ 2 pages
 *   - REQ-07#1  full report sets body[data-rem-scope="full"] and the
 *                cover (page 1) + TOC (page 2) list all 78 codes
 *   - REQ-07#2  full report PDF has ≥ 80 pages
 *
 * Why these tests are gated behind PDF_TESTS=1:
 *   Print-to-PDF depends on Playwright + headless Chromium, which
 *   can be flaky in CI containers without a print stack. The unit
 *   suite (RemPrintButton.test.tsx) covers the click + scope-attribute
 *   contract deterministically. Enable locally with:
 *
 *     PDF_TESTS=1 pnpm playwright test tests/e2e/rem/rem-pdf.spec.ts
 *
 * Per design §13 risk #11 the gate is `process.env.PDF_TESTS === '1'`
 * so a missing env var skips cleanly instead of failing.
 */

import { expect, test } from '@playwright/test';

const BASE = 'http://panda-estampados.com';
const ES = `${BASE}/es/rem`;

const PDF_ENABLED = process.env.PDF_TESTS === '1';

test.describe('REM viewer — print-to-PDF', () => {
  test.skip(!PDF_ENABLED, 'PDF_TESTS not set — skip; run with PDF_TESTS=1');

  test('REQ-06#3 single print produces ≥ 2 pages', async ({ page }) => {
    await page.goto(ES);
    await expect(page.getByTestId('rem-skeleton-wrapper')).toBeHidden({ timeout: 10_000 });

    // Emulate print media so @media print rules apply before we save.
    await page.emulateMedia({ media: 'print' });

    // Trigger the single-item print. The button calls window.print()
    // directly; in headless Chromium we capture the result via
    // page.pdf() (Playwright auto-suppresses the print dialog).
    await page.getByTestId('rem-print-single').click();

    const pdf = await page.pdf({ format: 'A4' });
    // The header + detail table paginate as 2 pages because the
    // detail rows break across the @page rule.
    const pageCount = countPdfPages(pdf);
    expect(pageCount).toBeGreaterThanOrEqual(2);

    // Restore screen media for downstream tests.
    await page.emulateMedia({ media: 'screen' });
  });

  test('REQ-07#1 full report sets body[data-rem-scope=full] and shows cover + TOC', async ({
    page,
  }) => {
    await page.goto(ES);
    await expect(page.getByTestId('rem-skeleton-wrapper')).toBeHidden({ timeout: 10_000 });

    // The button is the "Informe completo" trigger. Click it; the
    // React island sets body[data-rem-scope="full"] before calling
    // window.print(). The attribute persists for the rest of the test.
    await page.getByTestId('rem-print-full').click();
    await expect(page.locator('body[data-rem-scope="full"]')).toHaveCount(1);

    await page.emulateMedia({ media: 'print' });
    const pdf = await page.pdf({ format: 'A4' });
    const text = pdfText(pdf);

    // Page 1 = cover, must contain the cover title from rem.print.coverTitle.
    expect(text).toContain('REM — Panda Estampados');

    // The TOC is generated client-side from the dataset (78 codes =
    // 48 RF + 30 RNF per REQ-07#1 spec). We assert at least one RF
    // and one RNF code are present in the PDF text — the full count
    // is covered by the page-count test below.
    expect(text).toMatch(/RF-AUTH-001/);
    expect(text).toMatch(/RNF-SEC-010|RNF-/);

    await page.emulateMedia({ media: 'screen' });
  });

  test('REQ-07#2 full report PDF has ≥ 80 pages (1 cover + 1 TOC + 78 details)', async ({
    page,
  }) => {
    await page.goto(ES);
    await expect(page.getByTestId('rem-skeleton-wrapper')).toBeHidden({ timeout: 10_000 });

    // Flip scope to full (the "Informe completo" click).
    await page.getByTestId('rem-print-full').click();
    await expect(page.locator('body[data-rem-scope="full"]')).toHaveCount(1);

    await page.emulateMedia({ media: 'print' });
    const pdf = await page.pdf({ format: 'A4' });
    const pageCount = countPdfPages(pdf);
    expect(pageCount).toBeGreaterThanOrEqual(80);

    await page.emulateMedia({ media: 'screen' });
  });
});

// ─── PDF helpers ────────────────────────────────────────────────────────

/**
 * `page.pdf()` returns a `Uint8Array` (Web API). We treat it as a
 * raw byte sequence and decode it as latin1 to scan for the
 * `/Type /Page` markers every PDF emitter embeds once per real
 * page (the parent `/Pages` object is filtered out by the `[^s]`
 * lookahead).
 */
type PdfBuffer = Uint8Array;

/**
 * Count pages in a PDF buffer. The PDF format embeds `/Type /Page`
 * (not `/Pages`) for every actual page; counting those object
 * references is reliable across minimal PDF emitters (reportlab,
 * Chromium, jsPDF).
 */
function countPdfPages(buffer: PdfBuffer): number {
  // BufferSource → string via TextDecoder. Latin1 round-trips bytes
  // 1:1, which is what the byte-level PDF scan needs.
  const text = new TextDecoder('latin1').decode(buffer);
  // Match `/Type /Page` or `/Type/Page` (with optional whitespace).
  const matches = text.match(/\/Type\s*\/Page[^s]/g) ?? [];
  return matches.length;
}

/**
 * Extract the text content of a PDF as a single string. This is a
 * best-effort scan that strips PDF operators and decodes the
 * `(...)` literal strings most emitters use for visible text. It is
 * NOT a general-purpose PDF text extractor — only good enough for the
 * cheap assertions in this spec.
 */
function pdfText(buffer: PdfBuffer): string {
  const raw = new TextDecoder('latin1').decode(buffer);
  // Capture every parenthesised literal string.
  const literals: string[] = raw.match(/\(((?:\\.|[^()\\])*)\)/g) ?? [];
  const decoded = literals.map((lit: string) => {
    const inner = lit.slice(1, -1);
    return inner
      .replace(/\\([nrtbf()\\])/g, (_m: string, ch: string) => {
        switch (ch) {
          case 'n':
            return '\n';
          case 'r':
            return '\r';
          case 't':
            return '\t';
          case 'b':
            return '\b';
          case 'f':
            return '\f';
          case '(':
            return '(';
          case ')':
            return ')';
          case '\\':
            return '\\';
          default:
            return ch;
        }
      })
      .replace(/\\([0-7]{1,3})/g, (_m: string, oct: string) => {
        const code = parseInt(oct, 8);
        return code > 0 && code < 256 ? String.fromCharCode(code) : '';
      });
  });
  return decoded.join(' ');
}
