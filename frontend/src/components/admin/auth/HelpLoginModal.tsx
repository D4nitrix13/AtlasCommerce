import { useState, type CSSProperties, type ReactElement } from "react";
import { Eye, EyeOff, X } from "lucide-react";
import { apiFetch } from "../../../lib/api";
import { useAuth } from "../../../stores/auth";
import { useTranslations } from "../../../i18n";
import type { LoginResponse, ValidationErrors } from "../../../types/auth";
import type { StoreBrand } from "../../../config/stores";

/**
 * Modal "Ayuda para iniciar sesión" (Fender-style).
 *
 * Flujo de 2 pasos:
 *  - Step 1: el usuario ingresa su email → POST /auth/forgot-password
 *    (siempre 200 OK con mensaje genérico).
 *  - Step 2: el usuario ingresa código + nueva contraseña + confirmación
 *    → POST /auth/reset-password → auto-login.
 *
 * Multi-tenant: usa la tienda activa resuelta por el middleware del
 * backend (no necesita pasar store explícito).
 *
 * El modal usa los tokens del brand activo (loginTheme) para mantener
 * la convención de colores por tienda: fondo gradient basado en
 * accentColor/accentHoverColor, inputs con surface/border, CTA con
 * accentColor, errores con danger.
 */

type HelpLoginModalProps = {
  brand: StoreBrand;
  onClose: () => void;
};

type Step = "email" | "code";

const PASSWORD_MIN_LENGTH = 12;
const CODE_LENGTH = 6;

export default function HelpLoginModal({
  brand,
  onClose,
}: HelpLoginModalProps): ReactElement {
  const { t } = useTranslations();
  const { setSession } = useAuth();

  const [step, setStep] = useState<Step>("email");
  const [email, setEmail] = useState("");
  const [code, setCode] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<ValidationErrors>({});

  const accent = brand.accentColor;
  const accentHover = brand.accentHoverColor;
  const palette = brand.loginTheme;

  const reset = (): void => {
    setErrorMessage(null);
    setSuccessMessage(null);
    setFieldErrors({});
  };

  const close = (): void => {
    reset();
    onClose();
  };

  const validateEmail = (value: string): string | null => {
    if (value.trim() === "") {
      return t("auth.passwordReset.error.emailEmpty");
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      return t("auth.passwordReset.error.emailInvalid");
    }
    return null;
  };

  const handleEmailSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    reset();
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
      setStep("code");
    } catch (error_) {
      // La API siempre devuelve 200 OK con mensaje genérico. Si llega
      // un 4xx/5xx es por rate limiting o error de servidor.
      const apiError = error_ as { message?: string };
      setErrorMessage(apiError.message ?? t("auth.passwordReset.error.generic"));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleResetSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    reset();

    const codeError =
      code.trim() === ""
        ? t("auth.passwordReset.error.codeEmpty")
        : code.trim().length !== CODE_LENGTH
          ? t("auth.passwordReset.error.codeInvalid")
          : null;

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
          email: email.trim(),
          code: code.trim(),
          password,
          password_confirmation: confirmPassword,
          device_name: "web-admin",
        },
      });
      setSession(response);
      setSuccessMessage(t("auth.passwordReset.success"));
      // Cerrar modal después de un breve delay para que el usuario vea el mensaje.
      window.setTimeout(() => {
        close();
        // Redirigir al dashboard igual que el login normal.
        window.location.href = `${window.location.origin}/dashboard`;
      }, 1200);
    } catch (error_) {
      const apiError = error_ as { message?: string; errors?: ValidationErrors };
      if (apiError.errors !== null && apiError.errors !== undefined) {
        setFieldErrors(apiError.errors);
      }
      const codeFieldError = apiError.errors?.code?.[0];
      setErrorMessage(codeFieldError ?? apiError.message ?? t("auth.passwordReset.error.codeInvalid"));
    } finally {
      setIsSubmitting(false);
    }
  };

  const overlayStyle: CSSProperties = {
    backgroundColor: "rgba(0, 0, 0, 0.65)",
  };

  // Modal con gradient radial del brand (mismo lenguaje visual que el
  // aside del login) + backdrop con blur sutil.
  const modalStyle: CSSProperties = {
    background: `radial-gradient(ellipse 80% 60% at 30% 0%, ${accent}55 0%, transparent 70%), linear-gradient(160deg, ${accentHover} 0%, #0c0a09 100%)`,
    color: "#ffffff",
  };

  const inputStyle: CSSProperties = {
    backgroundColor: "rgba(0, 0, 0, 0.45)",
    borderColor: "rgba(255, 255, 255, 0.18)",
    color: "#ffffff",
  };

  const inputFocusStyle: CSSProperties = {
    borderColor: accent,
    backgroundColor: "rgba(0, 0, 0, 0.55)",
  };

  const inputStyleProps: CSSProperties = {
    ...inputStyle,
    "--focus-border": accent,
    "--focus-bg": "rgba(0, 0, 0, 0.55)",
  } as CSSProperties;

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="help-modal-title"
      className="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
      style={overlayStyle}
      onClick={(event) => {
        if (event.target === event.currentTarget) {
          close();
        }
      }}
    >
      <div
        className="relative w-full max-w-md rounded-xl p-8 shadow-2xl ring-1 ring-white/10"
        style={modalStyle}
        onClick={(event) => {
          event.stopPropagation();
        }}
      >
        <button
          type="button"
          onClick={close}
          aria-label={t("auth.passwordReset.close")}
          className="absolute right-4 top-4 text-white/70 transition hover:text-white"
        >
          <X size={20} strokeWidth={2} aria-hidden="true" />
        </button>

        <h2
          id="help-modal-title"
          className="mb-4 font-mono text-3xl font-bold leading-tight tracking-tight"
        >
          {t("auth.passwordReset.title")}
        </h2>

        {errorMessage !== null && (
          <div
            role="alert"
            className="mb-4 rounded-md border px-3 py-2 text-sm"
            style={{
              borderColor: palette.danger,
              backgroundColor: palette.dangerBg,
              color: palette.dangerText,
            }}
          >
            {errorMessage}
          </div>
        )}

        {successMessage !== null && (
          <div
            role="status"
            className="mb-4 rounded-md border px-3 py-2 text-sm"
            style={{
              borderColor: "rgba(34, 197, 94, 0.5)",
              backgroundColor: "rgba(34, 197, 94, 0.15)",
              color: "#bbf7d0",
            }}
          >
            {successMessage}
          </div>
        )}

        {step === "email" ? (
          <form onSubmit={handleEmailSubmit} className="flex flex-col gap-4" noValidate>
            <p className="text-sm leading-relaxed text-white/85">
              {t("auth.passwordReset.bodyEmail")}
            </p>

            <div className="flex flex-col gap-1">
              <label
                htmlFor="help-email"
                className="text-xs font-medium text-white/85"
              >
                {t("auth.passwordReset.email.label")}
              </label>
              <input
                id="help-email"
                type="email"
                inputMode="email"
                autoComplete="email"
                required
                value={email}
                onChange={(event) => {
                  setEmail(event.target.value);
                }}
                className="w-full rounded-md border-2 px-4 py-3 text-base outline-none transition"
                style={inputStyleProps}
                onFocus={(event) => {
                  Object.assign(event.currentTarget.style, inputFocusStyle);
                }}
                onBlur={(event) => {
                  Object.assign(event.currentTarget.style, inputStyle);
                }}
              />
              {fieldErrors.email?.[0] !== undefined && (
                <p className="px-1 text-xs" style={{ color: palette.dangerText }}>
                  {fieldErrors.email[0]}
                </p>
              )}
            </div>

            <button
              type="submit"
              disabled={isSubmitting}
              className="mt-2 rounded-md px-4 py-4 text-base font-semibold tracking-wide text-white transition disabled:cursor-not-allowed disabled:opacity-60"
              style={{
                backgroundColor: isSubmitting ? accentHover : palette.danger,
              }}
              onMouseEnter={(event) => {
                event.currentTarget.style.backgroundColor = "#b91c1c";
              }}
              onMouseLeave={(event) => {
                event.currentTarget.style.backgroundColor = palette.danger;
              }}
            >
              {t("auth.passwordReset.email.submit")}
            </button>
          </form>
        ) : (
          <form onSubmit={handleResetSubmit} className="flex flex-col gap-4" noValidate>
            <p className="text-sm leading-relaxed text-white/85">
              {t("auth.passwordReset.bodyCode")}
            </p>

            <div className="flex flex-col gap-1">
              <label
                htmlFor="help-code"
                className="text-xs font-medium text-white/85"
              >
                {t("auth.passwordReset.code.label")}
              </label>
              <input
                id="help-code"
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
                className="w-full rounded-md border-2 px-4 py-3 font-mono text-base tracking-[0.5em] outline-none transition"
                style={inputStyleProps}
                onFocus={(event) => {
                  Object.assign(event.currentTarget.style, inputFocusStyle);
                }}
                onBlur={(event) => {
                  Object.assign(event.currentTarget.style, inputStyle);
                }}
              />
            </div>

            <div className="flex flex-col gap-1">
              <label
                htmlFor="help-new-password"
                className="text-xs font-medium text-white/85"
              >
                {t("auth.passwordReset.newPassword.label")}
              </label>
              <div className="relative">
                <input
                  id="help-new-password"
                  type={showPassword ? "text" : "password"}
                  autoComplete="new-password"
                  required
                  minLength={PASSWORD_MIN_LENGTH}
                  value={password}
                  onChange={(event) => {
                    setPassword(event.target.value);
                  }}
                  className="w-full rounded-md border-2 px-4 py-3 pr-12 text-base outline-none transition"
                  style={inputStyleProps}
                  onFocus={(event) => {
                    Object.assign(event.currentTarget.style, inputFocusStyle);
                  }}
                  onBlur={(event) => {
                    Object.assign(event.currentTarget.style, inputStyle);
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
                  className="absolute inset-y-0 right-0 flex items-center px-4 text-white/70 transition hover:text-white"
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
                htmlFor="help-confirm-password"
                className="text-xs font-medium text-white/85"
              >
                {t("auth.passwordReset.confirmPassword.label")}
              </label>
              <input
                id="help-confirm-password"
                type={showPassword ? "text" : "password"}
                autoComplete="new-password"
                required
                minLength={PASSWORD_MIN_LENGTH}
                value={confirmPassword}
                onChange={(event) => {
                  setConfirmPassword(event.target.value);
                }}
                className="w-full rounded-md border-2 px-4 py-3 text-base outline-none transition"
                style={inputStyleProps}
                onFocus={(event) => {
                  Object.assign(event.currentTarget.style, inputFocusStyle);
                }}
                onBlur={(event) => {
                  Object.assign(event.currentTarget.style, inputStyle);
                }}
              />
            </div>

            <label className="flex cursor-pointer items-center gap-2 text-sm text-white/85">
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
                  className="block h-5 w-9 rounded-full border transition peer-checked:border-white/40 peer-checked:bg-white/30"
                  style={{
                    borderColor: "rgba(255, 255, 255, 0.35)",
                    backgroundColor: "rgba(255, 255, 255, 0.12)",
                  }}
                />
                <span
                  aria-hidden="true"
                  className="absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow transition peer-checked:translate-x-4"
                />
              </span>
              <span>
                {showPassword
                  ? t("auth.passwordReset.password.hide")
                  : t("auth.passwordReset.password.show")}
              </span>
            </label>

            <div className="flex items-start gap-2 text-xs leading-relaxed text-white/70">
              <span
                aria-hidden="true"
                className="mt-0.5 inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full border text-[10px] font-bold"
                style={{ borderColor: palette.danger, color: palette.danger }}
              >
                ×
              </span>
              <p>{t("auth.passwordReset.passwordHint")}</p>
            </div>

            <div className="flex items-center justify-end gap-3 pt-2">
              <button
                type="button"
                onClick={close}
                className="rounded-md px-4 py-2 text-sm font-semibold text-white/80 transition hover:text-white"
              >
                {t("auth.passwordReset.close")}
              </button>
              <button
                type="submit"
                disabled={isSubmitting}
                className="rounded-md px-6 py-3 text-sm font-bold text-white transition disabled:cursor-not-allowed disabled:opacity-60"
                style={{
                  backgroundColor: palette.danger,
                }}
                onMouseEnter={(event) => {
                  event.currentTarget.style.backgroundColor = "#b91c1c";
                }}
                onMouseLeave={(event) => {
                  event.currentTarget.style.backgroundColor = palette.danger;
                }}
              >
                {t("auth.passwordReset.submit")}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}

/**
 * Valida que la contraseña cumpla los requisitos mínimos:
 *  - Mínimo 12 caracteres.
 *  - Al menos una mayúscula.
 *  - Al menos una minúscula.
 *  - Al menos un número.
 */
function isStrongPassword(value: string): boolean {
  if (value.length < PASSWORD_MIN_LENGTH) return false;
  if (!/[A-Z]/.test(value)) return false;
  if (!/[a-z]/.test(value)) return false;
  if (!/[0-9]/.test(value)) return false;
  return true;
}