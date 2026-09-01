/**
 * RemRnfForm.test.tsx
 *
 * Mirror of `RemRfForm.test.tsx` for the RNF form.
 */

import { describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { RemRnfForm } from "../../../../../src/components/rem/forms/RemRnfForm";

describe("RemRnfForm · REQ-19#1", () => {
  it("invalid code blocks submit + shows the code.invalid message", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<RemRnfForm mode="create" onSubmit={onSubmit} onCancel={() => {}} />);
    await user.type(screen.getByTestId("rem-rnf-form-code"), "INVALID");
    await user.tab();
    expect(onSubmit).not.toHaveBeenCalled();
    expect(screen.getByRole("alert")).toBeInTheDocument();
  });

  it("valid submit calls onSubmit with the parsed values", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<RemRnfForm mode="create" onSubmit={onSubmit} onCancel={() => {}} />);
    await user.type(screen.getByTestId("rem-rnf-form-code"), "RNF-SEC-099");
    await user.type(screen.getByTestId("rem-rnf-form-title"), "Sample");
    await user.type(screen.getByTestId("rem-rnf-form-category"), "Security");
    await user.click(screen.getByTestId("rem-rnf-form-submit"));
    expect(onSubmit).toHaveBeenCalledTimes(1);
    const values = onSubmit.mock.calls[0]![0];
    expect(values.code).toBe("RNF-SEC-099");
    expect(values.category).toBe("Security");
  });

  it("cancel button does not call onSubmit", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    const onCancel = vi.fn();
    render(<RemRnfForm mode="create" onSubmit={onSubmit} onCancel={onCancel} />);
    await user.click(screen.getByTestId("rem-rnf-form-cancel"));
    expect(onSubmit).not.toHaveBeenCalled();
    expect(onCancel).toHaveBeenCalled();
  });
});
