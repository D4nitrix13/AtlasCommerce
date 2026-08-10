import { useState, type ReactElement } from "react";
import { Eye, EyeOff } from "lucide-react";
import { useAuth } from "../../../stores/auth";
import { useTranslations } from "../../../i18n";

/**
 * Form de login admin — sin register, sin social login.
 *
 * Solo email + password + "¿Olvidaste tu contraseña?" + checkbox
 * "Recuérdame" + submit.
 *
 * El admin usa credenciales default (admin@<slug>.com / password)
 * creadas por el seeder backend. No hay OAuth.
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
  emailPlaceholder = "tu@correo.com",
  passwordLabel,
  passwordPlaceholder = "••••••••",
  submitLabel,
  submittingLabel,
  forgotHref = "forgot-password",
}: LoginFormProps): ReactElement {
  const { login } = useAuth();
  const { t } = useTranslations();

  const resolvedEmailLabel = emailLabel ?? t("auth.login.shell.emailLabel");
  const resolvedPasswordLabel = passwordLabel ?? t("auth.login.shell.passwordLabel");
  const resolvedSubmitLabel = submitLabel ?? t("auth.login.shell.submit");
  const resolvedSubmittingLabel = submittingLabel ?? t("auth.login.shell.submitLoading");

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setErrorMessage(null);
    setIsSubmitting(true);

    try {
      await login({ email, password, remember: rememberMe });

      const params = new URLSearchParams(window.location.search);
      const fallback = `${window.location.origin}/dashboard`;
      const target = params.get("redirect") ?? fallback;

      window.location.href = target;
    } catch (error) {
      const apiError = error as {
        status?: number;
        message?: string;
      };
      setErrorMessage(
        apiError.message ?? t("auth.login.error.invalid"),
      );
      setIsSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-5" noValidate aria-label={t("auth.login.title")}>
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
            window.location.assign('/api/auth/social/google/redirect');
          }}
          data-auth-link="social-google"
          className="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
          </svg>
          <span>{t("auth.login.shell.google")}</span>
        </button>
        <button
          type="button"
          onClick={() => {
            window.location.assign('/api/auth/social/facebook/redirect');
          }}
          data-auth-link="social-facebook"
          className="flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
          </svg>
          <span>{t("auth.login.shell.facebook")}</span>
        </button>
      </div>

      <div className="flex items-center gap-3">
        <hr className="flex-1 border-slate-200" />
        <span className="text-xs uppercase tracking-wider text-slate-400">
          {t("auth.login.shell.divider")}
        </span>
        <hr className="flex-1 border-slate-200" />
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
            {t("auth.login.shell.forgotLink")}
          </a>
        </div>
        <div className="relative">
          <input
            id="password"
            name="password"
            type={showPassword ? "text" : "password"}
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
            aria-label={showPassword ? t("auth.login.password.toggle.hide") : t("auth.login.password.toggle.show")}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none"
          >
            {showPassword ? <EyeOff size={20} strokeWidth={2} /> : <Eye size={20} strokeWidth={2} />}
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
        <span className="text-sm text-slate-600">{t("auth.login.shell.rememberMe")}</span>
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