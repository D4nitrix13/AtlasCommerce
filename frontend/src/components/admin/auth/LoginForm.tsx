import { useState, type ReactElement } from "react";
import { useAuth } from "../../../stores/auth";
import { useTranslations } from "../../../i18n";
import type { ValidationErrors } from "../../../types/auth";
import LanguageSwitcher from "../../ui/LanguageSwitcher";

const ACCENT = "#DC2626";
const ACCENT_HOVER = "#B91C1C";

type Tab = "login" | "register";

export default function LoginForm(): ReactElement {
  const { login: loginMutation } = useAuth();
  const { t, locale } = useTranslations();

  const [tab, setTab] = useState<Tab>("login");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<ValidationErrors>({});

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setErrorMessage(null);
    setFieldErrors({});
    setIsSubmitting(true);

    try {
      await loginMutation({ email, password });

      const params = new URLSearchParams(window.location.search);
      const fallback = `/${locale}/admin/dashboard`;
      const target = params.get("redirect") ?? fallback;

      window.location.href = target;
    } catch (error) {
      const apiError = error as {
        status?: number;
        message?: string;
        errors?: ValidationErrors;
      };

      if (apiError.errors !== null && apiError.errors !== undefined) {
        setFieldErrors(apiError.errors);
      }

      setErrorMessage(
        apiError.message ?? t("auth.login.error.invalid"),
      );
      setIsSubmitting(false);
    }
  };

  // Mapea errores del backend a keys i18n cuando es posible.
  const emailErrorKey = fieldErrors.email?.[0];
  const passwordErrorKey = fieldErrors.password?.[0];

  return (
    <div className="flex w-full max-w-md flex-col gap-6 text-white">
      <header className="flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <img
            src="/images/stores/panda-estampados/brand/logo.webp"
            alt="Panda Estampados"
            className="h-10 w-10 rounded-md bg-white object-contain p-1"
          />
          <span className="text-2xl font-bold tracking-tight">
            {t("auth.brand.name")}
          </span>
        </div>
        <LanguageSwitcher />
      </header>

      <h1 className="text-base font-medium text-slate-300">
        {tab === "login"
          ? t("auth.login.subtitle")
          : locale === "es"
            ? "Crea tu cuenta para empezar."
            : "Create your account to get started."}
      </h1>

      <div
        role="tablist"
        className="relative grid grid-cols-2 items-center overflow-hidden rounded-full border text-sm font-semibold"
        style={{ borderColor: ACCENT }}
      >
        <span
          aria-hidden="true"
          className="pointer-events-none absolute top-0 bottom-0 w-1/2 rounded-full transition-transform duration-300 ease-out"
          style={{
            backgroundColor: ACCENT,
            transform: tab === "register" ? "translateX(100%)" : "translateX(0%)",
          }}
        />
        <button
          type="button"
          role="tab"
          aria-selected={tab === "login"}
          onClick={() => {
            setTab("login");
          }}
          className="relative z-10 px-4 py-2.5 transition-colors duration-300 focus:outline-none"
          style={{ color: tab === "login" ? "#fff" : ACCENT }}
        >
          {t("auth.login.tab.signin")}
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={tab === "register"}
          onClick={() => {
            setTab("register");
          }}
          className="relative z-10 px-4 py-2.5 transition-colors duration-300 focus:outline-none"
          style={{ color: tab === "register" ? "#fff" : ACCENT }}
          disabled
          title={t("auth.login.tab.signup.disabledHint")}
        >
          {t("auth.login.tab.signup")}
        </button>
      </div>

      <form
        onSubmit={handleSubmit}
        className="flex flex-col gap-3"
        noValidate
        aria-label={t("auth.login.title")}
      >
        {errorMessage !== null && (
          <div
            role="alert"
            className="rounded-md border border-red-400 bg-red-950/40 px-3 py-2 text-sm text-red-200"
          >
            {errorMessage}
          </div>
        )}

        <div className="flex flex-col gap-1">
          <input
            id="email"
            name="email"
            type="email"
            autoComplete="email"
            required
            placeholder={t("auth.login.email.placeholder")}
            value={email}
            onChange={(event) => {
              setEmail(event.target.value);
            }}
            className="w-full rounded-md border border-white/10 bg-white/5 px-4 py-3 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-white/30 focus:bg-white/10"
          />
          {emailErrorKey !== undefined && (
            <p className="px-1 text-xs text-red-300">{emailErrorKey}</p>
          )}
        </div>

        <div className="flex flex-col gap-1">
          <div className="relative">
            <input
              id="password"
              name="password"
              type={showPassword ? "text" : "password"}
              autoComplete="current-password"
              required
              minLength={8}
              placeholder={t("auth.login.password.placeholder")}
              value={password}
              onChange={(event) => {
                setPassword(event.target.value);
              }}
              className="w-full rounded-md border border-white/10 bg-white/5 px-4 py-3 pr-11 text-sm text-white placeholder:text-slate-400 outline-none transition focus:border-white/30 focus:bg-white/10"
            />
            <button
              type="button"
              onClick={() => {
                setShowPassword(!showPassword);
              }}
              aria-label={
                showPassword
                  ? t("auth.login.password.toggle.hide")
                  : t("auth.login.password.toggle.show")
              }
              className="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 transition hover:text-white"
            >
              {showPassword ? (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M3 3l18 18" />
                  <path d="M10.477 10.485a3 3 0 004.038 4.038" />
                  <path d="M9.88 5.084A10.477 10.477 0 0112 5c6 0 10 7 10 7a13.16 13.16 0 01-1.67 2.32" />
                  <path d="M6.61 6.61A13.526 13.526 0 002 12s4 7 10 7a10.46 10.46 0 005.39-1.39" />
                </svg>
              ) : (
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
              )}
            </button>
          </div>
          {passwordErrorKey !== undefined && (
            <p className="px-1 text-xs text-red-300">{passwordErrorKey}</p>
          )}
        </div>

        <button
          type="submit"
          disabled={isSubmitting}
          className="mt-2 rounded-md px-4 py-3 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60"
          style={{
            backgroundColor: isSubmitting ? ACCENT_HOVER : ACCENT,
          }}
          onMouseEnter={(event) => {
            event.currentTarget.style.backgroundColor = ACCENT_HOVER;
          }}
          onMouseLeave={(event) => {
            event.currentTarget.style.backgroundColor = ACCENT;
          }}
        >
          {isSubmitting ? t("auth.login.submit.loading") : t("auth.login.submit.idle")}
        </button>
      </form>

      <div className="flex items-center gap-3 py-1">
        <div className="h-px flex-1 bg-white/15" />
        <span className="text-xs text-slate-400">{t("auth.login.divider")}</span>
        <div className="h-px flex-1 bg-white/15" />
      </div>

      <div className="flex flex-col gap-2">
        <SocialButton provider="apple">{t("auth.login.social.apple")}</SocialButton>
        <SocialButton provider="facebook">{t("auth.login.social.facebook")}</SocialButton>
        <SocialButton provider="google">{t("auth.login.social.google")}</SocialButton>
      </div>

      <div className="mt-2 flex flex-col items-center gap-3 pt-2 text-xs text-slate-300">
        <a
          href="mailto:support@panda-estampados.test"
          className="font-semibold transition hover:opacity-80"
          style={{ color: ACCENT }}
        >
          {t("auth.login.help")}
        </a>
        <p className="text-center text-slate-400">
          {t("auth.login.termsPrefix")}{" "}
          <a href="#" className="underline transition hover:text-white">
            {t("auth.login.termsLink")}
          </a>
          .
        </p>
      </div>
    </div>
  );
}

type Provider = "apple" | "facebook" | "google";

function SocialButton({
  provider,
  children,
}: {
  provider: Provider;
  children: string;
}): ReactElement {
  return (
    <button
      type="button"
      data-provider={provider}
      className="flex w-full items-center justify-center gap-3 rounded-full bg-white/5 px-4 py-2.5 text-sm font-medium text-white ring-1 ring-inset ring-white/10 transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/30"
    >
      <SocialIcon provider={provider} />
      <span>{children}</span>
    </button>
  );
}

function SocialIcon({ provider }: { provider: Provider }): ReactElement {
  if (provider === "apple") {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M16.365 1.43c0 1.14-.457 2.246-1.222 3.022-.81.823-2.13 1.466-3.241 1.382-.143-1.097.413-2.246 1.2-3.07.815-.85 2.196-1.504 3.263-1.334zM21.5 17.16c-.563 1.297-.83 1.876-1.553 3.024-1.005 1.594-2.421 3.586-4.18 3.605-1.567.02-1.97-1.02-4.097-1.014-2.128.006-2.564 1.036-4.13 1.016-1.76-.02-3.106-1.81-4.112-3.405C.781 16.34-.193 11.7 1.86 8.49c1.46-2.286 3.766-3.62 5.91-3.62 2.179 0 3.546 1.196 5.353 1.196 1.755 0 2.823-1.197 5.342-1.197 1.913 0 3.948 1.04 5.41 2.83-4.755 2.6-3.982 9.41-2.376 9.46z" />
      </svg>
    );
  }

  if (provider === "facebook") {
    return (
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <path d="M22 12a10 10 0 10-11.56 9.88v-6.99H7.9V12h2.54v2.8h2.54V12h2.54c0-2.8-1.6-4.34-4.13-4.34a4.32 4.32 0 00-1.41.23v2.55a2.71 2.71 0 011.4-.27c1.39 0 2.4.69 2.4 2.05v.51h-2.54v2.42h2.54v6.99A10 10 0 0022 12z" />
      </svg>
    );
  }

  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4" />
      <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.66-2.26 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0012 23z" fill="#34A853" />
      <path d="M5.84 14.1A6.6 6.6 0 015.5 12c0-.73.12-1.44.34-2.1V7.06H2.18A11 11 0 001 12c0 1.78.43 3.46 1.18 4.94l3.66-2.84z" fill="#FBBC05" />
      <path d="M12 5.38c1.62 0 3.06.56 4.21 1.65l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 002.18 7.06l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z" fill="#EA4335" />
    </svg>
  );
}