/**
 * RemError tests — REQ-10#3.
 *
 * When the loader throws `RemParseError` (or any other failure), the
 * SSR frontmatter in `pages/[locale]/rem/index.astro` renders
 * `<RemError code={...} />` instead of `<RemSidebar>` + `<RemDetail>`.
 * The component:
 *   - Renders an `Alert` with copy "No se pudieron cargar los requisitos".
 *   - Surfaces the error code so the user can paste it in a bug report.
 *   - Does NOT trigger sidebar/detail rendering on the same page (that's
 *     a page-level concern; not asserted here).
 *
 * Pure helper `getErrorVariant(errorCode)` lives in `format.ts` so the
 * test can assert without rendering Astro.
 */

import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

import { getErrorVariant } from "../../../../src/lib/rem/format";

describe("RemError · REQ-10#3", () => {
  it("maps load error codes to the load variant", () => {
    expect(getErrorVariant("load:parse-error:req/rf_001.yml")).toBe("load");
  });

  it("maps notfound codes to the notfound variant", () => {
    expect(getErrorVariant("notfound:rf_does_not_exist")).toBe("notfound");
  });

  it("falls back to generic for unrecognized codes (no crash)", () => {
    expect(getErrorVariant("something:weird")).toBe("generic");
    expect(getErrorVariant("")).toBe("generic");
  });

  it("the .astro file renders an error-code element + does NOT render sidebar/detail", () => {
    const astroPath = resolve(__dirname, "../../../../src/components/rem/RemError.astro");
    const content = readFileSync(astroPath, "utf-8");
    // Must surface the error code somehow (data attribute + visible text).
    expect(content).toMatch(/data-rem-error-code/);
    expect(content).toMatch(/Código:|c[oó]digo/i);
    // Must NOT instantiate the sidebar or detail panel — scan the JSX
    // template region only (after the closing `*/` of the JSDoc block),
    // so the prose in the header comment doesn't accidentally trip the
    // assertion.
    const templateOnly = content.split("*/")[1] ?? "";
    expect(templateOnly).not.toMatch(/RemSidebar/);
    expect(templateOnly).not.toMatch(/RemDetail/);
  });
});
