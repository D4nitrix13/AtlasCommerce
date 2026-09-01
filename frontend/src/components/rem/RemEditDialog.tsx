/**
 * RemEditDialog.tsx
 *
 * Modal dialog shell for create / edit CRUD (REQ-18, REQ-19). Uses the
 * native `<dialog>` element so we get Escape, focus trap, and backdrop
 * click for free (no overlay/backdrop code).
 *
 * Two-phase lifecycle (D2 → D4):
 *   - D2 (this file): renders the dialog chrome + Cancel/Submit
 *     buttons + slot for the actual form body. The submit button is a
 *     stub that fires `onSubmit(values)` after Zod passes. Two skeleton
 *     tests verify the chrome + Cancel/Escape behavior.
 *   - D4: plugs the 3 Zod-backed forms (RemRfForm / RemRnfForm /
 *     RemStakeholderForm) and wires POST/PUT dispatch + 201/200/500
 *     success/error paths.
 *
 * The dialog does NOT call `fetch` directly. D4 introduces a thin
 * `submit` prop that takes a parsed payload and forwards it to the
 * endpoint — keeps the dialog testable in isolation.
 */

import {
  useCallback,
  useEffect,
  useRef,
  type FormEvent,
  type ReactElement,
  type ReactNode,
} from 'react';

import { useTranslations } from '../../i18n';

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
  /**
   * Form body slot — D4 plugs one of the 3 Zod-backed forms in.
   * The slot owns `formState` + onSubmit — the dialog is a thin shell.
   */
  children?: ReactNode;
  /**
   * Submit handler — D4 wires POST/PUT here. D2 ships a no-op so the
   * skeleton renders and Cancel/Escape tests pass.
   */
  onSubmit?: (event: FormEvent<HTMLFormElement>) => void;
  /** Cancel/Escape/backdrop close. */
  onClose: () => void;
  /** True while the request is in flight (disables Submit + shows spinner). */
  busy?: boolean;
  /** Top-level error message (server 500 or validation summary). */
  errorMessage?: string | null;
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
  uid: _uid,
  children,
  onSubmit,
  onClose,
  busy = false,
  errorMessage = null,
}: RemEditDialogProps): ReactElement | null {
  const { t } = useTranslations();
  const dialogRef = useRef<HTMLDialogElement | null>(null);

  // Open / close the native `<dialog>` based on the `open` prop. We
  // delegate to the browser for the visibility + Escape handling so we
  // never have to write overlay logic ourselves.
  useEffect(() => {
    const dialog = dialogRef.current;
    if (dialog === null) return;
    if (open && !dialog.open) {
      try {
        dialog.showModal();
      } catch {
        // `showModal()` throws if called from outside a user gesture in
        // some browsers; React's effect fires too late. We can fall
        // back to `setAttribute('open', '')` which keeps the dialog
        // visible without the modal backdrop.
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

  const handleFormSubmit = useCallback(
    (event: FormEvent<HTMLFormElement>) => {
      if (onSubmit !== undefined) {
        onSubmit(event);
        return;
      }
      // Default: prevent the page reload but do nothing else (D2 skeleton).
      event.preventDefault();
    },
    [onSubmit]
  );

  const handleCancel = useCallback(
    (event: React.MouseEvent<HTMLButtonElement>) => {
      event.preventDefault();
      onClose();
    },
    [onClose]
  );

  if (!open) {
    return null;
  }

  const titleKey =
    mode === 'create' ? 'rem.dialog.create.heading' : 'rem.dialog.edit.heading';
  const submitKey =
    mode === 'create' ? 'rem.crud.create.submit' : 'rem.crud.edit.submit';

  return (
    <dialog
      ref={dialogRef}
      data-rem-edit-dialog
      data-rem-edit-mode={mode}
      data-rem-edit-kind={kind}
      aria-labelledby="rem-edit-dialog-title"
      className="rem-edit-dialog w-full max-w-lg rounded-lg border border-slate-300 bg-white p-6 text-left shadow-xl backdrop:bg-slate-900/40"
    >
      <form onSubmit={handleFormSubmit} className="flex flex-col gap-4" noValidate>
        <header className="flex items-center justify-between border-b border-slate-200 pb-3">
          <h2
            id="rem-edit-dialog-title"
            className="text-lg font-semibold text-slate-900"
          >
            {t(titleKey)}
          </h2>
          <button
            type="button"
            data-testid="rem-edit-dialog-close"
            aria-label={t('rem.crud.cancel')}
            onClick={handleCancel}
            className="text-slate-500 hover:text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
          >
            ✕
          </button>
        </header>

        <div className="rem-edit-dialog__body flex flex-col gap-3">
          {children}
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

        <footer className="flex items-center justify-end gap-2 border-t border-slate-200 pt-3">
          <button
            type="button"
            data-testid="rem-edit-dialog-cancel"
            onClick={handleCancel}
            className="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
          >
            {t('rem.crud.cancel')}
          </button>
          <button
            type="submit"
            data-testid="rem-edit-dialog-submit"
            disabled={busy}
            className="inline-flex items-center justify-center rounded-md border border-[#2D9D8E] bg-[#2D9D8E] px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-[#24897B] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E] disabled:cursor-not-allowed disabled:opacity-60"
          >
            {t(submitKey)}
          </button>
        </footer>
      </form>
    </dialog>
  );
}

export default RemEditDialog;
