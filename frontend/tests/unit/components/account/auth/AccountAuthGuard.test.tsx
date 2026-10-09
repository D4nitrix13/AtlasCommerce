// @vitest-environment jsdom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, waitFor } from '@testing-library/react';

const { mockHydrate } = vi.hoisted(() => ({
  mockHydrate: vi.fn(),
}));

vi.mock('../../../../../src/stores/customer-auth', () => ({
  useCustomerAuth: () => ({
    isAuthenticated: false,
    isHydrating: false,
  }),
  customerAuthStore: {
    hydrate: mockHydrate,
    subscribe: () => () => {},
    getSnapshot: () => ({ token: null, user: null, customer: null, isHydrating: false }),
  },
}));

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

import AccountAuthGuard from '../../../../../src/components/account/auth/AccountAuthGuard';

/**
 * F0.3 — espejo de tests de admin/AuthGuard pero para el guard de la
 * sección /account/* del cliente.
 *
 * AccountAuthGuard debe:
 *   - Hidratar el store al mount.
 *   - NO redirigir en paths públicos (login, signup, forgot, reset,
 *     reset-success, 2fa).
 *   - Redirigir en cualquier otro /account/... preservando el locale.
 */
describe('components/account/auth/AccountAuthGuard', () => {
  let originalLocation: Location;

  beforeEach(() => {
    mockHydrate.mockReset();
    originalLocation = window.location;
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, pathname: '/es/account/orders', replace: vi.fn() },
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

  it('llama a authStore.hydrate() al mount', async () => {
    render(<AccountAuthGuard />);

    await waitFor(() => {
      expect(mockHydrate).toHaveBeenCalledTimes(1);
    });
  });

  it('NO redirige en /account/login (path público)', async () => {
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, pathname: '/es/account/login', replace: vi.fn() },
      writable: true,
      configurable: true,
    });
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AccountAuthGuard />);

    await waitFor(() => {
      expect(mockHydrate).toHaveBeenCalled();
    });

    expect(replaceMock).not.toHaveBeenCalled();
  });

  it('NO redirige en /account/forgot-password', async () => {
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, pathname: '/es/account/forgot-password', replace: vi.fn() },
      writable: true,
      configurable: true,
    });
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AccountAuthGuard />);

    await waitFor(() => {
      expect(mockHydrate).toHaveBeenCalled();
    });

    expect(replaceMock).not.toHaveBeenCalled();
  });

  it('NO redirige en /account/2fa', async () => {
    Object.defineProperty(window, 'location', {
      value: {
        ...originalLocation,
        pathname: '/es/account/2fa',
        search: '?token=42',
        replace: vi.fn(),
      },
      writable: true,
      configurable: true,
    });
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AccountAuthGuard />);

    await waitFor(() => {
      expect(mockHydrate).toHaveBeenCalled();
    });

    expect(replaceMock).not.toHaveBeenCalled();
  });

  it('redirige a /es/account/login cuando path es /es/account/orders sin auth', async () => {
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AccountAuthGuard />);

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalled();
    });

    const calledWith = replaceMock.mock.calls[0]?.[0] as string;
    expect(calledWith).toContain('/es/account/login');
    expect(calledWith).toContain('redirect=');
    expect(calledWith).toContain('%2Fes%2Faccount%2Forders');
  });

  it('preserva el locale /en/ en el redirect cuando el path origen es /en/account/wishlist', async () => {
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, pathname: '/en/account/wishlist', replace: vi.fn() },
      writable: true,
      configurable: true,
    });
    const replaceMock = vi.fn();
    Object.defineProperty(window.location, 'replace', {
      value: replaceMock,
      writable: true,
      configurable: true,
    });

    render(<AccountAuthGuard />);

    await waitFor(() => {
      expect(replaceMock).toHaveBeenCalled();
    });

    const calledWith = replaceMock.mock.calls[0]?.[0] as string;
    expect(calledWith).toContain('/en/account/login');
    expect(calledWith).not.toContain('/es/account/login');
  });
});