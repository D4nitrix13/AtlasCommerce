import { useState, type ReactElement } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { useAuth } from '../../../stores/auth';
import { useTranslations } from '../../../i18n';
import { AUTH_ACCENT } from '../../../lib/auth-accent';
import { stripLocaleFromPath, withLocale } from '../../../lib/path-helpers';
import { FacebookIcon, GoogleIcon } from '../../ui/SocialIcons';

/**
 * Form de login admin — email + password + OAuth (Google/Facebook).
 *
 * Bloque superior: 2 botones sociales (Google + Facebook) + divider
 * "o con tu correo" antes del bloque email/password. Los botones
 * delegan al backend vía `window.location.assign('/api/auth/social/{provider}/redirect')`
 * — el `EnsureFrontendRequestsAreStateful` middleware aplica
 * Sanctum stateful antes de la llamada para que el `state` CSRF
 * sobreviva el round-trip al provider.
 *
 * El admin puede usar credenciales internas (admin@<slug>.com / password)
 * o continuar con un provider OAuth — el backend rechaza admin signup
 * desde subdominios admin.* (P-C policy) pero usuarios pre-existentes
 * pueden seguir logueando.
 */
type LoginFormProps = {
  emailLabel?: string;
  emailPlaceholder?: string;
  passwordLabel?: string;
  passwordPlaceholder?: string;
  submitLabel?: string;
  submittingLabel?: string;
  forgotHref?: string;
};

export default function LoginForm({
  emailLabel,
  emailPlaceholder = 'tu@correo.com',
  passwordLabel,
  passwordPlaceholder = '••••••••',
  submitLabel,
  submittingLabel,
  forgotHref = 'forgot-password',
}: LoginFormProps): ReactElement {
  const { login } = useAuth();
  const { t } = useTranslations();

  const resolvedEmailLabel = emailLabel ?? t('auth.login.shell.emailLabel');
  const resolvedPasswordLabel = passwordLabel ?? t('auth.login.shell.passwordLabel');
  const resolvedSubmitLabel = submitLabel ?? t('auth.login.shell.submit');
  const resolvedSubmittingLabel = submittingLabel ?? t('auth.login.shell.submitLoading');

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setErrorMessage(null);
    setIsSubmitting(true);

    try {
      const result = await login({ email, password, remember: rememberMe });

      if (result.kind === '2fa_required') {
        redirectToTwoFactor(result.challengeId);
        return;
      }

      redirectAfterLogin();
    } catch (error) {
      const apiError = error as {
        status?: number;
        message?: string;
      };
      setErrorMessage(apiError.message ?? t('auth.login.error.invalid'));
      setIsSubmitting(false);
    }
  };

  /**
   * Construye la URL `/<locale>/auth/2fa?token=<challengeId>`
   * preservando el `?redirect=` original para volver al destino
   * correcto una vez verificado el código TOTP.
   */
  function redirectToTwoFactor(challengeId: number): void {
    const params = new URLSearchParams(window.location.search);
    const currentRedirect = params.get('redirect') ?? '';
    const locale = stripLocaleFromPath(window.location.pathname).locale;
    const target = withLocale('/auth/2fa', locale);
    const qs = new URLSearchParams();
    qs.set('token', String(challengeId));
    if (currentRedirect !== '') {
      qs.set('redirect', currentRedirect);
    }
    window.location.href = `${target}?${qs.toString()}`;
  }

  function redirectAfterLogin(): void {
    const params = new URLSearchParams(window.location.search);
    const fallback = `${window.location.origin}/dashboard`;
    const target = params.get('redirect') ?? fallback;
    window.location.href = target;
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="space-y-5"
      noValidate
      aria-label={t('auth.login.title')}
    >
      {errorMessage !== null && (
        <div
          role="alert"
          className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
          {errorMessage}
        </div>
      )}

      {/* Bloque social — Google (activo) + Facebook (deshabilitado) en fila + divider. */}
      <div className="space-y-3">
        <div className="flex gap-3">
          {/* Google: activo. Backend OAuth listo y probado end-to-end
              (config/services.php + SocialAuthController con `google`
              en SUPPORTED_PROVIDERS, redirect URI registrada en Google
              Cloud Console, session + state CSRF funcionando). */}
          <button
            type="button"
            data-auth-link="social-google"
            onClick={() => {
              window.location.assign('/api/auth/social/google/redirect');
            }}
            className={`flex-1 flex items-center justify-center gap-2 bg-[#${AUTH_ACCENT.primary.slice(1)}] hover:bg-[#${AUTH_ACCENT.hover.slice(1)}] text-white font-semibold py-2.5 rounded-lg transition focus:outline-none focus:ring-4 focus:ring-[#${AUTH_ACCENT.primary}]/25`}
          >
            <GoogleIcon className="size-5" />
            <span>{t('auth.login.shell.google')}</span>
          </button>
          {/* Facebook: deshabilitado temporalmente. El backend OAuth está
              listo (provider en SUPPORTED_PROVIDERS) pero las credenciales
              no están configuradas (Meta exige business verification
              ~7-14 días). Cursor `not-allowed` + opacidad + sin onClick
              → no dispara redirect. */}
          <button
            type="button"
            data-auth-link="social-facebook"
            disabled
            aria-disabled="true"
            title={t('auth.login.shell.facebookComingSoon')}
            className={`flex-1 flex items-center justify-center gap-2 bg-[#${AUTH_ACCENT.primary.slice(1)}] text-white font-semibold py-2.5 rounded-lg transition opacity-50 cursor-not-allowed`}
          >
            <FacebookIcon className="size-5" />
            <span>{t('auth.login.shell.facebook')}</span>
          </button>
        </div>
        <div className="flex items-center gap-3" aria-hidden="false">
          <span className="flex-1 h-px bg-slate-200" aria-hidden="true" />
          <span className="text-xs uppercase tracking-wide text-slate-500">
            {t('auth.login.shell.divider')}
          </span>
          <span className="flex-1 h-px bg-slate-200" aria-hidden="true" />
        </div>
      </div>

      <div>
        <label htmlFor="email" className="block text-sm font-medium text-slate-700 mb-1.5">
          {resolvedEmailLabel}
        </label>
        <input
          id="email"
          name="email"
          type="email"
          autoComplete="email"
          required
          placeholder={emailPlaceholder}
          value={email}
          onChange={(event) => {
            setEmail(event.target.value);
          }}
          className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[#2D9D8E] focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
        />
      </div>

      <div>
        <div className="flex items-center justify-between mb-1.5">
          <label htmlFor="password" className="block text-sm font-medium text-slate-700">
            {resolvedPasswordLabel}
          </label>
          <a
            href={forgotHref}
            data-auth-link="forgot"
            className="text-xs font-semibold text-[#2D9D8E] hover:text-[#1E6F62]"
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
            placeholder={passwordPlaceholder}
            value={password}
            onChange={(event) => {
              setPassword(event.target.value);
            }}
            className="w-full px-4 py-2.5 pr-10 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[#2D9D8E] focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
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
      </div>

      <label className="flex items-center gap-2 cursor-pointer">
        <input
          type="checkbox"
          checked={rememberMe}
          onChange={(event) => {
            setRememberMe(event.target.checked);
          }}
          className="w-4 h-4 rounded border-slate-300 text-[#2D9D8E] focus:ring-[#2D9D8E]"
        />
        <span className="text-sm text-slate-600">{t('auth.login.shell.rememberMe')}</span>
      </label>

      <button
        type="submit"
        disabled={isSubmitting}
        className="w-full bg-[#2D9D8E] hover:bg-[#258777] disabled:bg-[#258777] disabled:opacity-70 text-white font-semibold py-2.5 rounded-lg transition focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
      >
        {isSubmitting ? resolvedSubmittingLabel : resolvedSubmitLabel}
      </button>
    </form>
  );
}
