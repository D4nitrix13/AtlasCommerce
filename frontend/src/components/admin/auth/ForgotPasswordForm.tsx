import { useState, type ReactElement } from "react";
import { apiFetch } from "../../../lib/api";
import { useTranslations } from "../../../i18n";

/**
 * Step 1 del reset de password: ingresar email.
 *
 * Llama a POST /auth/forgot-password (siempre 200 OK con mensaje
 * genérico). En éxito, redirige a la página de Step 2 con el email
 * como query param (deep-linkable, sobrevive a refresh).
 */

type ForgotPasswordFormProps = {
  callbackPath?: string;
  locale?: string;
  emailPlaceholder?: string;
  emailLabel?: string;
  helperText?: string;
  submitLabel?: string;
  submittingLabel?: string;
};

export default function ForgotPasswordForm({
  callbackPath = "/admin/dashboard",
  locale = "es",
  emailPlaceholder = "tu@correo.com",
  emailLabel,
  helperText,
  submitLabel,
  submittingLabel,
}: ForgotPasswordFormProps): ReactElement {
  const { t } = useTranslations();

  const resolvedEmailLabel = emailLabel ?? t("auth.forgot.shell.emailLabel");
  const resolvedHelperText = helperText ?? t("auth.forgot.shell.helper");
  const resolvedSubmitLabel = submitLabel ?? t("auth.forgot.shell.submit");
  const resolvedSubmittingLabel = submittingLabel ?? t("auth.forgot.shell.submitLoading");

  const [email, setEmail] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

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
    <form onSubmit={handleSubmit} className="space-y-5" noValidate aria-label={t("auth.passwordReset.title")}>
      {errorMessage !== null && (
        <div
          role="alert"
          className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
        >
          {errorMessage}
        </div>
      )}

      <div>
        <label
          htmlFor="email"
          className="block text-sm font-medium text-slate-700 mb-1.5"
        >
          {resolvedEmailLabel}
        </label>
        <input
          id="email"
          name="email"
          type="email"
          inputMode="email"
          autoComplete="email"
          required
          placeholder={emailPlaceholder}
          value={email}
          onChange={(event) => {
            setEmail(event.target.value);
          }}
          className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[#2D9D8E] focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25"
        />
        <p className="text-xs text-slate-400 mt-1.5">{resolvedHelperText}</p>
      </div>

      <button
        type="submit"
        disabled={isSubmitting}
        className="w-full bg-[#2D9D8E] hover:bg-[#258777] disabled:bg-[#258777] disabled:opacity-70 text-white font-semibold py-2.5 rounded-lg transition focus:outline-none focus:ring-4 focus:ring-[#2D9D8E]/25 flex items-center justify-center gap-2"
      >
        {isSubmitting ? (
          <>
            <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            {resolvedSubmittingLabel}
          </>
        ) : (
          <>
            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
            {resolvedSubmitLabel}
          </>
        )}
      </button>
    </form>
  );
}