/**
 * RemGroupShell.test.tsx
 *
 * Coverage for the v2 collapsible group shell (Tasks 1.4 / REQ-13 +
 * REQ-14). Strict TDD: every `it()` ships in the same D1 commit as
 * `RemGroupShell.tsx`.
 *
 * The 6 `it` blocks cover REQ-13#1..#5 + REQ-14#1 (per tasks.md
 * Task 2.1):
 *   - 3 `<details data-rem-group-top>` rendered (REQ-13#1)
 *   - Click summary writes the closed key (REQ-13#2)
 *   - Reload preserves collapse state (REQ-13#3)
 *   - Legacy `'open' | 'closed'` values parse without throw (REQ-13#4)
 *   - First-visit defaults to open (REQ-13#5)
 *   - Nested `<details>` per RF `module` (REQ-14#1)
 */

import { afterEach, beforeEach, describe, expect, it } from "vitest";
import { cleanup, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import {
  RemGroupShell,
  RemNestedGroup,
} from "../../../../src/components/rem/RemGroupShell";
import {
  expandedStorageKey,
  nestedExpandedStorageKey,
} from "../../../../src/lib/rem/types";

beforeEach(() => {
  window.localStorage.clear();
});

afterEach(() => {
  cleanup();
});

describe("RemGroupShell · REQ-13#1..#5 + REQ-14#1", () => {
  it("renders a single <details data-rem-group-top> for the groupId (REQ-13#1)", () => {
    render(
      <RemGroupShell groupId="rf" label="Requisitos funcionales">
        <span data-testid="child">child content</span>
      </RemGroupShell>,
    );
    const details = document.querySelector('details[data-rem-group-top="rf"]');
    expect(details).not.toBeNull();
    expect(screen.getByTestId("child")).toBeInTheDocument();
    // Default-open on first visit (REQ-13#5).
    expect(details?.hasAttribute("open")).toBe(true);
  });

  it("writes the closed key to localStorage on toggle (REQ-13#2)", async () => {
    const user = userEvent.setup();
    const setSpy = vi.spyOn(window.localStorage, "setItem");
    render(
      <RemGroupShell groupId="rf" label="Requisitos funcionales">
        <span>x</span>
      </RemGroupShell>,
    );
    const details = document.querySelector<HTMLDetailsElement>(
      'details[data-rem-group-top="rf"]',
    );
    expect(details).not.toBeNull();
    // Native `<details>` toggles when the `<summary>` child is
    // activated; clicking the wrapper alone is a no-op in jsdom.
    const summary = details?.querySelector("summary");
    expect(summary).not.toBeNull();
    if (summary !== undefined && summary !== null) await user.click(summary);
    // Expect exactly one localStorage interaction per toggle.
    expect(setSpy).toHaveBeenCalledTimes(1);
    expect(window.localStorage.getItem(expandedStorageKey("rf"))).toBe(
      "closed",
    );
    setSpy.mockRestore();
  });

  it("reads the stored collapse state on mount (REQ-13#3)", () => {
    // Seed the closed state for `rnf`.
    window.localStorage.setItem(expandedStorageKey("rnf"), "closed");
    render(
      <RemGroupShell groupId="rnf" label="Requisitos no funcionales">
        <span>x</span>
      </RemGroupShell>,
    );
    const details = document.querySelector<HTMLDetailsElement>(
      'details[data-rem-group-top="rnf"]',
    );
    expect(details?.hasAttribute("open")).toBe(false);
  });

  it("tolerates legacy 'open' | 'closed' stored values (REQ-13#4)", () => {
    // Both shapes parse without throwing and yield the expected open state.
    window.localStorage.setItem(expandedStorageKey("rf"), "open");
    const { unmount } = render(
      <RemGroupShell groupId="rf" label="RF">
        <span>x</span>
      </RemGroupShell>,
    );
    let details = document.querySelector<HTMLDetailsElement>(
      'details[data-rem-group-top="rf"]',
    );
    expect(details?.hasAttribute("open")).toBe(true);
    unmount();

    window.localStorage.setItem(expandedStorageKey("rf"), "closed");
    render(
      <RemGroupShell groupId="rf" label="RF">
        <span>x</span>
      </RemGroupShell>,
    );
    details = document.querySelector<HTMLDetailsElement>(
      'details[data-rem-group-top="rf"]',
    );
    expect(details?.hasAttribute("open")).toBe(false);
  });

  it("defaults to open when no key is stored (REQ-13#5)", () => {
    // localStorage empty.
    render(
      <RemGroupShell groupId="stakeholders" label="Stakeholders">
        <span>x</span>
      </RemGroupShell>,
    );
    const details = document.querySelector<HTMLDetailsElement>(
      'details[data-rem-group-top="stakeholders"]',
    );
    expect(details?.hasAttribute("open")).toBe(true);
  });

  it("renders nested <details data-rem-group-nest> per RF module (REQ-14#1)", async () => {
    const user = userEvent.setup();
    // Pre-close the RF parent so the nested details are still
    // mounted and reachable by query.
    window.localStorage.setItem(expandedStorageKey("rf"), "closed");
    render(
      <RemGroupShell groupId="rf" label="Requisitos funcionales">
        <RemNestedGroup groupId="rf" bucketName="Autenticación" label="Autenticación">
          <li data-testid="leaf-1">RF-AUTH-001</li>
        </RemNestedGroup>
        <RemNestedGroup groupId="rf" bucketName="Carrito" label="Carrito">
          <li data-testid="leaf-2">RF-CART-010</li>
        </RemNestedGroup>
      </RemGroupShell>,
    );
    const nestAut = document.querySelector<HTMLDetailsElement>(
      'details[data-rem-group-nest="rf.Autenticación"]',
    );
    expect(nestAut).not.toBeNull();
    expect(nestAut?.hasAttribute("open")).toBe(true);

    // Collapse the Autenticación nested group; the Carrito one stays open.
    const setSpy = vi.spyOn(window.localStorage, "setItem");
    const summaryAut = nestAut?.querySelector("summary");
    if (summaryAut !== undefined && summaryAut !== null) {
      await user.click(summaryAut);
    }
    expect(window.localStorage.getItem(nestedExpandedStorageKey("rf", "Autenticación"))).toBe(
      "closed",
    );
    // Carrito untouched.
    expect(window.localStorage.getItem(nestedExpandedStorageKey("rf", "Carrito"))).toBeNull();
    setSpy.mockRestore();
  });
});

import { vi } from "vitest";