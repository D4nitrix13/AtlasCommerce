import { useMemo, useState, type ReactElement } from 'react';
import { Switch } from '../../ui/Switch';

/**
 * Tipo genérico de preferencias: matriz category × channel.
 *
 * El backend real debería tiparlo igual en `notification-prefs.ts`
 * para evitar drift entre cliente y servidor.
 */
export type NotificationChannel = 'email' | 'sms' | 'push';
export type NotificationCategory = 'orders' | 'promotions' | 'newsletter' | 'security';

export type PrefsMatrix = Record<NotificationCategory, Record<NotificationChannel, boolean>>;

interface CategoryLabels {
  label: string;
  hint: string;
}

interface NotificationsPreferencesTableProps {
  /** Estado inicial (desde `account-mock.ts`). */
  initial: PrefsMatrix;
  /** Labels para canales (column header). */
  channels: Record<NotificationChannel, string>;
  /** Labels para categorías (row header) — incluye `hint` para accesibilidad. */
  categories: Record<NotificationCategory, CategoryLabels>;
  /** Texto del botón guardar. */
  saveLabel: string;
  /** Texto durante el submit. */
  savingLabel: string;
  /** Texto de éxito post-save. */
  savedLabel: string;
  /** Texto de error post-save. */
  errorLabel: string;
  /** Hint mostrado cuando una celda está bloqueada (categoría security). */
  disabledHint: string;
}

type Status = { kind: 'idle' } | { kind: 'saving' } | { kind: 'saved' } | { kind: 'error' };

const CATEGORIES: readonly NotificationCategory[] = [
  'orders',
  'promotions',
  'newsletter',
  'security',
];

const CHANNELS: readonly NotificationChannel[] = ['email', 'sms', 'push'];

/**
 * Categorías donde los toggles son fijos (compliance/legal).
 * `security` siempre es true en todos los canales — el backend fuerza
 * esto. La UI lo refleja como bloqueado.
 */
const LOCKED_CATEGORIES: ReadonlySet<NotificationCategory> = new Set<NotificationCategory>([
  'security',
]);

const SIMULATED_SAVE_DELAY_MS = 500;

/**
 * Compara dos matrices de preferencias (orden estable, sin importar
 * el orden de las keys).
 */
function matricesEqual(a: PrefsMatrix, b: PrefsMatrix): boolean {
  for (const category of CATEGORIES) {
    for (const channel of CHANNELS) {
      if (a[category][channel] !== b[category][channel]) {
        return false;
      }
    }
  }
  return true;
}

export default function NotificationsPreferencesTable({
  initial,
  channels,
  categories,
  saveLabel,
  savingLabel,
  savedLabel,
  errorLabel,
  disabledHint,
}: NotificationsPreferencesTableProps): ReactElement {
  const [matrix, setMatrix] = useState<PrefsMatrix>(initial);
  const [status, setStatus] = useState<Status>({ kind: 'idle' });

  // Detect dirty state para deshabilitar el botón cuando no hay cambios.
  const isDirty = useMemo(() => !matricesEqual(matrix, initial), [matrix, initial]);

  const isSaving = status.kind === 'saving';

  function handleToggle(
    category: NotificationCategory,
    channel: NotificationChannel,
    next: boolean
  ): void {
    setMatrix((prev) => ({
      ...prev,
      [category]: {
        ...prev[category],
        [channel]: next,
      },
    }));
    // Cualquier edit invalida un mensaje de éxito/error previo.
    if (status.kind === 'saved' || status.kind === 'error') {
      setStatus({ kind: 'idle' });
    }
  }

  function handleSubmit(event: React.FormEvent<HTMLFormElement>): void {
    event.preventDefault();
    if (!isDirty || isSaving) return;

    setStatus({ kind: 'saving' });
    // Stub: en F7+ esto llama `apiFetch(API_ENDPOINTS.account.notifications, ...)`.
    window.setTimeout(() => {
      setStatus({ kind: 'saved' });
    }, SIMULATED_SAVE_DELAY_MS);
  }

  const submitLabel = isSaving ? savingLabel : saveLabel;

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5" noValidate>
      <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table className="w-full text-sm">
          <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
            <tr>
              <th scope="col" className="px-4 py-3 text-left font-semibold">
                {/* Etiqueta vacía para la esquina. */}
              </th>
              {CHANNELS.map((channel) => (
                <th key={channel} scope="col" className="px-4 py-3 text-center font-semibold">
                  {channels[channel]}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {CATEGORIES.map((category) => {
              const locked = LOCKED_CATEGORIES.has(category);
              const categoryLabel = categories[category];
              return (
                <tr key={category} className="align-middle">
                  <th scope="row" className="px-4 py-4 text-left">
                    <div className="flex flex-col gap-0.5">
                      <span className="text-sm font-semibold text-slate-900">
                        {categoryLabel.label}
                      </span>
                      <span className="text-xs text-slate-500">{categoryLabel.hint}</span>
                    </div>
                  </th>
                  {CHANNELS.map((channel) => {
                    const cellChecked = matrix[category][channel];
                    const switchId = `pref-${category}-${channel}`;
                    const ariaLabel = `${categoryLabel.label} — ${channels[channel]}`;
                    return (
                      <td key={channel} className="px-4 py-4 text-center">
                        <div className="flex justify-center">
                          <Switch
                            id={switchId}
                            checked={cellChecked}
                            onChange={(next) => {
                              handleToggle(category, channel, next);
                            }}
                            disabled={locked}
                            ariaLabel={ariaLabel}
                            name={`${category}[${channel}]`}
                          />
                        </div>
                      </td>
                    );
                  })}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      <p className="text-xs text-slate-500" role="note">
        {disabledHint}
      </p>

      {status.kind === 'saved' && (
        <p role="status" className="text-sm font-medium text-emerald-600">
          {savedLabel}
        </p>
      )}
      {status.kind === 'error' && (
        <p role="alert" className="text-sm font-medium text-red-600">
          {errorLabel}
        </p>
      )}

      <div className="flex items-center justify-end">
        <button
          type="submit"
          disabled={!isDirty || isSaving}
          aria-busy={isSaving}
          className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[var(--primary,#7c3aed)] px-5 text-sm font-semibold text-white transition hover:bg-[var(--primary-hover,#6d28d9)] disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#7c3aed)] focus-visible:ring-offset-2"
        >
          {submitLabel}
        </button>
      </div>
    </form>
  );
}
