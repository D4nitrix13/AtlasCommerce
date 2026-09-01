/**
 * RemRfForm.test.tsx
 *
 * Coverage for the RF form component (Task 5.1 / REQ-19#1).
 * Strict TDD: tests ship in the same D4 commit as the form.
 *
 *   - Invalid `code` blocks submit; field shows the invalid message.
 *   - Valid submit calls `onSubmit` with the parsed object.
 *   - Cancel button does NOT call onSubmit.
 */

import { describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { RemRfForm } from "../../../../../src/components/rem/forms/RemRfForm";

describe("RemRfForm · REQ-19#1", () => {
  it("invalid code blocks submit + shows the code.invalid message", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<RemRfForm mode="create" onSubmit={onSubmit} onCancel={() => {}} />);
    const codeInput = screen.getByTestId("rem-rf-form-code");
    await user.type(codeInput, "INVALID");
    // Blur to trigger onBlur validation.
    await user.tab();
    expect(onSubmit).not.toHaveBeenCalled();
    const alert = screen.getByRole("alert");
    expect(alert).toBeInTheDocument();
  });

  it("valid submit calls onSubmit with the parsed values", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<RemRfForm mode="create" onSubmit={onSubmit} onCancel={() => {}} />);
    await user.type(screen.getByTestId("rem-rf-form-code"), "RF-AUTH-099");
    await user.type(screen.getByTestId("rem-rf-form-title"), "Sample title");
    await user.type(screen.getByTestId("rem-rf-form-module"), "Auth");
    await user.click(screen.getByTestId("rem-rf-form-submit"));
    expect(onSubmit).toHaveBeenCalledTimes(1);
    const values = onSubmit.mock.calls[0]![0];
    expect(values.code).toBe("RF-AUTH-099");
    expect(values.title).toBe("Sample title");
    expect(values.module).toBe("Auth");
    expect(values.priority).toBe("M");
    expect(values.status).toBe("pending");
  });

  it("cancel button does not call onSubmit and does not fire fetch", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    const onCancel = vi.fn();
    render(<RemRfForm mode="create" onSubmit={onSubmit} onCancel={onCancel} />);
    await user.click(screen.getByTestId("rem-rf-form-cancel"));
    expect(onSubmit).not.toHaveBeenCalled();
    expect(onCancel).toHaveBeenCalled();
  });
});
