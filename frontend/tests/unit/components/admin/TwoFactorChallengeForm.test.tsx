// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const { mockVerifyTotpChallenge, mockSetSession } = vi.hoisted(() => ({
  mockVerifyTotpChallenge: vi.fn(),
  mockSetSession: vi.fn(),
}));

vi.mock('../../../../src/stores/auth', () => ({
  useAuth: () => ({
    verifyTotpChallenge: mockVerifyTotpChallenge,
    setSession: mockSetSession,
  }),
  authStore: {
    verifyTotpChallenge: mockVerifyTotpChallenge,
    setSession: mockSetSession,
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

import TwoFactorChallengeForm from '../../../../src/components/admin/auth/TwoFactorChallengeForm';

const fakeSuccessResponse = {
  kind: 'success' as const,
  token: 'sanctum-token-2fa',
  user: {
    id: 1,
    email: 'admin@panda-estampados.com',
    full_name: 'Admin',
    role: { id: 1, slug: 'admin', name: 'Administrador' },
    role_id: 1,
    store_ids: [1],
    is_active: true,
    permissions: [],
  },
};

/**
 * Tests del componente TwoFactorChallengeForm.
 *
 * Contrato:
 *   - Acepta `challengeId` y `redirectTo` como props.
 *   - Llama `useAuth().verifyTotpChallenge(challengeId, code)`.
 *   - En success, llama `setSession` y navega a `redirectTo`.
 *   - En error, muestra alert con el mensaje del backend y NO navega.
 *   - Si el error es "challenge expirado", muestra link "Volver a login".
 */
describe('components/admin/auth/TwoFactorChallengeForm', () => {
  beforeEach(() => {
    mockVerifyTotpChallenge.mockReset();
    mockSetSession.mockReset();
    window.history.replaceState({}, '', '/es/admin/auth/2fa');
  });

  it('renderiza label + input + submit', () => {
    render(<TwoFactorChallengeForm challengeId={42} redirectTo="/es/admin/dashboard" />);

    expect(screen.getByLabelText('auth.twoFactor.code.label')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('auth.twoFactor.code.placeholder')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'auth.twoFactor.submit' })).toBeInTheDocument();
  });

  it('submit con código válido llama verifyTotpChallenge + setSession + navega', async () => {
    const user = userEvent.setup();
    mockVerifyTotpChallenge.mockResolvedValue(fakeSuccessResponse);

    // Stub window.location.href para que setTimeout/navigation no falle en jsdom.
    const originalLocation = window.location;
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, href: '' },
      writable: true,
      configurable: true,
    });

    try {
      render(<TwoFactorChallengeForm challengeId={42} redirectTo="/es/admin/dashboard" />);

      await user.type(screen.getByLabelText('auth.twoFactor.code.label'), '123456');
      await user.click(screen.getByRole('button', { name: 'auth.twoFactor.submit' }));

      await waitFor(() => {
        expect(mockVerifyTotpChallenge).toHaveBeenCalledWith({
          challengeId: 42,
          code: '123456',
        });
      });

      await waitFor(() => {
        expect(mockSetSession).toHaveBeenCalledWith(fakeSuccessResponse);
      });
    } finally {
      Object.defineProperty(window, 'location', {
        value: originalLocation,
        writable: true,
        configurable: true,
      });
    }
  });

  it('código inválido muestra alert con el mensaje del backend y NO navega', async () => {
    const user = userEvent.setup();
    mockVerifyTotpChallenge.mockRejectedValue({
      status: 422,
      message: 'Código inválido.',
      errors: null,
    });

    render(<TwoFactorChallengeForm challengeId={42} redirectTo="/es/admin/dashboard" />);

    await user.type(screen.getByLabelText('auth.twoFactor.code.label'), '000000');
    await user.click(screen.getByRole('button', { name: 'auth.twoFactor.submit' }));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent(/código inválido/i);
    });

    expect(mockSetSession).not.toHaveBeenCalled();
  });

  it("challenge expirado muestra mensaje + link 'Volver a iniciar sesión'", async () => {
    const user = userEvent.setup();
    mockVerifyTotpChallenge.mockRejectedValue({
      status: 422,
      message: 'Challenge inválido o expirado.',
      errors: null,
    });

    render(<TwoFactorChallengeForm challengeId={42} redirectTo="/es/admin/dashboard" />);

    await user.type(screen.getByLabelText('auth.twoFactor.code.label'), '111111');
    await user.click(screen.getByRole('button', { name: 'auth.twoFactor.submit' }));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent(/expirado/i);
    });

    const recoveryLink = screen.getByText('auth.twoFactor.recoveryLink');
    expect(recoveryLink).toBeInTheDocument();
    expect(recoveryLink.closest('a')).toHaveAttribute('href', '/es/auth/login');
  });
});
