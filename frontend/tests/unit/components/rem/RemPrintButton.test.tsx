/**
 * RemPrintButton.test.tsx — REQ-06#1 + REQ-06#2.
 *
 * The button needs to:
 *   1. Click "Imprimir PDF" → `window.print()` invoked within 100ms.
 *   2. The print stylesheet (`styles/rem-print.css`, fetched by the
 *      layout's `<link media="print">`) applies
 *      `page-break-after: always` to `.rem-detail` under
 *      `@media print`.
 *
 * The stylesheet assertion uses jsdom's manual print emulation:
 *
 *   - We assert the CSS file ships the `@media print { .rem-detail {
 *     page-break-after: always } }` rule (file-content check), AND
 *   - The layout-rendered `<link rel="stylesheet" media="print" />`
 *     points at the file (HTML/root sanity).
 *
 * The 100 ms timing uses `performance.now()` around the click handler
 * with a stubbed `window.print` spy.
 */

import { describe, expect, it, beforeEach, vi, afterEach } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

import { render, screen, cleanup } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import RemPrintButton from "../../../../src/components/rem/RemPrintButton";
const t = (key: string) => key;

describe("RemPrintButton · REQ-06#1 + REQ-06#2", () => {
  let printSpy: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    printSpy = vi.fn();
    window.print = printSpy as unknown as typeof window.print;
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
  });

  it("REQ-06#1: clicking 'Imprimir PDF' invokes window.print within 100 ms", async () => {
    const user = userEvent.setup();
    render(<RemPrintButton t={t} />);
    const btn = screen.getByTestId("rem-print-single");

    const t0 = performance.now();
    await user.click(btn);
    const t1 = performance.now();

    // jsdom + a stubbed window.print makes the call synchronous and
    // well under the 100ms budget. The real production check is for
    // the browser's native print dialog, which DOM-level tests cannot
    // assert against directly.
    expect(printSpy).toHaveBeenCalledTimes(1);
    expect(t1 - t0).toBeLessThan(100);
  });

  it("REQ-06#2: the print stylesheet applies page-break-after:always to .rem-detail under @media print", () => {
    const cssPath = resolve(__dirname, "../../../../src/styles/rem-print.css");
    const css = readFileSync(cssPath, "utf-8");
    // The F3-locked rule: see design §10 / spec REQ-06#2.
    expect(css).toMatch(/@media\s+print\s*\{/);
    expect(css).toMatch(/\.rem-detail\s*\{[^}]*page-break-after\s*:\s*always/);
    // Plus the page-break-inside: avoid companion rule for the same
    // detail container.
    expect(css).toMatch(/\.rem-detail\s*\{[^}]*page-break-inside\s*:\s*avoid/);

    // Sanity: the layout links the stylesheet with media="print".
    const layoutPath = resolve(__dirname, "../../../../src/layouts/RemLayout.astro");
    const layout = readFileSync(layoutPath, "utf-8");
    expect(layout).toMatch(/rem-print\.css/);
    expect(layout).toMatch(/media\s*=\s*"print"/);
  });
});
