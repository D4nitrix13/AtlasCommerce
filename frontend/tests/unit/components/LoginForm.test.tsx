// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

// Mocks hoisted: vi.mock corre antes del import, asi que las variables
// tienen que estar disponibles en la fase de hoisting.
const { mockLogin, mockSetSession, mockApiFetch } = vi.hoisted(() => ({
  mockLogin: vi.fn(),
  mockSetSession: vi.fn(),
  mockApiFetch: vi.fn(),
}));

vi.mock("../../../src/stores/auth", () => ({
  useAuth: () => ({
    login: mockLogin,
    setSession: mockSetSession,
  }),
  authStore: { login: mockLogin, setSession: mockSetSession },
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

import LoginForm from "../../../src/components/admin/auth/LoginForm";

const loginPath = "/es/admin/auth/login";

beforeEach(() => {
  mockLogin.mockReset();
  mockSetSession.mockReset();
  mockApiFetch.mockReset();
  window.history.replaceState({}, "", loginPath);
});

describe("components/admin/auth/LoginForm", () => {
  describe("tab inicial", () => {
    it("muestra tab=login por defecto cuando no hay query param", () => {
      render(<LoginForm />);

      const loginTab = screen.getByRole("tab", {
        name: "auth.login.tab.signin",
      });
      expect(loginTab).toHaveAttribute("aria-selected", "true");
    });

    it("muestra tab=register cuando la URL tiene ?tab=register", async () => {
      window.history.replaceState({}, "", `${loginPath}?tab=register`);

      render(<LoginForm />);

      await waitFor(() => {
        const registerTab = screen.getByRole("tab", {
          name: "auth.login.tab.signup",
        });
        expect(registerTab).toHaveAttribute("aria-selected", "true");
      });

      const loginTab = screen.getByRole("tab", {
        name: "auth.login.tab.signin",
      });
      expect(loginTab).toHaveAttribute("aria-selected", "false");
    });

    it("respeta initialTab prop pasado desde el padre", () => {
      render(<LoginForm initialTab="register" />);

      const registerTab = screen.getByRole("tab", {
        name: "auth.login.tab.signup",
      });
      expect(registerTab).toHaveAttribute("aria-selected", "true");
    });
  });

  describe("cambio de tab", () => {
    it("click en Registrarse actualiza la URL sin recargar", async () => {
      const user = userEvent.setup();
      render(<LoginForm />);

      const registerTab = screen.getByRole("tab", {
        name: "auth.login.tab.signup",
      });
      await user.click(registerTab);

      expect(window.location.search).toContain("tab=register");
    });

    it("click en Iniciar sesión borra el query param", async () => {
      const user = userEvent.setup();
      window.history.replaceState({}, "", `${loginPath}?tab=register`);
      render(<LoginForm />);

      const loginTab = screen.getByRole("tab", {
        name: "auth.login.tab.signin",
      });
      await user.click(loginTab);

      expect(window.location.search).not.toContain("tab=");
    });

    it("no actualiza URL si clickeas el tab activo", async () => {
      const user = userEvent.setup();
      render(<LoginForm />);

      const loginTab = screen.getByRole("tab", {
        name: "auth.login.tab.signin",
      });
      await user.click(loginTab);

      expect(window.location.search).not.toContain("tab=");
    });

    it("cambia aria-selected al cambiar de tab", async () => {
      const user = userEvent.setup();
      render(<LoginForm />);

      const loginTab = screen.getByRole("tab", {
        name: "auth.login.tab.signin",
      });
      const registerTab = screen.getByRole("tab", {
        name: "auth.login.tab.signup",
      });

      expect(loginTab).toHaveAttribute("aria-selected", "true");
      expect(registerTab).toHaveAttribute("aria-selected", "false");

      await user.click(registerTab);

      await waitFor(() => {
        expect(registerTab).toHaveAttribute("aria-selected", "true");
        expect(loginTab).toHaveAttribute("aria-selected", "false");
      });
    });
  });

  describe("reset de campos al cambiar de tab", () => {
    it("limpia email/password al cambiar de tab", async () => {
      const user = userEvent.setup();
      render(<LoginForm />);

      const emailInput = screen.getByPlaceholderText(
        "auth.login.email.placeholder",
      );
      const passwordInput = screen.getByPlaceholderText(
        "auth.login.password.placeholder",
      );

      await user.type(emailInput, "user@example.com");
      await user.type(passwordInput, "secretpassword");

      const registerTab = screen.getByRole("tab", {
        name: "auth.login.tab.signup",
      });
      await user.click(registerTab);

      expect(emailInput).toHaveValue("");
      expect(passwordInput).toHaveValue("");
    });

    it("muestra campos first_name/last_name solo en tab=register", async () => {
      const user = userEvent.setup();
      render(<LoginForm />);

      expect(
        screen.queryByPlaceholderText("auth.login.firstName.placeholder"),
      ).toBeNull();

      const registerTab = screen.getByRole("tab", {
        name: "auth.login.tab.signup",
      });
      await user.click(registerTab);

      await waitFor(() => {
        expect(
          screen.getByPlaceholderText("auth.login.firstName.placeholder"),
        ).toBeInTheDocument();
      });
    });
  });

  describe("submit", () => {
    it("login exitoso llama a authStore.login", async () => {
      const user = userEvent.setup();
      const fakeResponse = {
        token: "tok123",
        user: {
          id: 1,
          email: "test@example.com",
          full_name: "Test",
          role: null,
          role_id: null,
          store_ids: [1],
          is_active: true,
          permissions: [],
        },
      };
      mockLogin.mockResolvedValue(fakeResponse);

      render(<LoginForm />);

      const emailInput = screen.getByPlaceholderText(
        "auth.login.email.placeholder",
      );
      const passwordInput = screen.getByPlaceholderText(
        "auth.login.password.placeholder",
      );

      await user.type(emailInput, "test@example.com");
      await user.type(passwordInput, "secret-password");

      const submitButton = screen.getByRole("button", {
        name: "auth.login.submit.idle",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(mockLogin).toHaveBeenCalledWith({
          email: "test@example.com",
          password: "secret-password",
        });
      });
    });

    it("register exitoso llama a /auth/register y setSession", async () => {
      const user = userEvent.setup();
      const fakeResponse = {
        token: "tok456",
        user: {
          id: 2,
          email: "new@example.com",
          full_name: "New User",
          role: null,
          role_id: null,
          store_ids: [1],
          is_active: true,
          permissions: [],
        },
      };
      mockApiFetch.mockResolvedValue(fakeResponse);

      window.history.replaceState({}, "", `${loginPath}?tab=register`);
      render(<LoginForm />);

      const firstNameInput = screen.getByPlaceholderText(
        "auth.login.firstName.placeholder",
      );
      const lastNameInput = screen.getByPlaceholderText(
        "auth.login.lastName.placeholder",
      );
      const emailInput = screen.getByPlaceholderText(
        "auth.login.email.placeholder",
      );
      const passwordInput = screen.getByPlaceholderText(
        "auth.login.password.placeholder",
      );

      await user.type(firstNameInput, "New");
      await user.type(lastNameInput, "User");
      await user.type(emailInput, "new@example.com");
      await user.type(passwordInput, "StrongPass2026");

      const submitButton = screen.getByRole("button", {
        name: "auth.login.submit.create",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(mockApiFetch).toHaveBeenCalledWith(
          "/auth/register",
          expect.objectContaining({
            method: "POST",
            body: expect.objectContaining({
              first_name: "New",
              last_name: "User",
              email: "new@example.com",
              password: "StrongPass2026",
            }),
          }),
        );
        expect(mockSetSession).toHaveBeenCalledWith(fakeResponse);
      });
    });

    it("login fallido muestra mensaje de error", async () => {
      const user = userEvent.setup();
      mockLogin.mockRejectedValue({
        status: 422,
        message: "Las credenciales no son válidas.",
        errors: null,
      });

      render(<LoginForm />);

      const emailInput = screen.getByPlaceholderText(
        "auth.login.email.placeholder",
      );
      const passwordInput = screen.getByPlaceholderText(
        "auth.login.password.placeholder",
      );

      await user.type(emailInput, "bad@example.com");
      await user.type(passwordInput, "wrong");

      const submitButton = screen.getByRole("button", {
        name: "auth.login.submit.idle",
      });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole("alert")).toHaveTextContent(
          /credenciales no son válidas/i,
        );
      });
    });
  });
});
