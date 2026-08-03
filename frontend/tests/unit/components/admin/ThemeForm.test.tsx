// @vitest-environment jsdom
import { describe, it, expect, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import ThemeForm from "../../../../src/components/admin/settings/ThemeForm";
import { DEFAULT_THEME } from "../../../../src/lib/theme";

const mockFetch = vi.fn();

vi.mock("../../../src/lib/api", () => ({
  apiFetch: (...args: unknown[]) => mockFetch(...args),
}));

const originalFetch = globalThis.fetch;
beforeEach(() => {
  mockFetch.mockReset();
  globalThis.fetch = mockFetch as unknown as typeof fetch;
});
afterEach(() => {
  globalThis.fetch = originalFetch;
});

describe("components/admin/settings/ThemeForm", () => {
  describe("renderizado inicial", () => {
    it("muestra todos los campos del theme", () => {
      render(
        <ThemeForm
          initialTheme={{
            ...DEFAULT_THEME,
            short_name: "Panda",
            accent_color: "#DC2626",
            support_email: "support@panda.com",
          }}
        />,
      );

      expect(screen.getByDisplayValue("Panda")).toBeInTheDocument();
      expect(screen.getByDisplayValue("#DC2626")).toBeInTheDocument();
      expect(screen.getByDisplayValue("support@panda.com")).toBeInTheDocument();
    });

    it("muestra defaults cuando el theme está vacío", () => {
      render(<ThemeForm initialTheme={DEFAULT_THEME} />);

      // Inputs vacíos (no value, no placeholder visible).
      const inputs = screen.getAllByRole("textbox");
      expect(inputs.length).toBeGreaterThan(0);
    });
  });

  describe("submit", () => {
    it("llama PATCH /api/admin/theme con el theme actualizado", async () => {
      const user = userEvent.setup();
      mockFetch.mockResolvedValue(
        new Response(
          JSON.stringify({
            data: {
              ...DEFAULT_THEME,
              short_name: "Panda",
              accent_color: "#FF0000",
            },
          }),
          { status: 200, headers: { "Content-Type": "application/json" } },
        ),
      );

      render(
        <ThemeForm
          initialTheme={{
            ...DEFAULT_THEME,
            short_name: "Panda",
            accent_color: "#DC2626",
          }}
        />,
      );

      // Cambiar accent color.
      const accentInput = screen.getAllByDisplayValue("#DC2626")[0];
      if (accentInput) {
        await user.clear(accentInput);
        await user.type(accentInput, "#FF0000");
      }

      // Submit.
      const submitButton = screen.getByRole("button", { name: /Guardar theme/i });
      await user.click(submitButton);

      await waitFor(() => {
        expect(mockFetch).toHaveBeenCalled();
      });

      const call = mockFetch.mock.calls[0] as [
        string,
        RequestInit,
      ];
      expect(call[0]).toBe("/api/admin/theme");
      expect(call[1].method).toBe("PATCH");
      const body = JSON.parse(call[1].body as string);
      expect(body.accent_color).toBe("#FF0000");
    });

    it("muestra mensaje de exito al guardar correctamente", async () => {
      const user = userEvent.setup();
      mockFetch.mockResolvedValue(
        new Response(JSON.stringify({ data: DEFAULT_THEME }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        }),
      );

      render(<ThemeForm initialTheme={DEFAULT_THEME} />);

      const submitButton = screen.getByRole("button", { name: /Guardar theme/i });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole("status")).toHaveTextContent(/guardado/i);
      });
    });

    it("muestra mensaje de error al fallar el guardado", async () => {
      const user = userEvent.setup();
      mockFetch.mockResolvedValue(
        new Response(
          JSON.stringify({ message: "Color invalido" }),
          {
            status: 422,
            headers: { "Content-Type": "application/json" },
          },
        ),
      );

      render(<ThemeForm initialTheme={DEFAULT_THEME} />);

      const submitButton = screen.getByRole("button", { name: /Guardar theme/i });
      await user.click(submitButton);

      await waitFor(() => {
        expect(screen.getByRole("alert")).toHaveTextContent(/Color invalido/);
      });
    });
  });
});
