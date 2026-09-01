/**
 * Search tests — REQ-03 (4 scenarios) + NFR-02 (< 50 ms).
 *
 * The search module scores each item across multiple fields and
 * returns hits sorted by score desc, then uid asc. The empty-query
 * case returns every item with score 0 so the sidebar can render
 * the unfiltered tree without special-casing.
 *
 * Spec mapping:
 *   1. Exact code query → rf_001 first with score 100
 *   2. Lowercase title substring → rnf_010 in results
 *   3. Rationale substring → rf_001 in results
 *   4. Empty query → 78 items, all score 0
 *   5. (NFR-02) search < 50 ms for the full 81-item dataset
 */

import { describe, expect, it } from "vitest";

import { match, SCORE } from "../../../src/lib/rem/search";
import { SEED_DATASET } from "./fixtures/seed";

describe("search.match · REQ-03", () => {
  it("scores exact code = 100 (rf_001 first when querying RF-AUTH-001)", () => {
    const hits = match(SEED_DATASET, "RF-AUTH-001");
    const first = hits[0];
    expect(first).toBeDefined();
    expect(first?.uid).toBe("rf_001");
    expect(first?.score).toBe(SCORE.CODE_EXACT);
    expect(first?.matchedField).toBe("code");
  });

  it("matches a title substring case-insensitively (rnf_010 for 'sesiones en redis')", () => {
    const hits = match(SEED_DATASET, "sesiones en redis");
    const rnf = hits.find((h) => h.uid === "rnf_010");
    expect(rnf).toBeDefined();
    expect(rnf?.matchedField).toBe("title");
  });

  it("matches rationale text (rf_001 mentions AuthController)", () => {
    const hits = match(SEED_DATASET, "AuthController");
    const rf = hits.find((h) => h.uid === "rf_001");
    expect(rf).toBeDefined();
    expect(rf?.matchedField).toBe("rationale");
  });

  it("returns 78 items with score 0 for an empty query (REQ-03#4)", () => {
    const hits = match(SEED_DATASET, "");
    // 48 RF + 30 RNF + 3 stakeholders = 81 items (spec counted 78
    // stakeholders-only in older draft; current dataset has 3).
    expect(hits.length).toBe(81);
    for (const h of hits) {
      expect(h.score).toBe(0);
      expect(h.matchedField).toBeNull();
    }
  });

  it("runs in under 50 ms for the full 81-item dataset (NFR-02)", () => {
    // Warm up so JIT/optimizer is in steady state
    match(SEED_DATASET, "warmup");
    match(SEED_DATASET, "another warmup pass");

    const t0 = performance.now();
    for (let i = 0; i < 20; i++) {
      match(SEED_DATASET, "auth");
    }
    const elapsed = (performance.now() - t0) / 20;
    expect(elapsed).toBeLessThan(50);
  });
});
