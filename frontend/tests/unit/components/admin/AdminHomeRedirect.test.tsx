// @vitest-environment jsdom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, waitFor } from '@testing-library/react';
import type { User } from '../../../../src/types/auth';

const { mockHydrate } = vi.hoisted(() => ({
  mockHydrate: vi.fn(),
}));

// Mock reactivo: cada test setea el user/estado deseado.
let mockedUser: User | null = null;
let mockedIsHydrating = false;

vi.mock('../../../../src/stores/auth', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../../../../src/stores/auth')>();
  return {
    ...actual,
    useAuth: () => ({
      user: mockedUser,
      token: null,
      isAuthenticated: mockedUser !== null,
      isHydrating: mockedIsHydrating,
      login: vi.fn(),
      logout: vi.fn(),
      setSession: vi.fn(),
      verifyTotpChallenge: vi.fn(),
    }),
    authStore: {
      hydrate: mockHydrate,
      subscribe: () => () => {},
      getSnapshot: () => ({ token: null, user: mockedUser, isHydrating: mockedIsHydrating }),
    },
  };
});

vi.mock('../../../../src/i18n', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../../../../src/i18n')>();
  return {
    ...actual,
    useTranslations: () => ({
      t: (key: string) => key,
      locale: 'es',
    }),
  };
});

import AdminHomeRedirect from '../../../../src/components/admin/AdminHomeRedirect';

/**
 * F0.5 — admin role-check en AdminHomeRedirect.
 *
 * Acceptance del plan:
 *   "Cliente que tipea /admin/dashboard manualmente → redirect a /account".
 *   (Interpretado: cualquier user sin rol admin/staff autenticado
 *   en el User store debe ser redirigido al área de cliente.)
 */
describe('components/admin/AdminHomeRedirect (F0.5 role-check)', () => {
  let originalLocation: Location;

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

  const staffUser: User = {
    id: 2,
    email: 'staff@example.com',
    full_name: 'Staff',
    role: { id: 2, slug: 'staff', name: 'Operador' },
    role_id: 2,
    store_ids: [1],
    is_active: true,
    permissions: ['customers.view'],
  };

  const customerUser: User = {
    id: 3,
    email: 'cust@example.com',
    full_name: 'Customer',
    role: { id: 3, slug: 'customer', name: 'Customer' },
    role_id: 3,
    store_ids: [1],
    is_active: true,
    permissions: [],
  };

  const noRoleUser: User = {
    ...adminUser,
    id: 4,
    role: null,
    role_id: null,
  };

  beforeEach(() => {
    mockHydrate.mockReset();
    mockedIsHydrating = false;
    mockedUser = null;
    originalLocation = window.location;
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, pathname: '/es/admin/', replace: vi.fn() },
      writable: true,
      configurable: true,
    });
  });

  afterEach(() => {
    Object.defineProperty(window, 'location', {
      value: originalLocation,
      writable: true,
      configurable: true,
    });
  });

  it('hidrata el store al mount', async () => {
    render(<AdminHomeRedirect />);

    await waitFor(() => {
      expect(mockHydrate).toHaveBeenCalledTimes(1);
    });
  });

  it('no autenticado → redirect a /es/account/login con redirect= query', async () => {
    mockedUser = null;
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AdminHomeRedirect />);

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalled();
    });

    const calledWith = replaceMock.mock.calls[0]?.[0] as string;
    expect(calledWith).toContain('/es/account/login');
    expect(calledWith).toContain('redirect=');
  });

  it('autenticado como admin → redirect a /es/admin/dashboard', async () => {
    mockedUser = adminUser;
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AdminHomeRedirect />);

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalled();
    });

    const calledWith = replaceMock.mock.calls[0]?.[0] as string;
    expect(calledWith).toBe('/es/admin/dashboard');
  });

  it('autenticado como staff → redirect a /es/admin/dashboard (F0.1 permite staff)', async () => {
    mockedUser = staffUser;
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AdminHomeRedirect />);

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalled();
    });

    const calledWith = replaceMock.mock.calls[0]?.[0] as string;
    expect(calledWith).toBe('/es/admin/dashboard');
  });

  it('autenticado como Customer (slug no-admin) → redirect a /es/account (sin ?redirect=)', async () => {
    mockedUser = customerUser;
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AdminHomeRedirect />);

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalled();
    });

    const calledWith = replaceMock.mock.calls[0]?.[0] as string;
    expect(calledWith).toBe('/es/account');
    expect(calledWith).not.toContain('redirect=');
  });

  it('autenticado sin rol (role null) → redirect a /es/account', async () => {
    mockedUser = noRoleUser;
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AdminHomeRedirect />);

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalled();
    });

    const calledWith = replaceMock.mock.calls[0]?.[0] as string;
    expect(calledWith).toBe('/es/account');
  });

  it('preserva el locale /en/ en todos los redirects', async () => {
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, pathname: '/en/admin/', replace: vi.fn() },
      writable: true,
      configurable: true,
    });
    mockedUser = customerUser;
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AdminHomeRedirect />);

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalled();
    });

    const calledWith = replaceMock.mock.calls[0]?.[0] as string;
    expect(calledWith).toContain('/en/account');
    expect(calledWith).not.toContain('/es/account');
  });
});