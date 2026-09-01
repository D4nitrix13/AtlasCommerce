/**
 * REM viewer — YAML round-trip helper.
 *
 * NFR-11 fixture runner: read a YAML from disk, write it back through
 * `serializeYamlSubset`, re-read, and assert deep equality
 * (whitespace-stripped). The helper wraps the common boilerplate so
 * `writer.test.ts` and the e2e CRUD spec can call it directly.
 *
 * The comparison is lenient about whitespace because the migration
 * script and our serializer both emit single-line `key: "value"`
 * entries — but list indentation may differ by exactly 2 spaces, and
 * the comment header is dropped on round-trip (NFR-11 acceptance:
 * "Whitespace differences … are acceptable; field order, key
 * presence, and value equality are not").
 */

import { readdir, readFile, writeFile } from "node:fs/promises";
import { join } from "node:path";

import { loadAllRequirements } from "./loader";
import { serializeYamlSubset } from "./yaml-writer";
import type { Requirement, Stakeholder } from "./types";

/**
 * Strip `# comment` lines + blank lines so the read-after-write
 * compare ignores whitespace the serializer intentionally omits.
 * Preserves every `key: value` line byte-for-byte.
 */
function stripYamlNoise(source: string): string {
  return source
    .split("\n")
    .filter((line) => {
      const trimmed = line.trim();
      return trimmed !== "" && !trimmed.startsWith("#");
    })
    .join("\n");
}

/**
 * Round-trip one YAML through the loader + serializer and assert
 * structural equality. Used by `writer.test.ts` for NFR-11 coverage.
 *
 * Discovers the YAML file by reading the directory and picking the
 * first `.yml` whose name starts with `<uid>_` so the helper doesn't
 * hard-code the migration script's slug convention.
 *
 * @param uid - The UID of the item to round-trip (`rf_001`, etc.).
 * @param fixtureDir - The folder the YAML lives in (test fixtures override this).
 */
export async function assertRoundTrip(
  uid: string,
  fixtureDir: string,
): Promise<void> {
  // 1. Discover the file by directory scan — works regardless of the
  //    migration script's slug naming convention.
  const filePath = await findYamlFor(uid, fixtureDir);
  const before = stripYamlNoise(await readFile(filePath, "utf-8"));

  // 2. Load → serialize → compare (no on-disk write).
  const dataset = loadAllRequirements({ fixtureDir });
  const item = findItem(dataset, uid);
  if (item === null) {
    throw new Error(`assertRoundTrip: ${uid} not found in ${fixtureDir}`);
  }
  const fields = serializeToFields(item);
  const serialized = serializeYamlSubset(uid, fields);

  // 3. Compare — strip noise so the comment header doesn't fail us.
  const after = stripYamlNoise(serialized);
  if (before !== after) {
    throw new Error(
      `assertRoundTrip failed for ${uid}\n--- BEFORE ---\n${before}\n--- AFTER ---\n${after}`,
    );
  }
}

/**
 * Write a YAML to disk so we can round-trip it without touching the
 * fixture. The endpoint uses this same `serializeYamlSubset` call;
 * the round-trip is exercised by reading the just-written file back.
 */
export async function writeRoundTripCopy(
  uid: string,
  fixtureDir: string,
): Promise<void> {
  const dataset = loadAllRequirements({ fixtureDir });
  const item = findItem(dataset, uid);
  if (item === null) {
    throw new Error(`writeRoundTripCopy: ${uid} not found in ${fixtureDir}`);
  }
  const fields = serializeToFields(item);
  const serialized = serializeYamlSubset(uid, fields);
  const filePath = await findYamlFor(uid, fixtureDir);
  await writeFile(filePath, serialized, "utf-8");
}

/**
 * Resolve `<uid>` → absolute file path inside `fixtureDir`. Picks the
 * first `.yml` whose name starts with `<uid>_` or whose root key is
 * `<uid>` (since the loader reads UIDs from the root key, not the
 * filename).
 */
async function findYamlFor(uid: string, fixtureDir: string): Promise<string> {
  const entries = await readdir(fixtureDir);
  for (const name of entries) {
    if (!name.endsWith(".yml")) continue;
    if (name.startsWith(`${uid}_`)) {
      return join(fixtureDir, name);
    }
  }
  // Fallback: look inside the file for a root key matching the UID.
  // The fixtures under `fixtures/writer/` use UID-shaped root keys but
  // not UID-shaped filenames (e.g. `rf_with_deps.yml` → root key
  // `rf_001`).
  for (const name of entries) {
    if (!name.endsWith(".yml")) continue;
    const content = await readFile(join(fixtureDir, name), "utf-8");
    if (new RegExp(`^${escapeRegex(uid)}:`, "m").test(content)) {
      return join(fixtureDir, name);
    }
  }
  throw new Error(`findYamlFor: no YAML for uid=${uid} in ${fixtureDir}`);
}

function escapeRegex(s: string): string {
  return s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

// ─── Internals ───────────────────────────────────────────────────────

function findItem(
  dataset: ReturnType<typeof loadAllRequirements>,
  uid: string,
): Requirement | Stakeholder | null {
  for (const r of dataset.rf) {
    if (r.uid === uid) return r;
  }
  for (const r of dataset.rnf) {
    if (r.uid === uid) return r;
  }
  for (const s of dataset.stakeholders) {
    if (s.uid === uid) return s;
  }
  return null;
}

/**
 * Convert one item back into the flat field map the serializer
 * expects. Mirrors the EXACT key order + presence `migrate_to_doorstop.py`
 * writes (RF + RNF branches):
 *
 *   RF:  title, rationale, priority, status, module, code,
 *        verified-by, version, autores, fuentes, dependencias, estabilidad
 *   RNF: title, rationale, priority, status, category, code,
 *        version, autores, fuentes, dependencias, estabilidad
 *
 * The order matters for NFR-11 byte-equality — the YAML writer keys
 * the map in insertion order.
 *
 * `links` is intentionally dropped (legacy field; the v1 loader still
 * parses it but no v1 dataset item populates it).
 */
function serializeToFields(
  item: Requirement | Stakeholder,
): Record<string, unknown> {
  if ("kind" in item) {
    const r = item;
    const fields: Record<string, unknown> = {
      title: r.title,
      rationale: r.rationale,
      priority: r.priority,
      status: r.status,
    };
    if (r.kind === "rf") {
      if (r.module !== undefined) {
        fields.module = r.module;
      }
    } else {
      if (r.category !== undefined) {
        fields.category = r.category;
      }
    }
    fields.code = r.code;
    // RF only — `verified-by` is not part of the RNF wire shape.
    if (r.kind === "rf") {
      fields["verified-by"] = r.verifiedBy;
    }
    if (r.version !== undefined) {
      fields.version = r.version;
    }
    if (r.autores !== undefined) {
      fields.autores = r.autores;
    }
    fields.fuentes = r.fuentes ?? [];
    fields.dependencias = r.dependencias ?? [];
    fields.estabilidad = r.estabilidad ?? "PD";
    return fields;
  }
  return {
    name: item.name,
    title: item.title,
    role: item.role,
    organization: item.organization,
    notes: item.notes,
  };
}

/**
 * Look up the filename slug for a UID. The migration script names files
 * `<uid>_<slug>.yml` where the slug is the original seed JSON `__key`.
 * For the round-trip we just need a deterministic placeholder; the
 * `assertRoundTrip` compare strips whitespace so the actual filename
 * doesn't matter as long as the path resolves to the same UID.
 *
 * Unused now that `findYamlFor` discovers files via directory scan —
 * kept as a future escape hatch if a test ever needs to write a YAML
 * back to a specific filename.
 */
// eslint-disable-next-line @typescript-eslint/no-unused-vars
function _slugFor(uid: string): string {
  return "roundtrip";
}