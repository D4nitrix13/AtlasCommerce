/**
 * RemFieldRow tests — REQ-05#3 (PD badge) + REQ-05#4 (red `?`).
 *
 * The Astro file `RemFieldRow.astro` has no Vitest render container in
 * this stack (no `@astrojs/check` container API). The row's visual
 * contract is captured by `getFieldRowVariant()` in `lib/rem/format.ts`
 * (pure helper) and verified here:
 *   - value=null → variant 'pd'  (renders "PD" badge).
 *   - value=[] on links row     → variant 'no-links' (red `?` with
 *     aria-label="Sin fuentes").
 *
 * Plus a static smoke check that the Astro file itself ships the
 * expected markup strings — guards against silent divergence.
 */

import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

import { getFieldRowVariant } from "../../../../src/lib/rem/format";

describe("RemFieldRow · REQ-05#3 + REQ-05#4", () => {
  it("REQ-05#3: value null renders the PD (Por Definir) variant", () => {
    expect(getFieldRowVariant(null)).toBe("pd");
    expect(getFieldRowVariant(null, { isLinksRow: true })).toBe("pd");
  });

  it("REQ-05#4: empty array on the links row renders the no-links variant (red ?)", () => {
    expect(getFieldRowVariant([])).toBe("plain");
    expect(getFieldRowVariant([], { isLinksRow: true })).toBe("no-links");
  });

  it("non-empty values render as 'plain' regardless of row context", () => {
    expect(getFieldRowVariant("Some text")).toBe("plain");
    expect(getFieldRowVariant(["a", "b"])).toBe("plain");
    expect(getFieldRowVariant(["a", "b"], { isLinksRow: true })).toBe("plain");
  });

  it("the .astro file ships the expected aria-label and badge text", () => {
    const astroPath = resolve(__dirname, "../../../../src/components/rem/RemFieldRow.astro");
    const content = readFileSync(astroPath, "utf-8");
    // Red `?` for empty links row per REQ-05#4.
    expect(content).toContain('aria-label="Sin fuentes"');
    // PD badge text per REQ-05#3.
    expect(content).toMatch(/PD/);
  });
});
