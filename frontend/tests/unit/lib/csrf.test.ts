// @vitest-environment jsdom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';

/**
 * Tests del módulo CSRF bootstrap para Sanctum stateful cookies (CR2).
 *
 * El módulo `csrf.ts` mantiene state interno (`cachedToken`) — usamos
 * `vi.resetModules()` + dynamic import en cada test para garantizar un
 * módulo fresh (sin cache bleed entre tests).
 *
 * Flow:
 *   1. App boot → `bootstrapCsrf()` hace GET /sanctum/csrf-cookie.
 *      Backend emite `XSRF-TOKEN=...` (URL-encoded) en Set-Cookie.
 *   2. Módulo decodifica la cookie y la cachea en `cachedToken`.
 *   3. `getXsrfToken()` devuelve el token cacheado (null antes del 1er
 *      bootstrap o si la cookie no se emitió).
 */
describe('lib/csrf · Sanctum stateful CSRF bootstrap', () => {
  beforeEach(() => {
    // Reset module cache + limpiar cookies + spies entre tests.
    vi.resetModules();
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    vi.restoreAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
  });

  it('bootstrapCsrf() llama GET /sanctum/csrf-cookie con credentials:include', async () => {
    const { bootstrapCsrf } = await import('../../../src/lib/csrf');
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(null, {
        status: 204,
        headers: { 'Set-Cookie': 'XSRF-TOKEN=test-token-123; Path=/' },
      })
    );

    await bootstrapCsrf();

    expect(fetchSpy).toHaveBeenCalledTimes(1);
    const [url, init] = fetchSpy.mock.calls[0] as [string, RequestInit];
    expect(url).toMatch(/\/sanctum\/csrf-cookie$/);
    expect(init.method ?? 'GET').toBe('GET');
    expect(init.credentials).toBe('include');
  });

  it('bootstrapCsrf() decodifica XSRF-TOKEN desde document.cookie', async () => {
    const { bootstrapCsrf, getXsrfToken } = await import('../../../src/lib/csrf');

    // Sanctum emite la cookie URL-encoded; el frontend debe decodeURIComponent
    // para obtener el token raw que enviará en X-XSRF-TOKEN header.
    const rawToken = 'abc+def/ghi==';
    const encodedToken = encodeURIComponent(rawToken);
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(null, {
        status: 204,
        headers: {},
      })
    );
    // jsdom: asignar cookie ANTES de bootstrapCsrf para que la lea.
    document.cookie = `XSRF-TOKEN=${encodedToken}; Path=/`;

    await bootstrapCsrf();

    // El token cacheado debe estar decoded (raw) — es lo que se envía
    // como header X-XSRF-TOKEN en POST stateful.
    expect(getXsrfToken()).toBe(rawToken);
  });

  it('getXsrfToken() devuelve null antes del primer bootstrapCsrf()', async () => {
    const { getXsrfToken } = await import('../../../src/lib/csrf');

    // Sin cookie + módulo fresh: cachedToken arranca en null.
    expect(getXsrfToken()).toBeNull();
  });

  it('bootstrapCsrf() maneja gracefully la ausencia de XSRF-TOKEN cookie', async () => {
    const { bootstrapCsrf, getXsrfToken } = await import('../../../src/lib/csrf');

    vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(null, { status: 204 }));
    // Sin cookie XSRF-TOKEN en document.cookie.

    await expect(bootstrapCsrf()).resolves.toBeUndefined();

    // Sin token cacheado: getXsrfToken devuelve null (no throw).
    expect(getXsrfToken()).toBeNull();
  });
});
