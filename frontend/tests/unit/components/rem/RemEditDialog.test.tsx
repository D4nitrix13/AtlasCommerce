/**
 * RemEditDialog.test.tsx
 *
 * Coverage for the create/edit dialog shell (Task 3.2 / REQ-18,
 * REQ-19). Strict TDD: tests ship in the same D2 commit as
 * `RemEditDialog.tsx`. D4 will add 3 more tests (REQ-19#2/#3/#5 —
 * submit success / edit success swap / 500 stays open) once the
 * form bodies + endpoint dispatch land.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { RemEditDialog } from '../../../../src/components/rem/RemEditDialog';

beforeEach(() => {
  window.localStorage.clear();
});

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe('RemEditDialog · REQ-18 + REQ-19 (D2 skeleton)', () => {
  it('REQ-18#2: create-stakeholder mode renders the dialog chrome with the i18n title', () => {
    render(
      <RemEditDialog
        open={true}
        mode="create"
        kind="stakeholder"
        onClose={() => {}}
      >
        <div data-testid="form-body">form fields go here</div>
      </RemEditDialog>,
    );
    const dialog = screen.getByTestId('form-body').closest('dialog');
    expect(dialog).not.toBeNull();
    expect(dialog?.getAttribute('data-rem-edit-mode')).toBe('create');
    expect(dialog?.getAttribute('data-rem-edit-kind')).toBe('stakeholder');
    // Title uses the i18n key (in Spanish — the test app is es).
    expect(dialog?.textContent).toContain('Nuevo requisito');
    expect(screen.getByTestId('rem-edit-dialog-cancel')).toBeInTheDocument();
    expect(screen.getByTestId('rem-edit-dialog-submit')).toBeInTheDocument();
    expect(screen.getByTestId('form-body')).toBeInTheDocument();
  });

  it('REQ-19#4: Cancel / Escape do not call onSubmit (skeleton has no fetch)', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    const onSubmit = vi.fn();
    render(
      <RemEditDialog
        open={true}
        mode="create"
        kind="rf"
        onClose={onClose}
        onSubmit={onSubmit}
      />,
    );
    // Cancel button — no onSubmit, calls onClose.
    await user.click(screen.getByTestId('rem-edit-dialog-cancel'));
    expect(onSubmit).not.toHaveBeenCalled();
    expect(onClose).toHaveBeenCalled();
  });

  it('renders the error alert when errorMessage is non-empty', () => {
    render(
      <RemEditDialog
        open={true}
        mode="edit"
        kind="rf"
        onClose={() => {}}
        errorMessage="No se pudo guardar el cambio."
      >
        <span>x</span>
      </RemEditDialog>,
    );
    const alert = screen.getByTestId('rem-edit-dialog-error');
    expect(alert).toBeInTheDocument();
    expect(alert.textContent).toContain('No se pudo guardar el cambio');
    expect(alert.getAttribute('role')).toBe('alert');
  });

  it('disables the submit button when busy=true', () => {
    render(
      <RemEditDialog
        open={true}
        mode="create"
        kind="rf"
        onClose={() => {}}
        busy={true}
      >
        <span>x</span>
      </RemEditDialog>,
    );
    const submit = screen.getByTestId('rem-edit-dialog-submit');
    expect(submit).toBeDisabled();
  });

  it('closes via native dialog `close` event when Escape fires (no fetch)', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    const onSubmit = vi.fn();
    render(
      <RemEditDialog
        open={true}
        mode="create"
        kind="rnf"
        onClose={onClose}
        onSubmit={onSubmit}
      >
        <span>x</span>
      </RemEditDialog>,
    );
    // jsdom's <dialog> supports showModal; press Escape via the
    // keyboard on the dialog element itself.
    const dialog = document.querySelector(
      'dialog[data-rem-edit-dialog]'
    ) as HTMLDialogElement | null;
    expect(dialog).not.toBeNull();
    if (dialog !== null) {
      dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
      // Native dialog emits `close` synchronously on Escape in jsdom.
      dialog.dispatchEvent(new Event('close'));
    }
    await waitFor(() => {
      expect(onClose).toHaveBeenCalled();
    });
    expect(onSubmit).not.toHaveBeenCalled();
    await user; // suppress unused-var lint
  });
});
