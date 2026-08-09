// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

// Mocks hoisted.
const { mockApiFetch, mockSetSession } = vi.hoisted(() => ({
  mockApiFetch: vi.fn(),
  mockSetSession: vi.fn(),
}));

vi.mock("../../../src/stores/auth", () => ({
  useAuth: () => ({
    login: vi.fn(),
    setSession: mockSetSession,
  }),
  authStore: { login: vi.fn(), setSession: mockSetSession },
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

import HelpLoginModal from "../../../src/components/admin/auth/HelpLoginModal";
import { pandaTheme } from "@theme/panda-estampados";

const brand = pandaTheme.brand;

beforeEach(() => {
  mockApiFetch.mockReset();
  mockSetSession.mockReset();
});

describe("components/admin/auth/HelpLoginModal", () => {
  describe("render", () => {
    it("renderiza el título del modal y el formulario de email", () => {
      render(<HelpLoginModal brand={brand} onClose={vi.fn()} />);

      expect(screen.getByRole("dialog")).toBeInTheDocument();
      expect(
        screen.getByRole("heading", { name: "auth.passwordReset.title" }),
      ).toBeInTheDocument();
      expect(screen.getByLabelText("auth.passwordReset.email.label")).toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: "auth.passwordReset.email.submit" }),
      ).toBeInTheDocument();
    });

    it("renderiza el button de cerrar con aria-label correcto", () => {
      render(<HelpLoginModal brand={brand} onClose={vi.fn()} />);

      const closeButton = screen.getByRole("button", {
        name: "auth.passwordReset.close",
      });
      expect(closeButton).toBeInTheDocument();
    });

    it("tiene aria-modal=true para accesibilidad", () => {
      render(<HelpLoginModal brand={brand} onClose={vi.fn()} />);

      const dialog = screen.getByRole("dialog");
      expect(dialog).toHaveAttribute("aria-modal", "true");
    });
  });

  describe("step 1 (email)", () => {
    it("muestra error si el email está vacío", async () => {
      const user = userEvent.setup();
      render(<HelpLoginModal brand={brand} onClose={vi.fn()} />);

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
      render(<HelpLoginModal brand={brand} onClose={vi.fn()} />);

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

    it("llama a POST /auth/forgot-password con el email trimmeado", async () => {
      const user = userEvent.setup();
      mockApiFetch.mockResolvedValue({
        message: "Si el email existe, te enviamos un código.",
        expires_minutes: 15,
      });

      render(<HelpLoginModal brand={brand} onClose={vi.fn()} />);

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

    it("avanza al step 2 cuando el backend responde 200 OK", async () => {
      const user = userEvent.setup();
      mockApiFetch.mockResolvedValue({
        message: "ok",
        expires_minutes: 15,
      });

      render(<HelpLoginModal brand={brand} onClose={vi.fn()} />);

      const emailInput = screen.getByLabelText("auth.passwordReset.email.label");
      await user.type(emailInput, "jane@example.com");

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.email.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(
          screen.getByLabelText("auth.passwordReset.code.label"),
        ).toBeInTheDocument();
        expect(
          screen.getByLabelText("auth.passwordReset.newPassword.label"),
        ).toBeInTheDocument();
        expect(
          screen.getByLabelText("auth.passwordReset.confirmPassword.label"),
        ).toBeInTheDocument();
      });
    });

    it("muestra error genérico si el backend falla con 4xx/5xx", async () => {
      const user = userEvent.setup();
      mockApiFetch.mockRejectedValue({
        message: "Demasiados intentos.",
      });

      render(<HelpLoginModal brand={brand} onClose={vi.fn()} />);

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

  describe("step 2 (code + password)", () => {
    async function goToStep2(): Promise<void> {
      const user = userEvent.setup();
      mockApiFetch.mockResolvedValue({
        message: "ok",
        expires_minutes: 15,
      });

      render(<HelpLoginModal brand={brand} onClose={vi.fn()} />);

      const emailInput = screen.getByLabelText("auth.passwordReset.email.label");
      await user.type(emailInput, "jane@example.com");

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.email.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(
          screen.getByLabelText("auth.passwordReset.code.label"),
        ).toBeInTheDocument();
      });
    }

    it("muestra error si el código está vacío", async () => {
      const user = userEvent.setup();
      await goToStep2();

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole("alert")).toHaveTextContent(
          "auth.passwordReset.error.codeEmpty",
        );
      });
    });

    it("muestra error si el código no tiene 6 dígitos", async () => {
      const user = userEvent.setup();
      await goToStep2();

      const codeInput = screen.getByLabelText("auth.passwordReset.code.label");
      await user.type(codeInput, "12345");

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole("alert")).toHaveTextContent(
          "auth.passwordReset.error.codeInvalid",
        );
      });
    });

    it("muestra error si la contraseña es débil", async () => {
      const user = userEvent.setup();
      await goToStep2();

      const codeInput = screen.getByLabelText("auth.passwordReset.code.label");
      await user.type(codeInput, "123456");

      const passwordInput = screen.getByLabelText(
        "auth.passwordReset.newPassword.label",
      );
      await user.type(passwordInput, "weak");

      const confirmInput = screen.getByLabelText(
        "auth.passwordReset.confirmPassword.label",
      );
      await user.type(confirmInput, "weak");

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole("alert")).toHaveTextContent(
          "auth.passwordReset.error.passwordWeak",
        );
      });
    });

    it("muestra error si las contraseñas no coinciden", async () => {
      const user = userEvent.setup();
      await goToStep2();

      const codeInput = screen.getByLabelText("auth.passwordReset.code.label");
      await user.type(codeInput, "123456");

      const passwordInput = screen.getByLabelText(
        "auth.passwordReset.newPassword.label",
      );
      await user.type(passwordInput, "StrongPass1234");

      const confirmInput = screen.getByLabelText(
        "auth.passwordReset.confirmPassword.label",
      );
      await user.type(confirmInput, "OtherPass1234");

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole("alert")).toHaveTextContent(
          "auth.passwordReset.error.passwordMismatch",
        );
      });
    });

    it("llama a POST /auth/reset-password con todos los datos correctos", async () => {
      const user = userEvent.setup();
      mockApiFetch.mockResolvedValueOnce({
        message: "ok",
        expires_minutes: 15,
      });
      mockApiFetch.mockResolvedValueOnce({
        token: "1|abc123",
        user: { id: 1, email: "jane@example.com" },
      });
      await goToStep2();

      const codeInput = screen.getByLabelText("auth.passwordReset.code.label");
      await user.type(codeInput, "123456");

      const passwordInput = screen.getByLabelText(
        "auth.passwordReset.newPassword.label",
      );
      await user.type(passwordInput, "StrongPass1234");

      const confirmInput = screen.getByLabelText(
        "auth.passwordReset.confirmPassword.label",
      );
      await user.type(confirmInput, "StrongPass1234");

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(mockApiFetch).toHaveBeenCalledWith(
          "/auth/reset-password",
          expect.objectContaining({
            method: "POST",
            body: {
              email: "jane@example.com",
              code: "123456",
              password: "StrongPass1234",
              password_confirmation: "StrongPass1234",
              device_name: "web-admin",
            },
          }),
        );
      });

      await waitFor(() => {
        expect(mockSetSession).toHaveBeenCalledWith(
          expect.objectContaining({
            token: "1|abc123",
            user: expect.objectContaining({ email: "jane@example.com" }),
          }),
        );
      });
    });

    it("muestra error si el backend rechaza el código", async () => {
      const user = userEvent.setup();
      mockApiFetch.mockResolvedValueOnce({
        message: "ok",
        expires_minutes: 15,
      });
      mockApiFetch.mockRejectedValueOnce({
        message: "El código es inválido o ha expirado.",
        errors: { code: ["El código es inválido o ha expirado."] },
      });
      await goToStep2();

      const codeInput = screen.getByLabelText("auth.passwordReset.code.label");
      await user.type(codeInput, "000000");

      const passwordInput = screen.getByLabelText(
        "auth.passwordReset.newPassword.label",
      );
      await user.type(passwordInput, "StrongPass1234");

      const confirmInput = screen.getByLabelText(
        "auth.passwordReset.confirmPassword.label",
      );
      await user.type(confirmInput, "StrongPass1234");

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole("alert")).toHaveTextContent(
          "El código es inválido o ha expirado.",
        );
      });
    });
  });

  describe("close", () => {
    it("llama onClose al click en el botón cerrar", async () => {
      const onClose = vi.fn();
      const user = userEvent.setup();
      render(<HelpLoginModal brand={brand} onClose={onClose} />);

      const closeButton = screen.getByRole("button", {
        name: "auth.passwordReset.close",
      });
      await user.click(closeButton);

      expect(onClose).toHaveBeenCalledTimes(1);
    });
  });

  describe("show password toggle", () => {
    it("alterna el tipo de input de password a text y viceversa", async () => {
      const user = userEvent.setup();
      render(<HelpLoginModal brand={brand} onClose={vi.fn()} />);

      // Sin step 2 no tenemos el input de password. Necesitamos el código.
      mockApiFetch.mockResolvedValue({ message: "ok", expires_minutes: 15 });

      const emailInput = screen.getByLabelText("auth.passwordReset.email.label");
      await user.type(emailInput, "jane@example.com");

      const submitButton = screen.getByRole("button", {
        name: "auth.passwordReset.email.submit",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(
          screen.getByLabelText("auth.passwordReset.newPassword.label"),
        ).toBeInTheDocument();
      });

      const passwordInput = screen.getByLabelText(
        "auth.passwordReset.newPassword.label",
      );
      expect(passwordInput).toHaveAttribute("type", "password");

      const toggle = screen.getByRole("checkbox", {
        name: /auth\.passwordReset\.password\.(show|hide)/,
      });
      await user.click(toggle);

      expect(passwordInput).toHaveAttribute("type", "text");
    });
  });
});