// @vitest-environment jsdom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const { mockRegister, mockHydrate } = vi.hoisted(() => ({
  mockRegister: vi.fn(),
  mockHydrate: vi.fn(),
}));

vi.mock('../../../../../src/stores/customer-auth', () => ({
  customerAuthStore: {
    register: mockRegister,
    hydrate: mockHydrate,
  },
  useCustomerAuth: () => ({
    user: null,
    customer: null,
    isHydrating: false,
    isAuthenticated: false,
    register: mockRegister,
    login: vi.fn(),
    logout: vi.fn(),
  }),
}));

vi.mock('../../../../../src/lib/api', () => ({
  buildApiUrl: vi.fn(() => 'http://localhost:8000/api/social/google'),
}));

import RegisterForm from '../../../../../src/components/storefront/auth/RegisterForm';

/**
 * RegisterForm — UX de validación por campo (T3–T4 del feature
 * auth-signup-login-ux).
 *
 * Los mensajes esperados corresponden al wording actual de
 * `src/i18n/locales/es.ts` (locale default de jsdom) y a la spec:
 * si se refina el copy, actualizar acá en conjunto.
 */
const TERMS_REQUIRED =
  'Para crear tu cuenta, aceptá los Términos y la Política de Privacidad.';

/** [fragmento del label, mensaje de "requerido" esperado por campo] */
const REQUIRED_FIELD_MESSAGES: [RegExp, string][] = [
  [/^Nombre/, 'Ingresá Nombre.'],
  [/^Apellido/, 'Ingresá Apellido.'],
  [/^Correo electrónico/, 'Ingresá Correo electrónico.'],
  [/^Contraseña/, 'Ingresá Contraseña.'],
  [/^Confirmar contraseña/, 'Ingresá Confirmar contraseña.'],
];

const REQUIRED_INPUT_IDS = [
  'firstName',
  'lastName',
  'email',
  'password',
  'passwordConfirmation',
  'acceptTerms',
];

type User = ReturnType<typeof userEvent.setup>;

async function fillValidForm(user: User): Promise<void> {
  await user.type(screen.getByLabelText(/^Nombre/), 'Ana');
  await user.type(screen.getByLabelText(/^Apellido/), 'García');
  await user.type(screen.getByLabelText(/^Correo electrónico/), 'ana@example.com');
  await user.type(screen.getByLabelText(/^Contraseña/), 'Secreto123');
  await user.type(screen.getByLabelText(/^Confirmar contraseña/), 'Secreto123');
}

function submitButton(): HTMLElement {
  return screen.getByRole('button', { name: 'Crear mi cuenta' });
}

let hrefSetter: { value: string };

beforeEach(() => {
  mockRegister.mockReset();
  mockHydrate.mockReset();
  window.history.replaceState({}, '', '/es/account/signup');
  // jsdom no implementa scrollIntoView.
  window.HTMLElement.prototype.scrollIntoView = vi.fn();
  hrefSetter = { value: '' };
  Object.defineProperty(window, 'location', {
    value: {
      get href() {
        return hrefSetter.value;
      },
      set href(value: string) {
        hrefSetter.value = value;
      },
      origin: 'http://localhost',
      pathname: '/es/account/signup',
      search: '',
      assign: vi.fn(),
      replace: vi.fn(),
    },
    writable: true,
    configurable: true,
  });
});

afterEach(() => {
  Object.defineProperty(window, 'location', {
    value: window.location,
    writable: true,
    configurable: true,
  });
});

describe('components/storefront/auth/RegisterForm', () => {
  describe('validación client-side', () => {
    it('muestra termsRequired al enviar sin aceptar términos, nunca el label crudo', async () => {
      const user = userEvent.setup();
      render(<RegisterForm locale="es" />);
      await fillValidForm(user);

      await user.click(submitButton());

      expect(await screen.findByText(TERMS_REQUIRED)).toBeInTheDocument();
      // El label del checkbox (con placeholders sin interpolar) NUNCA
      // puede aparecer como mensaje de error.
      expect(screen.queryByText(/Acepto los \{terms\}/)).not.toBeInTheDocument();
      expect(screen.queryByText(/\{terms\}/)).not.toBeInTheDocument();
      expect(mockRegister).not.toHaveBeenCalled();
    });

    it('setea errores por campo para cada campo requerido y marca los labels con *', async () => {
      const user = userEvent.setup();
      render(<RegisterForm locale="es" />);

      await user.click(submitButton());

      for (const [labelPattern, message] of REQUIRED_FIELD_MESSAGES) {
        const input = screen.getByLabelText(labelPattern);
        const errorId = `${input.id}-error`;
        const error = await screen.findByText(message);
        expect(error).toHaveAttribute('id', errorId);
        expect(error).toHaveAttribute('role', 'alert');
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(input).toHaveAttribute('aria-describedby', errorId);
      }
      expect(await screen.findByText(TERMS_REQUIRED)).toBeInTheDocument();

      // 5 campos obligatorios + el label de términos.
      expect(screen.getAllByTitle('obligatorio')).toHaveLength(6);
      for (const id of REQUIRED_INPUT_IDS) {
        expect(document.getElementById(id)).toHaveAttribute('aria-required', 'true');
      }
    });

    it('enfoca el primer campo inválido al enviar', async () => {
      const user = userEvent.setup();
      render(<RegisterForm locale="es" />);

      await user.click(submitButton());

      await waitFor(() => {
        expect(document.getElementById('firstName')).toHaveFocus();
      });
    });

    it('usa mensajes propios para email inválido, password corta y confirmación distinta', async () => {
      const user = userEvent.setup();
      render(<RegisterForm locale="es" />);

      await user.type(screen.getByLabelText(/^Nombre/), 'Ana');
      await user.type(screen.getByLabelText(/^Apellido/), 'García');
      await user.type(screen.getByLabelText(/^Correo electrónico/), 'no-es-un-email');
      await user.type(screen.getByLabelText(/^Contraseña/), 'corta');
      await user.type(screen.getByLabelText(/^Confirmar contraseña/), 'distinta');

      await user.click(submitButton());

      expect(await screen.findByText('Ingresá un correo válido.')).toBeInTheDocument();
      expect(
        await screen.findByText('Contraseña debe tener al menos 8 caracteres.'),
      ).toBeInTheDocument();
      expect(await screen.findByText('Las contraseñas no coinciden.')).toBeInTheDocument();
      expect(mockRegister).not.toHaveBeenCalled();
    });
  });

  describe('errores 422 del API', () => {
    it('mapea errors de Laravel al campo correspondiente, no solo al banner', async () => {
      const user = userEvent.setup();
      render(<RegisterForm locale="es" />);
      await fillValidForm(user);
      await user.click(screen.getByRole('checkbox'));

      mockRegister.mockRejectedValue({
        status: 422,
        message: 'Los datos proporcionados no son válidos.',
        errors: { email: ['Ingresá tu correo electrónico.'] },
        isNetworkError: false,
      });

      await user.click(submitButton());

      const emailError = await screen.findByText('Ingresá tu correo electrónico.');
      expect(emailError).toHaveAttribute('id', 'email-error');
      expect(emailError).toHaveAttribute('role', 'alert');
      const emailInput = document.getElementById('email');
      expect(emailInput).toHaveAttribute('aria-invalid', 'true');
      expect(emailInput).toHaveAttribute('aria-describedby', 'email-error');
      // El banner top queda reservado para errores sin campo propio.
      expect(
        screen.queryByText('Los datos proporcionados no son válidos.'),
      ).not.toBeInTheDocument();
    });
  });

  describe('redirect post-registro', () => {
    it('redirige a /{locale}/account preservando el prefijo de locale', async () => {
      const user = userEvent.setup();
      render(<RegisterForm locale="es" />);
      await fillValidForm(user);
      await user.click(screen.getByRole('checkbox'));
      mockRegister.mockResolvedValue({ user: {}, customer: {} });
      mockHydrate.mockResolvedValue(undefined);

      await user.click(submitButton());

      await waitFor(() => {
        expect(hrefSetter.value).toBe('http://localhost/es/account');
      });
    });
  });

  describe('label de términos y privacidad', () => {
    it('arma el label con links reales a /{locale}/terms y /{locale}/privacy', () => {
      render(<RegisterForm locale="es" />);

      expect(screen.getByRole('link', { name: 'Términos' })).toHaveAttribute(
        'href',
        '/es/terms',
      );
      expect(screen.getByRole('link', { name: 'Política de Privacidad' })).toHaveAttribute(
        'href',
        '/es/privacy',
      );
    });

    it('localiza el label del checkbox en rutas /en/ (sin JSX hardcodeado en español)', () => {
      Object.defineProperty(window, 'location', {
        value: {
          get href() {
            return hrefSetter.value;
          },
          set href(value: string) {
            hrefSetter.value = value;
          },
          origin: 'http://localhost',
          pathname: '/en/account/signup',
          search: '',
          assign: vi.fn(),
          replace: vi.fn(),
        },
        writable: true,
        configurable: true,
      });
      render(<RegisterForm locale="en" />);

      expect(screen.getByRole('link', { name: 'Terms' })).toHaveAttribute('href', '/en/terms');
      expect(screen.getByRole('link', { name: 'Privacy Policy' })).toHaveAttribute(
        'href',
        '/en/privacy',
      );
      expect(screen.getByText(/I accept the/)).toBeInTheDocument();
    });
  });
});
