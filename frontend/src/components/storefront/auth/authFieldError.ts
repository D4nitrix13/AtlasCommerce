import type { Translations } from '../../../i18n/types';

/**
 * Validación client-side y mapeo de errores por campo para los forms
 * de auth (RegisterForm y StorefrontLoginForm). Extraído como helper
 * local para mantener los componentes acotados (convención de tamaño
 * en frontend/AGENTS.md).
 */

export type FieldName =
  | 'first_name'
  | 'last_name'
  | 'email'
  | 'password'
  | 'password_confirmation'
  | 'terms';

export type FieldErrors = Partial<Record<FieldName, string>>;

export type TranslateFn = (key: keyof Translations, vars?: Record<string, string | number>) => string;

export const FIELD_ORDER: readonly FieldName[] = [
  'first_name',
  'last_name',
  'email',
  'password',
  'password_confirmation',
  'terms',
];

export const FIELD_IDS: Record<FieldName, string> = {
  first_name: 'firstName',
  last_name: 'lastName',
  email: 'email',
  password: 'password',
  password_confirmation: 'passwordConfirmation',
  terms: 'acceptTerms',
};

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export interface RegisterFormValues {
  first_name: string;
  last_name: string;
  email: string;
  password: string;
  password_confirmation: string;
  accept_terms: boolean;
}

export function firstInvalidField(errors: FieldErrors): FieldName | null {
  return FIELD_ORDER.find((name) => errors[name] !== undefined) ?? null;
}

/**
 * Mapea el `errors` de Laravel (422) a un error por campo, usando el
 * primer mensaje de cada uno. Solo campos del form; el resto queda
 * para el banner via `apiError.message`.
 */
export function mapApiFieldErrors(
  errors: Record<string, string[]> | null | undefined,
): FieldErrors {
  const mapped: FieldErrors = {};
  if (errors === null || errors === undefined) {
    return mapped;
  }
  for (const name of FIELD_ORDER) {
    const messages = errors[name];
    if (messages !== undefined && messages.length > 0) {
      mapped[name] = messages[0];
    }
  }
  return mapped;
}

export interface LoginFormValues {
  email: string;
  password: string;
}

/**
 * Validación client-side del login: email requerido + formato,
 * password requerida (el `min:8` del backend llega via 422 y se
 * mapea al campo con `mapApiFieldErrors`).
 */
export function validateLoginForm(values: LoginFormValues, t: TranslateFn): FieldErrors {
  const errors: FieldErrors = {};

  if (values.email.trim() === '') {
    errors.email = t('auth.validation.required', { field: t('auth.login.shell.emailLabel') });
  } else if (!EMAIL_PATTERN.test(values.email)) {
    errors.email = t('auth.validation.email');
  }

  if (values.password === '') {
    errors.password = t('auth.validation.required', {
      field: t('auth.login.shell.passwordLabel'),
    });
  }

  return errors;
}

/**
 * Validación client-side alineada al backend: nombres min 2, email
 * formato, password min 8, confirmación igual, términos aceptados.
 */
export function validateRegisterForm(
  values: RegisterFormValues,
  t: TranslateFn,
): FieldErrors {
  const errors: FieldErrors = {};
  const requiredMessage = (label: string): string =>
    t('auth.validation.required', { field: label });

  if (values.first_name.trim() === '') {
    errors.first_name = requiredMessage(t('auth.signup.shell.firstNameLabel'));
  } else if (values.first_name.trim().length < 2) {
    errors.first_name = t('auth.validation.minLength', {
      field: t('auth.signup.shell.firstNameLabel'),
      min: 2,
    });
  }

  if (values.last_name.trim() === '') {
    errors.last_name = requiredMessage(t('auth.signup.shell.lastNameLabel'));
  } else if (values.last_name.trim().length < 2) {
    errors.last_name = t('auth.validation.minLength', {
      field: t('auth.signup.shell.lastNameLabel'),
      min: 2,
    });
  }

  if (values.email.trim() === '') {
    errors.email = requiredMessage(t('auth.signup.shell.emailLabel'));
  } else if (!EMAIL_PATTERN.test(values.email)) {
    errors.email = t('auth.validation.email');
  }

  if (values.password === '') {
    errors.password = requiredMessage(t('auth.signup.shell.passwordLabel'));
  } else if (values.password.length < 8) {
    errors.password = t('auth.validation.minLength', {
      field: t('auth.signup.shell.passwordLabel'),
      min: 8,
    });
  }

  if (values.password_confirmation === '') {
    errors.password_confirmation = requiredMessage(
      t('auth.signup.shell.passwordConfirmationLabel'),
    );
  } else if (values.password !== values.password_confirmation) {
    errors.password_confirmation = t('auth.signup.shell.passwordMismatch');
  }

  if (!values.accept_terms) {
    errors.terms = t('auth.signup.shell.termsRequired');
  }

  return errors;
}
