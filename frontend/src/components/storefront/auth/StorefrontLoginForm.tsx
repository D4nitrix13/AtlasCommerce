import { useEffect, useState, type ReactElement } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { buildApiUrl } from '../../../lib/api';
import { API_ENDPOINTS } from '../../../lib/endpoints';
import { useTranslations, type Locale } from '../../../i18n';
import { withLocale, stripLocaleFromPath } from '../../../lib/path-helpers';
import { GoogleIcon, FacebookIcon } from '../../../components/ui/SocialIcons';
import { useCustomerAuth } from '../../../stores/customer-auth';
import FormField, { RequiredMark, inputClass } from './FormField';
import {
  FIELD_IDS,
  firstInvalidField,
  mapApiFieldErrors,
  validateLoginForm,
  type FieldErrors,
  type FieldName,
} from './authFieldError';

/**
 * Form de login para clientes (storefront).
 *
 * Estructura del design system:
 *   - Social login: Google (botón secundario, Facebook deshabilitado
 *     como funcionalidad futura — ver F2 del feature doc)
 *   - Divider "o con tu correo"
 *   - Email + password con toggle de visibilidad
 *   - Link "¿Olvidaste tu contraseña?"
 *   - Checkbox "Recuérdame"
 *   - Submit "Iniciar sesión"
 *
 * Admin y customer loguean por este mismo form (el `LoginForm` propio
 * del admin fue eliminado en 7878f871): las credenciales admin son
 * internas (admin@<slug>.com / password) y el challenge 2FA se
 * resuelve en /{locale}/account/2fa.
 *
 * Validación client-side alineada al backend: errores por campo
 * (inline + highlight + foco al primer inválido), mensajes
 * localizados y redirect post-login preservando el locale.
 *
 * Backend OAuth (Google/Facebook) requiere:
 *   - composer require laravel/socialite
 *   - Configurar GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET y
 *     FACEBOOK_CLIENT_ID / FACEBOOK_CLIENT_SECRET en .env
 *   - Implementar SocialAuthController con redirect + callback
 * Mientras esas rutas devuelven 501, los botones navegan al endpoint
 * y el usuario ve el error en pantalla.
 */

interface StorefrontLoginFormProps {
  callbackPath?: string;
  forgotHref?: string;
  /** Locale activo: prefijo para rutas internas (ej. `/es/account/2fa`)
   *  y fallback del redirect post-login. */
  locale?: Locale;
}

export default function StorefrontLoginForm({
  callbackPath = '/account',
  forgotHref = 'forgot-password',
  locale = 'es',
}: StorefrontLoginFormProps): ReactElement {
  const { t } = useTranslations();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [attentionField, setAttentionField] = useState<FieldName | null>(null);

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

  const handleSocialLogin = (provider: 'google' | 'facebook'): void => {
    // Facebook está deshabilitado como funcionalidad futura — el botón
    // existe en el DOM pero el handler no navega. Si en el futuro se
    // reactiva, basta con eliminar el early-return.
    if (provider === 'facebook') {
      return;
    }
    // Navega al endpoint OAuth del backend. Cuando Socialite esté
    // configurado (GOOGLE_CLIENT_ID), el backend responde con un 302
    // a la página de OAuth del provider. Si las credenciales no están
    // configuradas, devuelve 501.
    window.location.assign(buildApiUrl(API_ENDPOINTS.auth.social.redirect(provider)));
  };

  // CA.5 — usamos el customer auth store (CA.4) en vez del admin
  // auth store. /account/* es territorio del customer, no del
  // admin. El customer login no soporta 2FA flow (CA.3 retorna
  // el customer data en /auth/login sin challenge).
  const { login: customerLogin } = useCustomerAuth();

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setErrorMessage(null);

    const clientErrors = validateLoginForm({ email, password }, t);
    if (Object.keys(clientErrors).length > 0) {
      showFieldErrors(clientErrors);
      return;
    }
    setFieldErrors({});

    setIsSubmitting(true);
    try {
      const outcome = await customerLogin(email, password);

      const params = new URLSearchParams(window.location.search);
      // Fallback con prefijo de locale: /{locale}/account (el
      // callbackPath puede venir sin locale o con uno viejo).
      const fallbackPath = withLocale(stripLocaleFromPath(callbackPath).barePath, locale);

      // 2FA (admin y usuarios con TOTP): el challenge se resuelve en
      // /{locale}/account/2fa. El token viaja en la query porque esa
      // página lo recibe como prop (ver account/2fa.astro).
      if (outcome.kind === '2fa_required') {
        const requested = params.get('redirect') ?? '';
        const isAdminPath = /^\/(?:[a-z]{2}\/)?admin\//i.test(requested);
        const target = requested !== '' && !isAdminPath ? requested : fallbackPath;
        window.location.href =
          `/${locale}/account/2fa?token=${outcome.challengeId}` +
          `&redirect=${encodeURIComponent(target)}`;
        return;
      }

      // Login success: redirect al Account del customer.
      // CRÍTICO: el `?redirect=` no debe apuntar a /admin/* — un
      // cliente no debe terminar en admin aunque el query param lo
      // pida (un atacante podría haber injectado esa URL). Si el target
      // pide admin, usamos el fallback.
      const requestedRedirect = params.get('redirect');
      const fallback = `${window.location.origin}${fallbackPath}`;

      let target = fallback;
      if (requestedRedirect !== null && requestedRedirect !== '') {
        const isAdminPath = /^\/(?:[a-z]{2}\/)?admin\//i.test(requestedRedirect);
        if (!isAdminPath) {
          target = requestedRedirect;
        }
      }

      window.location.href = target;
    } catch (error) {
      const apiError = error as { message?: string; errors?: Record<string, string[]> };
      const mapped = mapApiFieldErrors(apiError.errors);
      if (Object.keys(mapped).length > 0) {
        showFieldErrors(mapped);
      } else {
        setErrorMessage(apiError.message ?? t('auth.login.error.invalid'));
      }
      setIsSubmitting(false);
    }
  };

  return (
    <div className="space-y-5">
      {errorMessage !== null && (
        <div
          role="alert"
          className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
          {errorMessage}
        </div>
      )}

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
          <span>{t('auth.login.shell.google')}</span>
        </button>
        <button
          type="button"
          disabled
          aria-disabled="true"
          title={t('auth.login.shell.facebookComingSoon')}
          data-auth-link="social-facebook"
          className="flex cursor-not-allowed items-center justify-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-400 opacity-60"
        >
          <FacebookIcon />
          <span>{t('auth.login.shell.facebook')}</span>
        </button>
      </div>

      <div className="flex items-center gap-3">
        <hr className="flex-1 border-slate-200" />
        <span className="text-xs uppercase tracking-wider text-slate-400">
          {t('auth.login.shell.divider')}
        </span>
        <hr className="flex-1 border-slate-200" />
      </div>

      <form
        onSubmit={handleSubmit}
        className="space-y-5"
        noValidate
        aria-label={t('auth.login.shell.heading')}
      >
        <p className="text-xs text-slate-500">{t('forms.requiredLegend')}</p>

        <FormField
          id="email"
          label={t('auth.login.shell.emailLabel')}
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
            placeholder={t('auth.login.shell.emailPlaceholder')}
            value={email}
            onChange={(event) => {
              setEmail(event.target.value);
            }}
            className={inputClass(fieldErrors.email !== undefined)}
          />
        </FormField>

        {/* Label row a medida: el link de "olvidaste tu contraseña"
            vive al lado del label (FormField no acepta label extras). */}
        <div>
          <div className="flex items-center justify-between mb-1.5">
            <label htmlFor="password" className="block text-sm font-medium text-slate-700">
              {t('auth.login.shell.passwordLabel')}
              <RequiredMark />
            </label>
            <a
              href={forgotHref}
              data-auth-link="forgot"
              className="text-xs font-semibold text-[var(--brand-accent,#2D9D8E)] hover:text-[var(--brand-accent-hover,#258777)]"
            >
              {t('auth.login.shell.forgotLink')}
            </a>
          </div>
          <div className="relative">
            <input
              id="password"
              name="password"
              type={showPassword ? 'text' : 'password'}
              autoComplete="current-password"
              required
              aria-required="true"
              aria-invalid={fieldErrors.password !== undefined ? 'true' : undefined}
              aria-describedby={fieldErrors.password !== undefined ? 'password-error' : undefined}
              placeholder="••••••••"
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
          {fieldErrors.password !== undefined && (
            <p
              id="password-error"
              role="alert"
              className={`mt-1.5 text-sm text-red-600${
                attentionField === 'password' ? ' motion-safe:animate-pulse' : ''
              }`}
            >
              {fieldErrors.password}
            </p>
          )}
        </div>

        <label className="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            checked={rememberMe}
            onChange={(event) => {
              setRememberMe(event.target.checked);
            }}
            className="w-4 h-4 rounded border-slate-300 text-[var(--brand-accent,#2D9D8E)] focus:ring-[var(--brand-accent,#2D9D8E)]"
          />
          <span className="text-sm text-slate-600">{t('auth.login.shell.rememberMe')}</span>
        </label>

        <button
          type="submit"
          disabled={isSubmitting}
          className="w-full bg-[var(--brand-accent,#2D9D8E)] hover:bg-[var(--brand-accent-hover,#258777)] disabled:bg-[var(--brand-accent-hover,#258777)] disabled:opacity-70 text-white font-semibold py-2.5 rounded-lg transition focus:outline-none focus:ring-4 focus:ring-[var(--brand-accent-ring,#2D9D8E40)]"
        >
          {isSubmitting ? t('auth.login.shell.submitLoading') : t('auth.login.shell.submit')}
        </button>
      </form>

      <div className="mt-6 text-center">
        <button
          type="button"
          data-passkey-login-trigger
          className="w-full bg-white border border-slate-300 hover:border-[var(--brand-accent,#2D9D8E)] hover:bg-slate-50 text-slate-700 font-medium py-2.5 rounded-lg transition focus:outline-none focus:ring-4 focus:ring-[var(--brand-accent-ring,#2D9D8E40)] inline-flex items-center justify-center gap-2"
        >
          <span className="text-base" aria-hidden="true">
            🔑
          </span>
          <span>{t('auth.login.shell.passkeyButton') ?? 'Iniciar sesión con passkey'}</span>
        </button>
        <p className="mt-2 text-xs text-slate-400">
          {t('auth.login.shell.passkeyHint') ??
            'WebAuthn · Tu dispositivo biométrico o llave de seguridad'}
        </p>
      </div>
    </div>
  );
}
