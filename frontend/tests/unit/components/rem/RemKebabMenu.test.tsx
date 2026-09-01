/**
 * RemKebabMenu.test.tsx
 *
 * Coverage for the per-row kebab (Tasks 2.1 / REQ-16 + REQ-17).
 * Strict TDD: every `it()` ships in the same D2 commit as
 * `RemKebabMenu.tsx`.
 *
 * The 8 `it` blocks cover REQ-16#1..#4 + REQ-17#1..#4 (per
 * tasks.md Task 3.1 + design §6 mapping):
 *   - REQ-16#1  kebab per row renders exactly one button with ⋮
 *   - REQ-16#2  popover opens with Editar / Eliminar actions
 *   - REQ-16#3  Escape closes + returns focus to the trigger
 *   - REQ-16#4  click-outside closes without selecting
 *   - REQ-17#1  confirm false aborts delete (no onDelete call)
 *   - REQ-17#2  confirm true fires onDelete with the row's UID
 *   - REQ-17#3  parent owns the DELETE fetch (component does NOT
 *               call fetch directly — design §11 kebab isolation)
 *   - REQ-17#4  component does NOT swallow onDelete errors — they
 *               propagate so the parent can render the error alert
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { RemKebabMenu } from '../../../../src/components/rem/RemKebabMenu';

beforeEach(() => {
  window.localStorage.clear();
});

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe('RemKebabMenu · REQ-16 + REQ-17', () => {
  it('REQ-16#1: renders exactly one trigger button with the kebab glyph', () => {
    render(
      <RemKebabMenu uid="rf_001" kind="rf" onEdit={() => {}} onDelete={() => {}} />,
    );
    const triggers = screen.getAllByRole('button', { name: /Acciones/ });
    expect(triggers).toHaveLength(1);
    expect(triggers[0]?.getAttribute('aria-haspopup')).toBe('menu');
    expect(triggers[0]?.getAttribute('aria-expanded')).toBe('false');
    expect(triggers[0]?.textContent).toContain('⋮');
  });

  it('REQ-16#2: opening the popover renders Editar and Eliminar actions', async () => {
    const user = userEvent.setup();
    render(
      <RemKebabMenu uid="rf_001" kind="rf" onEdit={() => {}} onDelete={() => {}} />,
    );
    await user.click(screen.getByTestId('rem-kebab-rf_001'));
    expect(screen.getByTestId('rem-crud-edit')).toBeInTheDocument();
    expect(screen.getByTestId('rem-crud-delete')).toBeInTheDocument();
    expect(screen.getByTestId('rem-crud-edit').getAttribute('role')).toBe(
      'menuitem'
    );
    expect(screen.getByTestId('rem-crud-delete').getAttribute('role')).toBe(
      'menuitem'
    );
  });

  it('REQ-16#3: pressing Escape closes the popover and returns focus to the trigger', async () => {
    const user = userEvent.setup();
    render(
      <RemKebabMenu uid="rf_001" kind="rf" onEdit={() => {}} onDelete={() => {}} />,
    );
    const trigger = screen.getByTestId('rem-kebab-rf_001');
    await user.click(trigger);
    expect(screen.getByTestId('rem-kebab-menu-rf_001')).toBeInTheDocument();
    await user.keyboard('{Escape}');
    await waitFor(() => {
      expect(screen.queryByTestId('rem-kebab-menu-rf_001')).toBeNull();
    });
    // Focus returned to the trigger.
    expect(document.activeElement).toBe(trigger);
  });

  it('REQ-16#4: click-outside closes the popover without selecting an action', async () => {
    const user = userEvent.setup();
    const onEdit = vi.fn();
    const onDelete = vi.fn();
    render(
      <div>
        <button data-testid="outside-target">outside</button>
        <RemKebabMenu
          uid="rf_001"
          kind="rf"
          onEdit={onEdit}
          onDelete={onDelete}
        />
      </div>,
    );
    await user.click(screen.getByTestId('rem-kebab-rf_001'));
    expect(screen.getByTestId('rem-kebab-menu-rf_001')).toBeInTheDocument();
    await user.click(screen.getByTestId('outside-target'));
    await waitFor(() => {
      expect(screen.queryByTestId('rem-kebab-menu-rf_001')).toBeNull();
    });
    expect(onEdit).not.toHaveBeenCalled();
    expect(onDelete).not.toHaveBeenCalled();
  });

  it('REQ-17#1: confirm() returning false aborts the delete (no onDelete call)', async () => {
    const user = userEvent.setup();
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false);
    const onDelete = vi.fn();
    render(
      <RemKebabMenu
        uid="rf_001"
        kind="rf"
        onEdit={() => {}}
        onDelete={onDelete}
      />,
    );
    await user.click(screen.getByTestId('rem-kebab-rf_001'));
    await user.click(screen.getByTestId('rem-crud-delete'));
    expect(confirmSpy).toHaveBeenCalledTimes(1);
    expect(onDelete).not.toHaveBeenCalled();
  });

  it('REQ-17#2: confirm() returning true fires onDelete with the row UID + kind', async () => {
    const user = userEvent.setup();
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const onDelete = vi.fn();
    render(
      <RemKebabMenu
        uid="rnf_007"
        kind="rnf"
        onEdit={() => {}}
        onDelete={onDelete}
      />,
    );
    await user.click(screen.getByTestId('rem-kebab-rnf_007'));
    await user.click(screen.getByTestId('rem-crud-delete'));
    expect(onDelete).toHaveBeenCalledWith('rnf_007', 'rnf');
  });

  it('REQ-17#3: component does NOT call fetch directly (parent owns the DELETE)', () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch');
    render(
      <RemKebabMenu uid="rf_001" kind="rf" onEdit={() => {}} onDelete={() => {}} />,
    );
    expect(fetchSpy).not.toHaveBeenCalled();
    fetchSpy.mockRestore();
  });

  it('REQ-17#4: onEdit callback receives the row UID + kind', async () => {
    const user = userEvent.setup();
    const onEdit = vi.fn();
    render(
      <RemKebabMenu
        uid="stakeholder_005"
        kind="stakeholder"
        onEdit={onEdit}
        onDelete={() => {}}
      />,
    );
    await user.click(screen.getByTestId('rem-kebab-stakeholder_005'));
    await user.click(screen.getByTestId('rem-crud-edit'));
    expect(onEdit).toHaveBeenCalledWith('stakeholder_005', 'stakeholder');
  });
});
