import { useState, type CSSProperties, type ReactElement } from "react";
import { apiFetch } from "../../../lib/api";
import { useTranslations } from "../../../i18n";
import { DEFAULT_BRAND, type StoreBrand } from "../../../config/stores";

/**
 * Form para Step 1 del reset de password: ingresar email.
 *
 * Llama a POST /auth/forgot-password (siempre 200 OK con mensaje
 * genérico). En éxito, redirige a la página de Step 2 con el email
 * como query param (deep-linkable, sobrevive a refresh).
 *
 * Multi-tenant: usa la tienda activa resuelta por el middleware del
 * backend (no necesita pasar store explícito).
 */

type ForgotPasswordFormProps = {
  brand?: StoreBrand;
  callbackPath?: string;
  locale?: string;
};

export default function ForgotPasswordForm({
  brand = DEFAULT_BRAND,
  callbackPath = "/admin/auth/forgot-password",
  locale = "es",
}: ForgotPasswordFormProps): ReactElement {
  const { t } = useTranslations();

  const accent = brand.accentColor;
  const accentHover = brand.accentHoverColor;
  const palette = brand.loginTheme;

  const [email, setEmail] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const themeVars: CSSProperties = {
    "--login-bg": palette.bg,
    "--login-surface": palette.surface,
    "--login-surface-hover": palette.surfaceHover,
    "--login-border": palette.border,
    "--login-border-strong": palette.borderStrong,
    "--login-text": palette.text,
    "--login-text-muted": palette.textMuted,
    "--login-text-subtle": palette.textSubtle,
    "--login-input-bg": palette.inputBg,
    "--login-input-bg-focus": palette.inputBgFocus,
    "--login-danger": palette.danger,
    "--login-danger-text": palette.dangerText,
    "--login-danger-bg": palette.dangerBg,
    "--login-divider": palette.divider,
  } as CSSProperties;

  const validateEmail = (value: string): string | null => {
    if (value.trim() === "") {
      return t("auth.passwordReset.error.emailEmpty");
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      return t("auth.passwordReset.error.emailInvalid");
    }
    return null;
  };

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setErrorMessage(null);
    const error = validateEmail(email);
    if (error !== null) {
      setErrorMessage(error);
      return;
    }
    setIsSubmitting(true);
    try {
      await apiFetch<{ message: string; expires_minutes: number; code?: string }>(
        "/auth/forgot-password",
        { method: "POST", body: { email: email.trim() } },
      );
      const params = new URLSearchParams({
        email: email.trim(),
        callback: callbackPath,
      });
      window.location.href = `/${locale}/admin/auth/reset-password?${params.toString()}`;
    } catch (error_) {
      const apiError = error_ as { message?: string };
      setErrorMessage(apiError.message ?? t("auth.passwordReset.error.generic"));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div
      className="flex w-full max-w-xl flex-col gap-6 text-[var(--login-text)]"
      style={themeVars}
    >
      <div className="space-y-2 text-center lg:text-left">
        <h1 className="text-3xl font-bold tracking-tight md:text-4xl">
          {t("auth.passwordReset.title")}
        </h1>
        <p className="text-base leading-relaxed text-[var(--login-text-muted)]">
          {t("auth.passwordReset.bodyEmail")}
        </p>
      </div>

      {errorMessage !== null && (
        <div
          role="alert"
          className="rounded-lg border px-4 py-3 text-sm"
          style={{
            borderColor: "var(--login-danger)",
            backgroundColor: "var(--login-danger-bg)",
            color: "var(--login-danger-text)",
          }}
        >
          {errorMessage}
        </div>
      )}

      <form
        onSubmit={handleSubmit}
        className="flex flex-col gap-5"
        noValidate
        aria-label={t("auth.passwordReset.title")}
      >
        <div className="flex flex-col gap-2">
          <label
            htmlFor="email"
            className="text-sm font-medium text-[var(--login-text)]"
          >
            {t("auth.passwordReset.email.label")}
          </label>
          <input
            id="email"
            name="email"
            type="email"
            inputMode="email"
            autoComplete="email"
            required
            value={email}
            onChange={(event) => {
              setEmail(event.target.value);
            }}
            className="w-full rounded-lg border-2 bg-[var(--login-input-bg)] px-5 py-4 text-lg text-[var(--login-text)] placeholder:text-[var(--login-text-subtle)] outline-none transition focus:bg-[var(--login-input-bg-focus)]"
            style={{ borderColor: "var(--login-border)" }}
            onFocus={(event) => {
              event.currentTarget.style.borderColor = "var(--login-border-strong)";
            }}
            onBlur={(event) => {
              event.currentTarget.style.borderColor = "var(--login-border)";
            }}
          />
        </div>

        <button
          type="submit"
          disabled={isSubmitting}
          className="mt-2 rounded-lg px-5 py-4 text-lg font-bold tracking-wide text-white transition disabled:cursor-not-allowed disabled:opacity-60"
          style={{
            backgroundColor: isSubmitting ? accentHover : accent,
          }}
          onMouseEnter={(event) => {
            event.currentTarget.style.backgroundColor = accentHover;
          }}
          onMouseLeave={(event) => {
            event.currentTarget.style.backgroundColor = isSubmitting
              ? accentHover
              : accent;
          }}
        >
          {isSubmitting
            ? t("common.loading")
            : t("auth.passwordReset.email.submit")}
        </button>
      </form>

      <p className="text-center text-sm text-[var(--login-text-muted)]">
        <a
          href={`/${locale}/admin/auth/login`}
          className="font-semibold transition hover:opacity-80"
          style={{ color: "var(--login-text)" }}
        >
          ← {t("common.back")}
        </a>
      </p>
    </div>
  );
}