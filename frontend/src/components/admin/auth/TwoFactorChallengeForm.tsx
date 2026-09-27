import { useState, type ReactElement } from 'react';
import { useAuth } from '../../../stores/auth';
import { useTranslations } from '../../../i18n';
import { stripLocaleFromPath, withLocale } from '../../../lib/path-helpers';

/**
 * Form de challenge 2FA (TOTP) tras login.
 *
 * El caller (la página `/[locale]/admin/auth/2fa`) monta este
 * componente con `challengeId` (viene de `?token=` en el URL) y
 * `redirectTo` (donde ir tras el verify exitoso; típicamente el
 * `?redirect=` del login).
 *
 * Flujo:
 *   1. Usuario tipea código de 6 dígitos.
 *   2. `verifyTotpChallenge({challengeId, code})` resuelve con
 *      `{ kind: 'success', token, user }` o `{ kind: '2fa_required' }`.
 *   3. En success, `setSession(...)` y `window.location.href = redirectTo`.
 *   4. En error, alert traducido; el challengeId NO se pierde (el
 *      componente sigue mostrando el form para reintentar).
 */
interface TwoFactorChallengeFormProps {
  challengeId: number;
  redirectTo: string;
  codeLength?: number;
  submittingLabel?: string;
  recoveryLinkHref?: string;
}

const DEFAULT_CODE_LENGTH = 6;
const EXPIRED_PATTERNS = [/expirado/i, /expir/i, /invalid or expired/i];

function isExpiredMessage(message: string): boolean {
  return EXPIRED_PATTERNS.some((pattern) => pattern.test(message));
}

function resolveRecoveryHref(override: string | undefined): string {
  if (override !== undefined) {
    return override;
  }

  if (typeof window === 'undefined') {
    return '/auth/login';
  }

  return withLocale('/auth/login', stripLocaleFromPath(window.location.pathname).locale);
}
export default function TwoFactorChallengeForm(props: TwoFactorChallengeFormProps): ReactElement {
  const { verifyTotpChallenge, setSession } = useAuth();
  const { t } = useTranslations();
  const state = useTwoFactorState();
  const handleSubmit = buildSubmitHandler({
    challengeId: props.challengeId,
    redirectTo: props.redirectTo,
    state,
    verifyTotpChallenge,
    setSession,
    t,
  });

  return (
    <TwoFactorFormBody
      ariaLabel={t('auth.twoFactor.heading')}
      onSubmit={handleSubmit}
      errorMessage={state.errorMessage}
      code={state.code}
      codeLength={props.codeLength ?? DEFAULT_CODE_LENGTH}
      isSubmitting={state.isSubmitting}
      isExpired={state.isExpired}
      submitLabel={
        state.isSubmitting
          ? (props.submittingLabel ?? t('auth.twoFactor.submitting'))
          : t('auth.twoFactor.submit')
      }
      codeLabel={t('auth.twoFactor.code.label')}
      codePlaceholder={t('auth.twoFactor.code.placeholder')}
      codeHelp={t('auth.twoFactor.code.help')}
      recoveryHref={resolveRecoveryHref(props.recoveryLinkHref)}
      recoveryLabel={t('auth.twoFactor.recoveryLink')}
      onCodeChange={state.setCode}
    />
  );
}

interface TwoFactorFormBodyProps {
  ariaLabel: string;
  onSubmit: (event: React.FormEvent) => Promise<void>;
  errorMessage: string | null;
  code: string;
  codeLength: number;
  isSubmitting: boolean;
  isExpired: boolean;
  submitLabel: string;
  codeLabel: string;
  codePlaceholder: string;
  codeHelp: string;
  recoveryHref: string;
  recoveryLabel: string;
  onCodeChange: (next: string) => void;
}

function TwoFactorFormBody(props: TwoFactorFormBodyProps): ReactElement {
  return (
    <form onSubmit={props.onSubmit} className="space-y-5" noValidate aria-label={props.ariaLabel}>
      {props.errorMessage !== null ? <ErrorBanner message={props.errorMessage} /> : null}

      <CodeField
        code={props.code}
        codeLength={props.codeLength}
        onChange={props.onCodeChange}
        label={props.codeLabel}
        placeholder={props.codePlaceholder}
        help={props.codeHelp}
      />

      <SubmitButton
        disabled={props.isSubmitting || props.code.length !== props.codeLength}
        label={props.submitLabel}
      />

      {props.isExpired ? (
        <RecoveryLink href={props.recoveryHref}>{props.recoveryLabel}</RecoveryLink>
      ) : null}
    </form>
  );
}

interface TwoFactorState {
  code: string;
  setCode: (next: string) => void;
  isSubmitting: boolean;
  setIsSubmitting: (next: boolean) => void;
  errorMessage: string | null;
  setErrorMessage: (next: string | null) => void;
  isExpired: boolean;
  setIsExpired: (next: boolean) => void;
}

function useTwoFactorState(): TwoFactorState {
  const [code, setCode] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [isExpired, setIsExpired] = useState(false);

  return {
    code,
    setCode,
    isSubmitting,
    setIsSubmitting,
    errorMessage,
    setErrorMessage,
    isExpired,
    setIsExpired,
  };
}

interface SubmitHandlerDeps {
  challengeId: number;
  redirectTo: string;
  state: TwoFactorState;
  verifyTotpChallenge: ReturnType<typeof useAuth>['verifyTotpChallenge'];
  setSession: ReturnType<typeof useAuth>['setSession'];
  t: ReturnType<typeof useTranslations>['t'];
}

function buildSubmitHandler(deps: SubmitHandlerDeps): (event: React.FormEvent) => Promise<void> {
  return async (event) => {
    event.preventDefault();
    deps.state.setErrorMessage(null);
    deps.state.setIsExpired(false);
    deps.state.setIsSubmitting(true);

    try {
      const result = await deps.verifyTotpChallenge({
        challengeId: deps.challengeId,
        code: deps.state.code,
      });

      if (result.kind === '2fa_required') {
        deps.state.setErrorMessage(deps.t('auth.twoFactor.error.expired'));
        deps.state.setIsExpired(true);
        deps.state.setIsSubmitting(false);
        return;
      }

      deps.setSession(result);
      window.location.href = deps.redirectTo;
    } catch (error) {
      const apiError = error as { status?: number; message?: string };
      const message = apiError.message ?? deps.t('auth.twoFactor.error.invalidCode');
      deps.state.setErrorMessage(message);
      deps.state.setIsExpired(isExpiredMessage(message));
      deps.state.setIsSubmitting(false);
    }
  };
}

function ErrorBanner({ message }: { message: string }): ReactElement {
  return (
    <div
      role="alert"
      className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {message}
    </div>
  );
}

interface CodeFieldProps {
  code: string;
  codeLength: number;
  onChange: (next: string) => void;
  label: string;
  placeholder: string;
  help: string;
}

function CodeField({
  code,
  codeLength,
  onChange,
  label,
  placeholder,
  help,
}: CodeFieldProps): ReactElement {
  return (
    <div>
      <label htmlFor="totp-code" className="block text-sm font-medium text-slate-700 mb-1.5">
        {label}
      </label>
      <input
        id="totp-code"
        name="code"
        type="text"
        inputMode="numeric"
        autoComplete="one-time-code"
        pattern={`[0-9]{${codeLength}}`}
        maxLength={codeLength}
        required
        placeholder={placeholder}
        value={code}
        onChange={(event) => {
          // Filtrar no-numéricos para evitar pérdida de foco al pegar.
          const digits = event.target.value.replace(/\D/g, '');
          onChange(digits.slice(0, codeLength));
        }}
        className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-900 placeholder:text-slate-400 text-center tracking-[0.5em] font-mono text-lg transition focus:bg-white focus:border-[var(--brand-accent,#2D9D8E)] focus:outline-none focus:ring-4 focus:ring-[var(--brand-accent-ring,#2D9D8E40)]"
      />
      <p className="mt-2 text-xs text-slate-500">{help}</p>
    </div>
  );
}

function SubmitButton({ disabled, label }: { disabled: boolean; label: string }): ReactElement {
  return (
    <button
      type="submit"
      disabled={disabled}
      className="w-full bg-[var(--brand-accent,#2D9D8E)] hover:bg-[var(--brand-accent-hover,#258777)] disabled:bg-[var(--brand-accent-hover,#258777)] disabled:opacity-70 text-white font-semibold py-2.5 rounded-lg transition focus:outline-none focus:ring-4 focus:ring-[var(--brand-accent-ring,#2D9D8E40)]"
    >
      {label}
    </button>
  );
}

function RecoveryLink({ href, children }: { href: string; children: string }): ReactElement {
  return (
    <a
      href={href}
      data-auth-link="recovery-back"
      className="block text-center text-sm font-semibold text-[var(--brand-accent,#2D9D8E)] hover:text-[var(--brand-accent-hover,#258777)]"
    >
      {children}
    </a>
  );
}
