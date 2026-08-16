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
   * sdd/critical-2fa-frontend-flow · Task 2.3 + 2.4
   *
   * El admin usa credenciales internas (admin@<slug>.com / password), sin
   * OAuth. El docstring del componente (lines 6-14) y la e2e
   * (`tests/e2e/login.spec.ts:52-63`) lo afirman. Los botones sociales
   * fueron un refactor incompleto de PR 0 y DEBEN estar ausentes.
   */
  it('NO renderiza botones sociales (Google/Facebook)', () => {
    const { container } = render(<LoginForm />);

    expect(screen.queryByRole('button', { name: /Google/i })).toBeNull();
    expect(screen.queryByRole('button', { name: /Facebook/i })).toBeNull();
    expect(container.querySelector("[data-auth-link='social-google']")).toBeNull();
    expect(container.querySelector("[data-auth-link='social-facebook']")).toBeNull();
  });

  it("NO renderiza el divider 'o con tu correo' (sin social, no hace falta)", () => {
    render(<LoginForm />);

    expect(screen.queryByText('auth.login.shell.divider')).toBeNull();
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
