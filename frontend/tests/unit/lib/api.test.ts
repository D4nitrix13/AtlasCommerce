// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { apiFetch, type ApiError } from '../../../src/lib/api';

describe('lib/api · network error wrapping', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('wraps fetch TypeError as ApiError con isNetworkError: true', async () => {
    // fetch() lanza TypeError cuando la red cae (Failed to fetch).
    vi.spyOn(globalThis, 'fetch').mockRejectedValue(new TypeError('Failed to fetch'));

    let caught: ApiError | null = null;
    try {
      await apiFetch('/auth/me', { token: 'x' });
    } catch (error) {
      caught = error as ApiError;
    }

    expect(caught).not.toBeNull();
    expect(caught?.isNetworkError).toBe(true);
    expect(caught?.status).toBe(0);
    expect(typeof caught?.message).toBe('string');
    expect(caught?.message.length).toBeGreaterThan(0);
  });

  it('mantiene throw shape para errores HTTP no-2xx (isNetworkError: false)', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ message: 'No autenticado.' }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' },
      })
    );

    let caught: ApiError | null = null;
    try {
      await apiFetch('/auth/me', { token: 'x' });
    } catch (error) {
      caught = error as ApiError;
    }

    expect(caught).not.toBeNull();
    expect(caught?.isNetworkError).toBe(false);
    expect(caught?.status).toBe(401);
    expect(caught?.message).toBe('No autenticado.');
    expect(caught?.errors).toBeNull();
  });

  it('fetch ok (2xx) devuelve el body parseado y NO lanza', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ ok: 1 }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    );

    const result = await apiFetch<{ ok: number }>('/ping');
    expect(result.ok).toBe(1);
  });

  it('ApiError type incluye isNetworkError: boolean', () => {
    // Esto es un type-level test: si la propiedad no existe, typecheck fail.
    const sample: ApiError = {
      status: 0,
      message: 'Network error',
      errors: null,
      isNetworkError: true,
    };
    expect(sample.isNetworkError).toBe(true);
  });
});
