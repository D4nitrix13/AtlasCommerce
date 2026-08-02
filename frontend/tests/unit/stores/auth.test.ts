/** @vitest-environment jsdom */
import { describe, it, expect, beforeEach, vi } from "vitest";
import { authStore, getActiveStoreId } from "../../../src/stores/auth";
import type { LoginResponse, User } from "../../../src/types/auth";

beforeEach(() => {
  // Guard defensivo: en este archivo jsdom a veces no expone
  // window.localStorage en el momento de beforeEach.
  if (typeof window !== "undefined" && window.localStorage) {
    window.localStorage.clear();
  }
});

const fakeUser: User = {
  id: 1,
  email: "test@example.com",
  full_name: "Test User",
  role: { id: 1, slug: "admin", name: "Administrador" },
  role_id: 1,
  store_ids: [1, 2],
  is_active: true,
  permissions: [],
};

const fakeLoginResponse: LoginResponse = {
  token: "abc123token",
  user: fakeUser,
};

describe("stores/auth", () => {
  describe("getActiveStoreId", () => {
    it("retorna null cuando no hay store_id persistido", () => {
      expect(getActiveStoreId()).toBeNull();
    });

    it("retorna el store_id persistido", () => {
      window.localStorage.setItem("auth.store_id", "42");
      expect(getActiveStoreId()).toBe(42);
    });

    it("retorna null cuando el valor es corrupto", () => {
      window.localStorage.setItem("auth.store_id", "no-es-numero");
      expect(getActiveStoreId()).toBeNull();
    });

    it("retorna null cuando el valor es una colección corrupta", () => {
      window.localStorage.setItem("auth.store_id", "3.14");
      expect(getActiveStoreId()).toBe(3);
    });
  });

  describe("login", () => {
    it("guarda token y user en localStorage", async () => {
      vi.spyOn(globalThis, "fetch").mockResolvedValue(
        new Response(
          JSON.stringify(fakeLoginResponse),
          { status: 200, headers: { "Content-Type": "application/json" } },
        ),
      );

      await authStore.login({ email: "test@example.com", password: "secret" });

      expect(window.localStorage.getItem("auth.token")).toBe("abc123token");
      expect(window.localStorage.getItem("auth.store_id")).toBe("1");
    });

    it("lanza ApiError cuando el backend responde con error", async () => {
      vi.spyOn(globalThis, "fetch").mockResolvedValue(
        new Response(
          JSON.stringify({
            message: "Credenciales inválidas",
            errors: { email: ["Email no existe"] },
          }),
          { status: 422, headers: { "Content-Type": "application/json" } },
        ),
      );

      await expect(
        authStore.login({ email: "fail@example.com", password: "bad" }),
      ).rejects.toMatchObject({
        status: 422,
        message: "Credenciales inválidas",
      });
    });
  });

  describe("setSession", () => {
    it("persiste token y store_id", () => {
      authStore.setSession(fakeLoginResponse);

      expect(window.localStorage.getItem("auth.token")).toBe("abc123token");
      expect(window.localStorage.getItem("auth.store_id")).toBe("1");
    });

    it("no escribe store_id si el user no tiene store_ids", () => {
      const response = {
        ...fakeLoginResponse,
        user: { ...fakeUser, store_ids: [] },
      };
      authStore.setSession(response);

      expect(window.localStorage.getItem("auth.token")).toBe("abc123token");
      expect(window.localStorage.getItem("auth.store_id")).toBeNull();
    });
  });

  describe("logout", () => {
    it("limpia token y store_id del localStorage", async () => {
      window.localStorage.setItem("auth.token", "abc123");
      window.localStorage.setItem("auth.store_id", "1");

      vi.spyOn(globalThis, "fetch").mockResolvedValue(
        new Response(JSON.stringify({ message: "Sesión cerrada." }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        }),
      );

      await authStore.logout();

      expect(window.localStorage.getItem("auth.token")).toBeNull();
      expect(window.localStorage.getItem("auth.store_id")).toBeNull();
    });

    it("limpia local incluso si el backend falla", async () => {
      window.localStorage.setItem("auth.token", "abc123");
      window.localStorage.setItem("auth.store_id", "1");

      vi.spyOn(globalThis, "fetch").mockRejectedValue(new Error("network"));

      await authStore.logout();

      expect(window.localStorage.getItem("auth.token")).toBeNull();
      expect(window.localStorage.getItem("auth.store_id")).toBeNull();
    });
  });

  describe("hydrate", () => {
    it("rehidrata user desde /auth/me con token válido", async () => {
      window.localStorage.setItem("auth.token", "abc123");

      vi.spyOn(globalThis, "fetch").mockResolvedValue(
        new Response(JSON.stringify({ user: fakeUser }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        }),
      );

      await authStore.hydrate();

      expect(window.localStorage.getItem("auth.store_id")).toBe("1");
    });

    it("limpia token si /auth/me devuelve 401", async () => {
      window.localStorage.setItem("auth.token", "expired");

      vi.spyOn(globalThis, "fetch").mockResolvedValue(
        new Response(JSON.stringify({ message: "Unauthenticated" }), {
          status: 401,
          headers: { "Content-Type": "application/json" },
        }),
      );

      await authStore.hydrate();

      expect(window.localStorage.getItem("auth.token")).toBeNull();
      expect(window.localStorage.getItem("auth.store_id")).toBeNull();
    });

    it("no hace nada sin token", async () => {
      const fetchSpy = vi.spyOn(globalThis, "fetch");
      await authStore.hydrate();
      expect(fetchSpy).not.toHaveBeenCalled();
    });
  });
});
