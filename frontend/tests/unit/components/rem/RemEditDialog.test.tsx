/**
 * RemEditDialog.test.tsx
 *
 * Coverage for the create/edit dialog (Task 5.2 / REQ-19). Strict TDD:
 * tests ship in the same D4 commit as the dialog wire-up.
 *
 *   - REQ-19#1  invalid code blocks submit; field shows Zod error
 *   - REQ-19#2  201 closes + clearLoaderCache + onSuccess(uid)
 *   - REQ-19#3  200 swaps detail (PUT mode) + closes
 *   - REQ-19#4  Cancel / Escape do not call fetch
 *   - REQ-19#5  500 stays open + error alert
 */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { cleanup, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { RemEditDialog } from "../../../../src/components/rem/RemEditDialog";
import { clearLoaderCache } from "../../../../src/lib/rem/loader";

vi.mock("../../../../src/lib/rem/loader", () => ({
  clearLoaderCache: vi.fn(),
}));

beforeEach(() => {
  window.localStorage.clear();
});

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe("RemEditDialog · REQ-19#1..#5", () => {
  it("REQ-19#1: invalid input (empty name) blocks submit on stakeholder create", async () => {
    const user = userEvent.setup();
    const fetchSpy = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response("{}", { status: 200 })
    );
    render(
      <RemEditDialog open={true} mode="create" kind="stakeholder" onClose={() => {}} />
    );
    // Click submit with empty name.
    await user.click(screen.getByTestId("rem-stakeholder-form-submit"));
    // No fetch was made.
    expect(fetchSpy).not.toHaveBeenCalled();
    // The error message for name is shown.
    expect(screen.getAllByRole("alert").length).toBeGreaterThan(0);
  });

  it("REQ-19#2: 201 success closes the dialog + calls clearLoaderCache + onSuccess", async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    const onSuccess = vi.fn();
    const fetchSpy = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ ok: true, uid: "stakeholder_005" }), {
        status: 201,
        headers: { "Content-Type": "application/json" },
      })
    );
    render(
      <RemEditDialog
        open={true}
        mode="create"
        kind="stakeholder"
        onClose={onClose}
        onSuccess={onSuccess}
      />
    );
    await user.type(screen.getByTestId("rem-stakeholder-form-name"), "Alice");
    await user.click(screen.getByTestId("rem-stakeholder-form-submit"));
    await waitFor(() => {
      expect(onSuccess).toHaveBeenCalledWith("stakeholder_005");
    });
    expect(onClose).toHaveBeenCalled();
    expect(clearLoaderCache).toHaveBeenCalled();
    expect(fetchSpy).toHaveBeenCalledWith(
      "/api/rem/stakeholder",
      expect.objectContaining({ method: "POST" })
    );
  });

  it("REQ-19#3: PUT 200 closes + onSuccess with the existing UID", async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    const onSuccess = vi.fn();
    const fetchSpy = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ ok: true, uid: "stakeholder_001" }), {
        status: 200,
        headers: { "Content-Type": "application/json" },
      })
    );
    render(
      <RemEditDialog
        open={true}
        mode="edit"
        kind="stakeholder"
        uid="stakeholder_001"
        onClose={onClose}
        onSuccess={onSuccess}
      />
    );
    // Tab through the name field to trigger onBlur, then submit.
    const nameInput = screen.getByTestId("rem-stakeholder-form-name");
    await user.type(nameInput, "Updated");
    await user.tab();
    await user.click(screen.getByTestId("rem-stakeholder-form-submit"));
    // Wait for the fetch call to happen.
    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalled();
    });
    await waitFor(() => {
      expect(onSuccess).toHaveBeenCalledWith("stakeholder_001");
    });
    expect(onClose).toHaveBeenCalled();
  });

  it("REQ-19#4: cancel button does not call fetch and does call onClose", async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    const fetchSpy = vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response("{}", { status: 200 })
    );
    render(
      <RemEditDialog open={true} mode="create" kind="stakeholder" onClose={onClose} />
    );
    await user.click(screen.getByTestId("rem-stakeholder-form-cancel"));
    expect(fetchSpy).not.toHaveBeenCalled();
    expect(onClose).toHaveBeenCalled();
  });

  it("REQ-19#5: 500 stays open + error alert visible", async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      new Response(JSON.stringify({ ok: false, error: "crud:write:EACCES" }), {
        status: 500,
        headers: { "Content-Type": "application/json" },
      })
    );
    render(
      <RemEditDialog open={true} mode="create" kind="stakeholder" onClose={onClose} />
    );
    await user.type(screen.getByTestId("rem-stakeholder-form-name"), "Alice");
    await user.click(screen.getByTestId("rem-stakeholder-form-submit"));
    await waitFor(() => {
      expect(screen.getByTestId("rem-edit-dialog-error")).toBeInTheDocument();
    });
    // Dialog stays open — onClose NOT called.
    expect(onClose).not.toHaveBeenCalled();
  });

  it("renders an RF form when kind=rf", () => {
    render(<RemEditDialog open={true} mode="create" kind="rf" onClose={() => {}} />);
    expect(screen.getByTestId("rem-rf-form")).toBeInTheDocument();
    expect(screen.getByTestId("rem-rf-form-code")).toBeInTheDocument();
  });

  it("renders an RNF form when kind=rnf", () => {
    render(<RemEditDialog open={true} mode="create" kind="rnf" onClose={() => {}} />);
    expect(screen.getByTestId("rem-rnf-form")).toBeInTheDocument();
  });
});
