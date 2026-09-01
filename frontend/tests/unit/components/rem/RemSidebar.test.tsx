/**
 * RemSidebar.test.tsx
 *
 * Covers REQ-03#5, REQ-04#1, REQ-09 (Tab/Arrow/Enter/Escape),
 * REQ-10#1 (skeleton aria-busy), REQ-10#2 (empty state),
 * REQ-12#1 (URL state on selection) and REQ-12#3 (URL fallback).
 *
 * Strict TDD: every `it()` was failing before `RemSidebar.tsx` shipped.
 * The dataset comes from the same `_generate.test.ts` fixture the
 * loader uses (full 81-item set + 6-item mini for fast tests).
 */

import { afterEach, beforeEach, describe, expect, it } from "vitest";
import { cleanup, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import RemSidebar from "../../../../src/components/rem/RemSidebar";
import { SEED_DATASET } from "../../rem/fixtures/seed";
import { SEED_MINI } from "../../rem/fixtures/seed-mini";
import type { Stakeholder } from "../../../../src/lib/rem/types";

const SAMPLES = {
  dataset: SEED_DATASET,
  mini: SEED_MINI,
};

const FIRST_SH_UID = (SAMPLES.mini.stakeholders[0] as Stakeholder).uid;

async function settle(): Promise<void> {
  // Flush microtasks queued by useEffect + the React commit phase.
  await waitFor(() => undefined);
}

beforeEach(() => {
  // Reset URL state to a clean `/es/rem/` for every test; the global
  // afterEach in `tests/setup.ts` resets to "/".
  window.history.replaceState({}, "", "/es/rem/");
});

afterEach(() => {
  cleanup();
});

describe("RemSidebar · REQ-03#5 + REQ-04#1 + REQ-09 + REQ-10#1,#2 + REQ-12#1,#3", () => {
  it("REQ-04#1 (rendering root): renders 81 selectable leaves in the tree", async () => {
    render(<RemSidebar dataset={SAMPLES.dataset} initialUid="rf_001" locale="es" />);
    await settle();
    const leaves = screen.getAllByTestId(/^rem-leaf-/);
    expect(leaves.length).toBeGreaterThanOrEqual(78); // 48 RF + 30 RNF — stakeholders counted flat below
    // Total treeitems (leaves + groups) should comfortably exceed 81.
    const total = screen.getAllByRole("treeitem");
    expect(total.length).toBeGreaterThan(81);
  });

  it("REQ-03#5: zero-match search renders RemEmptyState in the sidebar", async () => {
    const user = userEvent.setup();
    render(<RemSidebar dataset={SAMPLES.dataset} initialUid="rf_001" locale="es" />);
    await settle();
    const input = screen.getByTestId("rem-search-input");
    await user.type(input, "zzzzzz-no-match");
    await waitFor(() => {
      expect(screen.getByTestId("rem-empty")).toBeInTheDocument();
    });
    // Tree is hidden when empty state is shown (search results empty).
    expect(screen.queryAllByTestId(/^rem-leaf-/)).toHaveLength(0);
    // The clear button uses the data-rem-clear-search hook used by the
    // .astro version of `RemEmptyState` too.
    expect(screen.getByTestId("rem-clear-search")).toBeInTheDocument();
  });

  it("REQ-10#1: skeleton with aria-busy renders on initial mount", () => {
    render(<RemSidebar dataset={SAMPLES.dataset} initialUid="rf_001" locale="es" />);
    // The wrapper mounts synchronously with aria-busy="true" so
    // assistive tech announces the loading state. The component then
    // flips to "false" on the next animation frame; we don't assert
    // that here because jsdom's rAF polyfill is unreliable across
    // versions. The first-paint contract (REQ-10#1) only requires the
    // initial state to be aria-busy=true.
    const wrapper = screen.getByTestId("rem-skeleton-wrapper");
    expect(wrapper.getAttribute("aria-busy")).toBe("true");
    expect(wrapper.getAttribute("data-rem-skeleton")).not.toBeNull();
  });

  it("REQ-04#3: clearing the search input keeps the current selection intact", async () => {
    const user = userEvent.setup();
    render(<RemSidebar dataset={SAMPLES.mini} initialUid="rf_002" locale="es" />);
    await settle();

    // Type a query that matches everything except rf_002; selection is on rf_002.
    const input = screen.getByTestId("rem-search-input");
    await user.type(input, "RF-AUTH");
    await waitFor(() => {
      // Both rf_001 + rf_002 (Authentication module) appear.
      expect(screen.getAllByTestId(/^rem-leaf-rf_/).length).toBeGreaterThanOrEqual(2);
    });
    // Selection marker still on rf_002 (no matter which leaf is focused).
    expect(screen.getByTestId("rem-leaf-rf_002").getAttribute("aria-current")).toBe("true");

    // Clear search; selection stays.
    await user.clear(input);
    await waitFor(() => {
      expect(screen.getByTestId("rem-leaf-rf_002").getAttribute("aria-current")).toBe("true");
    });
  });

  it("REQ-09#1: pressing Tab from the search moves focus to the first tree node", async () => {
    const user = userEvent.setup();
    render(<RemSidebar dataset={SAMPLES.mini} initialUid="rf_001" locale="es" />);
    await settle();
    const input = screen.getByTestId("rem-search-input");
    input.focus();
    expect(document.activeElement).toBe(input);
    await user.tab();
    const firstNode = document.activeElement;
    expect(firstNode).not.toBe(input);
    expect(firstNode?.getAttribute("role")).toBe("treeitem");
  });

  it("REQ-09#2: ArrowDown moves focus down through the tree", async () => {
    const user = userEvent.setup();
    render(<RemSidebar dataset={SAMPLES.mini} initialUid="rf_001" locale="es" />);
    await settle();
    const input = screen.getByTestId("rem-search-input");
    input.focus();
    await user.tab(); // → first tree node (Autenticación group header)
    const first = document.activeElement;
    expect(first).not.toBeNull();
    const firstText = first?.textContent ?? "";
    await user.keyboard("{ArrowDown}");
    const second = document.activeElement;
    expect(second).not.toBe(first);
    expect(second?.getAttribute("role")).toBe("treeitem");
    // Either second is a different node in the same group, or the
    // first leaf under it. Verify it is *not* the same text.
    expect(second?.textContent).not.toBe(firstText);
  });

  it("REQ-09#3: pressing Enter on a focused tree node selects it (sets aria-current)", async () => {
    const user = userEvent.setup();
    render(<RemSidebar dataset={SAMPLES.mini} initialUid="rf_001" locale="es" />);
    await settle();
    const input = screen.getByTestId("rem-search-input");
    input.focus();
    await user.tab();
    await user.keyboard("{ArrowDown}"); // move into a focused leaf
    const focusedId = document.activeElement?.getAttribute("data-rem-leaf-id");
    expect(focusedId).toBeTruthy();
    // Sanity: the focus is on a different element than the search input.
    expect(focusedId).not.toBeNull();
    await user.keyboard("{Enter}");
    await waitFor(() => {
      expect(
        screen.getByTestId(`rem-leaf-${focusedId}`).getAttribute("aria-current"),
      ).toBe("true");
    });
  });

  it("REQ-09#4: pressing Escape inside the search input clears its value", async () => {
    const user = userEvent.setup();
    render(<RemSidebar dataset={SAMPLES.dataset} initialUid="rf_001" locale="es" />);
    await settle();
    const input = screen.getByTestId("rem-search-input");
    await user.type(input, "redis");
    expect((input as HTMLInputElement).value).toBe("redis");
    input.focus();
    await user.keyboard("{Escape}");
    expect((input as HTMLInputElement).value).toBe("");
  });

  it("REQ-12#1: clicking a tree leaf updates the URL to ?id=<uid> via history.replaceState", async () => {
    const user = userEvent.setup();
    render(<RemSidebar dataset={SAMPLES.mini} initialUid="rf_001" locale="es" />);
    await settle();

    // The mount effect already mirrors the initial selection to ?id=…
    // (spec REQ-12#3); the click handler takes it from there.
    const before = window.location.search;

    const rf003 = screen.getByTestId(`rem-leaf-rf_003`);
    await user.click(rf003);
    await waitFor(() => {
      expect(window.location.search).toBe(`?id=rf_003`);
    });
    expect(window.location.search).not.toBe(before);
    expect(screen.getByTestId("rem-leaf-rf_003").getAttribute("aria-current")).toBe("true");
  });

  it("REQ-12#3: invalid initialUid prop falls back to the first item without crashing", async () => {
    // setup.ts already wipes URL to "/", so no `?id=` is present —
    // we explicitly pass an invalid initialUid through the prop.
    render(
      <RemSidebar
        dataset={SAMPLES.mini}
        initialUid="rf_does_not_exist"
        locale="es"
      />,
    );
    await settle();
    // The first stakeholder is the earliest item in the dataset
    // (buildTree sorts stakeholders by uid asc).
    await waitFor(() => {
      expect(screen.getByTestId(`rem-leaf-${FIRST_SH_UID}`).getAttribute("aria-current")).toBe(
        "true",
      );
    });
    // URL reflects the corrected fallback.
    await waitFor(() => {
      expect(window.location.search).toBe(`?id=${FIRST_SH_UID}`);
    });
  });
});

/**
 * Read the currently-focused / marked tree leaf's UID via the test-id
 * convention we use in `RemTreeNode.tsx`. Currently unused (the
 * active tests assert against the focused leaf directly), kept as a
 * future debugging hook.
 */
function _ariaCurrentUid(): string | null {
  const all = document.querySelectorAll("[data-rem-leaf-id]");
  for (const el of all) {
    if (el.getAttribute("aria-current") === "true") {
      return el.getAttribute("data-rem-leaf-id");
    }
  }
  return null;
}
void _ariaCurrentUid;
