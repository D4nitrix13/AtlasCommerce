/**
 * storageKeys.test.ts
 *
 * The localStorage key helpers live in `lib/rem/types.ts` and are the
 * single source of truth for every key the sidebar reads or writes.
 * A typo here breaks `localStorage` state for the whole feature, so
 * we lock the strings down with these 3 `it` blocks per NFR-13
 * (sidebar state stability).
 */

import { describe, expect, it } from "vitest";

import {
  expandedStorageKey,
  nestedExpandedStorageKey,
  SIDEBAR_WIDTH_STORAGE_KEY,
} from "../../../../src/lib/rem/types";

describe("Sidebar localStorage key helpers · REQ-13/14/15", () => {
  it("expandedStorageKey returns the canonical prefix + group id", () => {
    expect(expandedStorageKey("rf")).toBe("rem.viewer.expanded.rf");
    expect(expandedStorageKey("rnf")).toBe("rem.viewer.expanded.rnf");
    expect(expandedStorageKey("stakeholders")).toBe(
      "rem.viewer.expanded.stakeholders",
    );
  });

  it("nestedExpandedStorageKey composes group + bucket name", () => {
    expect(nestedExpandedStorageKey("rf", "Carrito")).toBe(
      "rem.viewer.expanded.rf.Carrito",
    );
    expect(nestedExpandedStorageKey("rnf", "Seguridad")).toBe(
      "rem.viewer.expanded.rnf.Seguridad",
    );
  });

  it("SIDEBAR_WIDTH_STORAGE_KEY is the literal v2 contract", () => {
    expect(SIDEBAR_WIDTH_STORAGE_KEY).toBe("rem.viewer.sidebar.width");
  });
});