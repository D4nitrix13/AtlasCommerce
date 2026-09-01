/**
 * PUT /api/rem/rnf/[uid]  +  DELETE /api/rem/rnf/[uid]
 *
 * Mirror of `rf/[uid].ts`; the differences are the prefix regex,
 * the schema (rnfCreateSchema, with `category` instead of `module`),
 * and `estabilidad` always defaults to "PD" (RNFs are not verified).
 */

import { readdirSync, unlinkSync } from "node:fs";
import { resolve } from "node:path";
import type { APIRoute } from "astro";

import { rnfCreateSchema } from "../../../../lib/rem/rnf.schema";
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

function reqDir(): string {
  return resolve(process.cwd(), "..", "scripts/requirements/req");
}

function resolveUidPath(uid: string): string | null {
  if (!/^rnf_\d{3}$/.test(uid)) {
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

  const withoutUid: Record<string, unknown> = { ...(raw as Record<string, unknown>) };
  delete withoutUid.uid;
  const defaulted = {
    code: withoutUid.code ?? "RNF-NEW-000",
    title: withoutUid.title ?? "",
    rationale: withoutUid.rationale ?? "",
    priority: (withoutUid.priority as "H" | "M" | "L") ?? "M",
    status:
      (withoutUid.status as
        | "pending"
        | "in-analysis"
        | "approved"
        | "implemented"
        | "verified") ?? "pending",
    category: withoutUid.category ?? "General",
    fuentes: Array.isArray(withoutUid.fuentes) ? withoutUid.fuentes : [],
    dependencias: Array.isArray(withoutUid.dependencias)
      ? withoutUid.dependencias
      : [],
  };
  const parsed = rnfCreateSchema.safeParse(defaulted);
  if (!parsed.success) {
    const first = parsed.error.issues[0];
    const field = first?.path.join(".") ?? "body";
    return errorBody(`crud:validation:${field}`, 400);
  }

  const yaml = serializeYamlSubset(uid, {
    code: parsed.data.code,
    title: parsed.data.title,
    rationale: parsed.data.rationale,
    priority: parsed.data.priority,
    status: parsed.data.status,
    category: parsed.data.category,
    fuentes: parsed.data.fuentes,
    dependencias: parsed.data.dependencias,
    estabilidad: "PD",
    version: "1.0",
    autores: [],
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
    const code =
      cause instanceof Error && cause.message !== ""
        ? cause.message.replace(/[^A-Z0-9]+/gi, "-").slice(0, 40)
        : "unknown";
    return errorBody(`crud:write:${code}`, 500);
  }
};
