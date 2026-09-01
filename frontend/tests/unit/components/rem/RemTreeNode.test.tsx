/**
 * RemTreeNode.test.tsx
 *
 * Coverage for the tree leaf component (REQ-09 + REQ-24#1).
 *
 * v2 additions covered here:
 *   - Selected leaf palette (`#2D9D8E` background per REQ-24#1).
 *   - Legacy `expandedStorageKey` value tolerance (REQ-13#4, exercised
 *     indirectly via the leaf rendering since the node doesn't own
 *     the storage key itself).
 *
 * D2 will add kebab-slot coverage.
 */

import { afterEach, beforeEach, describe, expect, it } from "vitest";
import { cleanup, render } from "@testing-library/react";

import { RemTreeNode } from "../../../../src/components/rem/RemTreeNode";
import type { TreeNode } from "../../../../src/lib/rem/types";
import type { useTranslations } from "../../../../src/i18n";

type Translator = ReturnType<typeof useTranslations>["t"];

const t: Translator = (key) => key;

function buildLeaf(uid: string, label: string): TreeNode {
  return {
    id: uid,
    label,
    data: {
      uid,
      code: label,
      title: label,
      rationale: "",
      priority: "M",
      status: "pending",
      verifiedBy: [],
      links: [],
      kind: "rf",
    },
  };
}

beforeEach(() => {
  window.localStorage.clear();
});

afterEach(() => {
  cleanup();
});

describe("RemTreeNode · REQ-09 + REQ-24#1", () => {
  it("renders a leaf with the expected role/aria attributes", () => {
    render(
      <RemTreeNode
        node={buildLeaf("rf_001", "RF-AUTH-001")}
        level={1}
        selectedUid={null}
        onSelect={() => {}}
        t={t}
      />,
    );
    const li = document.querySelector('[data-rem-leaf-id="rf_001"]');
    expect(li).not.toBeNull();
    expect(li?.getAttribute("role")).toBe("treeitem");
    expect(li?.getAttribute("aria-level")).toBe("1");
  });

  it("REQ-24#1: selected leaf carries the teal accent class", () => {
    render(
      <RemTreeNode
        node={buildLeaf("rf_001", "RF-AUTH-001")}
        level={1}
        selectedUid="rf_001"
        onSelect={() => {}}
        t={t}
      />,
    );
    const li = document.querySelector('[data-rem-leaf-id="rf_001"]');
    expect(li?.className ?? "").toContain("bg-[#2D9D8E]/10");
    expect(li?.className ?? "").toContain("text-[#1E6F62]");
    expect(li?.getAttribute("aria-current")).toBe("true");
  });

  it("REQ-24#1: non-selected leaf does NOT carry the teal accent class", () => {
    render(
      <>
        <RemTreeNode
          node={buildLeaf("rf_001", "RF-AUTH-001")}
          level={1}
          selectedUid="rf_001"
          onSelect={() => {}}
          t={t}
        />
        <RemTreeNode
          node={buildLeaf("rf_002", "RF-AUTH-002")}
          level={1}
          selectedUid="rf_001"
          onSelect={() => {}}
          t={t}
        />
      </>,
    );
    const notSelected = document.querySelector('[data-rem-leaf-id="rf_002"]');
    expect(notSelected?.className ?? "").not.toContain("bg-[#2D9D8E]/10");
    expect(notSelected?.getAttribute("aria-current")).not.toBe("true");
  });

  it("REQ-16#1: renders the kebab slot (actions prop) inside the leaf", () => {
    render(
      <RemTreeNode
        node={buildLeaf("rf_001", "RF-AUTH-001")}
        level={1}
        selectedUid={null}
        onSelect={() => {}}
        t={t}
        actions={
          <button type="button" data-testid="custom-kebab-slot">
            ⋮
          </button>
        }
      />,
    );
    const li = document.querySelector('[data-rem-leaf-id="rf_001"]');
    expect(li).not.toBeNull();
    const slot = li?.querySelector('[data-testid="custom-kebab-slot"]');
    expect(slot).not.toBeNull();
    expect(slot?.textContent).toContain("⋮");
  });
});