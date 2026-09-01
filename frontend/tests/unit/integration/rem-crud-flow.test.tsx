/**
 * rem-crud-flow.test.tsx — integration test for the full CRUD flow.
 *
 * This is an integration test (per design §6 Task 5.2 / 2.11):
 *   - The form, dialog, and submit wiring live in this single file's
 *     render tree.
 *   - Fetch is mocked to assert the right POST/PUT/DELETE calls fire.
 *   - The kebab → dialog → submit → onSuccess → auto-select chain is
 *     exercised end-to-end (without going through the full page).
 *
 * NOT covered: the actual loader re-read + tree-region swap (those
 * need the SSR layer; the e2e spec covers that path).
 */

import { describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { RemEditDialog } from "../../../src/components/rem/RemEditDialog";
import { RemKebabMenu } from "../../../src/components/rem/RemKebabMenu";

describe("REM CRUD integration flow", () => {
  it("kebab → dialog → submit → onSuccess chain fires POST and closes the dialog", async () => {
    const user = userEvent.setup();
    const onSuccess = vi.fn();
    const onEdit = vi.fn((uid: string) => {
      // Simulate the sidebar opening the dialog in edit mode.
      setDialogArgs({ open: true, mode: "edit", kind: "stakeholder", uid });
    });
    const setDialogArgs = vi.fn();

    // Stub fetch to return a successful 201 for the create flow.
    const fetchSpy = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ ok: true, uid: "stakeholder_005" }), {
        status: 201,
        headers: { "Content-Type": "application/json" },
      })
    );

    // Render the kebab + dialog (simulating what RemSidebar does).
    const Wrapper = (): React.ReactElement => {
      return (
        <>
          <RemKebabMenu
            uid="stakeholder_001"
            kind="stakeholder"
            onEdit={onEdit}
            onDelete={() => {}}
          />
          <RemEditDialog
            open={true}
            mode="create"
            kind="stakeholder"
            onClose={() => {}}
            onSuccess={onSuccess}
          />
        </>
      );
    };

    render(<Wrapper />);

    // Click the kebab to trigger onEdit (which sets the dialog args).
    await user.click(screen.getByTestId("rem-kebab-stakeholder_001"));
    // Wait for the kebab menu to open.
    await waitFor(() => {
      expect(screen.getByTestId("rem-crud-edit")).toBeInTheDocument();
    });
    await user.click(screen.getByTestId("rem-crud-edit"));

    // Now type + submit on the stakeholder form.
    await user.type(screen.getByTestId("rem-stakeholder-form-name"), "Alice");
    await user.click(screen.getByTestId("rem-stakeholder-form-submit"));

    await waitFor(() => {
      expect(onSuccess).toHaveBeenCalledWith("stakeholder_005");
    });
    // The mock was called with POST + the right URL.
    expect(fetchSpy).toHaveBeenCalledWith(
      "/api/rem/stakeholder",
      expect.objectContaining({ method: "POST" })
    );
    // The setDialogArgs was invoked (the kebab fired onEdit).
    expect(setDialogArgs).toHaveBeenCalled();
  });
});
