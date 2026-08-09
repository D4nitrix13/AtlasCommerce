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
import { pandaTheme } from "@theme/panda-estampados";

const brand = pandaTheme.brand;

let hrefSetter: { value: string };

beforeEach(() => {
  mockApiFetch.mockReset();
  window.history.replaceState({}, "", "/es/admin/auth/forgot-password");
  // Spy del setter de location.href para capturar la navegación sin
  // provocar navegación real (jsdom no soporta location.assign).
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
    it("muestra el título y el body de la página", () => {
      render(<ForgotPasswordForm brand={brand} />);

      expect(
        screen.getByRole("heading", { name: "auth.passwordReset.title" }),
      ).toBeInTheDocument();
      expect(screen.getByText("auth.passwordReset.bodyEmail")).toBeInTheDocument();
    });

    it("muestra el input de email con el label correcto", () => {
      render(<ForgotPasswordForm brand={brand} />);

      const emailInput = screen.getByLabelText("auth.passwordReset.email.label");
      expect(emailInput).toBeInTheDocument();
      expect(emailInput).toHaveAttribute("type", "email");
      expect(emailInput).toHaveAttribute("autoComplete", "email");
    });

    it("muestra el brand name en el header", () => {
      render(<ForgotPasswordForm brand={brand} />);

      expect(screen.getByText("Panda Estampados")).toBeInTheDocument();
    });

    it("muestra el link 'Volver' apuntando al login", () => {
      render(<ForgotPasswordForm brand={brand} />);

      const backLink = screen.getByRole("link", { name: /common\.back/ });
      expect(backLink).toHaveAttribute("href", "/es/admin/auth/login");
    });
  });

  describe("validación client-side", () => {
    it("muestra error si el email está vacío", async () => {
      const user = userEvent.setup();
      render(<ForgotPasswordForm brand={brand} />);

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.email.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole("alert")).toHaveTextContent(
          "auth.passwordReset.error.emailEmpty",
        );
      });
      expect(mockApiFetch).not.toHaveBeenCalled();
    });

    it("muestra error si el email no tiene formato válido", async () => {
      const user = userEvent.setup();
      render(<ForgotPasswordForm brand={brand} />);

      const emailInput = screen.getByLabelText("auth.passwordReset.email.label");
      await user.type(emailInput, "not-an-email");

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.email.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole("alert")).toHaveTextContent(
          "auth.passwordReset.error.emailInvalid",
        );
      });
      expect(mockApiFetch).not.toHaveBeenCalled();
    });

    it("trimea el email antes de enviar", async () => {
      const user = userEvent.setup();
      mockApiFetch.mockResolvedValue({
        message: "ok",
        expires_minutes: 15,
      });

      render(<ForgotPasswordForm brand={brand} />);

      const emailInput = screen.getByLabelText("auth.passwordReset.email.label");
      await user.type(emailInput, "  jane@example.com  ");

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.email.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(mockApiFetch).toHaveBeenCalledWith(
          "/auth/forgot-password",
          expect.objectContaining({
            method: "POST",
            body: { email: "jane@example.com" },
          }),
        );
      });
    });
  });

  describe("envío exitoso", () => {
    it("redirige a step 2 con email en query param", async () => {
      const user = userEvent.setup();
      mockApiFetch.mockResolvedValue({
        message: "ok",
        expires_minutes: 15,
      });

      render(<ForgotPasswordForm brand={brand} />);

      const emailInput = screen.getByLabelText("auth.passwordReset.email.label");
      await user.type(emailInput, "jane@example.com");

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.email.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(hrefSetter.value).toContain("/es/admin/auth/reset-password");
        expect(hrefSetter.value).toContain("email=jane%40example.com");
      });
    });

    it("muestra error genérico si el backend falla", async () => {
      const user = userEvent.setup();
      mockApiFetch.mockRejectedValue({
        message: "Demasiados intentos.",
      });

      render(<ForgotPasswordForm brand={brand} />);

      const emailInput = screen.getByLabelText("auth.passwordReset.email.label");
      await user.type(emailInput, "jane@example.com");

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.email.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole("alert")).toHaveTextContent("Demasiados intentos.");
      });
    });
  });
});