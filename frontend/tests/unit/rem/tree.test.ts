/**
 * Tree tests — REQ-02 (4 scenarios) + NFR-06 (deterministic).
 *
 * The tree builder groups RF by `module`, RNF by `category`, and
 * keeps stakeholders flat. Each group and stakeholder list is sorted
 * by `code` ascending. The function is pure, so the same dataset
 * must produce a byte-identical tree across many calls.
 *
 * Spec mapping:
 *   1. RF module grouping (Autenticación = 6 items)
 *   2. RNF category grouping (Rendimiento / Fiabilidad present)
 *   3. Stakeholders flat
 *   4. Items inside a group ordered by code ascending
 *   5. Deterministic across 10 calls (NFR-06)
 */

import { describe, expect, it } from "vitest";

import { buildTree } from "../../../src/lib/rem/tree";
import { SEED_DATASET } from "./fixtures/seed";
import { SEED_MINI } from "./fixtures/seed-mini";
import type { RemDataset, RemTree } from "../../../src/lib/rem/types";

describe("tree.buildTree · REQ-02", () => {
  it("groups RF by `module` (Autenticación has exactly 6 items)", () => {
    const tree = buildTree(SEED_DATASET);
    const authGroup = tree.functional.find((g) => g.label === "Autenticación");
    expect(authGroup).toBeDefined();
    expect(authGroup?.children?.length).toBe(6);
    expect(authGroup?.children?.every((c) => c.label.startsWith("RF-AUTH-"))).toBe(true);
  });

  it("groups RNF by `category` (Rendimiento / Fiabilidad group present)", () => {
    const tree = buildTree(SEED_DATASET);
    const group = tree.nonFunctional.find(
      (g) => g.label === "Rendimiento / Fiabilidad",
    );
    expect(group).toBeDefined();
    expect(group?.children?.length).toBeGreaterThan(0);
  });

  it("keeps stakeholders flat (3 leaf nodes, no children)", () => {
    const tree = buildTree(SEED_DATASET);
    expect(tree.stakeholders.length).toBe(3);
    for (const node of tree.stakeholders) {
      expect(node.children).toBeUndefined();
      expect(node.data).toBeDefined();
    }
  });

  it("orders members by `code` ascending inside a group (RF-AUTH-001…006)", () => {
    const tree = buildTree(SEED_DATASET);
    const authGroup = tree.functional.find((g) => g.label === "Autenticación");
    expect(authGroup).toBeDefined();
    const codes = (authGroup?.children ?? []).map((c) => c.label);
    expect(codes).toEqual([
      "RF-AUTH-001",
      "RF-AUTH-002",
      "RF-AUTH-003",
      "RF-AUTH-004",
      "RF-AUTH-005",
      "RF-AUTH-006",
    ]);
  });

  it("is deterministic across 10 calls (NFR-06, byte-equal trees)", () => {
    let first: RemTree | undefined;
    for (let i = 0; i < 10; i++) {
      const tree = buildTree(SEED_DATASET);
      if (first) {
        expect(tree).toEqual(first);
      } else {
        first = tree;
      }
    }
  });

  it("works against the mini fixture (3 RF, 2 RNF, 1 stakeholder)", () => {
    const tree = buildTree(SEED_MINI);
    // 3 RF all in Autenticación module → one group with 3 leaves
    expect(tree.functional.length).toBe(1);
    expect(tree.functional[0]?.children?.length).toBe(3);
    // 2 RNF in different categories → two groups
    expect(tree.nonFunctional.length).toBe(2);
    // 1 stakeholder
    expect(tree.stakeholders.length).toBe(1);
  });

  it("falls back to 'Sin clasificar' for items missing the discriminator", () => {
    const broken: RemDataset = {
      rf: [
        {
          uid: "rf_999",
          code: "RF-FAKE-999",
          title: "Sin módulo",
          rationale: "x",
          priority: "M",
          status: "pending",
          verifiedBy: [],
          links: [],
          kind: "rf",
        },
      ],
      rnf: [],
      stakeholders: [],
    };
    const tree = buildTree(broken);
    const fallback = tree.functional.find((g) => g.label === "Sin clasificar");
    expect(fallback).toBeDefined();
    expect(fallback?.children?.[0]?.label).toBe("RF-FAKE-999");
  });
});
