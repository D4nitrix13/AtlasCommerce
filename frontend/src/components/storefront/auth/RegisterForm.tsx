import { useEffect, useMemo, useState, type ReactElement } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { buildApiUrl } from '../../../lib/api';
import { API_ENDPOINTS } from '../../../lib/endpoints';
import { useTranslations, type Locale } from '../../../i18n';
import { withLocale, stripLocaleFromPath } from '../../../lib/path-helpers';
import { GoogleIcon, FacebookIcon } from '../../../components/ui/SocialIcons';
import { customerAuthStore, useCustomerAuth } from '../../../stores/customer-auth';
import FormField, { RequiredMark, inputClass } from './FormField';
import {
  FIELD_IDS,
  firstInvalidField,
  mapApiFieldErrors,
  validateRegisterForm,
  type FieldErrors,
  type FieldName,
} from './authFieldError';

/**
 * Form de registro para clientes.
 *
 * Sigue el design system:
 *   - Nombre + Apellido (2 columnas)
 *   - Correo
 *   - Contraseña con indicador de fuerza (4 barras)
 *   - Checkbox de Términos + Privacidad (requerido)
 *   - Submit "Crear mi cuenta"
 *
 * Validación client-side alineada al backend: errores por campo
 * (inline + highlight + foco al primer inválido), mensajes
 * localizados y redirect post-registro preservando el locale.
 */

interface RegisterFormProps {
  callbackPath?: string;
  /** Locale activo: links de términos/privacidad y redirect post-registro. */
  locale?: Locale;
}

type StrengthLevel = 'weak' | 'medium' | 'good';

function scorePassword(value: string): number {
  let score = 0;
  if (value.length >= 8) score += 1;
  if (value.length >= 12) score += 1;
  if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score += 1;
  if (/\d/.test(value) || /[^A-Za-z0-9]/.test(value)) score += 1;
  return score;
}

function strengthLevel(score: number): StrengthLevel {
  if (score <= 1) return 'weak';
  if (score === 2) return 'medium';
  return 'good';
}

export default function RegisterForm({
  callbackPath = '/account',
  locale = 'es',
}: RegisterFormProps): ReactElement {
  const { t } = useTranslations();

  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [phone, setPhone] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [acceptTerms, setAcceptTerms] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [attentionField, setAttentionField] = useState<FieldName | null>(null);

  // CA.5 — usamos el customer auth store (CA.4) en vez de apiFetch
  // directo. El store se hidrata via /me si Sanctum stateful ya tiene
  // la cookie; si el backend falla, el store queda en estado limpio
  // y el form muestra el error. customerAuthStore.register es la
  // función que llama POST /v1/storefront/auth/register (CA.2).
  // `hydrate` vive en el store, no en el hook — el hook solo expone
  // register/login/logout. Por eso importamos el store directamente.
  useCustomerAuth();

  const score = useMemo(() => scorePassword(password), [password]);
  const strength = useMemo(() => strengthLevel(score), [score]);

  // Cue de atención breve (~600ms) en el error del primer campo
  // inválido. `motion-safe:` lo anima solo si el usuario no pidió
  // reduced-motion.
  useEffect(() => {
    if (attentionField === null) {
      return;
    }
    const timer = window.setTimeout(() => {
      setAttentionField(null);
    }, 600);
    return () => {
      window.clearTimeout(timer);
    };
  }, [attentionField]);

  const showFieldErrors = (errors: FieldErrors): void => {
    setFieldErrors(errors);
    const first = firstInvalidField(errors);
    if (first === null) {
      return;
    }
    setAttentionField(first);
    const el = document.getElementById(FIELD_IDS[first]);
    if (el === null) {
      return;
    }
    el.focus();
    if (typeof el.scrollIntoView === 'function') {
      el.scrollIntoView({ block: 'center' });
    }
  };

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setErrorMessage(null);

    const clientErrors = validateRegisterForm(
      {
        first_name: firstName,
        last_name: lastName,
        email,
        password,
        password_confirmation: passwordConfirmation,
        accept_terms: acceptTerms,
      },
      t,
    );
    if (Object.keys(clientErrors).length > 0) {
      showFieldErrors(clientErrors);
      return;
    }
    setFieldErrors({});

    setIsSubmitting(true);
    try {
      await customerAuthStore.register({
        first_name: firstName,
        last_name: lastName,
        email,
        password,
        password_confirmation: passwordConfirmation,
        phone: phone !== '' ? phone : undefined,
        customer_type: 'retail',
      });

      // Hidratar el store desde /me antes de redirigir. customer
      // ya quedó en el state, pero /me es la fuente autoritativa
      // (por si el backend normalizó algo del payload en register).
      await customerAuthStore.hydrate();

      const params = new URLSearchParams(window.location.search);
      // Fallback con prefijo de locale: /{locale}/account (el
      // callbackPath puede venir sin locale o con uno viejo).
      const fallback = `${window.location.origin}${withLocale(
        stripLocaleFromPath(callbackPath).barePath,
        locale,
      )}`;
      const target = params.get('redirect') ?? fallback;
      window.location.href = target;
    } catch (error) {
      const apiError = error as {
        message?: string;
        errors?: Record<string, string[]>;
      };
      const mapped = mapApiFieldErrors(apiError.errors);
      if (Object.keys(mapped).length > 0) {
        showFieldErrors(mapped);
      } else {
        setErrorMessage(apiError.message ?? t('auth.passwordReset.error.generic'));
      }
      setIsSubmitting(false);
    }
  };

  const strengthBarCount = 4;
  const filledBars = strength === 'weak' ? 1 : strength === 'medium' ? 3 : 4;

  const handleSocialLogin = (provider: 'google' | 'facebook'): void => {
    // Facebook está deshabilitado como funcionalidad futura — el botón
    // existe en el DOM pero el handler no navega.
    if (provider === 'facebook') {
      return;
    }
    window.location.assign(buildApiUrl(API_ENDPOINTS.auth.social.redirect(provider)));
  };

  return (
    <form
      onSubmit={handleSubmit}
      className="space-y-5"
      noValidate
      aria-label={t('auth.signup.shell.heading')}
    >
      {errorMessage !== null && (
        <div
          role="alert"
          className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
          {errorMessage}
        </div>
      )}

      <p className="text-xs text-slate-500">{t('forms.requiredLegend')}</p>

      <div className="grid grid-cols-2 gap-3">
        <button
          type="button"
          onClick={() => {
            handleSocialLogin('google');
          }}
          data-auth-link="social-google"
          className="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-[var(--brand-accent-ring,#2D9D8E40)]"
        >
          <GoogleIcon />
          <span>{t('auth.signup.shell.google')}</span>
        </button>
        <button
          type="button"
          disabled
          aria-disabled="true"
          title={t('auth.signup.shell.facebookComingSoon')}
          data-auth-link="social-facebook"
          className="flex cursor-not-allowed items-center justify-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-400 opacity-60"
        >
          <FacebookIcon />
          <span>{t('auth.signup.shell.facebook')}</span>
        </button>
      </div>

      <div className="flex items-center gap-3">
        <hr className="flex-1 border-slate-200" />
        <span className="text-xs uppercase tracking-wider text-slate-400">
          {t('auth.signup.shell.divider')}
        </span>
        <hr className="flex-1 border-slate-200" />
      </div>

      <div className="grid grid-cols-2 gap-3">
        <FormField
          id="firstName"
          label={t('auth.signup.shell.firstNameLabel')}
          required
          error={fieldErrors.first_name}
          attention={attentionField === 'first_name'}
        >
          <input
            id="firstName"
            name="first_name"
            type="text"
            autoComplete="given-name"
            required
            aria-required="true"
            aria-invalid={fieldErrors.first_name !== undefined ? 'true' : undefined}
            aria-describedby={
              fieldErrors.first_name !== undefined ? 'firstName-error' : undefined
            }
            placeholder={t('auth.signup.shell.firstNamePlaceholder')}
            value={firstName}
            onChange={(event) => {
              setFirstName(event.target.value);
            }}
            className={inputClass(fieldErrors.first_name !== undefined)}
          />
        </FormField>
        <FormField
          id="lastName"
          label={t('auth.signup.shell.lastNameLabel')}
          required
          error={fieldErrors.last_name}
          attention={attentionField === 'last_name'}
        >
          <input
            id="lastName"
            name="last_name"
            type="text"
            autoComplete="family-name"
            required
            aria-required="true"
            aria-invalid={fieldErrors.last_name !== undefined ? 'true' : undefined}
            aria-describedby={fieldErrors.last_name !== undefined ? 'lastName-error' : undefined}
            placeholder={t('auth.signup.shell.lastNamePlaceholder')}
            value={lastName}
            onChange={(event) => {
              setLastName(event.target.value);
            }}
            className={inputClass(fieldErrors.last_name !== undefined)}
          />
        </FormField>
      </div>

      <FormField
        id="email"
        label={t('auth.signup.shell.emailLabel')}
        required
        error={fieldErrors.email}
        attention={attentionField === 'email'}
      >
        <input
          id="email"
          name="email"
          type="email"
          autoComplete="email"
          required
          aria-required="true"
          aria-invalid={fieldErrors.email !== undefined ? 'true' : undefined}
          aria-describedby={fieldErrors.email !== undefined ? 'email-error' : undefined}
          placeholder={t('auth.signup.shell.emailPlaceholder')}
          value={email}
          onChange={(event) => {
            setEmail(event.target.value);
          }}
          className={inputClass(fieldErrors.email !== undefined)}
        />
      </FormField>

      <FormField
        id="password"
        label={t('auth.signup.shell.passwordLabel')}
        required
        error={fieldErrors.password}
        attention={attentionField === 'password'}
      >
        <div className="relative">
          <input
            id="password"
            name="password"
            type={showPassword ? 'text' : 'password'}
            autoComplete="new-password"
            required
            aria-required="true"
            minLength={8}
            aria-invalid={fieldErrors.password !== undefined ? 'true' : undefined}
            aria-describedby={fieldErrors.password !== undefined ? 'password-error' : undefined}
            placeholder={t('auth.signup.shell.passwordPlaceholder')}
            value={password}
            onChange={(event) => {
              setPassword(event.target.value);
            }}
            className={`${inputClass(fieldErrors.password !== undefined)} pr-10`}
          />
          <button
            type="button"
            onClick={() => {
              setShowPassword(!showPassword);
            }}
            aria-label={
              showPassword
                ? t('auth.login.password.toggle.hide')
                : t('auth.login.password.toggle.show')
            }
            className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none"
          >
            {showPassword ? (
              <EyeOff size={20} strokeWidth={2} />
            ) : (
              <Eye size={20} strokeWidth={2} />
            )}
          </button>
        </div>
        <div className="flex gap-1 mt-2" aria-hidden="true">
          {Array.from({ length: strengthBarCount }).map((_, index) => (
            <div
              key={index}
              className={`h-1 flex-1 rounded ${
                index < filledBars ? 'bg-[var(--brand-accent,#2D9D8E)]' : 'bg-slate-200'
              }`}
            />
          ))}
        </div>
        <p className="text-xs text-slate-500 mt-1.5">
          {t('auth.signup.shell.strengthLabel')}{' '}
          <span className="font-semibold text-[var(--brand-accent,#2D9D8E)]">
            {t(
              strength === 'weak'
                ? 'auth.signup.shell.strengthWeak'
                : strength === 'medium'
                  ? 'auth.signup.shell.strengthMedium'
                  : 'auth.signup.shell.strengthGood'
            )}
          </span>
        </p>
      </FormField>

      {/* CA.5 — password_confirmation (validation cliente-side antes de
          mandar al backend, que también lo valida via same:password). */}
      <FormField
        id="passwordConfirmation"
        label={t('auth.signup.shell.passwordConfirmationLabel')}
        required
        error={fieldErrors.password_confirmation}
        attention={attentionField === 'password_confirmation'}
      >
        <div className="relative">
          <input
            id="passwordConfirmation"
            name="password_confirmation"
            type={showPassword ? 'text' : 'password'}
            autoComplete="new-password"
            required
            aria-required="true"
            minLength={8}
            aria-invalid={fieldErrors.password_confirmation !== undefined ? 'true' : undefined}
            aria-describedby={
              fieldErrors.password_confirmation !== undefined
                ? 'passwordConfirmation-error'
                : undefined
            }
            placeholder={t('auth.signup.shell.passwordConfirmationPlaceholder')}
            value={passwordConfirmation}
            onChange={(event) => {
              setPasswordConfirmation(event.target.value);
            }}
            className={`${inputClass(fieldErrors.password_confirmation !== undefined)} pr-10`}
          />
        </div>
      </FormField>

      {/* CA.5 — phone opcional. El backend acepta el campo como null. */}
      <FormField id="phone" label={t('auth.signup.shell.phoneLabel')}>
        <input
          id="phone"
          name="phone"
          type="tel"
          autoComplete="tel"
          placeholder={t('auth.signup.shell.phonePlaceholder')}
          value={phone}
          onChange={(event) => {
            setPhone(event.target.value);
          }}
          className={inputClass(false)}
        />
      </FormField>

      <div>
        <label htmlFor="acceptTerms" className="flex items-start gap-2 cursor-pointer">
          <input
            id="acceptTerms"
            type="checkbox"
            required
            aria-required="true"
            aria-invalid={fieldErrors.terms !== undefined ? 'true' : undefined}
            aria-describedby={fieldErrors.terms !== undefined ? 'acceptTerms-error' : undefined}
            checked={acceptTerms}
            onChange={(event) => {
              setAcceptTerms(event.target.checked);
            }}
            className="w-4 h-4 mt-0.5 rounded border-slate-300 text-[var(--brand-accent,#2D9D8E)] focus:ring-[var(--brand-accent,#2D9D8E)]"
          />
          <span className="text-sm text-slate-600">
            {t('auth.signup.shell.termsAcceptPrefix')}{' '}
            <a
              href={withLocale('/terms', locale)}
              className="text-[var(--brand-accent,#2D9D8E)] hover:underline"
            >
              {t('auth.signup.shell.termsLink')}
            </a>{' '}
            {t('auth.signup.shell.termsAcceptInfix')}{' '}
            <a
              href={withLocale('/privacy', locale)}
              className="text-[var(--brand-accent,#2D9D8E)] hover:underline"
            >
              {t('auth.signup.shell.privacyLink')}
            </a>
            .<RequiredMark />
          </span>
        </label>
        {fieldErrors.terms !== undefined && (
          <p
            id="acceptTerms-error"
            role="alert"
            className={`mt-1.5 text-sm text-red-600${
              attentionField === 'terms' ? ' motion-safe:animate-pulse' : ''
            }`}
          >
            {fieldErrors.terms}
          </p>
        )}
      </div>

      <button
        type="submit"
        disabled={isSubmitting}
        className="w-full bg-[var(--brand-accent,#2D9D8E)] hover:bg-[var(--brand-accent-hover,#258777)] disabled:bg-[var(--brand-accent-hover,#258777)] disabled:opacity-70 text-white font-semibold py-2.5 rounded-lg transition focus:outline-none focus:ring-4 focus:ring-[var(--brand-accent-ring,#2D9D8E40)]"
      >
        {isSubmitting ? t('auth.signup.shell.submitLoading') : t('auth.signup.shell.submit')}
      </button>
    </form>
  );
}
