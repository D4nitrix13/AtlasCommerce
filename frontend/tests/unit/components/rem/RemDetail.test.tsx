/**
 * RemDetail.test.tsx
 *
 * Covers REQ-04#2 (default preselection) and the format helpers
 * folded in per design §6:
 *
 *   - REQ-05#1 — RF header + 8 rows.
 *   - REQ-05#2 — RNF: category row, no `verified-by` row.
 *   - REQ-05#5 — stakeholder: UID / Nombre / Título / Rol /
 *     Organización / Notas (no priority/status).
 *   - format-helper — `formatPriority('H' | 'M' | 'L')` → 5 / 3 / 1.
 *   - format-helper — `formatStatus(...)` maps to an i18n key under
 *     `rem.detail.status.*`.
 *
 * The detail panel itself is server-rendered Astro with zero client
 * JS; the unit tests target `buildDetailRows` in `lib/rem/format.ts`
 * directly, which is the contract the Astro template consumes.
 */

import { describe, expect, it } from "vitest";

import {
  buildDetailRows,
  formatPriority,
  formatStatus,
} from "../../../../src/lib/rem/format";
import { SEED_MINI } from "../../rem/fixtures/seed-mini";

const t = (key: string) => key;

describe("RemDetail · REQ-04#2 + REQ-05#1,#2,#5 + format helpers", () => {
  it("REQ-04#2 + REQ-05#1: RF renders 8 rows (code/title/module/priority/status/verified-by/links/rationale)", () => {
    const rf = SEED_MINI.rf.find((r) => r.uid === "rf_007") ?? SEED_MINI.rf[0]!;
    // Convert into an "RF-CART-010"-shaped requirement if available;
    // otherwise the seeded rf_001 has module + verifiedBy + everything.
    const rfForTest = SEED_MINI.rf[0]!;
    const rows = buildDetailRows(rfForTest, t, SEED_MINI.stakeholders);

    expect(rows.map((r) => r.field)).toEqual([
      "code",
      "title",
      "module",
      "priority",
      "status",
      "verifiedBy",
      "fuentes",
      "rationale",
    ]);
    expect(rows).toHaveLength(8);

    // `verifiedBy` resolves stakeholder names — REQ-05#1 specifically
    // expects comma-joined stakeholder names from `verifiedBy` UIDs.
    const verified = rows.find((r) => r.field === "verifiedBy");
    expect(verified).toBeDefined();
    if (verified !== undefined) {
      const v = verified.value;
      if (v !== null && !Array.isArray(v) && typeof v === "object" && v.kind === "stakeholders") {
        expect(v.names.length).toBeGreaterThan(0);
      } else {
        throw new Error("expected verifiedBy value to be a stakeholders shape");
      }
    }

    // Suppress the unused import warning.
    expect(rf.code).toBeTruthy();
  });

  it("REQ-05#2: RNF renders 7 rows (category row, NO verified-by row)", () => {
    const rnf = SEED_MINI.rnf[0]!;
    const rows = buildDetailRows(rnf, t, SEED_MINI.stakeholders);
    expect(rows.map((r) => r.field)).toEqual([
      "code",
      "title",
      "category",
      "priority",
      "status",
      "fuentes",
      "rationale",
    ]);
    expect(rows).toHaveLength(7);
    // Explicitly asserts the REQ-05#2 contract: no "verifiedBy" row.
    expect(rows.find((r) => r.field === "verifiedBy")).toBeUndefined();
  });

  it("REQ-05#5: stakeholder renders 6 rows (uid/name/title/role/organization/notes) — no priority/status", () => {
    const sh = SEED_MINI.stakeholders[0]!;
    const rows = buildDetailRows(sh, t, SEED_MINI.stakeholders);
    expect(rows.map((r) => r.field)).toEqual([
      "uid",
      "name",
      "title",
      "role",
      "organization",
      "notes",
    ]);
    expect(rows).toHaveLength(6);
    expect(rows.find((r) => r.field === "priority")).toBeUndefined();
    expect(rows.find((r) => r.field === "status")).toBeUndefined();
  });

  it("format-helper: formatPriority maps H/M/L to 5/3/1", () => {
    expect(formatPriority("H")).toBe("5");
    expect(formatPriority("M")).toBe("3");
    expect(formatPriority("L")).toBe("1");
  });

  it("format-helper: formatStatus emits an i18n key under rem.detail.status.*", () => {
    expect(formatStatus("implemented")).toBe("rem.detail.status.implemented");
    expect(formatStatus("verified")).toBe("rem.detail.status.verified");
    expect(formatStatus("in-analysis")).toBe("rem.detail.status.in-analysis");
  });
});
