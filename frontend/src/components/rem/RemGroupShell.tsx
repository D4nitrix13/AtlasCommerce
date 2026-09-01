/**
 * RemGroupShell.tsx
 *
 * 3 native `<details>` collapsible top-level groups + nested
 * `<details>` per RF `module` / RNF `category` (REQ-13, REQ-14).
 *
 * Persistence: every `<details>` toggles its `open` state into
 * `localStorage` via the `expandedStorageKey` /
 * `nestedExpandedStorageKey` helpers in `lib/rem/types.ts`. Reload
 * re-reads the stored value (REQ-13#3). The default for an
 * unset key is `open` (REQ-13#5).
 *
 * Format-drift tolerance (REQ-13#4): prior v2 builds may have written
 * `'open' | 'closed'` literal strings; the current build normalizes
 * to `''` (just toggling `open`). Both shapes are read without
 * throwing — see `parseStoredOpenState`.
 *
 * Why native `<details>` (DD1 lock): keyboard nav (Tab → summary,
 * Space/Enter toggle), screen-reader `expanded`/`collapsed`
 * announcement, free `name=` for single-open groups.
 *
 * The `+ Nuevo` button slot is rendered at the top of each group's
 * content area (REQ-18#1) but the dialog wiring is owned by
 * `RemSidebar` (D2). The shell exposes an `onCreate` callback so the
 * parent owns the dialog state.
 */

import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type ReactElement,
  type ReactNode,
} from 'react';

import {
  expandedStorageKey,
  nestedExpandedStorageKey,
  type RemGroupId,
} from '../../lib/rem/types';

// ─── Helpers ─────────────────────────────────────────────────────────

/**
 * Parse a stored value into a boolean. Tolerates `'open' | 'closed'`
 * (legacy literals) and `'' | null | undefined` (default-open).
 *
 * Whitespace is stripped so a future build that wrote
 * `localStorage.setItem(key, ' closed ')` still parses correctly.
 */
function parseStoredOpenState(raw: string | null): boolean {
  if (raw === null || raw === undefined) {
    return true; // default-open per REQ-13#5
  }
  const trimmed = raw.trim();
  if (trimmed === '' || trimmed === 'open' || trimmed === '1') {
    return true;
  }
  if (trimmed === 'closed' || trimmed === '0') {
    return false;
  }
  // Unknown values fall back to default-open rather than throwing —
  // see REQ-13#4 (format drift tolerance).
  return true;
}

/** Read a stored open-state; SSR-safe (returns the default when no window). */
function readStoredOpen(key: string): boolean {
  if (typeof window === 'undefined') {
    return true;
  }
  return parseStoredOpenState(window.localStorage.getItem(key));
}

/** Persist an open-state without throwing (localStorage may be disabled). */
function writeStoredOpen(key: string, open: boolean): void {
  if (typeof window === 'undefined') {
    return;
  }
  try {
    // We persist only the `closed` value; default-open is the
    // implicit default when the key is missing. This keeps storage
    // minimal and avoids polluting keys on first-visit (REQ-13#5).
    if (open) {
      window.localStorage.removeItem(key);
    } else {
      window.localStorage.setItem(key, 'closed');
    }
  } catch {
    // localStorage may throw in Safari private mode or in the
    // jsdom polyfill — never crash the sidebar over a persist.
  }
}

// ─── Top-level group ─────────────────────────────────────────────────

export interface RemGroupShellProps {
  /** Top-level group identifier (`stakeholders` | `rf` | `rnf`). */
  groupId: RemGroupId;
  /** Visible label rendered inside the `<summary>`. */
  label: string;
  /** Children rendered inside the `<details>` (nested shells or leaves). */
  children: ReactNode;
  /**
   * `+ Nuevo` callback. Fires with `groupId` when the user clicks the
   * top-of-group create button (REQ-18#1). Parent owns the dialog
   * state.
   */
  onCreate?: ((groupId: RemGroupId) => void) | undefined;
}

/**
 * Single collapsible `<details>` wrapper for one of the three
 * top-level groups. Reads/writes `expandedStorageKey(groupId)` on
 * toggle; mounts open by default.
 */
export function RemGroupShell({
  groupId,
  label,
  children,
  onCreate,
}: RemGroupShellProps): ReactElement {
  const storageKey = expandedStorageKey(groupId);
  const [open, setOpen] = useState<boolean>(() => readStoredOpen(storageKey));

  // Sync to a `storage` event so multi-tab toggles pick up the new
  // state without a reload (REQ-13 contract; v2 keeps the cross-tab
  // read-only — writes still flow through the user-driven `onToggle`).
  useEffect(() => {
    if (typeof window === 'undefined') return;
    const onStorage = (event: StorageEvent) => {
      if (event.key !== storageKey) return;
      setOpen(readStoredOpen(storageKey));
    };
    window.addEventListener('storage', onStorage);
    return () => {
      window.removeEventListener('storage', onStorage);
    };
  }, [storageKey]);

  const handleToggle = useCallback(
    (event: React.SyntheticEvent<HTMLDetailsElement>) => {
      const nextOpen = event.currentTarget.open;
      setOpen(nextOpen);
      writeStoredOpen(storageKey, nextOpen);
    },
    [storageKey],
  );

  const handleCreate = useCallback(() => {
    onCreate?.(groupId);
  }, [groupId, onCreate]);

  return (
    <details
      data-rem-group-top={groupId}
      open={open}
      onToggle={handleToggle}
      className="rem-group-shell group mb-2 rounded-md border border-slate-300 bg-white"
    >
      <summary
        className="rem-group-shell__summary flex cursor-pointer list-none items-center justify-between gap-2 rounded-t-md bg-gradient-to-r from-slate-50 to-white px-3 py-2 text-sm font-semibold text-slate-800 transition-colors hover:from-slate-100 hover:to-slate-50 hover:text-[#1E6F62]"
      >
        <span className="rem-group-shell__label flex min-w-0 flex-1 items-center gap-2">
          <svg
            width="14"
            height="14"
            viewBox="0 0 16 16"
            aria-hidden="true"
            className="rem-group-shell__chevron shrink-0 text-slate-500 transition-transform duration-150 group-open:rotate-90"
          >
            <path d="M5.5 3 L11 8 L5.5 13" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
          <span className="truncate">{label}</span>
        </span>
        {onCreate !== undefined ? (
          <button
            type="button"
            data-rem-crud-create={groupId}
            data-testid={`rem-crud-create-${groupId}`}
            aria-label={`+ ${label}`}
            onClick={(event) => {
              event.preventDefault();
              event.stopPropagation();
              handleCreate();
            }}
            className="rem-group-shell__create inline-flex items-center justify-center rounded border border-slate-300 bg-white px-2 py-0.5 text-xs font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9D8E]"
          >
            + Nuevo
          </button>
        ) : null}
      </summary>
      <div className="rem-group-shell__content border-t border-slate-200 p-2">
        {children}
      </div>
    </details>
  );
}

// ─── Nested group (RF `module` / RNF `category`) ─────────────────────

export interface RemNestedGroupProps {
  /** Top-level group — must be `rf` or `rnf` (stakeholders are flat). */
  groupId: 'rf' | 'rnf';
  /** Bucket name (`Carrito`, `Seguridad`, etc.). */
  bucketName: string;
  /** Visible label rendered inside the nested `<summary>`. */
  label: string;
  /** Leaves rendered inside the nested `<details>`. */
  children: ReactNode;
}

/**
 * Per-module (RF) / per-category (RNF) collapsible. Same persistence
 * + format-drift rules as `RemGroupShell`, but uses
 * `nestedExpandedStorageKey(groupId, bucketName)` so the keys stay
 * unique under the parent group.
 */
export function RemNestedGroup({
  groupId,
  bucketName,
  label,
  children,
}: RemNestedGroupProps): ReactElement {
  const storageKey = nestedExpandedStorageKey(groupId, bucketName);
  const [open, setOpen] = useState<boolean>(() => readStoredOpen(storageKey));

  useEffect(() => {
    if (typeof window === 'undefined') return;
    const onStorage = (event: StorageEvent) => {
      if (event.key !== storageKey) return;
      setOpen(readStoredOpen(storageKey));
    };
    window.addEventListener('storage', onStorage);
    return () => {
      window.removeEventListener('storage', onStorage);
    };
  }, [storageKey]);

  const handleToggle = useCallback(
    (event: React.SyntheticEvent<HTMLDetailsElement>) => {
      const nextOpen = event.currentTarget.open;
      setOpen(nextOpen);
      writeStoredOpen(storageKey, nextOpen);
    },
    [storageKey],
  );

  return (
    <details
      data-rem-group-nest={`${groupId}.${bucketName}`}
      open={open}
      onToggle={handleToggle}
      className="rem-group-nested group mb-1 rounded border border-slate-200 bg-white"
    >
      <summary className="rem-group-nested__summary flex cursor-pointer list-none items-center gap-2 rounded-t bg-gradient-to-r from-slate-50 to-white px-2 py-1 text-xs font-medium text-slate-700 transition-colors hover:from-slate-100 hover:to-slate-50 hover:text-[#1E6F62]">
        <svg
          width="12"
          height="12"
          viewBox="0 0 16 16"
          aria-hidden="true"
          className="rem-group-nested__chevron shrink-0 text-slate-500 transition-transform duration-150 group-open:rotate-90"
        >
          <path d="M5.5 3 L11 8 L5.5 13" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
        <span className="truncate">{label}</span>
      </summary>
      <ul className="rem-group-nested__content space-y-0.5 border-t border-slate-200 p-1">
        {children}
      </ul>
    </details>
  );
}

export default RemGroupShell;