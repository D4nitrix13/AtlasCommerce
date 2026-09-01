/**
 * e2e/rem/rem-crud.spec.ts
 *
 * End-to-end coverage for the REM viewer CRUD flow (D4 / Task 5.2).
 *
 * Per the pragmatic guidance in `sdd-apply` (Phase 2 + 3 batch): the
 * Astro dev server is not running in this CI/local environment, so
 * the spec is shipped as `test.skip()` placeholders. The integration
 * test (`tests/unit/integration/rem-crud-flow.test.tsx`) covers the
 * React island → endpoint → tree-region swap path in isolation.
 *
 * To re-enable for local dev:
 *   1. Bring up the dev environment per `playwright-e2e-patterns`:
 *        docker compose up -d
 *        pnpm --filter frontend run dev
 *        sudo systemctl start caddy
 *   2. Run `pnpm playwright test tests/e2e/rem/rem-crud.spec.ts`.
 *
 * Maps to:
 *   REQ-18#1  + Nuevo button opens the dialog
 *   REQ-19#2  POST → 201 → close + tree refresh
 *   REQ-19#3  PUT → 200 → detail swap
 *   REQ-19#4  Cancel / Escape → no fetch
 *   REQ-19#5  500 → stays open + error alert
 *   REQ-20#1  POST endpoint round-trip
 *   REQ-21#1  PUT endpoint round-trip
 *   REQ-22#1  DELETE endpoint round-trip
 *   REQ-23#1  detail panel max-w-6xl at 1280×800
 *   NFR-09    CRUD ≤ 200 ms p95
 *
 * Implementation notes:
 *   - The page is fully SSR-rendered; the React island only needs to
 *     hydrate for the form + dialog to work. We block the dev toolbar
 *     chunks (same as `rem-viewer.spec.ts`) to keep the SSR DOM stable.
 *   - `CRUD_PERF_BUDGET_MS = 200` from NFR-09; we measure end-to-end
 *     POST latency with `performance.now()`.
 */

import { expect, test, type Page } from '@playwright/test';

const BASE = 'http://panda-estampados.com';
const ES_REM = `${BASE}/es/rem`;
const PERF_BUDGET_MS = 200;

async function blockReactChunks(page: Page): Promise<void> {
  await page.route('**/@id/__x00__astro:toolbar*', (route) => route.abort());
  await page.route('**/node_modules/.vite/deps/**', (route) => route.abort());
}

test.describe('REM viewer CRUD (v2)', () => {
  test.skip('REQ-18#1 + REQ-19#2: + Nuevo opens dialog and POST returns 201', async ({ page }) => {
    await page.goto(ES_REM);
    await blockReactChunks(page);
    // The + Nuevo button lives inside each top-level group summary.
    await page.locator('[data-rem-crud-create="stakeholders"]').first().click();
    await expect(page.getByTestId('rem-stakeholder-form-name')).toBeVisible();
    await page.getByTestId('rem-stakeholder-form-name').fill('Sponsor CRUD Test');
    const t0 = Date.now();
    await page.getByTestId('rem-stakeholder-form-submit').click();
    await page.waitForResponse(
      (resp) => resp.url().endsWith('/api/rem/stakeholder') && resp.status() === 201
    );
    const dt = Date.now() - t0;
    expect(dt).toBeLessThan(PERF_BUDGET_MS * 5); // e2e budget = 5×unit (NFR-09)
  });

  test.skip('REQ-19#3: PUT updates the row and detail panel swaps', async ({ page }) => {
    await page.goto(`${ES_REM}?id=rf_001`);
    await blockReactChunks(page);
    await page.getByTestId('rem-kebab-rf_001').click();
    await page.getByTestId('rem-crud-edit').click();
    await page.getByTestId('rem-rf-form-title').fill('Updated via PUT');
    await page.getByTestId('rem-rf-form-submit').click();
    await page.waitForResponse(
      (resp) => resp.url().includes('/api/rem/rf/rf_001') && resp.status() === 200
    );
    await expect(page.locator('.rem-detail')).toContainText('Updated via PUT');
  });

  test.skip('REQ-19#4: Cancel does not fire POST', async ({ page }) => {
    await page.goto(ES_REM);
    await blockReactChunks(page);
    await page.locator('[data-rem-crud-create="rf"]').first().click();
    let postFired = false;
    page.on('request', (req) => {
      if (req.method() === 'POST' && req.url().endsWith('/api/rem/rf')) {
        postFired = true;
      }
    });
    await page.getByTestId('rem-edit-dialog-cancel').click();
    await page.waitForTimeout(200);
    expect(postFired).toBe(false);
  });

  test.skip('REQ-19#5: 500 keeps dialog open + error alert', async ({ page }) => {
    await page.route('**/api/rem/stakeholder', (route) =>
      route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ ok: false, error: 'crud:write:EACCES' }),
      })
    );
    await page.goto(ES_REM);
    await blockReactChunks(page);
    await page.locator('[data-rem-crud-create="stakeholders"]').first().click();
    await page.getByTestId('rem-stakeholder-form-name').fill('X');
    await page.getByTestId('rem-stakeholder-form-submit').click();
    await expect(page.getByTestId('rem-edit-dialog-error')).toBeVisible();
  });

  test.skip('REQ-20#1: POST /api/rem/stakeholder round-trip', async ({ request }) => {
    const resp = await request.post(`${BASE}/api/rem/stakeholder`, {
      data: { name: 'Sponsor E2E', title: '', role: '', organization: '', notes: '' },
    });
    expect(resp.status()).toBe(201);
    const body = (await resp.json()) as { ok: boolean; uid: string };
    expect(body.ok).toBe(true);
    expect(body.uid).toMatch(/^stakeholder_\d{3}$/);
  });

  test.skip('REQ-21#1: PUT /api/rem/stakeholder/[uid] round-trip', async ({ request }) => {
    const create = await request.post(`${BASE}/api/rem/stakeholder`, {
      data: { name: 'Round Trip', title: 't', role: '', organization: '', notes: '' },
    });
    const created = (await create.json()) as { uid: string };
    const update = await request.put(`${BASE}/api/rem/stakeholder/${created.uid}`, {
      data: { uid: created.uid, name: 'Updated', title: '', role: '', organization: '', notes: '' },
    });
    expect(update.status()).toBe(200);
  });

  test.skip('REQ-22#1: DELETE /api/rem/stakeholder/[uid] returns 204', async ({ request }) => {
    const create = await request.post(`${BASE}/api/rem/stakeholder`, {
      data: { name: 'To Delete', title: '', role: '', organization: '', notes: '' },
    });
    const created = (await create.json()) as { uid: string };
    const del = await request.delete(`${BASE}/api/rem/stakeholder/${created.uid}`);
    expect(del.status()).toBe(204);
  });

  test.skip('REQ-23#1: detail panel max-w-6xl at 1280×800', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(ES_REM);
    await blockReactChunks(page);
    const main = page.locator('main.rem-shell__main');
    const width = await main.evaluate((el) => (el as HTMLElement).offsetWidth);
    // The sidebar takes ~320 px (lg:w-80) so the main sits at
    // 1280 - 320 - 48 (px-6 padding*2) ≈ 864 px (≥ lg breakpoint).
    // `max-w-6xl` is 72rem = 1152 px on Tailwind 4, so we expect the
    // main to NOT exceed that ceiling.
    expect(width).toBeLessThanOrEqual(1152);
    expect(width).toBeGreaterThan(700);
  });
});
