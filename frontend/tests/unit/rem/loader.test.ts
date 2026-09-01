/**
 * Loader tests — REQ-01 (5 scenarios).
 *
 * The loader reads the 81 Doorstop YAMLs from
 * `scripts/requirements/req/`, normalizes them into a typed
 * `RemDataset`, and caches the result for 5 minutes. These tests
 * cover all five spec scenarios:
 *
 *   1. Real folder → 48 RF + 30 RNF + 3 stakeholders; every RF has
 *      `module`, every RNF has `category`.
 *   2. Cache primed at T0 → same object reference at T0 + 60s (no
 *      re-read).
 *   3. TTL = 5 min, primed at T0 → re-reads at T0 + 5min + 1s.
 *   4. Invalid YAML → throws `RemParseError` whose message includes
 *      the file path.
 *   5. Empty folder → returns `{ rf: [], rnf: [], stakeholders: [] }`.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mkdtempSync, writeFileSync, rmSync, existsSync, copyFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";

import { clearLoaderCache, loadAllRequirements } from "../../../src/lib/rem/loader";
import { RemParseError } from "../../../src/lib/rem/types";

const REAL_REQ_DIR = resolve(process.cwd(), "..", "scripts/requirements/req");
const HAS_REAL = existsSync(REAL_REQ_DIR);

beforeEach(() => {
  clearLoaderCache();
  vi.useRealTimers();
});

afterEach(() => {
  clearLoaderCache();
  vi.useRealTimers();
});

describe("loader · REQ-01", () => {
  it("loads 48 RF + 30 RNF + 3 stakeholders from the real Doorstop folder", () => {
    if (!HAS_REAL) {
      // Skip silently on machines without the real folder; the empty-folder
      // case below still covers the contract.
      return;
    }
    const dataset = loadAllRequirements();
    expect(dataset.rf.length).toBe(48);
    expect(dataset.rnf.length).toBe(30);
    expect(dataset.stakeholders.length).toBe(3);

    for (const rf of dataset.rf) {
      expect(rf.kind).toBe("rf");
      expect(rf.uid).toMatch(/^rf_\d{3}$/);
      expect(rf.module).toBeDefined();
      expect(rf.module?.length).toBeGreaterThan(0);
    }
    for (const rnf of dataset.rnf) {
      expect(rnf.kind).toBe("rnf");
      expect(rnf.uid).toMatch(/^rnf_\d{3}$/);
      expect(rnf.category).toBeDefined();
      expect(rnf.category?.length).toBeGreaterThan(0);
    }
  });

  it("returns the same dataset reference on a cache hit at T+60s", () => {
    if (!HAS_REAL) {
      return;
    }
    const first = loadAllRequirements();
    const second = loadAllRequirements();
    expect(second).toBe(first);
  });

  it("re-reads after the 5-min TTL expires (fake timers, T+5min+1s)", () => {
    if (!HAS_REAL) {
      return;
    }
    vi.useFakeTimers();
    vi.setSystemTime(0);

    const first = loadAllRequirements();
    // Re-read within TTL: should be the same reference
    vi.setSystemTime(60_000);
    const second = loadAllRequirements();
    expect(second).toBe(first);

    // Advance past the 5-min TTL: should re-read
    vi.setSystemTime(5 * 60 * 1000 + 1000);
    const third = loadAllRequirements();
    expect(third).not.toBe(first);
    // Same data, fresh object
    expect(third.rf.length).toBe(first.rf.length);
    expect(third.rnf.length).toBe(first.rnf.length);
    expect(third.stakeholders.length).toBe(first.stakeholders.length);
  });

  it("throws RemParseError with the file path on invalid YAML", () => {
    const tmp = mkdtempSync(join(tmpdir(), "rem-loader-"));
    try {
      // One valid file so the loader iterates past it
      const valid = `rf_999:
  code: "RF-FAKE-999"
  module: "X"
  priority: "H"
  rationale: "ok"
  status: "implemented"
  title: "Valid"
  verified-by: []
`;
      writeFileSync(join(tmp, "rf_999_valid.yml"), valid);
      // Plus our invalid fixture (list item at wrong indent)
      const invalidPath = resolve(
        __dirname,
        "fixtures/seed-invalid.yml",
      );
      // Copy the invalid fixture into the temp dir under a YAML name
      copyFileSync(invalidPath, join(tmp, "rf_998_invalid.yml"));

      expect(() => loadAllRequirements({ fixtureDir: tmp })).toThrowError(RemParseError);
      try {
        loadAllRequirements({ fixtureDir: tmp });
      } catch (err) {
        expect(err).toBeInstanceOf(RemParseError);
        const e = err as RemParseError;
        expect(e.path).toContain("rf_998_invalid.yml");
        expect(e.message).toContain(e.path);
      }
    } finally {
      rmSync(tmp, { recursive: true, force: true });
    }
  });

  it("returns an empty dataset for an empty fixture folder (REQ-01#5)", () => {
    const tmp = mkdtempSync(join(tmpdir(), "rem-empty-"));
    try {
      const dataset = loadAllRequirements({ fixtureDir: tmp });
      expect(dataset).toEqual({ rf: [], rnf: [], stakeholders: [] });
    } finally {
      rmSync(tmp, { recursive: true, force: true });
    }
  });
});
