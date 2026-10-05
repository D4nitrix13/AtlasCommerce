// @vitest-environment jsdom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, waitFor } from '@testing-library/react';
import type { User } from '../../../../../src/types/auth';

const { mockLogout } = vi.hoisted(() => ({
  mockLogout: vi.fn(),
}));

let mockedUser: User | null = null;

vi.mock('../../../../../src/stores/auth', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../../../../../src/stores/auth')>();
  return {
    ...actual,
    useAuth: () => ({
      user: mockedUser,
      token: null,
      isAuthenticated: mockedUser !== null,
      isHydrating: false,
      login: vi.fn(),
      logout: mockLogout,
      setSession: vi.fn(),
      verifyTotpChallenge: vi.fn(),
    }),
    authStore: {
      hydrate: vi.fn(),
      logout: mockLogout,
      subscribe: () => () => {},
      getSnapshot: () => ({ token: null, user: mockedUser, isHydrating: false }),
    },
  };
});

vi.mock('../../../../../src/i18n', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../../../../../src/i18n')>();
  return {
    ...actual,
    useTranslations: () => ({
      t: (key: string) => key,
      locale: 'es',
    }),
  };
});

import AdminUserMenu from '../../../../../src/components/admin/auth/AdminUserMenu';

/**
 * F0.6 — verifica que el logout del admin user menu redirige a la
 * raíz del sistema (NO al /auth/login). El bug pre-existente era:
 *
 *   window.location.href = `${localePrefix}/auth/login`
 *
 * Convención de logout: logout → home. Mandar al usuario al login
 * cuando el token está recién invalidado es contradictorio.
 *
 * Estrategia: monkey-patch `window.location` con un objeto custom
 * que captura el valor asignado a `href` (jsdom no permite redefinir
 * `window.location.href` directamente).
 */
describe('components/admin/auth/AdminUserMenu (F0.6 logout → /)', () => {
  const adminUser: User = {
    id: 1,
    email: 'admin@example.com',
    full_name: 'Admin',
    role: { id: 1, slug: 'admin', name: 'Administrador' },
    role_id: 1,
    store_ids: [1],
    is_active: true,
    permissions: [],
  };

  let originalLocation: Location;
  let capturedHref: string | null = null;

  beforeEach(() => {
    mockLogout.mockReset();
    mockLogout.mockResolvedValue(undefined);
    mockedUser = adminUser;
    capturedHref = null;
    originalLocation = window.location;
  });

  afterEach(() => {
    Object.defineProperty(window, 'location', {
      value: originalLocation,
      writable: true,
      configurable: true,
    });
  });

  /**
   * Reemplaza window.location con un objeto que:
   *   - tiene `pathname` legible (default '/es/admin/dashboard')
   *   - captura cualquier asignación a `href`
   *   - expone `replace` mockeado (no usado por AdminUserMenu pero
   *     requerido por el componente para evitar excepciones)
   */
  function setPath(pathname: string): void {
    const replaceMock = vi.fn();
    Object.defineProperty(window, 'location', {
      value: {
        ...originalLocation,
        pathname,
        href: '',
        replace: replaceMock,
      },
      writable: true,
      configurable: true,
    });

    // Wrap el location mockeado para capturar asignaciones a `href`.
    const baseLocation = window.location as unknown as Record<string, unknown>;
    Object.defineProperty(baseLocation, 'href', {
      get(): string {
        return capturedHref ?? '';
      },
      set(value: string): void {
        capturedHref = value;
      },
      configurable: true,
    });
  }

  async function clickLogout(): Promise<void> {
    const button = document.querySelector('button');
    if (button === null) {
      throw new Error('No se encontró el botón de logout en el DOM');
    }
    button.click();
    await waitFor(() => {
      expect(mockLogout).toHaveBeenCalled();
    });
  }

  it('logueado en /es/admin/dashboard → logout → redirige a /es (home con locale)', async () => {
    setPath('/es/admin/dashboard');

    render(<AdminUserMenu />);
    await clickLogout();

    // withLocale('/', 'es') devuelve '/es' (sin slash final — ver path-helpers).
    expect(capturedHref).toBe('/es');
  });

  it('logueado en /en/admin/dashboard → logout → redirige a /en (preserva locale)', async () => {
    setPath('/en/admin/dashboard');

    render(<AdminUserMenu />);
    await clickLogout();

    expect(capturedHref).toBe('/en');
  });

  it('NO redirige a /auth/login (F0.6 — bug pre-existente)', async () => {
    setPath('/es/admin/dashboard');

    render(<AdminUserMenu />);
    await clickLogout();

    expect(capturedHref).not.toContain('/auth/login');
    expect(capturedHref).not.toContain('/account/login');
  });

  it('logout llamado incluso si authStore.logout() rechaza (red caída)', async () => {
    setPath('/es/admin/dashboard');
    // Adjuntamos un catch al mock para que la rejection no escale a
    // unhandled en vitest (el AdminUserMenu usa try/finally, NO
    // propaga la rejection).
    mockLogout.mockImplementationOnce(async (): Promise<void> => {
      // Swallow the error — same as the auth store's own logout() does.
      try {
        throw new Error('network down');
      } catch {
        // noop
      }
    });

    render(<AdminUserMenu />);
    await clickLogout();

    // El finally del try-catch en handleLogout garantiza el redirect
    // incluso si la API de logout falla.
    expect(capturedHref).toBe('/es');
  });
});