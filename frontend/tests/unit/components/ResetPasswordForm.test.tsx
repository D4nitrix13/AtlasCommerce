// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const { mockApiFetch, mockSetSession } = vi.hoisted(() => ({
  mockApiFetch: vi.fn(),
  mockSetSession: vi.fn(),
}));

vi.mock('../../../src/stores/auth', () => ({
  useAuth: () => ({
    login: vi.fn(),
    setSession: mockSetSession,
  }),
  authStore: { login: vi.fn(), setSession: mockSetSession },
}));

vi.mock('../../../src/i18n', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../../../src/i18n')>();
  return {
    ...actual,
    useTranslations: () => ({
      t: (key: string) => key,
      locale: 'es',
    }),
  };
});

vi.mock('../../../src/lib/api', () => ({
  apiFetch: mockApiFetch,
}));

import ResetPasswordForm from '../../../src/components/admin/auth/ResetPasswordForm';

beforeEach(() => {
  mockApiFetch.mockReset();
  mockSetSession.mockReset();
  window.history.replaceState({}, '', '/es/admin/auth/reset-password?email=jane@example.com');
});

describe('components/admin/auth/ResetPasswordForm', () => {
  describe('render', () => {
    it('muestra los 3 inputs (code, password, confirm)', () => {
      render(<ResetPasswordForm email="jane@example.com" />);

      expect(screen.getByLabelText('auth.passwordReset.code.label')).toBeInTheDocument();
      expect(screen.getByLabelText('auth.passwordReset.newPassword.label')).toBeInTheDocument();
      expect(screen.getByLabelText('auth.passwordReset.confirmPassword.label')).toBeInTheDocument();
    });

    it('code input tiene maxLength=6 y pattern numerico', () => {
      render(<ResetPasswordForm email="jane@example.com" />);

      const codeInput = screen.getByLabelText('auth.passwordReset.code.label');
      expect(codeInput).toHaveAttribute('maxLength', '6');
      expect(codeInput).toHaveAttribute('pattern', '[0-9]{6}');
      expect(codeInput).toHaveAttribute('inputMode', 'numeric');
    });

    it('password inputs tienen minLength=12 y autoComplete=new-password', () => {
      render(<ResetPasswordForm email="jane@example.com" />);

      const newPassword = screen.getByLabelText('auth.passwordReset.newPassword.label');
      const confirmPassword = screen.getByLabelText('auth.passwordReset.confirmPassword.label');
      expect(newPassword).toHaveAttribute('minLength', '12');
      expect(confirmPassword).toHaveAttribute('minLength', '12');
      expect(newPassword).toHaveAttribute('autoComplete', 'new-password');
      expect(confirmPassword).toHaveAttribute('autoComplete', 'new-password');
    });

    it('muestra el hint del code (15 minutos)', () => {
      render(<ResetPasswordForm email="jane@example.com" />);

      expect(screen.getByText('auth.passwordReset.step2.codeHelp')).toBeInTheDocument();
    });
  });

  describe('validación client-side', () => {
    it('muestra error si el código está vacío', async () => {
      const user = userEvent.setup();
      render(<ResetPasswordForm email="jane@example.com" />);

      const submitButton = screen.getByRole('button', {
        name: 'auth.passwordReset.submit',
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole('alert')).toHaveTextContent('auth.passwordReset.error.codeEmpty');
      });
      expect(mockApiFetch).not.toHaveBeenCalled();
    });

    it('muestra error si el código no tiene 6 dígitos', async () => {
      const user = userEvent.setup();
      render(<ResetPasswordForm email="jane@example.com" />);

      const codeInput = screen.getByLabelText('auth.passwordReset.code.label');
      await user.type(codeInput, '12345');

      const submitButton = screen.getByRole('button', {
        name: 'auth.passwordReset.submit',
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole('alert')).toHaveTextContent('auth.passwordReset.error.codeInvalid');
      });
      expect(mockApiFetch).not.toHaveBeenCalled();
    });

    it('filtra caracteres no-numéricos en el code input', async () => {
      const user = userEvent.setup();
      render(<ResetPasswordForm email="jane@example.com" />);

      const codeInput = screen.getByLabelText('auth.passwordReset.code.label');
      await user.type(codeInput, '1a2b3c4d5e6f');

      expect(codeInput).toHaveValue('123456');
    });

    it('muestra error si la contraseña es débil', async () => {
      const user = userEvent.setup();
      render(<ResetPasswordForm email="jane@example.com" />);

      const codeInput = screen.getByLabelText('auth.passwordReset.code.label');
      await user.type(codeInput, '123456');

      const passwordInput = screen.getByLabelText('auth.passwordReset.newPassword.label');
      await user.type(passwordInput, 'weak');

      const confirmInput = screen.getByLabelText('auth.passwordReset.confirmPassword.label');
      await user.type(confirmInput, 'weak');

      const submitButton = screen.getByRole('button', {
        name: 'auth.passwordReset.submit',
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole('alert')).toHaveTextContent(
          'auth.passwordReset.error.passwordWeak'
        );
      });
      expect(mockApiFetch).not.toHaveBeenCalled();
    });

    it('muestra error si las contraseñas no coinciden', async () => {
      const user = userEvent.setup();
      render(<ResetPasswordForm email="jane@example.com" />);

      const codeInput = screen.getByLabelText('auth.passwordReset.code.label');
      await user.type(codeInput, '123456');

      const passwordInput = screen.getByLabelText('auth.passwordReset.newPassword.label');
      await user.type(passwordInput, 'StrongPass1234');

      const confirmInput = screen.getByLabelText('auth.passwordReset.confirmPassword.label');
      await user.type(confirmInput, 'OtherPass1234');

      const submitButton = screen.getByRole('button', {
        name: 'auth.passwordReset.submit',
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole('alert')).toHaveTextContent(
          'auth.passwordReset.error.passwordMismatch'
        );
      });
      expect(mockApiFetch).not.toHaveBeenCalled();
    });
  });

  describe('envío exitoso', () => {
    it('llama a POST /auth/reset-password con todos los datos correctos', async () => {
      const user = userEvent.setup();
      mockApiFetch.mockResolvedValue({
        token: '1|abc123',
        user: { id: 1, email: 'jane@example.com' },
      });

      render(<ResetPasswordForm email="jane@example.com" />);

      const codeInput = screen.getByLabelText('auth.passwordReset.code.label');
      await user.type(codeInput, '123456');

      const passwordInput = screen.getByLabelText('auth.passwordReset.newPassword.label');
      await user.type(passwordInput, 'StrongPass1234');

      const confirmInput = screen.getByLabelText('auth.passwordReset.confirmPassword.label');
      await user.type(confirmInput, 'StrongPass1234');

      const submitButton = screen.getByRole('button', {
        name: 'auth.passwordReset.submit',
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(mockApiFetch).toHaveBeenCalledWith(
          '/auth/reset-password',
          expect.objectContaining({
            method: 'POST',
            body: {
              email: 'jane@example.com',
              code: '123456',
              password: 'StrongPass1234',
              password_confirmation: 'StrongPass1234',
              device_name: 'web-admin',
            },
          })
        );
      });

      await waitFor(() => {
        expect(mockSetSession).toHaveBeenCalledWith(
          expect.objectContaining({
            token: '1|abc123',
            user: expect.objectContaining({ email: 'jane@example.com' }),
          })
        );
      });
    });

    it('muestra mensaje de éxito y deshabilita el submit', async () => {
      const user = userEvent.setup();
      mockApiFetch.mockResolvedValue({
        token: '1|abc123',
        user: { id: 1, email: 'jane@example.com' },
      });

      // Stub window.location.href para que setTimeout no falle.
      const originalLocation = window.location;
      Object.defineProperty(window, 'location', {
        value: { ...originalLocation, href: '' },
        writable: true,
        configurable: true,
      });

      try {
        render(<ResetPasswordForm email="jane@example.com" />);

        const codeInput = screen.getByLabelText('auth.passwordReset.code.label');
        await user.type(codeInput, '123456');

        const passwordInput = screen.getByLabelText('auth.passwordReset.newPassword.label');
        await user.type(passwordInput, 'StrongPass1234');

        const confirmInput = screen.getByLabelText('auth.passwordReset.confirmPassword.label');
        await user.type(confirmInput, 'StrongPass1234');

        const submitButton = screen.getByRole('button', {
          name: 'auth.passwordReset.submit',
        });
        await user.click(submitButton);

        await waitFor(() => {
          expect(screen.getByRole('status')).toHaveTextContent('auth.passwordReset.success');
        });

        // Submit debe estar deshabilitado.
        expect(submitButton).toBeDisabled();
      } finally {
        Object.defineProperty(window, 'location', {
          value: originalLocation,
          writable: true,
          configurable: true,
        });
      }
    });

    it('muestra error si el backend rechaza el código', async () => {
      const user = userEvent.setup();
      mockApiFetch.mockRejectedValue({
        message: 'El código es inválido o ha expirado.',
        errors: { code: ['El código es inválido o ha expirado.'] },
      });

      render(<ResetPasswordForm email="jane@example.com" />);

      const codeInput = screen.getByLabelText('auth.passwordReset.code.label');
      await user.type(codeInput, '000000');

      const passwordInput = screen.getByLabelText('auth.passwordReset.newPassword.label');
      await user.type(passwordInput, 'StrongPass1234');

      const confirmInput = screen.getByLabelText('auth.passwordReset.confirmPassword.label');
      await user.type(confirmInput, 'StrongPass1234');

      const submitButton = screen.getByRole('button', {
        name: 'auth.passwordReset.submit',
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole('alert')).toHaveTextContent('El código es inválido o ha expirado.');
      });
    });
  });

  describe('show password toggle (sdd PR 2: PasswordInput extraído)', () => {
    it('cada PasswordInput tiene su propio toggle independiente', async () => {
      const user = userEvent.setup();
      render(<ResetPasswordForm email="jane@example.com" />);

      const newPassword = screen.getByLabelText('auth.passwordReset.newPassword.label');
      const confirmPassword = screen.getByLabelText('auth.passwordReset.confirmPassword.label');
      expect(newPassword).toHaveAttribute('type', 'password');
      expect(confirmPassword).toHaveAttribute('type', 'password');

      // Ambos inputs tienen su propio botón toggle (no se comparte).
      const toggleButtons = screen.getAllByRole('button', {
        name: 'auth.passwordReset.password.show',
      });
      expect(toggleButtons.length).toBe(2);

      // Toggle del primer input no afecta al segundo.
      await user.click(toggleButtons[0]);
      expect(newPassword).toHaveAttribute('type', 'text');
      expect(confirmPassword).toHaveAttribute('type', 'password');
    });
  });
});
