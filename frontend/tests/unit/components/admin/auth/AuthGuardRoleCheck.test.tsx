// @vitest-environment jsdom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, waitFor } from '@testing-library/react';
import type { User } from '../../../../../src/types/auth';

const { mockHydrate } = vi.hoisted(() => ({
  mockHydrate: vi.fn(),
}));

let mockedUser: User | null = null;
let mockedIsHydrating = false;

vi.mock('../../../../../src/stores/auth', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../../../../../src/stores/auth')>();
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

import AuthGuard from '../../../../../src/components/admin/auth/AuthGuard';

/**
 * F0.5 — admin role-check en AuthGuard.
 *
 * Acceptance del plan:
 *   "Cliente que tipea /admin/dashboard manualmente → redirect a /account".
 *   AuthGuard monta en páginas internas admin (/admin/dashboard etc.).
 *   Debe rechazar a cualquier User sin rol admin/staff y mandarlo al
 *   área de cliente /account.
 *
 * NOTA: este archivo SOLO cubre el role-check (F0.5). Los tests del
 * auth-check original viven en tests/unit/components/admin/AuthGuard.test.tsx
 * y tienen fallas conocidas pre-existentes (rotos desde commit 49687c6
 * que cambió /auth/login → /account/login sin sincronizar tests).
 */
describe('components/admin/auth/AuthGuard (F0.5 role-check)', () => {
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

  beforeEach(() => {
    mockHydrate.mockReset();
    mockedIsHydrating = false;
    mockedUser = null;
    originalLocation = window.location;
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, pathname: '/es/admin/dashboard', replace: vi.fn() },
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

  it('autenticado como admin → NO llama replace (puede quedarse en /admin/dashboard)', async () => {
    mockedUser = adminUser;
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AuthGuard />);

    await waitFor(() => {
      expect(mockHydrate).toHaveBeenCalled();
    });

    expect(replaceMock).not.toHaveBeenCalled();
  });

  it('autenticado como staff → NO llama replace (F0.1 permite staff en admin)', async () => {
    mockedUser = staffUser;
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AuthGuard />);

    await waitFor(() => {
      expect(mockHydrate).toHaveBeenCalled();
    });

    expect(replaceMock).not.toHaveBeenCalled();
  });

  it('autenticado como Customer → redirect a /es/account (sin ?redirect=)', async () => {
    mockedUser = customerUser;
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AuthGuard />);

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalled();
    });

    const calledWith = replaceMock.mock.calls[0]?.[0] as string;
    expect(calledWith).toBe('/es/account');
    expect(calledWith).not.toContain('redirect=');
  });

  it('preserva el locale /en/ cuando Customer entra en /en/admin/dashboard', async () => {
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, pathname: '/en/admin/dashboard', replace: vi.fn() },
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

    render(<AuthGuard />);

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalled();
    });

    const calledWith = replaceMock.mock.calls[0]?.[0] as string;
    expect(calledWith).toContain('/en/account');
    expect(calledWith).not.toContain('/es/account');
  });
});