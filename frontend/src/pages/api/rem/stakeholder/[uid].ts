/**
 * PUT /api/rem/stakeholder/[uid]  +  DELETE /api/rem/stakeholder/[uid]
 *
 * Astro APIRoute method dispatch (DD6 lock; design §8): a single
 * `[uid].ts` exports both `PUT` and `DELETE`. PUT merges the parsed
 * payload over the existing YAML record; DELETE removes the file
 * from disk.
 *
 * PUT errors:
 *   - 400 `crud:validation:<field>` when the body fails Zod.
 *   - 404 `crud:notfound:<uid>` when the UID does not resolve to a
 *     file in `scripts/requirements/req/`.
 *   - 500 `crud:write:<code>` on disk errors (NFR-10 keeps the rest
 *     of the directory untouched).
 *
 * DELETE returns:
 *   - 204 with empty body on success.
 *   - 404 when the UID is unknown.
 *   - 500 on unlink failure (cache is NOT cleared — the file is
 *     still on disk; per REQ-22#3).
 */

import { readdirSync, unlinkSync } from "node:fs";
import { resolve } from "node:path";
import type { APIRoute } from "astro";

import {
  stakeholderCreateSchema,
  stakeholderUpdateSchema,
} from "../../../../lib/rem/stakeholder.schema";
import { clearLoaderCache } from "../../../../lib/rem/loader";
import {
  discardBackup,
  rollbackFromBackup,
  serializeYamlSubset,
  writeYamlAtomic,
} from "../../../../lib/rem/yaml-writer";
import {
  crudSuccessResponse,
  errorBody,
} from "../../../../lib/rem/crud-errors";

export const prerender = false;

function reqDir(): string {
  return resolve(process.cwd(), "..", "scripts/requirements/req");
}

/**
 * Resolve a UID to its YAML path on disk. Returns `null` when the
 * folder is missing or no matching file exists. The lookup mirrors
 * `findByUid` in `[uid].html.ts` but stays filesystem-only — the
 * endpoint never needs to parse YAML to delete or update.
 */
function resolveUidPath(uid: string): string | null {
  if (!/^stakeholder_\d{3}$/.test(uid)) {
    return null;
  }
  let files: string[] = [];
  try {
    files = readdirSync(reqDir()).filter((n) => n.endsWith(".yml"));
  } catch {
    return null;
  }
  for (const name of files) {
    if (name === `${uid}.yml` || name.startsWith(`${uid}_`)) {
      return resolve(reqDir(), name);
    }
  }
  return null;
}

export const PUT: APIRoute = async ({ params, request }) => {
  const uid = params.uid ?? "";
  if (uid === "") {
    return errorBody("crud:validation:uid", 400);
  }
  const targetPath = resolveUidPath(uid);
  if (targetPath === null) {
    return errorBody(`crud:notfound:${uid}`, 404);
  }

  let raw: unknown;
  try {
    raw = await request.json();
  } catch {
    return errorBody("crud:validation:body", 400);
  }

  // Coerce the update payload to the same shape as a create schema so
  // Zod validates every field exactly once. We strip the `uid` from
  // the body, fall back to defaults for absent fields, then run Zod.
  const withoutUid: Record<string, unknown> = { ...(raw as Record<string, unknown>) };
  delete withoutUid.uid;
  const defaulted = {
    name: withoutUid.name ?? "",
    title: withoutUid.title ?? "",
    role: withoutUid.role ?? "",
    organization: withoutUid.organization ?? "",
    notes: withoutUid.notes ?? "",
  };
  const parsed = stakeholderCreateSchema.safeParse(defaulted);
  if (!parsed.success) {
    const first = parsed.error.issues[0];
    const field = first?.path.join(".") ?? "body";
    return errorBody(`crud:validation:${field}`, 400);
  }

  const yaml = serializeYamlSubset(uid, {
    name: parsed.data.name,
    title: parsed.data.title,
    role: parsed.data.role,
    organization: parsed.data.organization,
    notes: parsed.data.notes,
  });

  try {
    const { backupPath } = await writeYamlAtomic(targetPath, yaml);
    clearLoaderCache();
    await discardBackup(backupPath);
    return crudSuccessResponse({ uid });
  } catch (cause) {
    const code =
      cause instanceof Error && cause.message !== ""
        ? cause.message.replace(/[^A-Z0-9]+/gi, "-").slice(0, 40)
        : "unknown";
    // NFR-10: on write failure, restore the snapshot so the live
    // dataset is untouched. The `.bak` is in tmpdir().
    try {
      const err = cause as Error & { backupPath?: string | null };
      await rollbackFromBackup(targetPath, err.backupPath ?? null);
    } catch {
      // ignore — rollback is best-effort
    }
    return errorBody(`crud:write:${code}`, 500);
  }
};

export const DELETE: APIRoute = async ({ params }) => {
  const uid = params.uid ?? "";
  if (uid === "") {
    return errorBody("crud:validation:uid", 400);
  }
  const targetPath = resolveUidPath(uid);
  if (targetPath === null) {
    return errorBody(`crud:notfound:${uid}`, 404);
  }

  try {
    unlinkSync(targetPath);
    clearLoaderCache();
    return new Response(null, { status: 204 });
  } catch (cause) {
    // REQ-22#3: 500 must NOT clear the cache because the file is
    // still on disk; the next read should still see it.
    const code =
      cause instanceof Error && cause.message !== ""
        ? cause.message.replace(/[^A-Z0-9]+/gi, "-").slice(0, 40)
        : "unknown";
    return errorBody(`crud:write:${code}`, 500);
  }
};
