/**
 * POST /api/rem/stakeholder
 *
 * Allocate the next free `stakeholder_NNN` slot, serialize the
 * payload via `serializeYamlSubset`, and atomic-write the YAML into
 * `scripts/requirements/req/`. On success returns 201 + `{ok:true,
 * uid}`. Zod validation failures → 400; write errors → 500 (NFR-10
 * keeps the rest of the directory untouched).
 *
 * UID slot allocation (design §8): scan `readdirSync(req)` for files
 * matching `^stakeholder_\d{3}_`, pick `max + 1`, zero-pad to 3.
 * Filename slug is derived from the `name` field (lowercased,
 * ASCII-only); falls back to `<uid>.yml` if the slug would be empty.
 *
 * After a successful write we call `clearLoaderCache()` so the next
 * `loadAllRequirements()` reads the new file (REQ-20#4).
 */

import { readdirSync } from "node:fs";
import { resolve } from "node:path";
import type { APIRoute } from "astro";

import { stakeholderCreateSchema } from "../../../../lib/rem/stakeholder.schema";
import { clearLoaderCache } from "../../../../lib/rem/loader";
import {
  discardBackup,
  serializeYamlSubset,
  writeYamlAtomic,
} from "../../../../lib/rem/yaml-writer";
import {
  crudSuccessResponse,
  errorBody,
} from "../../../../lib/rem/crud-errors";

export const prerender = false;

const PREFIX = "stakeholder";

function reqDir(): string {
  return resolve(process.cwd(), "..", "scripts/requirements/req");
}

/**
 * Allocate the next free UID by scanning the directory for the
 * highest `^stakeholder_NNN_` slot and returning `max + 1`,
 * zero-padded to 3 digits. Returns "stakeholder_001" when the folder
 * is empty or unreadable.
 */
export function allocateStakeholderUid(): string {
  let files: string[] = [];
  try {
    files = readdirSync(reqDir()).filter((n) => n.endsWith(".yml"));
  } catch {
    return `${PREFIX}_001`;
  }
  let max = 0;
  const re = /^stakeholder_(\d{3})_/;
  for (const name of files) {
    const m = name.match(re);
    if (m && m[1] !== undefined) {
      const n = Number.parseInt(m[1], 10);
      if (Number.isFinite(n) && n > max) {
        max = n;
      }
    }
  }
  const next = String(max + 1).padStart(3, "0");
  return `${PREFIX}_${next}`;
}

/** Slugify `name` for the filename suffix (lowercased, ASCII only). */
function slugify(value: string): string {
  const normalized = value
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 60);
  return normalized;
}

export const POST: APIRoute = async ({ request }) => {
  let raw: unknown;
  try {
    raw = await request.json();
  } catch {
    return errorBody("crud:validation:body", 400);
  }

  const parsed = stakeholderCreateSchema.safeParse(raw);
  if (!parsed.success) {
    const first = parsed.error.issues[0];
    const field = first?.path.join(".") ?? "body";
    return errorBody(`crud:validation:${field}`, 400);
  }

  const uid = allocateStakeholderUid();
  const slug = slugify(parsed.data.name);
  const fileName = slug === "" ? `${uid}.yml` : `${uid}_${slug}.yml`;
  const filePath = resolve(reqDir(), fileName);

  const yaml = serializeYamlSubset(uid, {
    name: parsed.data.name,
    title: parsed.data.title,
    role: parsed.data.role,
    organization: parsed.data.organization,
    notes: parsed.data.notes,
  });

  try {
    const { backupPath } = await writeYamlAtomic(filePath, yaml);
    clearLoaderCache();
    await discardBackup(backupPath);
    return crudSuccessResponse({ uid }, 201);
  } catch (cause) {
    const code =
      cause instanceof Error && cause.message !== ""
        ? cause.message.replace(/[^A-Z0-9]+/gi, "-").slice(0, 40)
        : "unknown";
    return errorBody(`crud:write:${code}`, 500);
  }
};
