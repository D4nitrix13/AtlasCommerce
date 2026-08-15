import { useMemo, useState, type ReactElement } from "react";
import { Eye, EyeOff, Check, Circle } from "lucide-react";
import { apiFetch } from "../../../lib/api";
import { AUTH_ACCENT } from "../../../lib/auth-accent";
import { useAuth } from "../../../stores/auth";
import { useTranslations } from "../../../i18n";
import type { LoginResponse, ValidationErrors } from "../../../types/auth";

/**
 * Form para Step 2 del reset de password.
 *
 * Body del POST /auth/reset-password:
 *   { email, code, password, password_confirmation, device_name }
 *
 * El `code` se lee de la URL (`?code=...`) — cuando el usuario llega al
 * link del email ya tiene el código prellenado. Si no está, se muestra
 * como input pequeño para que lo pegue manualmente.
 *
 * Visual: usa la MISMA paleta auth universal que `LoginForm` y
 * `ForgotPasswordForm` (ver `lib/auth-accent.ts`). Los acentos se
 * aplican vía clases Tailwind arbitrarias (`#2D9D8E` family) — no
 * mediante `brand.accentColor`, que difiere por tienda y rompe la
 * consistencia visual entre páginas de auth.
 */

const PASSWORD_MIN_LENGTH = 12;
const CODE_LENGTH = 6;

type ResetPasswordFormProps = {
  email: string;
  codeFromUrl?: string;
  callbackPath?: string;
  locale?: string;
};

type PasswordStrength = 0 | 1 | 2 | 3 | 4;
const STRENGTH_LABELS = ["", "Débil", "Aceptable", "Buena", "Excelente"] as const;

export default function ResetPasswordForm({
  email,
  codeFromUrl = "",
  callbackPath = "/admin/dashboard",
  locale = "es",
}: ResetPasswordFormProps): ReactElement {
  const { t } = useTranslations();
  const { setSession } = useAuth();

  const accent = AUTH_ACCENT.primary;

  const [code, setCode] = useState(codeFromUrl);
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  const checks = useMemo(
    () => ({
      minLength: password.length >= PASSWORD_MIN_LENGTH,
      hasUpper: /[A-Z]/.test(password),
      hasLower: /[a-z]/.test(password),
      hasNumber: /[0-9]/.test(password),
    }),
    [password],
  );

  const passedChecks = Object.values(checks).filter(Boolean).length;
  const strength: PasswordStrength = Math.min(
    4,
    Math.max(0, passedChecks),
  ) as PasswordStrength;

  const passwordsMatch =
    password !== "" && confirmPassword !== "" && password === confirmPassword;

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setErrorMessage(null);

    if (code === "") {
      setErrorMessage(t("auth.passwordReset.error.codeEmpty"));
      return;
    }
    if (code.length !== CODE_LENGTH) {
      setErrorMessage(t("auth.passwordReset.error.codeInvalid"));
      return;
    }
    if (password === "" || !checks.minLength || !checks.hasUpper || !checks.hasLower) {
      setErrorMessage(t("auth.passwordReset.error.passwordWeak"));
      return;
    }
    if (!passwordsMatch) {
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
      window.setTimeout(() => {
        const params = new URLSearchParams({
          callback: callbackPath,
        });
        window.location.href =
          `/${locale}/admin/auth/reset-success?${params.toString()}`;
      }, 600);
    } catch (error_) {
      const apiError = error_ as {
        message?: string;
        errors?: ValidationErrors | null;
      };
      const codeFieldError = apiError.errors?.code?.[0];
      setErrorMessage(
        codeFieldError ??
          apiError.message ??
          t("auth.passwordReset.error.codeInvalid"),
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex w-full flex-col gap-4">
      {errorMessage !== null && (
        <div
          role="alert"
          className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
          {errorMessage}
        </div>
      )}

      {successMessage !== null && (
        <div
          role="status"
          className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"
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
        {/* Code: hidden cuando viene del URL, visible si no */}
        {codeFromUrl !== "" ? (
          <input
            type="hidden"
            name="code"
            value={code}
            autoComplete="one-time-code"
          />
        ) : (
          <div>
            <label
              htmlFor="code"
              className="block text-sm font-medium text-slate-700 mb-1.5"
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
                setCode(
                  event.target.value.replace(/\D/g, "").slice(0, CODE_LENGTH),
                );
              }}
              className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-center font-mono text-base tracking-[0.5em] text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[#2D9D8E] focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
            />
            <p className="text-xs text-slate-400 mt-1.5">
              {t("auth.passwordReset.step2.codeHelp")}
            </p>
          </div>
        )}

        {/* Nueva contraseña */}
        <div>
          <label
            htmlFor="password"
            className="block text-sm font-medium text-slate-700 mb-1.5"
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
              className="w-full px-4 py-2.5 pr-10 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[#2D9D8E] focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
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
              className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none"
            >
              {showPassword ? (
                <EyeOff size={20} strokeWidth={2} />
              ) : (
                <Eye size={20} strokeWidth={2} />
              )}
            </button>
          </div>

          {/* Strength meter: 4 bars */}
          <div className="mt-2 flex gap-1.5">
            {[1, 2, 3, 4].map((bar) => (
              <div
                key={bar}
                className="h-1 flex-1 rounded-full transition-colors"
                style={{
                  backgroundColor:
                    strength >= bar ? accent : "#E5E5E5",
                }}
              />
            ))}
          </div>
          {password !== "" && (
            <p className="mt-1.5 text-xs text-slate-400">
              {t("auth.passwordReset.strengthLabel")}{" "}
              <span className="font-semibold text-[#2D9D8E]">
                {STRENGTH_LABELS[strength]}
              </span>
            </p>
          )}
        </div>

        {/* Confirmar contraseña */}
        <div>
          <label
            htmlFor="confirmPassword"
            className="block text-sm font-medium text-slate-700 mb-1.5"
          >
            {t("auth.passwordReset.confirmPassword.label")}
          </label>
          <div className="relative">
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
              className={`w-full px-4 py-2.5 pr-10 bg-slate-50 border rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[#2D9D8E] focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25 ${
                passwordsMatch ? "border-[#2D9D8E]" : "border-slate-200"
              }`}
            />
            {passwordsMatch && (
              <div
                aria-hidden="true"
                className="absolute right-3 top-1/2 -translate-y-1/2 text-[#2D9D8E]"
              >
                <Check size={18} strokeWidth={2.5} />
              </div>
            )}
          </div>
          {passwordsMatch && (
            <p className="mt-1.5 flex items-center gap-1 text-xs text-[#2D9D8E]">
              <Check size={14} strokeWidth={2.5} />
              {t("auth.passwordReset.passwordsMatch")}
            </p>
          )}
        </div>

        {/* Checklist de seguridad */}
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-4">
          <p className="mb-2 text-xs font-semibold text-slate-700">
            {t("auth.passwordReset.securityTipsTitle")}
          </p>
          <ul className="space-y-1.5 text-xs text-slate-500">
            <CheckItem
              met={checks.minLength}
              label={t("auth.passwordReset.tip.minLength")}
            />
            <CheckItem
              met={checks.hasUpper && checks.hasLower}
              label={t("auth.passwordReset.tip.upperLower")}
            />
            <CheckItem
              met={checks.hasNumber}
              label={t("auth.passwordReset.tip.number")}
            />
            <CheckItem
              met={false}
              label={t("auth.passwordReset.tip.symbol")}
            />
          </ul>
        </div>

        <button
          type="submit"
          disabled={isSubmitting || successMessage !== null}
          className="w-full bg-[#2D9D8E] hover:bg-[#258777] disabled:bg-[#258777] disabled:opacity-70 text-white font-semibold py-2.5 rounded-lg transition focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25 flex items-center justify-center gap-2"
        >
          {isSubmitting ? (
            <>
              <svg
                className="w-4 h-4 animate-spin"
                fill="none"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <circle
                  className="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  strokeWidth="4"
                ></circle>
                <path
                  className="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                ></path>
              </svg>
              {t("auth.passwordReset.submitting")}
            </>
          ) : (
            <>
              <Check size={16} strokeWidth={2.5} />
              {t("auth.passwordReset.submit")}
            </>
          )}
        </button>
      </form>
    </div>
  );
}

function CheckItem({
  met,
  label,
}: {
  met: boolean;
  label: string;
}): ReactElement {
  return (
    <li className="flex items-center gap-2">
      {met ? (
        <Check
          size={14}
          strokeWidth={2.5}
          className="text-[#2D9D8E]"
          aria-hidden="true"
        />
      ) : (
        <Circle
          size={14}
          strokeWidth={2}
          className="text-slate-400"
          aria-hidden="true"
        />
      )}
      <span className={met ? "text-[#2D9D8E]" : "text-slate-400"}>
        {label}
      </span>
    </li>
  );
}