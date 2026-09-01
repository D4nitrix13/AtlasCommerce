/**
 * RemKebabMenu.tsx
 *
 * Per-row kebab (⋮) button + popover with Editar / Eliminar actions
 * (REQ-16, REQ-17). The kebab is always-visible (DD3 lock) — touch
 * devices have no hover, and a kiosk demo might run on a tablet.
 *
 * Behavior:
 *   - Always-visible `⋮` button (`aria-haspopup="menu"`, `aria-expanded`).
 *   - Popover with two `<button data-testid="rem-crud-edit">` /
 *     `data-testid="rem-crud-delete">` actions.
 *   - Escape + click-outside close the popover and return focus to the
 *     trigger (REQ-16#3, REQ-16#4).
 *   - `Eliminar` calls `window.confirm(t('rem.crud.delete.confirm'))`
 *     (DD4 lock). On `true` fires `onDelete(uid)`; on `false` closes.
 *   - The component itself does NOT call `fetch` — it delegates to
 *     `onDelete(uid)` so the parent (RemSidebar) wires the DELETE
 *     endpoint in D4. This keeps the component testable without HTTP
 *     mocks.
 *
 * Accessibility:
 *   - `role="menu"` on the popover; `role="menuitem"` on the actions.
 *   - Arrow keys cycle through the menu items.
 *   - Focus returns to the trigger button on close.
 */

import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type ReactElement,
} from 'react';

import { useTranslations } from '../../i18n';

export type RemKebabKind = 'rf' | 'rnf' | 'stakeholder';

export interface RemKebabMenuProps {
  /** UID of the row — passed to onEdit / onDelete. */
  uid: string;
  /** Item type — used for `data-rem-kebab-kind` hooks + endpoint URL. */
  kind: RemKebabKind;
  /** Fired when the user picks "Editar". Parent opens the dialog. */
  onEdit: (uid: string, kind: RemKebabKind) => void;
  /**
   * Fired when the user picks "Eliminar" AND confirms via
   * `window.confirm()`. Parent calls the DELETE endpoint.
   */
  onDelete: (uid: string, kind: RemKebabKind) => void;
}

/**
 * Resolve the closest focusable menu item relative to `current`. Used
 * for arrow-key navigation across the two menu items.
 */
function moveMenuFocus(
  current: HTMLButtonElement,
  direction: 1 | -1
): void {
  const menu = current.closest('[role="menu"]');
  if (menu === null) return;
  const items = Array.from(
    menu.querySelectorAll<HTMLButtonElement>('[role="menuitem"]')
  );
  if (items.length === 0) return;
  const idx = items.indexOf(current);
  if (idx === -1) return;
  const next = items[(idx + direction + items.length) % items.length];
  next?.focus();
}

export function RemKebabMenu({
  uid,
  kind,
  onEdit,
  onDelete,
}: RemKebabMenuProps): ReactElement {
  const { t } = useTranslations();
  const [open, setOpen] = useState(false);
  const triggerRef = useRef<HTMLButtonElement | null>(null);
  const menuRef = useRef<HTMLDivElement | null>(null);

  const close = useCallback((returnFocus: boolean): void => {
    setOpen(false);
    if (returnFocus) {
      triggerRef.current?.focus();
    }
  }, []);

  // Click-outside close (REQ-16#4).
  useEffect(() => {
    if (!open) return;
    const handler = (event: MouseEvent): void => {
      const target = event.target;
      if (
        target instanceof Node &&
        menuRef.current !== null &&
        !menuRef.current.contains(target) &&
        triggerRef.current !== null &&
        !triggerRef.current.contains(target)
      ) {
        close(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => {
      document.removeEventListener('mousedown', handler);
    };
  }, [open, close]);

  // Escape closes + returns focus (REQ-16#3).
  useEffect(() => {
    if (!open) return;
    const handler = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        event.preventDefault();
        close(true);
      }
    };
    document.addEventListener('keydown', handler);
    return () => {
      document.removeEventListener('keydown', handler);
    };
  }, [open, close]);

  const handleTriggerKeyDown = useCallback(
    (event: React.KeyboardEvent<HTMLButtonElement>) => {
      if (event.key === 'ArrowDown' && !open) {
        event.preventDefault();
        setOpen(true);
      }
    },
    [open]
  );

  const handleTriggerClick = useCallback(() => {
    setOpen((prev) => !prev);
  }, []);

  const handleEdit = useCallback(() => {
    close(false);
    onEdit(uid, kind);
  }, [close, onEdit, uid, kind]);

  const handleDelete = useCallback(() => {
    // DD4 lock: native `window.confirm()` is the gravitas this
    // hard-delete deserves.
    const ok = window.confirm(t('rem.crud.delete.confirm'));
    close(false);
    if (ok === true) {
      onDelete(uid, kind);
    }
  }, [close, onDelete, uid, kind, t]);

  const handleMenuItemKeyDown = useCallback(
    (event: React.KeyboardEvent<HTMLButtonElement>) => {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveMenuFocus(event.currentTarget, 1);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveMenuFocus(event.currentTarget, -1);
      }
    },
    []
  );

  return (
    <span
      className="rem-kebab inline-flex items-center"
      data-rem-kebab
      data-rem-kebab-kind={kind}
    >
      <button
        ref={triggerRef}
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-label={t('rem.crud.actions')}
        data-testid={`rem-kebab-${uid}`}
        onClick={handleTriggerClick}
        onKeyDown={handleTriggerKeyDown}
        className="rem-kebab__trigger inline-flex h-6 w-6 items-center justify-center rounded text-slate-500 hover:bg-slate-100 hover:text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
      >
        <span aria-hidden="true" className="text-base leading-none">
          ⋮
        </span>
      </button>
      {open ? (
        <div
          ref={menuRef}
          role="menu"
          aria-label={t('rem.crud.actions')}
          data-testid={`rem-kebab-menu-${uid}`}
          className="rem-kebab__menu absolute z-10 mt-7 inline-flex min-w-[10rem] flex-col rounded-md border border-slate-300 bg-white py-1 text-sm shadow-md"
        >
          <button
            type="button"
            role="menuitem"
            data-testid="rem-crud-edit"
            data-rem-crud-action="edit"
            onClick={handleEdit}
            onKeyDown={handleMenuItemKeyDown}
            className="rem-kebab__item-edit px-3 py-1.5 text-left text-slate-700 hover:bg-slate-100 focus-visible:bg-slate-100 focus-visible:outline-none"
          >
            {t('rem.crud.edit')}
          </button>
          <button
            type="button"
            role="menuitem"
            data-testid="rem-crud-delete"
            data-rem-crud-action="delete"
            onClick={handleDelete}
            onKeyDown={handleMenuItemKeyDown}
            className="rem-kebab__item-delete px-3 py-1.5 text-left text-red-700 hover:bg-red-50 focus-visible:bg-red-50 focus-visible:outline-none"
          >
            {t('rem.crud.delete')}
          </button>
        </div>
      ) : null}
    </span>
  );
}

export default RemKebabMenu;
