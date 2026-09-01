/**
 * schemas.test.ts
 *
 * Strict-TDD coverage for the 3 Zod schemas (RF / RNF / stakeholder)
 * and their create + update variants. Spec REQ-19#1 / REQ-20#2 /
 * REQ-21#3 map to these `it` blocks.
 *
 * The schemas live in `lib/rem/{rf,rnf,stakeholder}.schema.ts` and are
 * the wire contract for the 9 CRUD endpoints shipping in D3 — keep the
 * assertion surface narrow and the regex literals in the schemas in
 * sync with the migration script.
 */

import { describe, expect, it } from "vitest";

import { rfCreateSchema, rfUpdateSchema } from "../../../../src/lib/rem/rf.schema";
import { rnfCreateSchema, rnfUpdateSchema } from "../../../../src/lib/rem/rnf.schema";
import {
  stakeholderCreateSchema,
  stakeholderUpdateSchema,
} from "../../../../src/lib/rem/stakeholder.schema";

describe("Zod schemas · REQ-19#1, REQ-20#2, REQ-21#3", () => {
  // ─── RF create (REQ-20#2) ────────────────────────────────────────

  it("rfCreateSchema accepts a well-formed RF payload (REQ-20#2)", () => {
    const result = rfCreateSchema.safeParse({
      code: "RF-AUTH-001",
      title: "Login con email",
      rationale: "...",
      priority: "H",
      status: "implemented",
      module: "Autenticación",
      verifiedBy: ["stakeholder_001"],
      fuentes: [],
      dependencias: ["RNF-SEC-010"],
    });
    expect(result.success).toBe(true);
  });

  it("rfCreateSchema rejects an invalid code regex (REQ-19#1)", () => {
    const result = rfCreateSchema.safeParse({
      code: "RF-lowercase-001", // `[A-Z]+` rejects lowercase letters
      title: "x",
      rationale: "",
      priority: "M",
      status: "pending",
      module: "Test",
      verifiedBy: [],
      fuentes: [],
      dependencias: [],
    });
    expect(result.success).toBe(false);
  });

  it("rfCreateSchema rejects an out-of-enum priority (REQ-20#2)", () => {
    const result = rfCreateSchema.safeParse({
      code: "RF-TEST-001",
      title: "x",
      rationale: "",
      priority: "X", // not H|M|L
      status: "pending",
      module: "Test",
      verifiedBy: [],
      fuentes: [],
      dependencias: [],
    });
    expect(result.success).toBe(false);
  });

  // ─── RF update (REQ-21#3) ────────────────────────────────────────

  it("rfUpdateSchema accepts an empty body with only uid (REQ-21#4 empty payload)", () => {
    const result = rfUpdateSchema.safeParse({ uid: "rf_001" });
    expect(result.success).toBe(true);
  });

  it("rfUpdateSchema rejects an empty payload without uid (REQ-21#3 invalid)", () => {
    const result = rfUpdateSchema.safeParse({ title: "Updated" });
    expect(result.success).toBe(false);
  });

  // ─── RNF create + update ────────────────────────────────────────

  it("rnfCreateSchema accepts a well-formed RNF payload (no verifiedBy)", () => {
    const result = rnfCreateSchema.safeParse({
      code: "RNF-SEC-010",
      title: "Sanctum",
      rationale: "",
      priority: "H",
      status: "verified",
      category: "Seguridad",
      fuentes: [],
      dependencias: [],
    });
    expect(result.success).toBe(true);
  });

  it("rnfCreateSchema rejects an RF-shaped code (REQ-20#2)", () => {
    const result = rnfCreateSchema.safeParse({
      code: "RF-AUTH-001", // wrong prefix for RNF
      title: "x",
      rationale: "",
      priority: "M",
      status: "pending",
      category: "x",
      fuentes: [],
      dependencias: [],
    });
    expect(result.success).toBe(false);
  });

  it("rnfUpdateSchema accepts a partial payload with uid (REQ-21#3)", () => {
    const result = rnfUpdateSchema.safeParse({
      uid: "rnf_010",
      title: "Sanctum tokens",
    });
    expect(result.success).toBe(true);
  });

  // ─── Stakeholder create + update ────────────────────────────────

  it("stakeholderCreateSchema accepts the minimum required field (name)", () => {
    const result = stakeholderCreateSchema.safeParse({
      name: "Daniel",
      title: "",
      role: "",
      organization: "",
      notes: "",
    });
    expect(result.success).toBe(true);
  });

  it("stakeholderCreateSchema rejects a missing name (REQ-20#2)", () => {
    const result = stakeholderCreateSchema.safeParse({
      name: "",
      title: "",
      role: "",
      organization: "",
      notes: "",
    });
    expect(result.success).toBe(false);
  });

  it("stakeholderUpdateSchema requires uid and accepts a partial update", () => {
    const result = stakeholderUpdateSchema.safeParse({
      uid: "stakeholder_001",
      notes: "Updated notes",
    });
    expect(result.success).toBe(true);
  });
});