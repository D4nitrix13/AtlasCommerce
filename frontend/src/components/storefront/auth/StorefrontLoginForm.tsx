import { useState, type ReactElement } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { apiFetch } from '../../../lib/api';
import { useTranslations } from '../../../i18n';
import { GoogleIcon, FacebookIcon } from '../../../components/ui/SocialIcons';
import type { LoginResponse } from '../../../types/auth';

/**
 * Form de login para clientes (storefront).
 *
 * Estructura del design system:
 *   - Social login: Google + Facebook (botones secundarios)
 *   - Divider "o con tu correo"
 *   - Email + password con toggle de visibilidad
 *   - Link "¿Olvidaste tu contraseña?"
 *   - Checkbox "Recuérdame"
 *   - Submit "Iniciar sesión"
 *
 * El admin usa `LoginForm` directo (sin social, sin divider) porque
 * las credenciales son internas (admin@<slug>.com / password).
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
  // Reservado para uso futuro. Mantener el prop evita romper el call
  // site mientras no haya un consumer real.
  locale?: string;
}

export default function StorefrontLoginForm({
  callbackPath = '/account',
  forgotHref = 'forgot-password',
  locale: _locale = 'es',
}: StorefrontLoginFormProps): ReactElement {
  const { t } = useTranslations();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleSocialLogin = (provider: 'google' | 'facebook'): void => {
    // Navega al endpoint OAuth del backend. Cuando Socialite esté
    // configurado (GOOGLE_CLIENT_ID, FACEBOOK_CLIENT_ID), el backend
    // responde con un 302 a la página de OAuth del provider. Si las
    // credenciales no están configuradas, devuelve 501.
    window.location.assign(`/api/auth/social/${provider}/redirect`);
  };

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setErrorMessage(null);
    setIsSubmitting(true);

    try {
      await apiFetch<LoginResponse>('/auth/login', {
        method: 'POST',
        body: {
          email,
          password,
          device_name: 'web-client',
        },
      });

      const params = new URLSearchParams(window.location.search);
      const fallback = `${window.location.origin}${callbackPath}`;
      const target = params.get('redirect') ?? fallback;
      window.location.href = target;
    } catch (error) {
      const apiError = error as { message?: string };
      setErrorMessage(apiError.message ?? t('auth.login.error.invalid'));
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
          onClick={() => {
            handleSocialLogin('facebook');
          }}
          data-auth-link="social-facebook"
          className="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-[var(--brand-accent-ring,#2D9D8E40)]"
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
        <div>
          <label htmlFor="email" className="block text-sm font-medium text-slate-700 mb-1.5">
            {t('auth.login.shell.emailLabel')}
          </label>
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="email"
            required
            placeholder={t('auth.login.shell.emailPlaceholder')}
            value={email}
            onChange={(event) => {
              setEmail(event.target.value);
            }}
            className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[var(--brand-accent,#2D9D8E)] focus:outline-none focus:ring-4 focus:ring-[var(--brand-accent-ring,#2D9D8E40)]"
          />
        </div>

        <div>
          <div className="flex items-center justify-between mb-1.5">
            <label htmlFor="password" className="block text-sm font-medium text-slate-700">
              {t('auth.login.shell.passwordLabel')}
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
              placeholder="••••••••"
              value={password}
              onChange={(event) => {
                setPassword(event.target.value);
              }}
              className="w-full px-4 py-2.5 pr-10 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[var(--brand-accent,#2D9D8E)] focus:outline-none focus:ring-4 focus:ring-[var(--brand-accent-ring,#2D9D8E40)]"
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
