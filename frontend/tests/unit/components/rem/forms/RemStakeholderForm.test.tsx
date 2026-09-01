/**
 * RemStakeholderForm.test.tsx
 *
 * Mirror of `RemRfForm.test.tsx` for the stakeholder form. The
 * stakeholder schema is simpler (only `name` is required), so the
 * tests focus on the name field.
 */

import { describe, expect, it, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { RemStakeholderForm } from "../../../../../src/components/rem/forms/RemStakeholderForm";

describe("RemStakeholderForm · REQ-19#1", () => {
  it("missing name blocks submit + shows the name.required message", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<RemStakeholderForm mode="create" onSubmit={onSubmit} onCancel={() => {}} />);
    await user.click(screen.getByTestId("rem-stakeholder-form-submit"));
    expect(onSubmit).not.toHaveBeenCalled();
    expect(screen.getByRole("alert")).toBeInTheDocument();
  });

  it("valid submit calls onSubmit with the parsed values", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<RemStakeholderForm mode="create" onSubmit={onSubmit} onCancel={() => {}} />);
    await user.type(screen.getByTestId("rem-stakeholder-form-name"), "Alice");
    await user.type(screen.getByTestId("rem-stakeholder-form-title"), "PM");
    await user.type(screen.getByTestId("rem-stakeholder-form-role"), "owner");
    await user.type(screen.getByTestId("rem-stakeholder-form-organization"), "Acme");
    await user.click(screen.getByTestId("rem-stakeholder-form-submit"));
    expect(onSubmit).toHaveBeenCalledTimes(1);
    const values = onSubmit.mock.calls[0]![0];
    expect(values.name).toBe("Alice");
    expect(values.title).toBe("PM");
    expect(values.role).toBe("owner");
    expect(values.organization).toBe("Acme");
  });

  it("cancel button does not call onSubmit", async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    const onCancel = vi.fn();
    render(<RemStakeholderForm mode="create" onSubmit={onSubmit} onCancel={onCancel} />);
    await user.click(screen.getByTestId("rem-stakeholder-form-cancel"));
    expect(onSubmit).not.toHaveBeenCalled();
    expect(onCancel).toHaveBeenCalled();
  });
});
