/**
 * writer.test.ts
 *
 * Coverage of the YAML write-back safety + round-trip helpers (D0):
 *   - torn write safety (NFR-10)
 *   - atomic rename (`.tmp` → final, `.bak.<ts>` sibling untouched)
 *   - rollback restores original bytes
 *   - NFR-11 round-trip deep-equal across all 6 fixtures
 *
 * The fixtures live under `tests/unit/lib/rem/fixtures/writer/` and
 * are v1-shaped YAMLs (subset of what `migrate_to_doorstop.py`
 * emits). The round-trip test reads → writes → re-reads and asserts
 * structural equality (whitespace-stripped per NFR-11).
 */

import { readFile } from "node:fs/promises";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import {
  discardBackup,
  rollbackFromBackup,
  serializeYamlSubset,
  writeYamlAtomic,
} from "../../../../src/lib/rem/yaml-writer";
import { assertRoundTrip } from "../../../../src/lib/rem/yaml-roundtrip";
import { clearLoaderCache } from "../../../../src/lib/rem/loader";

// Vitest 4 + ESM: `__dirname` is not defined. Resolve the fixture dir
// relative to this file's URL so the path works in both CJS and ESM
// contexts and across vitest's worker boundaries.
const HERE = dirname(fileURLToPath(import.meta.url));
// The test file lives at `tests/unit/lib/rem/writer.test.ts`; the
// fixtures live two directories up in `tests/unit/lib/rem/fixtures/writer/`.
const FIXTURE_DIR = join(HERE, "fixtures", "writer");

beforeEach(() => {
  // The loader caches for 5 min; ensure every test sees a fresh read.
  clearLoaderCache();
});

afterEach(() => {
  vi.restoreAllMocks();
  clearLoaderCache();
});

describe("writeYamlAtomic · NFR-10 torn-write safety", () => {
  it("writes the file atomically and snapshots the original to tmpdir", async () => {
    const filePath = join(FIXTURE_DIR, "atomic_target.yml");
    // Seed the file with the original content so the snapshot path
    // fires (writeYamlAtomic only copies when the file exists).
    const seedContent = serializeYamlSubset(
      "atomic_target",
      { title: "Original", rationale: "pre-write", priority: "M", status: "pending" },
    );
    const { writeFile, unlink } = await import("node:fs/promises");
    await writeFile(filePath, seedContent, "utf-8");

    const newContent = serializeYamlSubset("atomic_target", {
      title: "Updated",
      rationale: "post-write",
      priority: "H",
      status: "implemented",
    });

    const result = await writeYamlAtomic(filePath, newContent);
    expect(result.backupPath).toBeTruthy();
    expect(result.backupPath).toContain(tmpdir());
    expect(result.backupPath).toContain("atomic_target.yml.bak.");

    // The file on disk now holds the new content.
    const after = await readFile(filePath, "utf-8");
    expect(after).toBe(newContent);

    // The snapshot in tmpdir matches the pre-write bytes.
    const backup = await readFile(result.backupPath!, "utf-8");
    expect(backup).toBe(seedContent);

    // Best-effort cleanup so the test is idempotent across re-runs.
    await discardBackup(result.backupPath);
    await unlink(filePath);
  });

  it("does not leave a `.tmp` file on success", async () => {
    const filePath = join(FIXTURE_DIR, "tmp_cleanup.yml");
    const { writeFile } = await import("node:fs/promises");
    await writeFile(
      filePath,
      serializeYamlSubset("tmp_cleanup", { title: "x", rationale: "", priority: "M", status: "pending" }),
      "utf-8",
    );

    const result = await writeYamlAtomic(
      filePath,
      serializeYamlSubset("tmp_cleanup", { title: "y", rationale: "", priority: "L", status: "verified" }),
    );

    const { stat } = await import("node:fs/promises");
    await expect(stat(`${filePath}.tmp`)).rejects.toThrow();
    await discardBackup(result.backupPath);
    await import("node:fs/promises").then((m) => m.unlink(filePath));
  });

  it("rollbackFromBackup restores the original bytes on a torn write", async () => {
    const filePath = join(FIXTURE_DIR, "rollback_target.yml");
    const { writeFile } = await import("node:fs/promises");
    const seedContent = serializeYamlSubset("rollback_target", {
      title: "Original",
      rationale: "",
      priority: "M",
      status: "pending",
    });
    await writeFile(filePath, seedContent, "utf-8");

    const { backupPath } = await writeYamlAtomic(
      filePath,
      serializeYamlSubset("rollback_target", {
        title: "WIP",
        rationale: "",
        priority: "L",
        status: "verified",
      }),
    );

    // Simulate a torn write by manually corrupting the file, then ask
    // the rollback helper to restore from the snapshot.
    await writeFile(
      filePath,
      serializeYamlSubset("rollback_target", {
        title: "CORRUPTED",
        rationale: "",
        priority: "H",
        status: "approved",
      }),
      "utf-8",
    );

    await rollbackFromBackup(filePath, backupPath);

    const restored = await readFile(filePath, "utf-8");
    expect(restored).toBe(seedContent);

    await discardBackup(backupPath);
    await import("node:fs/promises").then((m) => m.unlink(filePath));
  });
});

describe("assertRoundTrip · NFR-11 round-trip deep-equal across 6 fixtures", () => {
  // The fixtures use descriptive filenames but the YAML root key (and
  // therefore the dataset UID) follows the migration script's UID
  // convention. This map pins each fixture's root-key UID so the
  // round-trip test loads the right item.
  const fixtures = [
    { name: "rf_with_deps", uid: "rf_001" },
    { name: "rf_empty_lists", uid: "rf_empty_lists" },
    { name: "rnf_with_category", uid: "rnf_010" },
    { name: "rnf_empty_lists", uid: "rnf_empty_lists" },
    { name: "stakeholder_with_notes", uid: "stakeholder_with_notes" },
    { name: "stakeholder_empty_strings", uid: "stakeholder_empty_strings" },
  ];

  for (const { name, uid } of fixtures) {
    it(`round-trips ${name} (uid=${uid}) through loader → serializer`, async () => {
      await assertRoundTrip(uid, FIXTURE_DIR);
    });
  }
});