// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const { mockLogin } = vi.hoisted(() => ({
  mockLogin: vi.fn(),
}));

vi.mock('../../../src/stores/auth', () => ({
  useAuth: () => ({
    login: mockLogin,
  }),
  authStore: { login: mockLogin },
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

import LoginForm from '../../../src/components/admin/auth/LoginForm';

const loginPath = '/es/admin/auth/login';

describe('components/admin/auth/LoginForm', () => {
  beforeEach(() => {
    mockLogin.mockReset();
    window.history.replaceState({}, '', loginPath);
  });

  it('muestra el form de login sin tabs de register', () => {
    render(<LoginForm />);

    expect(screen.getByLabelText('auth.login.shell.emailLabel')).toBeInTheDocument();
    expect(screen.getByLabelText('auth.login.shell.passwordLabel')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'auth.login.shell.submit' })).toBeInTheDocument();
    expect(screen.getByText('auth.login.shell.rememberMe')).toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: /Registrarse/i })).toBeNull();
  });

  it('login exitoso llama a authStore.login', async () => {
    const user = userEvent.setup();
    mockLogin.mockResolvedValue({
      token: 'tok',
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
    });

    render(<LoginForm />);

    await user.type(
      screen.getByLabelText('auth.login.shell.emailLabel'),
      'admin@panda-estampados.com'
    );
    await user.type(screen.getByLabelText('auth.login.shell.passwordLabel'), 'password');
    await user.click(screen.getByRole('button', { name: 'auth.login.shell.submit' }));

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledWith({
        email: 'admin@panda-estampados.com',
        password: 'password',
        remember: false,
      });
    });
  });

  it('login fallido muestra alert', async () => {
    const user = userEvent.setup();
    mockLogin.mockRejectedValue({
      status: 422,
      message: 'Las credenciales no son válidas.',
    });

    render(<LoginForm />);

    await user.type(screen.getByLabelText('auth.login.shell.emailLabel'), 'bad@example.com');
    await user.type(screen.getByLabelText('auth.login.shell.passwordLabel'), 'wrong');
    await user.click(screen.getByRole('button', { name: 'auth.login.shell.submit' }));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent(/credenciales no son válidas/i);
    });
  });

  it("muestra el link '¿Olvidaste tu contraseña?' apuntando al forgot-password", () => {
    render(<LoginForm />);

    const forgotLink = screen.getByText('auth.login.shell.forgotLink');
    expect(forgotLink).toBeInTheDocument();
  });

  /**
   * sdd/oauth-google-facebook-login · PR 3
   *
   * El admin puede usar credenciales internas (admin@<slug>.com / password)
   * O continuar con Google/Facebook. Los botones sociales están
   * presentes y tienen `data-auth-link="social-{google|facebook}"` para
   * que E2E y vitest puedan targetearlos.
   */
  it('renderiza el botón Google arriba del email con data-auth-link', () => {
    const { container } = render(<LoginForm />);

    const googleBtn = container.querySelector("[data-auth-link='social-google']");
    expect(googleBtn).not.toBeNull();
    expect(googleBtn).toBeInstanceOf(HTMLButtonElement);
    expect((googleBtn as HTMLButtonElement).type).toBe('button');
  });

  it('renderiza el botón Facebook arriba del email con data-auth-link', () => {
    const { container } = render(<LoginForm />);

    const fbBtn = container.querySelector("[data-auth-link='social-facebook']");
    expect(fbBtn).not.toBeNull();
    expect(fbBtn).toBeInstanceOf(HTMLButtonElement);
  });

  it('renderiza el divider text desde i18n key auth.login.shell.divider', () => {
    render(<LoginForm />);

    expect(screen.getByText('auth.login.shell.divider')).toBeInTheDocument();
  });

  it('click en Google button llama a window.location.assign con el relative path', async () => {
    const user = userEvent.setup();

    // Mockeamos window.location.assign para no navegar realmente.
    const assignSpy = vi.fn();
    const originalLocation = window.location;
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, assign: assignSpy },
      writable: true,
      configurable: true,
    });

    try {
      render(<LoginForm />);
      const googleBtn = screen.getByRole('button', { name: /Google/i });
      await user.click(googleBtn);

      expect(assignSpy).toHaveBeenCalledWith('/api/auth/social/google/redirect');
    } finally {
      Object.defineProperty(window, 'location', {
        value: originalLocation,
        writable: true,
        configurable: true,
      });
    }
  });

  it('el checkbox rememberMe se propaga al login()', async () => {
    const user = userEvent.setup();
    mockLogin.mockResolvedValue({
      token: 'tok',
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
    });

    render(<LoginForm />);

    await user.type(
      screen.getByLabelText('auth.login.shell.emailLabel'),
      'admin@panda-estampados.com'
    );
    await user.type(screen.getByLabelText('auth.login.shell.passwordLabel'), 'password');
    await user.click(screen.getByLabelText('auth.login.shell.rememberMe'));
    await user.click(screen.getByRole('button', { name: 'auth.login.shell.submit' }));

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledWith({
        email: 'admin@panda-estampados.com',
        password: 'password',
        remember: true,
      });
    });
  });
});
