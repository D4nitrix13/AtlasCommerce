/**
 * /api/rem/[uid].html — unit tests (Task 2.11).
 *
 * Covers the three status codes the spec requires:
 *   - 200 with HTML body when UID resolves to a dataset item.
 *   - 404 when UID does not resolve to any item.
 *   - 500 (with `RemError` markup) when the loader throws.
 *
 * We mock `lib/rem/loader` so the test runs offline (no need for the
 * real Doorstop folder on disk) and exercises the endpoint's
 * branches deterministically.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { SEED_MINI } from '../rem/fixtures/seed-mini';
import { RemParseError } from '../../../src/lib/rem/types';

// Mock the loader so we control the dataset the endpoint sees. The
// mock is hoisted by Vitest so the imports below resolve to the fake.
vi.mock('../../../src/lib/rem/loader', () => ({
  loadAllRequirements: vi.fn(),
  clearLoaderCache: vi.fn(),
}));

// Mock `clearLoaderCache` is bound through the same module; we only
// need to assert on `loadAllRequirements` here.
import { loadAllRequirements } from '../../../src/lib/rem/loader';

// The endpoint is imported lazily so the loader mock is in scope.
const loadModule = async () => await import('../../../src/pages/api/rem/[uid].html');

// ─── Helpers ─────────────────────────────────────────────────────────

function callEndpoint(uid: string): Promise<Response> {
  return loadModule().then((mod) =>
    mod.GET({
      // The endpoint only reads `params.uid`; everything else can be
      // a minimal stub matching `APIContext`'s surface.
      params: { uid },
      request: new Request(`http://localhost/api/rem/${uid}.html`),
      url: new URL(`http://localhost/api/rem/${uid}.html`),
    } as unknown as Parameters<typeof mod.GET>[0])
  );
}

const asDatasetMock = (): ReturnType<typeof loadAllRequirements> => SEED_MINI;

beforeEach(() => {
  vi.clearAllMocks();
});

afterEach(() => {
  vi.restoreAllMocks();
});

// ─── Tests ──────────────────────────────────────────────────────────

describe('/api/rem/[uid].html', () => {
  it('200 — returns the rendered detail HTML for an existing UID', async () => {
    vi.mocked(loadAllRequirements).mockReturnValue(asDatasetMock());

    const response = await callEndpoint('rf_001');
    expect(response.status).toBe(200);
    expect(response.headers.get('Content-Type')).toContain('text/html');

    const body = await response.text();
    // Header row + 8 detail rows for an RF.
    expect(body).toMatch(/data-rem-detail-render/);
    expect(body).toMatch(/data-rem-detail-kind="rf"/);
    expect(body).toMatch(/data-rem-detail-uid="rf_001"/);
    expect(body).toMatch(/RF-AUTH-001/);
    // The title row's value should be the seeded title.
    expect(body).toContain('Login con email y contraseña');
    // 8 row elements.
    const rowCount = (body.match(/<tr\b/g) ?? []).length;
    expect(rowCount).toBe(8);
  });

  it('404 — returns a RemError-shaped body when the UID does not exist', async () => {
    vi.mocked(loadAllRequirements).mockReturnValue(asDatasetMock());

    const response = await callEndpoint('rf_does_not_exist');
    expect(response.status).toBe(404);
    expect(response.headers.get('Content-Type')).toContain('text/html');

    const body = await response.text();
    // 404 body is the `RemError.astro` markup so the React island can
    // swap it into `.rem-detail` verbatim.
    expect(body).toMatch(/data-rem-error/);
    expect(body).toMatch(/data-rem-error-code="notfound:rf_does_not_exist"/);
    expect(body).toMatch(/data-rem-error-variant="notfound"/);
  });

  it('500 — returns the loader-error markup when loadAllRequirements throws', async () => {
    vi.mocked(loadAllRequirements).mockImplementation(() => {
      throw new RemParseError('/abs/path/to/req/rf_001.yml', new Error('bad indent'));
    });

    const response = await callEndpoint('rf_001');
    expect(response.status).toBe(500);
    expect(response.headers.get('Content-Type')).toContain('text/html');

    const body = await response.text();
    expect(body).toMatch(/data-rem-error/);
    expect(body).toMatch(/data-rem-error-code="load:parse-error:\/abs\/path\/to\/req\/rf_001\.yml"/);
    expect(body).toMatch(/data-rem-error-variant="load"/);
  });
});
