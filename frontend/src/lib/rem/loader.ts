/**
 * REM viewer — loader.
 *
 * Reads the 81 Doorstop YAMLs from `scripts/requirements/req/`,
 * normalizes them into a typed `RemDataset`, and caches the result
 * for 5 minutes at module scope so dev hot-reload still works without
 * a server restart.
 *
 * Design §3 DP3 locks this as the data-loading path: runtime SSR with
 * a 5-minute module-scope cache. No build-time content collections.
 *
 * Why a hand-rolled minimal YAML parser instead of `yaml`:
 * - The `yaml` package is a transitive dep of Astro, but pnpm's strict
 *   hoisting prevents `import 'yaml'` from user code without listing
 *   it in the project's `dependencies`. The constraint forbids new
 *   direct packages.
 * - The Doorstop YAML schema is small and predictable (strings, lists
 *   of strings, two levels of nesting). Parsing it ourselves is ~100
 *   LoC, fully tested, with no new install.
 *
 * Loader contract:
 * - `loadAllRequirements()` returns the cached dataset on warm hits.
 * - On YAML parse error, throws `RemParseError` whose `.path` is the
 *   offending file (REQ-10#3).
 * - On empty folder, returns `{ rf: [], rnf: [], stakeholders: [] }`.
 * - `clearLoaderCache()` resets the cache (test-only).
 */

import { readdirSync, readFileSync } from "node:fs";
import { join, resolve } from "node:path";

import {
  type Kind,
  type Priority,
  type Requirement,
  type RemDataset,
  type Stakeholder,
  type Status,
  RemParseError,
} from "./types";

// ─── Constants ────────────────────────────────────────────────────────

/** 5 minutes — design §3 DP3. */
const CACHE_TTL_MS = 5 * 60 * 1000;

/**
 * Default folder. Resolved at first call against `process.cwd()` so
 * the loader works in dev (Astro SSR launched from `frontend/`) and
 * in the test runner (vitest also runs from `frontend/`). Pass
 * `fixtureDir` in tests to override.
 */
function defaultReqDir(): string {
  // The project root is the parent of the `frontend/` package; the
  // Doorstop folder lives at the repo root.
  return resolve(process.cwd(), "..", "scripts/requirements/req");
}

// ─── Cache ────────────────────────────────────────────────────────────

interface CacheEntry {
  dataset: RemDataset;
  lastLoadedAt: number;
}

const CACHE = new Map<string, CacheEntry>();

/**
 * Test-only: drop all cached datasets. Production code never calls
 * this — the 5-min TTL is enough.
 */
export function clearLoaderCache(): void {
  CACHE.clear();
}

// ─── YAML parser (Doorstop subset) ───────────────────────────────────

/**
 * Parses a Doorstop-style YAML string into a plain JS object.
 *
 * Supports the subset the migration script emits:
 * - `# comment` lines (skipped)
 * - `key: "value"` (quoted strings, escapes unescaped)
 * - `key: ''` (empty string)
 * - `key: []` (empty list)
 * - `key:` with indented children (nested map or list)
 * - `  - value` (list items, indented under parent)
 *
 * Strategy: stack-based indent parser. Each `key:` with an empty
 * value creates a placeholder map frame. The placeholder carries the
 * `parentKey` so a later list item can convert it to an array and
 * rewrite the parent map's reference (so the array is what ends up
 * at `parentMap[parentKey]`, not the empty map placeholder).
 *
 * Throws `Error` with a line-numbered message on malformed input.
 */
interface MapFrame {
  kind: "map";
  indent: number;
  container: Record<string, unknown>;
  /** Key in the parent map where this frame's value lives. Absent on root. */
  parentKey?: string;
}
interface ListFrame {
  kind: "list";
  indent: number;
  container: unknown[];
}
type Frame = MapFrame | ListFrame;

function parseYamlSubset(source: string): Record<string, unknown> {
  const lines = source.split("\n");
  const root: Record<string, unknown> = {};
  const stack: Frame[] = [{ kind: "map", indent: -1, container: root }];

  for (let i = 0; i < lines.length; i++) {
    const raw = lines[i];
    const lineNum = i + 1;

    if (raw.trim() === "" || raw.trimStart().startsWith("#")) {
      continue;
    }

    const indent = raw.length - raw.trimStart().length;
    const trimmed = raw.trim();

    // Pop frames that are no longer ancestors. A list frame at the
    // same column as the current line keeps the line inside, so we
    // skip the pop for list items whose column matches the top list.
    const isListItem = trimmed.startsWith("- ");
    while (
      stack.length > 1 &&
      stack[stack.length - 1].indent >= indent &&
      !(isListItem && stack[stack.length - 1].kind === "list" && stack[stack.length - 1].indent === indent)
    ) {
      stack.pop();
    }
    const parent = stack[stack.length - 1];

    // ─── List item: `  - value` or `  - "value"` ────────────────────
    if (trimmed.startsWith("- ")) {
      const value = unescapeScalar(trimmed.slice(2).trim());
      if (parent.kind === "list") {
        parent.container.push(value);
        continue;
      }
      // Parent is a placeholder map frame from a `key:` line.
      // Convert it to an array in place AND update the grandparent's
      // reference so the array (not the empty map) ends up at the
      // parent key.
      if (parent.kind === "map" && parent.parentKey !== undefined) {
        const arr: unknown[] = [value];
        const grandparent = stack[stack.length - 2];
        if (grandparent && grandparent.kind === "map") {
          grandparent.container[parent.parentKey] = arr;
        }
        stack[stack.length - 1] = { kind: "list", indent, container: arr };
        continue;
      }
      throw new Error(`Line ${lineNum}: unexpected list item in non-list context`);
    }

    // ─── Map entry: `key: value` or `key:` ──────────────────────────
    if (parent.kind !== "map") {
      throw new Error(`Line ${lineNum}: unexpected map entry inside list: ${trimmed}`);
    }
    const parentMap = parent.container;

    const colonIdx = trimmed.indexOf(":");
    if (colonIdx === -1) {
      throw new Error(`Line ${lineNum}: missing ':' in map entry: ${trimmed}`);
    }
    const key = trimmed.slice(0, colonIdx).trim();
    const after = trimmed.slice(colonIdx + 1).trim();

    if (after === "[]") {
      parentMap[key] = [];
    } else if (after === "") {
      // Placeholder frame. The next non-empty line decides whether
      // this becomes a sub-map (more indented map entries) or a list
      // (more indented `- value` lines). The placeholder carries
      // parentKey so list conversion can update the grandparent ref.
      const child: Record<string, unknown> = {};
      parentMap[key] = child;
      stack.push({ kind: "map", indent, container: child, parentKey: key });
    } else {
      parentMap[key] = unescapeScalar(after);
    }
  }

  return root;
}

/**
 * Strips surrounding quotes and unescapes `\"` and `\\` inside.
 * Returns `""` for an empty quoted string `''`.
 */
function unescapeScalar(raw: string): string {
  if (raw === "''" || raw === '""') {
    return "";
  }
  if (raw.startsWith('"') && raw.endsWith('"') && raw.length >= 2) {
    return raw.slice(1, -1).replace(/\\"/g, '"').replace(/\\\\/g, "\\");
  }
  if (raw.startsWith("'") && raw.endsWith("'") && raw.length >= 2) {
    return raw.slice(1, -1).replace(/''/g, "'");
  }
  return raw;
}

// ─── Normalization helpers ───────────────────────────────────────────

function parsePriority(value: unknown): Priority {
  const v = String(value ?? "").toUpperCase();
  if (v === "H" || v === "M" || v === "L") {
    return v;
  }
  return "M";
}

function parseStatus(value: unknown): Status {
  const v = String(value ?? "").toLowerCase();
  switch (v) {
    case "pending":
    case "in-analysis":
    case "approved":
    case "implemented":
    case "verified":
      return v;
    default:
      return "pending";
  }
}

function parseStringArray(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value.map((v) => String(v));
  }
  return [];
}

function kindFromUid(uid: string): Kind | null {
  if (uid.startsWith("rf_")) {
    return "rf";
  }
  if (uid.startsWith("rnf_")) {
    return "rnf";
  }
  return null;
}

function normalizeRequirement(uid: string, kind: Kind, raw: Record<string, unknown>): Requirement {
  const verifiedBy = parseStringArray(raw["verified-by"]);
  const req: Requirement = {
    uid,
    code: String(raw["code"] ?? ""),
    title: String(raw["title"] ?? ""),
    rationale: String(raw["rationale"] ?? ""),
    priority: parsePriority(raw["priority"]),
    status: parseStatus(raw["status"]),
    verifiedBy,
    links: parseStringArray(raw["links"]),
    kind,
  };
  if (typeof raw["module"] === "string" && raw["module"] !== "") {
    req.module = raw["module"];
  }
  if (typeof raw["category"] === "string" && raw["category"] !== "") {
    req.category = raw["category"];
  }
  // DP1=A: 4 new fields are optional in TS but the migration script
  // always emits them. Read them defensively so DP1=B (legacy YAMLs)
  // doesn't crash.
  if (typeof raw["version"] === "string") {
    req.version = raw["version"];
  }
  req.autores = parseStringArray(raw["autores"]);
  req.fuentes = parseStringArray(raw["fuentes"]);
  req.dependencias = parseStringArray(raw["dependencias"]);
  if (raw["estabilidad"] === "alta" || raw["estabilidad"] === "PD") {
    req.estabilidad = raw["estabilidad"];
  } else {
    // Default: derived from verifiedBy.
    req.estabilidad = verifiedBy.length > 0 ? "alta" : "PD";
  }
  return req;
}

function normalizeStakeholder(uid: string, raw: Record<string, unknown>): Stakeholder {
  return {
    uid,
    name: String(raw["name"] ?? ""),
    title: String(raw["title"] ?? ""),
    role: String(raw["role"] ?? ""),
    organization: String(raw["organization"] ?? ""),
    notes: String(raw["notes"] ?? ""),
  };
}

// ─── Public API ───────────────────────────────────────────────────────

export type LoadOptions = {
  /** Override the Doorstop folder (test fixtures). */
  fixtureDir?: string;
};

/**
 * Load every RF/RNF/stakeholder YAML from the Doorstop folder and
 * return a typed `RemDataset`. Cached for 5 minutes per folder.
 *
 * Throws `RemParseError` (with `.path`) on any malformed YAML.
 * Returns `{ rf: [], rnf: [], stakeholders: [] }` for an empty folder.
 */
export function loadAllRequirements(opts: LoadOptions = {}): RemDataset {
  const folder = opts.fixtureDir ?? defaultReqDir();

  const now = Date.now();
  const cached = CACHE.get(folder);
  if (cached && now - cached.lastLoadedAt < CACHE_TTL_MS) {
    return cached.dataset;
  }

  let files: string[];
  try {
    files = readdirSync(folder).filter((name) => name.endsWith(".yml"));
  } catch (err) {
    // Folder doesn't exist → empty dataset (matches the empty-fixture
    // contract from REQ-01#5).
    if ((err as NodeJS.ErrnoException).code === "ENOENT") {
      const empty: RemDataset = { rf: [], rnf: [], stakeholders: [] };
      CACHE.set(folder, { dataset: empty, lastLoadedAt: now });
      return empty;
    }
    throw err;
  }

  const rf: Requirement[] = [];
  const rnf: Requirement[] = [];
  const stakeholders: Stakeholder[] = [];

  for (const name of files) {
    const fullPath = join(folder, name);
    let parsed: Record<string, unknown>;
    try {
      const content = readFileSync(fullPath, "utf-8");
      parsed = parseYamlSubset(content);
    } catch (cause) {
      throw new RemParseError(fullPath, cause);
    }

    // Each Doorstop YAML has a single inner block keyed by the UID.
    for (const [uid, payload] of Object.entries(parsed)) {
      if (!payload || typeof payload !== "object" || Array.isArray(payload)) {
        continue;
      }
      const kind = kindFromUid(uid);
      if (kind === "rf") {
        rf.push(normalizeRequirement(uid, kind, payload as Record<string, unknown>));
      } else if (kind === "rnf") {
        rnf.push(normalizeRequirement(uid, kind, payload as Record<string, unknown>));
      } else if (uid.startsWith("stakeholder_")) {
        stakeholders.push(normalizeStakeholder(uid, payload as Record<string, unknown>));
      }
    }
  }

  const dataset: RemDataset = { rf, rnf, stakeholders };
  CACHE.set(folder, { dataset, lastLoadedAt: now });
  return dataset;
}
