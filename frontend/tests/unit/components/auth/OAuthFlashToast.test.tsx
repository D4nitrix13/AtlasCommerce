// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';

const { mockT } = vi.hoisted(() => ({
  mockT: vi.fn((key: string) => key),
}));

vi.mock('../../../../src/i18n', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../../../../src/i18n')>();
  return {
    ...actual,
    useTranslations: () => ({
      t: mockT,
      locale: 'es',
      setLocale: vi.fn(),
      switchLocalePath: vi.fn(),
    }),
  };
});

import OAuthFlashToast from '../../../../src/components/auth/OAuthFlashToast';

const basePath = '/es/admin/auth/login';

describe('components/auth/OAuthFlashToast', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', basePath);
  });

  it('no renderiza nada cuando no hay flash query param', () => {
    window.history.replaceState({}, '', basePath);

    const { container } = render(<OAuthFlashToast />);

    expect(container.firstChild).toBeNull();
  });

  it('renderiza toast para código conocido oauth_email_conflict', async () => {
    window.history.replaceState({}, '', `${basePath}?flash=oauth_email_conflict`);

    render(<OAuthFlashToast />);

    await waitFor(() => {
      expect(screen.getByTestId('oauth-flash-toast')).toBeInTheDocument();
    });
    expect(screen.getByTestId('oauth-flash-toast')).toHaveAttribute(
      'data-flash-code',
      'oauth_email_conflict'
    );
  });

  it('cae al generic fallback cuando el código es desconocido', async () => {
    window.history.replaceState({}, '', `${basePath}?flash=oauth_future_code`);

    render(<OAuthFlashToast />);

    await waitFor(() => {
      expect(screen.getByTestId('oauth-flash-toast')).toHaveTextContent('auth.login.error.generic');
    });
  });

  it('strips el query param flash de la URL sin recargar', async () => {
    window.history.replaceState({}, '', `${basePath}?flash=oauth_email_conflict`);

    render(<OAuthFlashToast />);

    await waitFor(() => {
      expect(screen.getByTestId('oauth-flash-toast')).toBeInTheDocument();
    });

    // Después del effect, la URL no debe tener `?flash=`.
    expect(window.location.search).not.toContain('flash=');
    expect(window.location.pathname).toBe(basePath);
  });

  it('preserva otros query params cuando strippea flash', async () => {
    window.history.replaceState(
      {},
      '',
      `${basePath}?flash=oauth_state_mismatch&redirect=%2Fdashboard`
    );

    render(<OAuthFlashToast />);

    await waitFor(() => {
      expect(screen.getByTestId('oauth-flash-toast')).toBeInTheDocument();
    });

    expect(window.location.search).toContain('redirect=%2Fdashboard');
    expect(window.location.search).not.toContain('flash=');
  });
});
