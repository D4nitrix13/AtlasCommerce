/**
 * e2e/rem/rem-sidebar.spec.ts
 *
 * End-to-end coverage for the v2 sidebar (D5 / Task 6.2):
 *   - REQ-13#2  collapsible group persistence across reloads
 *   - REQ-14#2  nested group persistence
 *   - REQ-15#1  resize handle visible + default 320 px
 *   - REQ-15#4  resize persists across reloads
 *   - REQ-23#1  detail panel max-w-6xl at 1280×800
 *   - REQ-24#1  selected leaf uses #2D9D8E/10 palette
 *
 * Per the pragmatic guidance in `sdd-apply` (Phase 2 + 3 batch): the
 * Astro dev server is not running in this CI/local environment, so
 * the spec is shipped as `test.skip()` placeholders. The unit tests
 * (`RemSidebar.test.tsx`, `RemTreeNode.test.tsx`, `RemResizeHandle.
 * test.tsx`) cover the same scenarios in isolation.
 *
 * To re-enable for local dev: bring up the dev environment per
 * `playwright-e2e-patterns` then run
 * `pnpm playwright test tests/e2e/rem/rem-sidebar.spec.ts`.
 */

import { expect, test, type Page } from '@playwright/test';

const BASE = 'http://panda-estampados.com';
const ES_REM = `${BASE}/es/rem`;

async function blockReactChunks(page: Page): Promise<void> {
  await page.route('**/@id/__x00__astro:toolbar*', (route) => route.abort());
  await page.route('**/node_modules/.vite/deps/**', (route) => route.abort());
}

test.describe('REM viewer sidebar (v2)', () => {
  test.skip('REQ-13#2: collapsing a top-level group persists across reloads', async ({ page }) => {
    await page.goto(ES_REM);
    await blockReactChunks(page);
    const rnfSummary = page.locator('details[data-rem-group-top="rnf"] summary');
    await rnfSummary.click();
    await page.reload();
    await expect(
      page.locator('details[data-rem-group-top="rnf"]')
    ).not.toHaveAttribute('open', '');
  });

  test.skip('REQ-14#2: collapsing a nested group persists across reloads', async ({ page }) => {
    await page.goto(ES_REM);
    await blockReactChunks(page);
    const auth = page.locator('details[data-rem-group-nest="rf.Autenticación"] summary');
    await auth.click();
    await page.reload();
    await expect(
      page.locator('details[data-rem-group-nest="rf.Autenticación"]')
    ).not.toHaveAttribute('open', '');
  });

  test.skip('REQ-15#1: resize handle is visible at 1280×800 (lg breakpoint)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(ES_REM);
    await blockReactChunks(page);
    await expect(page.locator('[data-rem-resize-handle]')).toBeVisible();
  });

  test.skip('REQ-15#4: resized width persists across reloads (clamped to [240, 480])', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(ES_REM);
    await blockReactChunks(page);
    // Simulate a resize by writing to localStorage.
    await page.evaluate(() => {
      window.localStorage.setItem('rem.viewer.sidebar.width', '400');
    });
    await page.reload();
    await blockReactChunks(page);
    const aside = page.locator('aside.rem-shell__nav');
    const width = await aside.evaluate((el) => (el as HTMLElement).offsetWidth);
    // Allow a few px of tolerance for borders / scrollbars.
    expect(width).toBeGreaterThanOrEqual(395);
    expect(width).toBeLessThanOrEqual(405);
  });

  test.skip('REQ-23#1: detail panel width at 1280×800 fits max-w-6xl (≤ 1152 px)', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(ES_REM);
    await blockReactChunks(page);
    const main = page.locator('main.rem-shell__main');
    const width = await main.evaluate((el) => (el as HTMLElement).offsetWidth);
    expect(width).toBeLessThanOrEqual(1152);
  });

  test.skip('REQ-24#1: selected leaf uses rgba(45, 157, 142, 0.10) (#2D9D8E/10)', async ({ page }) => {
    await page.goto(ES_REM);
    await blockReactChunks(page);
    const selected = page.locator('[data-rem-leaf-id="stakeholder_001"]');
    const bg = await selected.evaluate(
      (el) => window.getComputedStyle(el as HTMLElement).backgroundColor
    );
    expect(bg).toBe('rgba(45, 157, 142, 0.1)');
  });
});
