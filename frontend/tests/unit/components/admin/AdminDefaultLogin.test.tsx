// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

const { mockLogin, mockSetSession } = vi.hoisted(() => ({
  mockLogin: vi.fn(),
  mockSetSession: vi.fn(),
}));

vi.mock("../../../../src/stores/auth", () => ({
  useAuth: () => ({
    login: mockLogin,
    setSession: mockSetSession,
  }),
  authStore: { login: mockLogin, setSession: mockSetSession },
}));

vi.mock("../../../../src/i18n", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../../../../src/i18n")>();
  return {
    ...actual,
    useTranslations: () => ({
      t: (key: string) => key,
      locale: "es",
    }),
  };
});

import LoginForm from "../../../../src/components/admin/auth/LoginForm";

const loginPath = "/es/admin/auth/login";

/**
 * Tests E2E del flow admin login con las credenciales por defecto
 * creadas por el seeder backend (`admin@<slug>.com` / `password`).
 *
 * El objetivo es asegurar que el frontend puede autenticar a un admin
 * recién creado con las credenciales que el seeder le asigna, sin que
 * la primera pantalla de login requiera acciones adicionales.
 */
describe("admin login con credenciales por defecto (admin@<slug>.com / password)", () => {
  beforeEach(() => {
    mockLogin.mockReset();
    mockSetSession.mockReset();
    window.history.replaceState({}, "", loginPath);
  });

  describe("cada tenant default puede autenticar desde su dominio", () => {
    const tenants = [
      "panda-estampados",
      "kitsune",
      "mm-boutique",
      "morales-store",
    ] as const;

    for (const slug of tenants) {
      it(`admin@${slug}.com / password dispara el login`, async () => {
        const user = userEvent.setup();
        mockLogin.mockResolvedValue({
          token: `tok-${slug}`,
          user: {
            id: 1,
            email: `admin@${slug}.com`,
            full_name: "Admin",
            role: { id: 1, slug: "admin", name: "Administrador" },
            role_id: 1,
            store_ids: [1],
            is_active: true,
            permissions: [],
          },
        });

        render(<LoginForm />);

        const emailInput = screen.getByLabelText("auth.login.shell.emailLabel");
        const passwordInput = screen.getByLabelText("auth.login.shell.passwordLabel");

        await user.type(emailInput, `admin@${slug}.com`);
        await user.type(passwordInput, "password");

        const submitButton = screen.getByRole("button", {
          name: "auth.login.shell.submit",
        });
        await user.click(submitButton);

        await waitFor(() => {
          expect(mockLogin).toHaveBeenCalledWith(expect.objectContaining({
            email: `admin@${slug}.com`,
            password: "password",
          }));
        });
      });
    }
  });

  it("login exitoso con credenciales default devuelve token y user", async () => {
    const user = userEvent.setup();
    const expectedResponse = {
      token: "sanctum-token-abc",
      user: {
        id: 42,
        email: "admin@panda-estampados.com",
        full_name: "Admin Panda",
        role: { id: 1, slug: "admin", name: "Administrador" },
        role_id: 1,
        store_ids: [1],
        is_active: true,
        permissions: [],
      },
    };
    mockLogin.mockResolvedValue(expectedResponse);

    render(<LoginForm />);

    await user.type(
      screen.getByLabelText("auth.login.shell.emailLabel"),
      "admin@panda-estampados.com",
    );
    await user.type(
      screen.getByLabelText("auth.login.shell.passwordLabel"),
      "password",
    );

    await user.click(
      screen.getByRole("button", { name: "auth.login.shell.submit" }),
    );

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledTimes(1);
      expect(mockLogin).toHaveBeenCalledWith(expect.objectContaining({
        email: "admin@panda-estampados.com",
        password: "password",
      }));
    });
  });

  it("login con credenciales default en dominio admin.<slug>.com funciona", async () => {
    const user = userEvent.setup();
    window.history.replaceState(
      {},
      "",
      "/es/admin/auth/login?from=admin.panda-estampados.com",
    );
    mockLogin.mockResolvedValue({
      token: "tok-admin-domain",
      user: {
        id: 1,
        email: "admin@panda-estampados.com",
        full_name: "Admin",
        role: { id: 1, slug: "admin", name: "Administrador" },
        role_id: 1,
        store_ids: [1],
        is_active: true,
        permissions: [],
      },
    });

    render(<LoginForm />);

    await user.type(
      screen.getByLabelText("auth.login.shell.emailLabel"),
      "admin@panda-estampados.com",
    );
    await user.type(
      screen.getByLabelText("auth.login.shell.passwordLabel"),
      "password",
    );

    await user.click(
      screen.getByRole("button", { name: "auth.login.shell.submit" }),
    );

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledWith(expect.objectContaining({
        email: "admin@panda-estampados.com",
        password: "password",
      }));
    });
  });

  it("password incorrecta con email default muestra error de credenciales", async () => {
    const user = userEvent.setup();
    mockLogin.mockRejectedValue({
      status: 422,
      message: "Las credenciales no son válidas.",
      errors: null,
    });

    render(<LoginForm />);

    await user.type(
      screen.getByLabelText("auth.login.shell.emailLabel"),
      "admin@panda-estampados.com",
    );
    await user.type(
      screen.getByLabelText("auth.login.shell.passwordLabel"),
      "WRONG-password",
    );

    await user.click(
      screen.getByRole("button", { name: "auth.login.shell.submit" }),
    );

    await waitFor(() => {
      expect(
        screen.getByRole("alert"),
      ).toHaveTextContent(/credenciales no son válidas/i);
    });
  });

  it("login con la cuenta default desactivada muestra error específico", async () => {
    const user = userEvent.setup();
    mockLogin.mockRejectedValue({
      status: 422,
      message: "La cuenta está desactivada.",
      errors: null,
    });

    render(<LoginForm />);

    await user.type(
      screen.getByLabelText("auth.login.shell.emailLabel"),
      "admin@panda-estampados.com",
    );
    await user.type(
      screen.getByLabelText("auth.login.shell.passwordLabel"),
      "password",
    );

    await user.click(
      screen.getByRole("button", { name: "auth.login.shell.submit" }),
    );

    await waitFor(() => {
      expect(screen.getByRole("alert")).toHaveTextContent(
        /cuenta está desactivada/i,
      );
    });
  });

  it("deshabilita el botón mientras el submit está en curso", async () => {
    const user = userEvent.setup();
    let resolveLogin: (value: unknown) => void = () => {};
    mockLogin.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveLogin = resolve;
        }),
    );

    render(<LoginForm />);

    await user.type(
      screen.getByLabelText("auth.login.shell.emailLabel"),
      "admin@panda-estampados.com",
    );
    await user.type(
      screen.getByLabelText("auth.login.shell.passwordLabel"),
      "password",
    );

    const submitButton = screen.getByRole("button", {
      name: "auth.login.shell.submit",
    });
    await user.click(submitButton);

    // El botón debe quedar disabled y mostrar el texto de "cargando".
    await waitFor(() => {
      expect(submitButton).toBeDisabled();
      expect(submitButton).toHaveTextContent(/auth.login.shell.submitLoading/);
    });

    // Resolvemos para no dejar la promise pendiente y que el test termine.
    resolveLogin({
      token: "tok",
      user: {
        id: 1,
        email: "admin@panda-estampados.com",
        full_name: "Admin",
        role: { id: 1, slug: "admin", name: "Administrador" },
        role_id: 1,
        store_ids: [1],
        is_active: true,
        permissions: [],
      },
    });
  });
});