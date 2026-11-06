// @vitest-environment jsdom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const { mockLogin } = vi.hoisted(() => ({
  mockLogin: vi.fn(),
}));

vi.mock('../../../../../src/stores/customer-auth', () => ({
  customerAuthStore: {
    login: mockLogin,
    register: vi.fn(),
    hydrate: vi.fn(),
  },
  useCustomerAuth: () => ({
    user: null,
    customer: null,
    isHydrating: false,
    isAuthenticated: false,
    login: mockLogin,
    register: vi.fn(),
    logout: vi.fn(),
  }),
}));

vi.mock('../../../../../src/lib/api', () => ({
  buildApiUrl: vi.fn(() => 'http://localhost:8000/api/social/google'),
}));

import StorefrontLoginForm from '../../../../../src/components/storefront/auth/StorefrontLoginForm';

/**
 * StorefrontLoginForm — UX de validación por campo (T5 del feature
 * auth-signup-login-ux). Espejo del harness de RegisterForm.test.tsx.
 *
 * Los mensajes esperados corresponden al wording actual de
 * `src/i18n/locales/es.ts` (locale default de jsdom) y a la spec.
 */

type User = ReturnType<typeof userEvent.setup>;

function submitButton(): HTMLElement {
  return screen.getByRole('button', { name: 'Iniciar sesión' });
}

async function fillValidCredentials(user: User): Promise<void> {
  await user.type(screen.getByLabelText(/^Correo electrónico/), 'ana@example.com');
  await user.type(screen.getByLabelText(/^Contraseña/), 'Secreto123');
}

let hrefSetter: { value: string };
let locationSearch: { value: string };

function mockLocation(overrides: { pathname?: string; search?: string } = {}): void {
  hrefSetter = { value: '' };
  locationSearch = { value: overrides.search ?? '' };
  Object.defineProperty(window, 'location', {
    value: {
      get href() {
        return hrefSetter.value;
      },
      set href(value: string) {
        hrefSetter.value = value;
      },
      origin: 'http://localhost',
      pathname: overrides.pathname ?? '/es/account/login',
      get search() {
        return locationSearch.value;
      },
      assign: vi.fn(),
      replace: vi.fn(),
    },
    writable: true,
    configurable: true,
  });
}

beforeEach(() => {
  mockLogin.mockReset();
  window.history.replaceState({}, '', '/es/account/login');
  // jsdom no implementa scrollIntoView.
  window.HTMLElement.prototype.scrollIntoView = vi.fn();
  mockLocation();
});

afterEach(() => {
  Object.defineProperty(window, 'location', {
    value: window.location,
    writable: true,
    configurable: true,
  });
});

describe('components/storefront/auth/StorefrontLoginForm', () => {
  describe('validación client-side', () => {
    it('al enviar vacío setea errores por campo en email y password y marca los labels con *', async () => {
      const user = userEvent.setup();
      render(<StorefrontLoginForm locale="es" />);

      await user.click(submitButton());

      const emailInput = screen.getByLabelText(/^Correo electrónico/);
      const emailError = await screen.findByText('Ingresá Correo electrónico.');
      expect(emailError).toHaveAttribute('id', 'email-error');
      expect(emailError).toHaveAttribute('role', 'alert');
      expect(emailInput).toHaveAttribute('aria-invalid', 'true');
      expect(emailInput).toHaveAttribute('aria-describedby', 'email-error');

      const passwordInput = screen.getByLabelText(/^Contraseña/);
      const passwordError = await screen.findByText('Ingresá Contraseña.');
      expect(passwordError).toHaveAttribute('id', 'password-error');
      expect(passwordError).toHaveAttribute('role', 'alert');
      expect(passwordInput).toHaveAttribute('aria-invalid', 'true');
      expect(passwordInput).toHaveAttribute('aria-describedby', 'password-error');

      // Email + password obligatorios → dos marcas `*` + leyenda.
      expect(screen.getAllByTitle('obligatorio')).toHaveLength(2);
      expect(screen.getByText('* Campos obligatorios')).toBeInTheDocument();
      expect(document.getElementById('email')).toHaveAttribute('aria-required', 'true');
      expect(document.getElementById('password')).toHaveAttribute('aria-required', 'true');

      // Foco + scroll al primer campo inválido.
      await waitFor(() => {
        expect(emailInput).toHaveFocus();
      });
      expect(mockLogin).not.toHaveBeenCalled();
    });

    it('usa el mensaje propio para email con formato inválido', async () => {
      const user = userEvent.setup();
      render(<StorefrontLoginForm locale="es" />);
      await user.type(screen.getByLabelText(/^Correo electrónico/), 'no-es-un-email');
      await user.type(screen.getByLabelText(/^Contraseña/), 'Secreto123');

      await user.click(submitButton());

      expect(await screen.findByText('Ingresá un correo válido.')).toBeInTheDocument();
      expect(mockLogin).not.toHaveBeenCalled();
    });
  });

  describe('errores 422 del API', () => {
    it('mapea errors de Laravel al campo email, lo enfoca y no usa el banner', async () => {
      const user = userEvent.setup();
      render(<StorefrontLoginForm locale="es" />);
      await fillValidCredentials(user);

      mockLogin.mockRejectedValue({
        status: 422,
        message: 'x',
        errors: { email: ['Las credenciales no son válidas.'] },
        isNetworkError: false,
      });

      await user.click(submitButton());

      const emailError = await screen.findByText('Las credenciales no son válidas.');
      expect(emailError).toHaveAttribute('id', 'email-error');
      expect(emailError).toHaveAttribute('role', 'alert');
      const emailInput = document.getElementById('email');
      expect(emailInput).toHaveAttribute('aria-invalid', 'true');
      expect(emailInput).toHaveAttribute('aria-describedby', 'email-error');
      await waitFor(() => {
        expect(emailInput).toHaveFocus();
      });
      // El banner top queda reservado para errores sin campo propio.
      expect(screen.queryByText('x')).not.toBeInTheDocument();
    });
  });

  describe('redirect post-login', () => {
    it('redirige a /{locale}/account preservando el prefijo de locale', async () => {
      const user = userEvent.setup();
      render(<StorefrontLoginForm locale="es" />);
      await fillValidCredentials(user);
      mockLogin.mockResolvedValue({ kind: 'success', user: {}, customer: null });

      await user.click(submitButton());

      await waitFor(() => {
        expect(hrefSetter.value).toBe('http://localhost/es/account');
      });
    });

    it('preserva el ?redirect= provisto por el usuario', async () => {
      mockLocation({ search: '?redirect=%2Fes%2Fwishlist' });
      const user = userEvent.setup();
      render(<StorefrontLoginForm locale="es" />);
      await fillValidCredentials(user);
      mockLogin.mockResolvedValue({ kind: 'success', user: {}, customer: null });

      await user.click(submitButton());

      await waitFor(() => {
        expect(hrefSetter.value).toBe('/es/wishlist');
      });
    });
  });

  describe('flujo 2FA', () => {
    it('navega a /{locale}/account/2fa?token=...&redirect=... con el locale activo', async () => {
      mockLocation({ pathname: '/en/account/login', search: '?redirect=%2Fes%2Fwishlist' });
      const user = userEvent.setup();
      render(<StorefrontLoginForm locale="en" />);
      // Ruta /en/ → i18n resuelve el locale 'en' desde el pathname.
      await user.type(screen.getByLabelText(/^Email address/), 'ana@example.com');
      await user.type(screen.getByLabelText(/^Password/), 'Secreto123');
      mockLogin.mockResolvedValue({ kind: '2fa_required', challengeId: 4242 });

      await user.click(screen.getByRole('button', { name: 'Sign in' }));

      await waitFor(() => {
        expect(hrefSetter.value).toBe('/en/account/2fa?token=4242&redirect=%2Fes%2Fwishlist');
      });
    });

    it('rechaza ?redirect= admin y usa el fallback con locale en el 2FA', async () => {
      mockLocation({ search: '?redirect=%2Fadmin%2Fdashboard' });
      const user = userEvent.setup();
      render(<StorefrontLoginForm locale="es" />);
      await fillValidCredentials(user);
      mockLogin.mockResolvedValue({ kind: '2fa_required', challengeId: 7 });

      await user.click(submitButton());

      await waitFor(() => {
        expect(hrefSetter.value).toBe('/es/account/2fa?token=7&redirect=%2Fes%2Faccount');
      });
    });
  });
});
