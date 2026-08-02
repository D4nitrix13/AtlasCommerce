import "@testing-library/jest-dom/vitest";
import { afterEach, beforeAll, vi } from "vitest";
import { cleanup } from "@testing-library/react";

// jsdom 30 en Node 22+ no expone window.localStorage automáticamente.
// Polyfill defensivo: si falta, lo creamos en memoria.
beforeAll(() => {
  if (
    typeof globalThis !== "undefined" &&
    typeof (globalThis as { localStorage?: Storage }).localStorage === "undefined"
  ) {
    const store = new Map<string, string>();
    const fakeLocalStorage: Storage = {
      getItem: (key: string): string | null => store.get(key) ?? null,
      setItem: (key: string, value: string): void => {
        store.set(key, value);
      },
      removeItem: (key: string): void => {
        store.delete(key);
      },
      clear: (): void => {
        store.clear();
      },
      key: (index: number): string | null =>
        Array.from(store.keys())[index] ?? null,
      get length(): number {
        return store.size;
      },
    };
    (
      globalThis as unknown as { localStorage: Storage }
    ).localStorage = fakeLocalStorage;
    if (typeof window !== "undefined") {
      (
        window as unknown as { localStorage: Storage }
      ).localStorage = fakeLocalStorage;
    }
  }
});

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  if (typeof window !== "undefined" && window.localStorage) {
    window.localStorage.clear();
  }
  if (typeof window !== "undefined" && window.history) {
    window.history.replaceState({}, "", "/");
  }
});