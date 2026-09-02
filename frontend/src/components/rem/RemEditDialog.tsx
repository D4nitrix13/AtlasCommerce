/**
 * RemEditDialog.tsx
 *
 * Modal dialog shell for create / edit CRUD (REQ-18, REQ-19). Uses the
 * native `<dialog>` element so we get Escape, focus trap, and backdrop
 * click for free (no overlay/backdrop code).
 *
 * Two-phase lifecycle (D2 → D4):
 *   - D2: renders the dialog chrome + Cancel/Submit buttons + slot
 *     for the actual form body.
 *   - D4 (this version): plugs the 3 Zod-backed forms (RemRfForm /
 *     RemRnfForm / RemStakeholderForm) and wires POST/PUT dispatch.
 *
 * The dialog calls `fetch` directly on submit (REQ-19#2 #3 #5):
 *   - POST `/api/rem/<kind>` (create) or PUT `/api/rem/<kind>/<uid>` (edit)
 *   - On 201/200: call `onSuccess(uid)`, close the dialog
 *   - On 400: stay open; the form's Zod errors surface
 *     (`formState.errors` from the form component)
 *   - On 500: stay open + show `errorMessage`
 */

import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type ReactElement,
} from 'react';

import { useTranslations } from '../../i18n';
import { RemRfForm } from './forms/RemRfForm';
import { RemRnfForm } from './forms/RemRnfForm';
import { RemStakeholderForm } from './forms/RemStakeholderForm';
import type {
  RfCreateInput,
  RfUpdateInput,
} from '../../lib/rem/rf.schema';
import type {
  RnfCreateInput,
  RnfUpdateInput,
} from '../../lib/rem/rnf.schema';
import type {
  StakeholderCreateInput,
  StakeholderUpdateInput,
} from '../../lib/rem/stakeholder.schema';

export type RemEditMode = 'create' | 'edit';
export type RemEditKind = 'rf' | 'rnf' | 'stakeholder';

export interface RemEditDialogProps {
  /** Open/closed state — parent owns the visibility. */
  open: boolean;
  /** Create vs edit mode (drives title + submit label). */
  mode: RemEditMode;
  /** Item type — drives the form slot. */
  kind: RemEditKind;
  /** Existing UID (edit mode only). */
  uid?: string;
  /** Default values for edit mode. */
  defaultValues?: Record<string, unknown>;
  /** Cancel/Escape/backdrop close. */
  onClose: () => void;
  /**
   * Fired after a successful submit (201 or 200). The parent
   * (RemSidebar) refreshes the tree region + auto-selects the new
   * leaf (REQ-19#2 + DD5).
   */
  onSuccess?: (uid: string) => void;
}

/**
 * The modal dialog. Uses the native `<dialog>` element via
 * `dialog.showModal()` / `dialog.close()` so we get Escape and
 * backdrop-click dismissal for free (REQ-19#4 — Cancel/Escape no fetch).
 */
export function RemEditDialog({
  open,
  mode,
  kind,
  uid,
  defaultValues,
  onClose,
  onSuccess,
}: RemEditDialogProps): ReactElement | null {
  const { t } = useTranslations();
  const dialogRef = useRef<HTMLDialogElement | null>(null);
  const [busy, setBusy] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // Open / close the native `<dialog>` based on the `open` prop.
  useEffect(() => {
    const dialog = dialogRef.current;
    if (dialog === null) return;
    if (open && !dialog.open) {
      try {
        dialog.showModal();
      } catch {
        dialog.setAttribute('open', '');
      }
    } else if (!open && dialog.open) {
      dialog.close();
    }
  }, [open]);

  // Bridge the native dialog's `close` event (Escape / backdrop) back
  // to the parent so React state stays the source of truth.
  useEffect(() => {
    const dialog = dialogRef.current;
    if (dialog === null) return;
    const onNativeClose = (): void => {
      onClose();
    };
    dialog.addEventListener('close', onNativeClose);
    return () => {
      dialog.removeEventListener('close', onNativeClose);
    };
  }, [onClose]);

  // Reset error when the dialog re-opens.
  useEffect(() => {
    if (open) setErrorMessage(null);
  }, [open]);

  /**
   * Submit handler. The form components call this from their
   * `onSubmit(values)` callback with the parsed Zod payload.
   */
  const submit = useCallback(
    async (
      values:
        | RfCreateInput
        | RfUpdateInput
        | RnfCreateInput
        | RnfUpdateInput
        | StakeholderCreateInput
        | StakeholderUpdateInput
    ) => {
      setBusy(true);
      setErrorMessage(null);
      const endpointKind = kind === 'stakeholder' ? 'stakeholder' : kind;
      const url =
        mode === 'create'
          ? `/api/rem/${endpointKind}`
          : `/api/rem/${endpointKind}/${uid ?? ''}`;
      const method = mode === 'create' ? 'POST' : 'PUT';
      try {
        const res = await fetch(url, {
          method,
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(values),
        });
        if (res.status === 201 || res.status === 200) {
          const body = (await res.json()) as { ok: boolean; uid?: string };
          // Server already invalidated its loader cache via
          // `clearLoaderCache()` in the POST/PUT/DELETE handlers; the
          // client doesn't reach into server-side module state — it
          // just re-fetches the sidebar HTML on `onSuccess`.
          const newUid = body.uid ?? uid ?? '';
          if (newUid !== '') {
            onSuccess?.(newUid);
          }
          onClose();
          return;
        }
        if (res.status === 400) {
          // Surface the validation summary via the dialog alert; the
          // form's own errors are still visible inline.
          const body = (await res.json().catch(() => ({}))) as { error?: string };
          setErrorMessage(body.error ?? t('rem.crud.write.error'));
          return;
        }
        // 500 / unknown
        setErrorMessage(t('rem.crud.write.error'));
      } catch {
        setErrorMessage(t('rem.crud.write.error'));
      } finally {
        setBusy(false);
      }
    },
    [kind, mode, uid, onClose, onSuccess, t]
  );

  const handleShellSubmit = useCallback(() => {
    // The form components own their onSubmit (handleSubmit of
    // react-hook-form). We no longer wrap the dialog in an outer
    // `<form>` because nested forms are invalid HTML — the inner
    // form's submit handler captures the event.
    // (Kept as an unused no-op for future Enter-key handling.)
  }, []);

  if (!open) {
    return null;
  }

  const titleKey =
    mode === 'create' ? 'rem.dialog.create.heading' : 'rem.dialog.edit.heading';

  return (
    <dialog
      ref={dialogRef}
      data-rem-edit-dialog
      data-rem-edit-mode={mode}
      data-rem-edit-kind={kind}
      aria-labelledby="rem-edit-dialog-title"
      className="rem-edit-dialog w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-8 text-left shadow-2xl backdrop:bg-slate-900/60 backdrop:backdrop-blur-sm"
    >
      <div className="rem-edit-dialog__inner flex flex-col gap-5">
        <header className="rem-edit-dialog__header -mx-8 -mt-8 mb-2 flex items-center justify-between rounded-t-2xl border-b border-slate-200 bg-gradient-to-r from-[#2D9D8E]/10 via-white to-white px-8 py-4">
          <div className="flex items-center gap-3">
            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-[#2D9D8E]/15 text-lg font-bold text-[#1E6F62]">
              {kind === 'rf' ? '⚡' : kind === 'rnf' ? '⚙' : '👤'}
            </span>
            <div>
              <h2
                id="rem-edit-dialog-title"
                className="text-xl font-bold tracking-tight text-slate-900"
              >
                {t(
                  mode === 'create'
                    ? kind === 'stakeholder'
                      ? 'rem.dialog.create.stakeholder'
                      : 'rem.dialog.create.heading'
                    : kind === 'stakeholder'
                      ? 'rem.dialog.edit.stakeholder'
                      : 'rem.dialog.edit.heading',
                )}
              </h2>
              <p className="text-xs text-slate-500">
                {kind === 'rf'
                  ? 'Requisito funcional'
                  : kind === 'rnf'
                    ? 'Requisito no funcional'
                    : 'Stakeholder'}
                {' · '}
                {mode === 'create' ? 'Crear nuevo' : `Editar ${uid ?? ''}`}
              </p>
            </div>
          </div>
            {t(titleKey)}
          </h2>
<button
            type="button"
            onClick={onClose}
            aria-label={t('rem.dialog.close')}
            data-testid="rem-edit-dialog-close"
            className="rounded-full p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
          >
            <svg
              width="18"
              height="18"
              viewBox="0 0 20 20"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.75"
              strokeLinecap="round"
              aria-hidden="true"
            >
              <path d="M5 5 L15 15 M15 5 L5 15" />
            </svg>
          </button>
        </header>

        <div className="rem-edit-dialog__body flex flex-col gap-3">
          {kind === 'rf' ? (
            <RemRfForm
              mode={mode}
              defaultValues={
                mode === 'edit'
                  ? ({ uid, ...(defaultValues ?? {}) } as Partial<RfUpdateInput>)
                  : (defaultValues as Partial<RfUpdateInput>)
              }
              onSubmit={submit}
              onCancel={onClose}
            />
          ) : kind === 'rnf' ? (
            <RemRnfForm
              mode={mode}
              defaultValues={
                mode === 'edit'
                  ? ({ uid, ...(defaultValues ?? {}) } as Partial<RnfUpdateInput>)
                  : (defaultValues as Partial<RnfUpdateInput>)
              }
              onSubmit={submit}
              onCancel={onClose}
            />
          ) : (
            <RemStakeholderForm
              mode={mode}
              defaultValues={
                mode === 'edit'
                  ? ({ uid, ...(defaultValues ?? {}) } as Partial<StakeholderUpdateInput>)
                  : (defaultValues as Partial<StakeholderUpdateInput>)
              }
              onSubmit={submit}
              onCancel={onClose}
            />
          )}
        </div>

        {errorMessage !== null && errorMessage !== '' ? (
          <div
            role="alert"
            data-testid="rem-edit-dialog-error"
            className="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800"
          >
            {errorMessage}
          </div>
        ) : null}

        {busy ? (
          <div
            data-testid="rem-edit-dialog-busy"
            className="text-sm text-slate-500"
            aria-live="polite"
          >
            ...
          </div>
        ) : null}
      </div>
    </dialog>
  );
}

export default RemEditDialog;
