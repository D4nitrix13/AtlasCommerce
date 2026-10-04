import { useMemo, useState, type FormEvent, type ReactElement } from 'react';
import { PasswordInput } from '../../ui/PasswordInput';
import { PasswordStrengthMeter } from '../../ui/PasswordStrengthMeter';
import { SecurityChecklist } from '../../ui/SecurityChecklist';

/**
 * Form de cambio de contraseña del cliente (RF-CUST-044 / Slice 2).
 *
 * Sigue el patrón visual de `ResetPasswordForm` (Storyset-style): tres
 * campos con toggle show/hide, medidor de fuerza reactivo y checklist
 * de requisitos. La confirmación debe ser idéntica al nuevo password.
 *
 * En esta fase la página `security.astro` lo monta con `client:load`
 * y un `onSubmit` noop; cuando llegue `PATCH /api/account/password`
 * se cablea acá.
 *
 * El cálculo del strength lo hacemos en el cliente (zxcvbn-style
 * simplificado) — el backend re-valida siempre. Score 0-4:
 *   - 0: vacío
 *   - 1: solo minLength
 *   - 2: minLength + 1 categoría extra
 *   - 3: minLength + 2 categorías extra
 *   - 4: minLength + 3 categorías extra
 */
function calculateStrength(value: string): number {
  if (value.length === 0) return 0;
  let score = 0;
  if (value.length >= 12) score += 1;
  if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score += 1;
  if (/\d/.test(value)) score += 1;
  if (/[^A-Za-z0-9]/.test(value)) score += 1;
  return score;
}

interface ChecklistLabels {
  minLength: string;
  upperLower: string;
  number: string;
  symbol: string;
}

interface SecurityPasswordFormProps {
  /** i18n labels (todos vienen del padre via `getServerDictionary`). */
  currentLabel: string;
  newLabel: string;
  confirmLabel: string;
  showLabel: string;
  hideLabel: string;
  strengthLabel: string;
  weakLabel: string;
  fairLabel: string;
  goodLabel: string;
  strongLabel: string;
  checklistTitle: string;
  checklist: ChecklistLabels;
  submitLabel: string;
  submittingLabel: string;
  successLabel: string;
  errorEmptyLabel: string;
  errorMismatchLabel: string;
  errorWeakLabel: string;
  errorGenericLabel: string;
}

type Status =
  | { kind: 'idle' }
  | { kind: 'submitting' }
  | { kind: 'success' }
  | { kind: 'error'; key: 'empty' | 'mismatch' | 'weak' | 'generic' };

type StrengthKey = 'weak' | 'fair' | 'good' | 'strong';

const STRENGTH_LABEL_BY_SCORE: Record<number, StrengthKey | null> = {
  0: null,
  1: 'weak',
  2: 'fair',
  3: 'good',
  4: 'strong',
};

const MIN_LENGTH = 12;
const MIN_ACCEPTABLE_SCORE = 2;
const SIMULATED_SUBMIT_DELAY_MS = 400;

function resolveStrengthLabel(props: SecurityPasswordFormProps, score: number): string {
  const key = STRENGTH_LABEL_BY_SCORE[score] ?? null;
  if (key === null) return '';
  return props[`${key}Label` as const];
}

function resolveErrorMessage(props: SecurityPasswordFormProps, status: Status): string | null {
  if (status.kind !== 'error') return null;
  switch (status.key) {
    case 'empty':
      return props.errorEmptyLabel;
    case 'mismatch':
      return props.errorMismatchLabel;
    case 'weak':
      return props.errorWeakLabel;
    case 'generic':
      return props.errorGenericLabel;
  }
}

export default function SecurityPasswordForm(props: SecurityPasswordFormProps): ReactElement {
  const [currentPassword, setCurrentPassword] = useState<string>('');
  const [newPassword, setNewPassword] = useState<string>('');
  const [confirmPassword, setConfirmPassword] = useState<string>('');
  const [status, setStatus] = useState<Status>({ kind: 'idle' });

  const strengthScore = calculateStrength(newPassword);
  const strengthLabel = resolveStrengthLabel(props, strengthScore);

  // El checklist solo cambia con `newPassword` (los labels son
  // estáticos vía i18n).
  const checklistItems = useMemo(
    () => [
      { label: props.checklist.minLength, met: newPassword.length >= MIN_LENGTH },
      {
        label: props.checklist.upperLower,
        met: /[a-z]/.test(newPassword) && /[A-Z]/.test(newPassword),
      },
      { label: props.checklist.number, met: /\d/.test(newPassword) },
      { label: props.checklist.symbol, met: /[^A-Za-z0-9]/.test(newPassword) },
    ],
    [newPassword, props.checklist]
  );

  function handleSubmit(event: FormEvent<HTMLFormElement>): void {
    event.preventDefault();
    if (currentPassword === '' || newPassword === '' || confirmPassword === '') {
      setStatus({ kind: 'error', key: 'empty' });
      return;
    }
    if (newPassword !== confirmPassword) {
      setStatus({ kind: 'error', key: 'mismatch' });
      return;
    }
    if (strengthScore < MIN_ACCEPTABLE_SCORE) {
      setStatus({ kind: 'error', key: 'weak' });
      return;
    }
    setStatus({ kind: 'submitting' });
    // Stub: en F7+ esto llama `apiFetch(API_ENDPOINTS.account.password, ...)`.
    window.setTimeout(() => {
      setStatus({ kind: 'success' });
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
    }, SIMULATED_SUBMIT_DELAY_MS);
  }

  const errorMessage = resolveErrorMessage(props, status);
  const isSubmitting = status.kind === 'submitting';
  const isSuccess = status.kind === 'success';

  return (
    <form
      onSubmit={handleSubmit}
      className="flex flex-col gap-5"
      aria-describedby={errorMessage !== null ? 'password-form-error' : undefined}
      noValidate
    >
      <FieldRow id="current-password" label={props.currentLabel}>
        <PasswordInput
          id="current-password"
          value={currentPassword}
          onChange={setCurrentPassword}
          autoComplete="current-password"
          required
          showLabel={props.showLabel}
          hideLabel={props.hideLabel}
        />
      </FieldRow>

      <FieldRow id="new-password" label={props.newLabel}>
        <PasswordInput
          id="new-password"
          value={newPassword}
          onChange={setNewPassword}
          autoComplete="new-password"
          required
          showLabel={props.showLabel}
          hideLabel={props.hideLabel}
        />
        <PasswordStrengthMeter
          score={strengthScore}
          label={strengthLabel}
          labelPrefix={props.strengthLabel}
        />
      </FieldRow>

      <FieldRow id="confirm-password" label={props.confirmLabel}>
        <PasswordInput
          id="confirm-password"
          value={confirmPassword}
          onChange={setConfirmPassword}
          autoComplete="new-password"
          required
          showLabel={props.showLabel}
          hideLabel={props.hideLabel}
        />
      </FieldRow>

      <SecurityChecklist title={props.checklistTitle} items={checklistItems} />

      {errorMessage !== null && (
        <p id="password-form-error" role="alert" className="text-sm text-red-600">
          {errorMessage}
        </p>
      )}
      {isSuccess && (
        <p role="status" className="text-sm font-medium text-emerald-600">
          {props.successLabel}
        </p>
      )}

      <div className="flex items-center justify-end gap-3">
        <button
          type="submit"
          disabled={isSubmitting}
          className="inline-flex h-10 items-center justify-center rounded-lg bg-[var(--primary,#7c3aed)] px-5 text-sm font-semibold text-white transition-colors hover:bg-[var(--primary-hover,#6d28d9)] disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#7c3aed)] focus-visible:ring-offset-2"
        >
          {isSubmitting ? props.submittingLabel : props.submitLabel}
        </button>
      </div>
    </form>
  );
}

interface FieldRowProps {
  id: string;
  label: string;
  children: ReactElement | ReactElement[];
}

function FieldRow({ id, label, children }: FieldRowProps): ReactElement {
  return (
    <div className="flex flex-col gap-2">
      <label htmlFor={id} className="text-sm font-medium text-slate-700">
        {label}
      </label>
      {children}
    </div>
  );
}
