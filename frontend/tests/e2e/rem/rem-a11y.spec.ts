/**
 * e2e/rem/rem-a11y.spec.ts
 *
 * End-to-end coverage for the v2 a11y contracts (D5 / Task 6.2):
 *   - REQ-13#1  3 <details data-rem-group-top> render
 *   - REQ-16#3  kebab button is keyboard accessible + has aria-haspopup
 *   - NFR-13    axe-core scan reports zero AA violations on /es/rem
 *
 * Per the pragmatic guidance in `sdd-apply` (Phase 2 + 3 batch):
 * the Astro dev server is not running in this CI/local environment,
 * so the spec is shipped as `test.skip()` placeholders. Unit tests
 * (`RemKebabMenu.test.tsx`, `RemGroupShell.test.tsx`) cover the same
 * contracts in isolation.
 *
 * To re-enable for local dev: bring up the dev environment per
 * `playwright-e2e-patterns`, install `axe-core` per `astro-themes-and
 * -palette` skill, then run `pnpm playwright test rem/rem-a11y`.
 */

import { expect, test, type Page } from '@playwright/test';

const BASE = 'http://panda-estampados.com';
const ES_REM = `${BASE}/es/rem`;

async function blockReactChunks(page: Page): Promise<void> {
  await page.route('**/@id/__x00__astro:toolbar*', (route) => route.abort());
  await page.route('**/node_modules/.vite/deps/**', (route) => route.abort());
}

test.describe('REM viewer accessibility (v2)', () => {
  test.skip('REQ-13#1: 3 <details data-rem-group-top="..."> render (stakeholders, rf, rnf)', async ({
    page,
  }) => {
    await page.goto(ES_REM);
    await blockReactChunks(page);
    await expect(
      page.locator('details[data-rem-group-top="stakeholders"]')
    ).toHaveCount(1);
    await expect(
      page.locator('details[data-rem-group-top="rf"]')
    ).toHaveCount(1);
    await expect(
      page.locator('details[data-rem-group-top="rnf"]')
    ).toHaveCount(1);
  });

  test.skip('REQ-16#3: kebab button has aria-haspopup="menu" and aria-expanded', async ({ page }) => {
    await page.goto(ES_REM);
    await blockReactChunks(page);
    const kebab = page.getByTestId('rem-kebab-stakeholder_001').first();
    await expect(kebab).toHaveAttribute('aria-haspopup', 'menu');
    await expect(kebab).toHaveAttribute('aria-expanded', 'false');
    await kebab.click();
    await expect(kebab).toHaveAttribute('aria-expanded', 'true');
  });

  test.skip('NFR-13: axe-core scan reports zero AA violations on /es/rem', async ({ page }) => {
    await page.goto(ES_REM);
    await blockReactChunks(page);
    // Inject axe-core into the page. (Production install via
    // `@axe-core/playwright` would replace this in CI.)
    await page.addScriptTag({
      path: require.resolve('axe-core'),
    });
    const results = await page.evaluate(async () => {
      // @ts-expect-error axe is loaded via addScriptTag
      return await window.axe.run(document, {
        runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa'] },
      });
    });
    const violations = (results as { violations: unknown[] }).violations;
    expect(violations).toEqual([]);
  });
});
