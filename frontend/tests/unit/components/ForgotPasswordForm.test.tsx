// @vitest-environment jsdom
import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

const { mockApiFetch } = vi.hoisted(() => ({
  mockApiFetch: vi.fn(),
}));

vi.mock("../../../src/i18n", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../../src/i18n")>();
  return {
    ...actual,
    useTranslations: () => ({
      t: (key: string) => key,
      locale: "es",
    }),
  };
});

vi.mock("../../../src/lib/api", () => ({
  apiFetch: mockApiFetch,
}));

import ForgotPasswordForm from "../../../src/components/admin/auth/ForgotPasswordForm";

let hrefSetter: { value: string };

beforeEach(() => {
  mockApiFetch.mockReset();
  window.history.replaceState({}, "", "/es/admin/auth/forgot-password");
  hrefSetter = { value: "" };
  Object.defineProperty(window, "location", {
    value: {
      get href() {
        return hrefSetter.value;
      },
      set href(value: string) {
        hrefSetter.value = value;
      },
      pathname: "/es/admin/auth/forgot-password",
      assign: vi.fn(),
      replace: vi.fn(),
    },
    writable: true,
    configurable: true,
  });
});

afterEach(() => {
  Object.defineProperty(window, "location", {
    value: window.location,
    writable: true,
    configurable: true,
  });
});

describe("components/admin/auth/ForgotPasswordForm", () => {
  describe("render", () => {
    it("muestra el input de email con el label correcto", () => {
      render(<ForgotPasswordForm />);

      const emailInput = screen.getByLabelText("auth.forgot.shell.emailLabel");
      expect(emailInput).toBeInTheDocument();
      expect(emailInput).toHaveAttribute("type", "email");
      expect(emailInput).toHaveAttribute("autoComplete", "email");
    });

    it("muestra el botón submit con el texto correcto", () => {
      render(<ForgotPasswordForm />);

      const submitButton = screen.getByRole("button", {
        name: "auth.forgot.shell.submit",
      });
      expect(submitButton).toBeInTheDocument();
      expect(submitButton).toHaveAttribute("type", "submit");
    });

    it("muestra el helper text debajo del email", () => {
      render(<ForgotPasswordForm />);

      expect(screen.getByText("auth.forgot.shell.helper")).toBeInTheDocument();
    });
  });

  describe("validación client-side", () => {
    it("muestra error si el email está vacío", async () => {
      const user = userEvent.setup();
      render(<ForgotPasswordForm />);

      const submitButton = screen.getByRole("button", {
        name: "auth.forgot.shell.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole("alert")).toHaveTextContent(
          "auth.passwordReset.error.emailEmpty",
        );
      });
      expect(mockApiFetch).not.toHaveBeenCalled();
    });

    it("muestra error si el email no es válido", async () => {
      const user = userEvent.setup();
      render(<ForgotPasswordForm />);

      await user.type(screen.getByLabelText("auth.forgot.shell.emailLabel"), "not-an-email");
      await user.click(screen.getByRole("button", { name: "auth.forgot.shell.submit" }));

      await waitFor(() => {
        expect(screen.getByRole("alert")).toHaveTextContent(
          "auth.passwordReset.error.emailInvalid",
        );
      });
      expect(mockApiFetch).not.toHaveBeenCalled();
    });
  });

  describe("submit exitoso", () => {
    it("llama a /auth/forgot-password y redirige a reset-password", async () => {
      const user = userEvent.setup();
      mockApiFetch.mockResolvedValue({
        message: "ok",
        expires_minutes: 30,
      });

      render(<ForgotPasswordForm />);

      await user.type(
        screen.getByLabelText("auth.forgot.shell.emailLabel"),
        "admin@panda-estampados.com",
      );
      await user.click(screen.getByRole("button", { name: "auth.forgot.shell.submit" }));

      await waitFor(() => {
        expect(mockApiFetch).toHaveBeenCalledWith(
          "/auth/forgot-password",
          expect.objectContaining({
            method: "POST",
            body: expect.objectContaining({ email: "admin@panda-estampados.com" }),
          }),
        );
      });

      expect(hrefSetter.value).toContain("/es/admin/auth/reset-password");
      expect(hrefSetter.value).toContain("email=admin%40panda-estampados.com");
    });
  });
});