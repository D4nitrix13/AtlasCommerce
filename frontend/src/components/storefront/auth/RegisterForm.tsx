import { useMemo, useState, type ReactElement } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { apiFetch } from '../../../lib/api';
import { useTranslations } from '../../../i18n';
import { GoogleIcon, FacebookIcon } from '../../../components/ui/SocialIcons';
import type { LoginResponse } from '../../../types/auth';

/**
 * Form de registro para clientes.
 *
 * Solo email + password (sin social login). Sigue el design system:
 *   - Nombre + Apellido (2 columnas)
 *   - Correo
 *   - Contraseña con indicador de fuerza (4 barras)
 *   - Checkbox de Términos + Privacidad (requerido)
 *   - Submit "Crear mi cuenta"
 */

type RegisterFormProps = {
  callbackPath?: string;
  locale?: string;
};

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
  const [showPassword, setShowPassword] = useState(false);
  const [acceptTerms, setAcceptTerms] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const score = useMemo(() => scorePassword(password), [password]);
  const strength = useMemo(() => strengthLevel(score), [score]);

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setErrorMessage(null);

    if (!acceptTerms) {
      setErrorMessage(t('auth.signup.shell.termsAccept'));
      return;
    }

    setIsSubmitting(true);
    try {
      await apiFetch<LoginResponse>('/auth/register', {
        method: 'POST',
        body: {
          first_name: firstName,
          last_name: lastName,
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
      setErrorMessage(apiError.message ?? t('auth.passwordReset.error.generic'));
      setIsSubmitting(false);
    }
  };

  const strengthBarCount = 4;
  const filledBars = strength === 'weak' ? 1 : strength === 'medium' ? 3 : 4;

  const handleSocialLogin = (provider: 'google' | 'facebook'): void => {
    window.location.assign(`/api/auth/social/${provider}/redirect`);
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

      <div className="grid grid-cols-2 gap-3">
        <button
          type="button"
          onClick={() => {
            handleSocialLogin('google');
          }}
          data-auth-link="social-google"
          className="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
        >
          <GoogleIcon />
          <span>{t('auth.signup.shell.google')}</span>
        </button>
        <button
          type="button"
          onClick={() => {
            handleSocialLogin('facebook');
          }}
          data-auth-link="social-facebook"
          className="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
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
        <div>
          <label htmlFor="firstName" className="block text-sm font-medium text-slate-700 mb-1.5">
            {t('auth.signup.shell.firstNameLabel')}
          </label>
          <input
            id="firstName"
            name="first_name"
            type="text"
            autoComplete="given-name"
            required
            placeholder={t('auth.signup.shell.firstNamePlaceholder')}
            value={firstName}
            onChange={(event) => {
              setFirstName(event.target.value);
            }}
            className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[#2D9D8E] focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
          />
        </div>
        <div>
          <label htmlFor="lastName" className="block text-sm font-medium text-slate-700 mb-1.5">
            {t('auth.signup.shell.lastNameLabel')}
          </label>
          <input
            id="lastName"
            name="last_name"
            type="text"
            autoComplete="family-name"
            required
            placeholder={t('auth.signup.shell.lastNamePlaceholder')}
            value={lastName}
            onChange={(event) => {
              setLastName(event.target.value);
            }}
            className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[#2D9D8E] focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
          />
        </div>
      </div>

      <div>
        <label htmlFor="email" className="block text-sm font-medium text-slate-700 mb-1.5">
          {t('auth.signup.shell.emailLabel')}
        </label>
        <input
          id="email"
          name="email"
          type="email"
          autoComplete="email"
          required
          placeholder={t('auth.signup.shell.emailPlaceholder')}
          value={email}
          onChange={(event) => {
            setEmail(event.target.value);
          }}
          className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[#2D9D8E] focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
        />
      </div>

      <div>
        <label htmlFor="password" className="block text-sm font-medium text-slate-700 mb-1.5">
          {t('auth.signup.shell.passwordLabel')}
        </label>
        <div className="relative">
          <input
            id="password"
            name="password"
            type={showPassword ? 'text' : 'password'}
            autoComplete="new-password"
            required
            minLength={8}
            placeholder={t('auth.signup.shell.passwordPlaceholder')}
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
        <div className="flex gap-1 mt-2" aria-hidden="true">
          {Array.from({ length: strengthBarCount }).map((_, index) => (
            <div
              key={index}
              className={`h-1 flex-1 rounded ${
                index < filledBars ? 'bg-[#2D9D8E]' : 'bg-slate-200'
              }`}
            />
          ))}
        </div>
        <p className="text-xs text-slate-500 mt-1.5">
          {t('auth.signup.shell.strengthLabel')}{' '}
          <span className="font-semibold text-[#1E6F62]">
            {t(
              strength === 'weak'
                ? 'auth.signup.shell.strengthWeak'
                : strength === 'medium'
                  ? 'auth.signup.shell.strengthMedium'
                  : 'auth.signup.shell.strengthGood'
            )}
          </span>
        </p>
      </div>

      <label className="flex items-start gap-2 cursor-pointer">
        <input
          type="checkbox"
          required
          checked={acceptTerms}
          onChange={(event) => {
            setAcceptTerms(event.target.checked);
          }}
          className="w-4 h-4 mt-0.5 rounded border-slate-300 text-[#2D9D8E] focus:ring-[#2D9D8E]"
        />
        <span className="text-sm text-slate-600">
          <span>Acepto los </span>
          <a href="#" className="text-[#2D9D8E] hover:underline">
            {t('auth.signup.shell.termsLink')}
          </a>
          <span> y la </span>
          <a href="#" className="text-[#2D9D8E] hover:underline">
            {t('auth.signup.shell.privacyLink')}
          </a>
          <span>.</span>
        </span>
      </label>

      <button
        type="submit"
        disabled={isSubmitting}
        className="w-full bg-[#2D9D8E] hover:bg-[#258777] disabled:bg-[#258777] disabled:opacity-70 text-white font-semibold py-2.5 rounded-lg transition focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
      >
        {isSubmitting ? t('auth.signup.shell.submitLoading') : t('auth.signup.shell.submit')}
      </button>
    </form>
  );
}
