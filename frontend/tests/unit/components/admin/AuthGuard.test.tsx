// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, waitFor } from '@testing-library/react';

const { mockHydrate } = vi.hoisted(() => ({
  mockHydrate: vi.fn(),
}));

vi.mock('../../../../src/stores/auth', () => ({
  useAuth: () => ({
    isAuthenticated: false,
    isHydrating: false,
  }),
  authStore: {
    hydrate: mockHydrate,
    subscribe: () => () => {},
    getSnapshot: () => ({ token: null, user: null, isHydrating: false }),
  },
}));

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

import AuthGuard from '../../../../src/components/admin/auth/AuthGuard';

/**
 * sdd/critical-2fa-frontend-flow · Task 1.12 / CR2
 *
 * AuthGuard debe:
 *   - Hidratar el store al mount.
 *   - NO redirigir en paths públicos (login, forgot, reset, reset-success, 2fa).
 *   - Redirigir en cualquier otro /admin/... preservando el locale.
 */
describe('components/admin/auth/AuthGuard', () => {
  let originalLocation: Location;

  beforeEach(() => {
    mockHydrate.mockReset();
    originalLocation = window.location;
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, pathname: '/es/admin/dashboard', href: '', replace: vi.fn() },
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
    render(<AuthGuard />);

    await waitFor(() => {
      expect(mockHydrate).toHaveBeenCalledTimes(1);
    });
  });

  it('NO redirige en /admin/auth/login (path público)', async () => {
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, pathname: '/es/admin/auth/login', href: '', replace: vi.fn() },
      writable: true,
      configurable: true,
    });
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

  it('NO redirige en /admin/auth/forgot-password', async () => {
    Object.defineProperty(window, 'location', {
      value: {
        ...originalLocation,
        pathname: '/es/admin/auth/forgot-password',
        href: '',
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

    render(<AuthGuard />);

    await waitFor(() => {
      expect(mockHydrate).toHaveBeenCalled();
    });

    expect(replaceMock).not.toHaveBeenCalled();
  });

  it('NO redirige en /admin/auth/2fa', async () => {
    Object.defineProperty(window, 'location', {
      value: {
        ...originalLocation,
        pathname: '/es/admin/auth/2fa',
        search: '?token=42',
        href: '',
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

    render(<AuthGuard />);

    await waitFor(() => {
      expect(mockHydrate).toHaveBeenCalled();
    });

    expect(replaceMock).not.toHaveBeenCalled();
  });

  it('redirige a /es/admin/auth/login cuando path es /es/admin/dashboard sin auth', async () => {
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
    expect(calledWith).toContain('/es/admin/auth/login');
    expect(calledWith).toContain('redirect=');
  });

  it('preserva el locale /en/ en el redirect cuando el path origen es /en/admin/dashboard', async () => {
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, pathname: '/en/admin/dashboard', href: '', replace: vi.fn() },
      writable: true,
      configurable: true,
    });
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
    expect(calledWith).toContain('/en/admin/auth/login');
    expect(calledWith).not.toContain('/es/admin/auth/login');
  });
});
