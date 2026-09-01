/**
 * POST /api/rem/rnf
 *
 * Allocate next `rnf_NNN` slot, serialize via `serializeYamlSubset`,
 * atomic-write. Mirror of `rf/index.ts` with the RNF code regex
 * (`^RNF-[A-Z]+-\d{3}$`) and `category` instead of `module`.
 */

import { readdirSync } from "node:fs";
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

const PREFIX = "rnf";

function reqDir(): string {
  return resolve(process.cwd(), "..", "scripts/requirements/req");
}

export function allocateRnfUid(): string {
  let files: string[] = [];
  try {
    files = readdirSync(reqDir()).filter((n) => n.endsWith(".yml"));
  } catch {
    return `${PREFIX}_001`;
  }
  let max = 0;
  const re = /^rnf_(\d{3})_/;
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

function slugifyCode(code: string): string {
  return code.replace(/[^A-Za-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
}

export const POST: APIRoute = async ({ request }) => {
  let raw: unknown;
  try {
    raw = await request.json();
  } catch {
    return errorBody("crud:validation:body", 400);
  }

  const parsed = rnfCreateSchema.safeParse(raw);
  if (!parsed.success) {
    const first = parsed.error.issues[0];
    const field = first?.path.join(".") ?? "body";
    return errorBody(`crud:validation:${field}`, 400);
  }

  const uid = allocateRnfUid();
  const slug = slugifyCode(parsed.data.code);
  const fileName = slug === "" ? `${uid}.yml` : `${uid}_${slug}.yml`;
  const filePath = resolve(reqDir(), fileName);

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
