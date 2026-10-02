import { useMemo, useState, type ReactElement } from 'react';
import { Check } from 'lucide-react';
import { apiFetch } from '../../../lib/api';
import { useAuth } from '../../../stores/auth';
import { useTranslations } from '../../../i18n';
import { PasswordInput } from '../../../components/ui/PasswordInput';
import { PasswordStrengthMeter } from '../../../components/ui/PasswordStrengthMeter';
import { SecurityChecklist } from '../../../components/ui/SecurityChecklist';
import type { LoginResponse, ValidationErrors } from '../../../types/auth';

/**
 * Form para Step 2 del reset de password.
 *
 * Body del POST /auth/reset-password:
 *   { email, code, password, password_confirmation, device_name }
 *
 * El `code` se lee de la URL (`?code=...`) — cuando el usuario llega al
 * link del email ya tiene el código prellenado. Si no está, está
 * como input pequeño para que lo pegue manualmente.
 *
 * Visual: los acentos (`focus:border`, `focus:ring`, submit button,
 * success border) consumen `var(--brand-accent, #2D9D8E)` y
 * `var(--brand-accent-hover, #258777)`, publicados como CSS custom
 * properties por el `AuthShell` que envuelve la pantalla. El fallback
 * hex mantiene render correcto si el componente se monta fuera del
 * shell (por ejemplo en tests).
 *
 * Compone sub-componentes extraídos (sdd PR 2 · Tasks 2.12-2.15):
 *   - PasswordInput — input + toggle show/hide
 *   - PasswordStrengthMeter — 4 barras + label
 *   - SecurityChecklist — requisitos visuales
 */

const PASSWORD_MIN_LENGTH = 12;
const CODE_LENGTH = 6;

interface ResetPasswordFormProps {
  email: string;
  codeFromUrl?: string;
  callbackPath?: string;
  locale?: string;
}

type PasswordStrength = 0 | 1 | 2 | 3 | 4;
const STRENGTH_LABELS = ['', 'Débil', 'Aceptable', 'Buena', 'Excelente'] as const;

export default function ResetPasswordForm({
  email,
  codeFromUrl = '',
  callbackPath = '/admin/dashboard',
  locale = 'es',
}: ResetPasswordFormProps): ReactElement {
  const { t } = useTranslations();
  const { setSession } = useAuth();

  const [code, setCode] = useState(codeFromUrl);
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
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
    [password]
  );

  const passedChecks = Object.values(checks).filter(Boolean).length;
  const strength: PasswordStrength = Math.min(4, Math.max(0, passedChecks)) as PasswordStrength;

  const passwordsMatch = password !== '' && confirmPassword !== '' && password === confirmPassword;

  const handleSubmit = async (event: React.FormEvent): Promise<void> => {
    event.preventDefault();
    setErrorMessage(null);

    if (code === '') {
      setErrorMessage(t('auth.passwordReset.error.codeEmpty'));
      return;
    }
    if (code.length !== CODE_LENGTH) {
      setErrorMessage(t('auth.passwordReset.error.codeInvalid'));
      return;
    }
    if (password === '' || !checks.minLength || !checks.hasUpper || !checks.hasLower) {
      setErrorMessage(t('auth.passwordReset.error.passwordWeak'));
      return;
    }
    if (!passwordsMatch) {
      setErrorMessage(t('auth.passwordReset.error.passwordMismatch'));
      return;
    }

    setIsSubmitting(true);
    try {
      const response = await apiFetch<LoginResponse>('/auth/reset-password', {
        method: 'POST',
        body: {
          email,
          code: code.trim(),
          password,
          password_confirmation: confirmPassword,
          device_name: 'web-admin',
        },
      });
      setSession(response);
      setSuccessMessage(t('auth.passwordReset.success'));
      window.setTimeout(() => {
        const params = new URLSearchParams({
          callback: callbackPath,
        });
        window.location.href = `/${locale}/admin/auth/reset-success?${params.toString()}`;
      }, 600);
    } catch (error_) {
      const apiError = error_ as {
        message?: string;
        errors?: ValidationErrors | null;
      };
      const codeFieldError = apiError.errors?.code?.[0];
      setErrorMessage(
        codeFieldError ?? apiError.message ?? t('auth.passwordReset.error.codeInvalid')
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
        aria-label={t('auth.passwordReset.title')}
      >
        {/* Code: hidden cuando viene del URL, visible si no */}
        {codeFromUrl !== '' ? (
          <input type="hidden" name="code" value={code} autoComplete="one-time-code" />
        ) : (
          <CodeInput
            code={code}
            onChange={setCode}
            label={t('auth.passwordReset.code.label')}
            placeholder={t('auth.passwordReset.code.placeholder')}
            helpText={t('auth.passwordReset.step2.codeHelp')}
          />
        )}

        {/* Nueva contraseña */}
        <div>
          <label htmlFor="password" className="block text-sm font-medium text-slate-700 mb-1.5">
            {t('auth.passwordReset.newPassword.label')}
          </label>
          <PasswordInput
            id="password"
            value={password}
            onChange={setPassword}
            showLabel={t('auth.passwordReset.password.show')}
            hideLabel={t('auth.passwordReset.password.hide')}
            required
            minLength={PASSWORD_MIN_LENGTH}
          />
          <PasswordStrengthMeter
            score={strength}
            label={STRENGTH_LABELS[strength]}
            labelPrefix={t('auth.passwordReset.strengthLabel')}
          />
        </div>

        {/* Confirmar contraseña */}
        <div>
          <label
            htmlFor="confirmPassword"
            className="block text-sm font-medium text-slate-700 mb-1.5"
          >
            {t('auth.passwordReset.confirmPassword.label')}
          </label>
          <PasswordInput
            id="confirmPassword"
            value={confirmPassword}
            onChange={setConfirmPassword}
            showLabel={t('auth.passwordReset.password.show')}
            hideLabel={t('auth.passwordReset.password.hide')}
            required
            minLength={PASSWORD_MIN_LENGTH}
            className={passwordsMatch ? 'border-[var(--brand-accent,#2D9D8E)]' : 'border-slate-200'}
          />
          {passwordsMatch && (
            <p className="mt-1.5 flex items-center gap-1 text-xs text-[var(--brand-accent,#2D9D8E)]">
              <Check size={14} strokeWidth={2.5} />
              {t('auth.passwordReset.passwordsMatch')}
            </p>
          )}
        </div>

        {/* Checklist de seguridad */}
        <SecurityChecklist
          title={t('auth.passwordReset.securityTipsTitle')}
          items={[
            { met: checks.minLength, label: t('auth.passwordReset.tip.minLength') },
            {
              met: checks.hasUpper && checks.hasLower,
              label: t('auth.passwordReset.tip.upperLower'),
            },
            { met: checks.hasNumber, label: t('auth.passwordReset.tip.number') },
            { met: false, label: t('auth.passwordReset.tip.symbol') },
          ]}
        />

        <SubmitButton
          isSubmitting={isSubmitting}
          hasSuccess={successMessage !== null}
          submittingLabel={t('auth.passwordReset.submitting')}
          idleLabel={t('auth.passwordReset.submit')}
        />
      </form>
    </div>
  );
}

/**
 * Input del código de 6 dígitos con `inputMode="numeric"` y filtro
 * de caracteres no numéricos. Inline aquí (no extraído) porque solo se
 * usa en este flujo.
 */
function CodeInput({
  code,
  onChange,
  label,
  placeholder,
  helpText,
}: {
  code: string;
  onChange: (value: string) => void;
  label: string;
  placeholder: string;
  helpText: string;
}): ReactElement {
  return (
    <div>
      <label htmlFor="code" className="block text-sm font-medium text-slate-700 mb-1.5">
        {label}
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
        placeholder={placeholder}
        value={code}
        onChange={(event) => {
          onChange(event.target.value.replace(/\D/g, '').slice(0, CODE_LENGTH));
        }}
        className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-center font-mono text-base tracking-[0.5em] text-slate-900 placeholder:text-slate-400 transition focus:bg-white focus:border-[var(--brand-accent,#2D9D8E)] focus:outline-none focus:ring-4 focus:ring-[var(--brand-accent-ring,#2D9D8E40)]"
      />
      <p className="text-xs text-slate-400 mt-1.5">{helpText}</p>
    </div>
  );
}

/**
 * Botón submit con spinner inline (sin extracción porque es trivial).
 */
function SubmitButton({
  isSubmitting,
  hasSuccess,
  submittingLabel,
  idleLabel,
}: {
  isSubmitting: boolean;
  hasSuccess: boolean;
  submittingLabel: string;
  idleLabel: string;
}): ReactElement {
  return (
    <button
      type="submit"
      disabled={isSubmitting || hasSuccess}
      className="w-full bg-[var(--brand-accent,#2D9D8E)] hover:bg-[var(--brand-accent-hover,#258777)] disabled:bg-[var(--brand-accent-hover,#258777)] disabled:opacity-70 text-white font-semibold py-2.5 rounded-lg transition focus:outline-none focus:ring-4 focus:ring-[var(--brand-accent-ring,#2D9D8E40)] flex items-center justify-center gap-2"
    >
      {isSubmitting ? (
        <>
          <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
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
          {submittingLabel}
        </>
      ) : (
        <>
          <Check size={16} strokeWidth={2.5} />
          {idleLabel}
        </>
      )}
    </button>
  );
}
