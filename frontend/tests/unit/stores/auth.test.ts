/** @vitest-environment jsdom */
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { authStore, getActiveStoreId, getAuthToken } from '../../../src/stores/auth';
import type { LoginResponse, User } from '../../../src/types/auth';

beforeEach(() => {
  // Guard defensivo: en este archivo jsdom a veces no expone
  // window.localStorage en el momento de beforeEach.
  if (typeof window !== 'undefined' && window.localStorage) {
    window.localStorage.clear();
  }
});

const fakeUser: User = {
  id: 1,
  email: 'test@example.com',
  full_name: 'Test User',
  role: { id: 1, slug: 'admin', name: 'Administrador' },
  role_id: 1,
  store_ids: [1, 2],
  is_active: true,
  permissions: [],
};

const fakeLoginResponse: LoginResponse = {
  token: 'abc123token',
  user: fakeUser,
};

describe('stores/auth', () => {
  describe('getActiveStoreId', () => {
    it('retorna null cuando no hay store_id persistido', () => {
      expect(getActiveStoreId()).toBeNull();
    });

    it('retorna el store_id persistido', () => {
      window.localStorage.setItem('auth.store_id', '42');
      expect(getActiveStoreId()).toBe(42);
    });

    it('retorna null cuando el valor es corrupto', () => {
      window.localStorage.setItem('auth.store_id', 'no-es-numero');
      expect(getActiveStoreId()).toBeNull();
    });

    it('retorna null cuando el valor es una colección corrupta', () => {
      window.localStorage.setItem('auth.store_id', '3.14');
      expect(getActiveStoreId()).toBe(3);
    });
  });

  describe('login', () => {
    it('guarda token y user en localStorage', async () => {
      vi.spyOn(globalThis, 'fetch').mockResolvedValue(
        new Response(JSON.stringify(fakeLoginResponse), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      );

      await authStore.login({ email: 'test@example.com', password: 'secret' });

      expect(window.localStorage.getItem('auth.token')).toBe('abc123token');
      expect(window.localStorage.getItem('auth.store_id')).toBe('1');
    });

    it('lanza ApiError cuando el backend responde con error', async () => {
      vi.spyOn(globalThis, 'fetch').mockResolvedValue(
        new Response(
          JSON.stringify({
            message: 'Credenciales inválidas',
            errors: { email: ['Email no existe'] },
          }),
          { status: 422, headers: { 'Content-Type': 'application/json' } }
        )
      );

      await expect(
        authStore.login({ email: 'fail@example.com', password: 'bad' })
      ).rejects.toMatchObject({
        status: 422,
        message: 'Credenciales inválidas',
      });
    });
  });

  describe('setSession', () => {
    it('persiste token y store_id', () => {
      authStore.setSession(fakeLoginResponse);

      expect(window.localStorage.getItem('auth.token')).toBe('abc123token');
      expect(window.localStorage.getItem('auth.store_id')).toBe('1');
    });

    it('no escribe store_id si el user no tiene store_ids', () => {
      const response = {
        ...fakeLoginResponse,
        user: { ...fakeUser, store_ids: [] },
      };
      authStore.setSession(response);

      expect(window.localStorage.getItem('auth.token')).toBe('abc123token');
      expect(window.localStorage.getItem('auth.store_id')).toBeNull();
    });
  });

  describe('logout', () => {
    it('limpia token y store_id del localStorage', async () => {
      window.localStorage.setItem('auth.token', 'abc123');
      window.localStorage.setItem('auth.store_id', '1');

      vi.spyOn(globalThis, 'fetch').mockResolvedValue(
        new Response(JSON.stringify({ message: 'Sesión cerrada.' }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      );

      await authStore.logout();

      expect(window.localStorage.getItem('auth.token')).toBeNull();
      expect(window.localStorage.getItem('auth.store_id')).toBeNull();
    });

    it('limpia local incluso si el backend falla', async () => {
      window.localStorage.setItem('auth.token', 'abc123');
      window.localStorage.setItem('auth.store_id', '1');

      vi.spyOn(globalThis, 'fetch').mockRejectedValue(new Error('network'));

      await authStore.logout();

      expect(window.localStorage.getItem('auth.token')).toBeNull();
      expect(window.localStorage.getItem('auth.store_id')).toBeNull();
    });
  });

  describe('hydrate', () => {
    it('rehidrata user desde /auth/me con token válido', async () => {
      window.localStorage.setItem('auth.token', 'abc123');

      vi.spyOn(globalThis, 'fetch').mockResolvedValue(
        new Response(JSON.stringify({ user: fakeUser }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      );

      await authStore.hydrate();

      expect(window.localStorage.getItem('auth.store_id')).toBe('1');
    });

    it('limpia token si /auth/me devuelve 401', async () => {
      window.localStorage.setItem('auth.token', 'expired');

      vi.spyOn(globalThis, 'fetch').mockResolvedValue(
        new Response(JSON.stringify({ message: 'Unauthenticated' }), {
          status: 401,
          headers: { 'Content-Type': 'application/json' },
        })
      );

      await authStore.hydrate();

      expect(window.localStorage.getItem('auth.token')).toBeNull();
      expect(window.localStorage.getItem('auth.store_id')).toBeNull();
    });

    it('no hace nada sin token', async () => {
      const fetchSpy = vi.spyOn(globalThis, 'fetch');
      await authStore.hydrate();
      expect(fetchSpy).not.toHaveBeenCalled();
    });

    /**
     * sdd/critical-2fa-frontend-flow · Task 2.2
     *
     * Cuando el backend está caído (fetch rechaza con TypeError) NO
     * debemos limpiar el token: el token sigue siendo criptográficamente
     * válido, solo no pudimos validarlo contra el backend. Limpiar el
     * token en ese caso cierra la sesión al primer blip de red.
     */
    it('preserva el token cuando fetch tira TypeError (backend caído)', async () => {
      window.localStorage.setItem('auth.token', 'still-valid');
      window.localStorage.setItem('auth.store_id', '1');

      vi.spyOn(globalThis, 'fetch').mockRejectedValue(new TypeError('Failed to fetch'));

      await authStore.hydrate();

      expect(window.localStorage.getItem('auth.token')).toBe('still-valid');
      expect(window.localStorage.getItem('auth.store_id')).toBe('1');
      expect(getAuthToken()).toBe('still-valid');
    });

    it('preserva el token en HTTP 500 (no es un problema de auth)', async () => {
      window.localStorage.setItem('auth.token', 'still-valid');

      vi.spyOn(globalThis, 'fetch').mockResolvedValue(
        new Response(JSON.stringify({ message: 'Server error' }), {
          status: 500,
          headers: { 'Content-Type': 'application/json' },
        })
      );

      await authStore.hydrate();

      expect(window.localStorage.getItem('auth.token')).toBe('still-valid');
    });

    it('limpia el token en HTTP 403 (forbidden, además de 401)', async () => {
      window.localStorage.setItem('auth.token', 'forbidden');

      vi.spyOn(globalThis, 'fetch').mockResolvedValue(
        new Response(JSON.stringify({ message: 'Forbidden' }), {
          status: 403,
          headers: { 'Content-Type': 'application/json' },
        })
      );

      await authStore.hydrate();

      expect(window.localStorage.getItem('auth.token')).toBeNull();
    });
  });

  /**
   * sdd/critical-2fa-frontend-flow · Task 1.1
   *
   * Discriminated union en `login()`: el backend puede responder HTTP
   * 200 con `{ token, user }` (success) o con
   * `{ status: '2fa_required', challenge_token, method, expires_at }`
   * (challenge). El store NO debe escribir token/user en este último
   * caso — eso lo hace el caller después de verificar el código.
   */
  describe('login (discriminated union 2fa_required)', () => {
    it("responde 2fa_required con kind='2fa_required' y NO escribe token/user", async () => {
      vi.spyOn(globalThis, 'fetch').mockResolvedValue(
        new Response(
          JSON.stringify({
            status: '2fa_required',
            challenge_token: 42,
            method: 'totp',
            expires_at: '2026-08-15T23:00:00Z',
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        )
      );

      const result = await authStore.login({
        email: 'admin@example.com',
        password: 'secret',
      });

      expect(result.kind).toBe('2fa_required');
      if (result.kind === '2fa_required') {
        expect(result.challengeId).toBe(42);
        expect(result.method).toBe('totp');
        expect(result.expiresAt).toBe('2026-08-15T23:00:00Z');
      }

      // No debe haber escrito token ni user en el store.
      expect(window.localStorage.getItem('auth.token')).toBeNull();
      expect(window.localStorage.getItem('auth.store_id')).toBeNull();
      expect(getAuthToken()).toBeNull();
    });

    it("responde success con kind='success' y persiste token + store_id", async () => {
      vi.spyOn(globalThis, 'fetch').mockResolvedValue(
        new Response(JSON.stringify(fakeLoginResponse), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        })
      );

      const result = await authStore.login({
        email: 'test@example.com',
        password: 'secret',
      });

      expect(result.kind).toBe('success');
      if (result.kind === 'success') {
        expect(result.token).toBe('abc123token');
        expect(result.user.email).toBe('test@example.com');
      }

      expect(window.localStorage.getItem('auth.token')).toBe('abc123token');
      expect(window.localStorage.getItem('auth.store_id')).toBe('1');
    });

    it('HTTP 401 sigue siendo un throw, no un union member', async () => {
      vi.spyOn(globalThis, 'fetch').mockResolvedValue(
        new Response(JSON.stringify({ message: 'Credenciales inválidas' }), {
          status: 401,
          headers: { 'Content-Type': 'application/json' },
        })
      );

      await expect(authStore.login({ email: 'x@x.com', password: 'bad' })).rejects.toMatchObject({
        status: 401,
        message: 'Credenciales inválidas',
      });
    });
  });
});
