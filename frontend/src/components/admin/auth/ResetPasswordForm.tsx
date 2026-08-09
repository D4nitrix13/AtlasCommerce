import { useState, type CSSProperties, type ReactElement } from "react";
import { Eye, EyeOff } from "lucide-react";
import { apiFetch } from "../../../lib/api";
import { useAuth } from "../../../stores/auth";
import { useTranslations } from "../../../i18n";
import type { LoginResponse, ValidationErrors } from "../../../types/auth";
import { DEFAULT_BRAND, type StoreBrand } from "../../../config/stores";

/**
 * Form para Step 2 del reset de password: código + nueva contraseña.
 *
 * El email llega como query param (?email=...) desde la página step 1.
 * Llama a POST /auth/reset-password. En éxito hace setSession() y
 * redirige al dashboard (auto-login).
 *
 * Multi-tenant: usa la tienda activa resuelta por el middleware del
 * backend (no necesita pasar store explícito).
 */

const PASSWORD_MIN_LENGTH = 12;
const CODE_LENGTH = 6;

type ResetPasswordFormProps = {
  brand?: StoreBrand;
  email: string;
  callbackPath?: string;
  locale?: string;
};

export default function ResetPasswordForm({
  brand = DEFAULT_BRAND,
  email,
  callbackPath = "/admin/dashboard",
  locale = "es",
}: ResetPasswordFormProps): ReactElement {
  const { t } = useTranslations();
  const { setSession } = useAuth();

  const accent = brand.accentColor;
  const accentHover = brand.accentHoverColor;
  const palette = brand.loginTheme;

  const [code, setCode] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<ValidationErrors>({});

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

  const validateCode = (value: string): string | null => {
    if (value.trim() === "") {
      return t("auth.passwordReset.error.codeEmpty");
    }
    if (value.length !== CODE_LENGTH) {
      return t("auth.passwordReset.error.codeInvalid");
    }
    return null;
  };

  const isStrongPassword = (value: string): boolean => {
    if (value.length < PASSWORD_MIN_LENGTH) return false;
    if (!/[A-Z]/.test(value)) return false;
    if (!/[a-z]/.test(value)) return false;
    if (!/[0-9]/.test(value)) return false;
    return true;
  };

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setErrorMessage(null);
    setFieldErrors({});

    const codeError = validateCode(code);
    if (codeError !== null) {
      setErrorMessage(codeError);
      return;
    }

    if (password === "") {
      setErrorMessage(t("auth.passwordReset.error.passwordEmpty"));
      return;
    }
    if (!isStrongPassword(password)) {
      setErrorMessage(t("auth.passwordReset.error.passwordWeak"));
      return;
    }
    if (password !== confirmPassword) {
      setErrorMessage(t("auth.passwordReset.error.passwordMismatch"));
      return;
    }

    setIsSubmitting(true);
    try {
      const response = await apiFetch<LoginResponse>("/auth/reset-password", {
        method: "POST",
        body: {
          email,
          code: code.trim(),
          password,
          password_confirmation: confirmPassword,
          device_name: "web-admin",
        },
      });
      setSession(response);
      setSuccessMessage(t("auth.passwordReset.success"));
      // Redirigir al destino después de mostrar el mensaje.
      window.setTimeout(() => {
        window.location.href = callbackPath;
      }, 1200);
    } catch (error_) {
      const apiError = error_ as {
        message?: string;
        errors?: ValidationErrors;
      };
      if (apiError.errors !== null && apiError.errors !== undefined) {
        setFieldErrors(apiError.errors);
      }
      const codeFieldError = apiError.errors?.code?.[0];
      setErrorMessage(
        codeFieldError ?? apiError.message ?? t("auth.passwordReset.error.codeInvalid"),
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div
      className="flex w-full max-w-md flex-col gap-5 text-[var(--login-text)]"
      style={themeVars}
    >
      <header className="flex items-center gap-3">
        <img
          src={brand.logoPath}
          alt={brand.name}
          className="h-10 w-10 rounded-md bg-white object-contain p-1"
        />
        <span className="text-xl font-bold italic tracking-tight">
          {brand.name}
        </span>
      </header>

      <h1 className="text-2xl font-bold tracking-tight">
        {t("auth.passwordReset.title")}
      </h1>
      <p className="text-sm text-[var(--login-text-muted)]">
        {t("auth.passwordReset.bodyCode")}
      </p>

      {errorMessage !== null && (
        <div
          role="alert"
          className="rounded-md border px-3 py-2 text-sm"
          style={{
            borderColor: "var(--login-danger)",
            backgroundColor: "var(--login-danger-bg)",
            color: "var(--login-danger-text)",
          }}
        >
          {errorMessage}
        </div>
      )}

      {successMessage !== null && (
        <div
          role="status"
          className="rounded-md border border-green-500 bg-green-50 px-3 py-2 text-sm text-green-700"
        >
          {successMessage}
        </div>
      )}

      <form
        onSubmit={handleSubmit}
        className="flex flex-col gap-4"
        noValidate
        aria-label={t("auth.passwordReset.title")}
      >
        <div className="flex flex-col gap-1">
          <label
            htmlFor="code"
            className="text-xs font-medium text-[var(--login-text-muted)]"
          >
            {t("auth.passwordReset.code.label")}
          </label>
          <input
            id="code"
            name="code"
            type="text"
            inputMode="numeric"
            autoComplete="one-time-code"
            required
            maxLength={CODE_LENGTH}
            pattern="[0-9]{6}"
            placeholder={t("auth.passwordReset.code.placeholder")}
            value={code}
            onChange={(event) => {
              setCode(event.target.value.replace(/\D/g, "").slice(0, CODE_LENGTH));
            }}
            className="w-full rounded-md border-2 bg-[var(--login-input-bg)] px-4 py-3 font-mono text-base tracking-[0.5em] text-[var(--login-text)] placeholder:text-[var(--login-text-subtle)] outline-none transition focus:bg-[var(--login-input-bg-focus)]"
            style={{ borderColor: "var(--login-border)" }}
            onFocus={(event) => {
              event.currentTarget.style.borderColor = "var(--login-border-strong)";
            }}
            onBlur={(event) => {
              event.currentTarget.style.borderColor = "var(--login-border)";
            }}
          />
        </div>

        <div className="flex flex-col gap-1">
          <label
            htmlFor="password"
            className="text-xs font-medium text-[var(--login-text-muted)]"
          >
            {t("auth.passwordReset.newPassword.label")}
          </label>
          <div className="relative">
            <input
              id="password"
              name="password"
              type={showPassword ? "text" : "password"}
              autoComplete="new-password"
              required
              minLength={PASSWORD_MIN_LENGTH}
              value={password}
              onChange={(event) => {
                setPassword(event.target.value);
              }}
              className="w-full rounded-md border-2 bg-[var(--login-input-bg)] px-4 py-3 pr-12 text-base text-[var(--login-text)] placeholder:text-[var(--login-text-subtle)] outline-none transition focus:bg-[var(--login-input-bg-focus)]"
              style={{ borderColor: "var(--login-border)" }}
              onFocus={(event) => {
                event.currentTarget.style.borderColor = "var(--login-border-strong)";
              }}
              onBlur={(event) => {
                event.currentTarget.style.borderColor = "var(--login-border)";
              }}
            />
            <button
              type="button"
              onClick={() => {
                setShowPassword(!showPassword);
              }}
              aria-label={
                showPassword
                  ? t("auth.passwordReset.password.hide")
                  : t("auth.passwordReset.password.show")
              }
              className="absolute inset-y-0 right-0 flex items-center px-4 text-[var(--login-text-muted)] transition hover:text-[var(--login-text)]"
            >
              {showPassword ? (
                <EyeOff size={18} strokeWidth={1.75} />
              ) : (
                <Eye size={18} strokeWidth={1.75} />
              )}
            </button>
          </div>
        </div>

        <div className="flex flex-col gap-1">
          <label
            htmlFor="confirmPassword"
            className="text-xs font-medium text-[var(--login-text-muted)]"
          >
            {t("auth.passwordReset.confirmPassword.label")}
          </label>
          <input
            id="confirmPassword"
            name="confirmPassword"
            type={showPassword ? "text" : "password"}
            autoComplete="new-password"
            required
            minLength={PASSWORD_MIN_LENGTH}
            value={confirmPassword}
            onChange={(event) => {
              setConfirmPassword(event.target.value);
            }}
            className="w-full rounded-md border-2 bg-[var(--login-input-bg)] px-4 py-3 text-base text-[var(--login-text)] placeholder:text-[var(--login-text-subtle)] outline-none transition focus:bg-[var(--login-input-bg-focus)]"
            style={{ borderColor: "var(--login-border)" }}
            onFocus={(event) => {
              event.currentTarget.style.borderColor = "var(--login-border-strong)";
            }}
            onBlur={(event) => {
              event.currentTarget.style.borderColor = "var(--login-border)";
            }}
          />
        </div>

        <label className="flex cursor-pointer items-center gap-2 text-xs text-[var(--login-text-muted)]">
          <span className="relative inline-block shrink-0">
            <input
              type="checkbox"
              checked={showPassword}
              onChange={(event) => {
                setShowPassword(event.target.checked);
              }}
              className="peer sr-only"
            />
            <span
              aria-hidden="true"
              className="block h-5 w-9 rounded-full border transition peer-checked:border-[var(--login-border-strong)] peer-checked:bg-[var(--login-border-strong)]"
              style={{
                borderColor: "var(--login-border)",
                backgroundColor: "var(--login-surface)",
              }}
            />
            <span
              aria-hidden="true"
              className="absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-[var(--login-text)] shadow transition peer-checked:translate-x-4"
            />
          </span>
          <span>
            {showPassword
              ? t("auth.passwordReset.password.hide")
              : t("auth.passwordReset.password.show")}
          </span>
        </label>

        <p className="text-xs leading-relaxed text-[var(--login-text-subtle)]">
          {t("auth.passwordReset.passwordHint")}
        </p>

        <button
          type="submit"
          disabled={isSubmitting || successMessage !== null}
          className="mt-2 rounded-md px-4 py-4 text-lg font-semibold tracking-wide text-white transition disabled:cursor-not-allowed disabled:opacity-60"
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
            : t("auth.passwordReset.submit")}
        </button>
      </form>

      <p className="text-center text-xs text-[var(--login-text-muted)]">
        <a
          href={`/${locale}/admin/auth/forgot-password`}
          className="font-semibold transition hover:opacity-80"
          style={{ color: "var(--login-text)" }}
        >
          ← {t("common.back")}
        </a>
      </p>
    </div>
  );
}