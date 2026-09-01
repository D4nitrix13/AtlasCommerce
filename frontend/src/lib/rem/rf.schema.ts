/**
 * REM viewer — Zod schema for functional requirements (RF).
 *
 * Source of truth for the wire shape `POST /api/rem/rf` /
 * `PUT /api/rem/rf/[uid]` accept. Mirrors `Requirement` from
 * `types.ts` (the read-side dataset type) but with stricter regex /
 * enum constraints so a malformed payload never reaches the YAML
 * writer.
 *
 * Why two schemas:
 *   - `rfCreateSchema` requires every field (the server picks the UID).
 *   - `rfUpdateSchema` makes every field optional + requires `uid`.
 *
 * The `uid` field is intentionally absent from `rfCreateSchema` —
 * the POST handler allocates `rf_NNN` from the next free slot.
 */

import { z } from "zod";

import type { Priority, Status } from "./types";

// ─── Field refinements ───────────────────────────────────────────────

/**
 * `code` is the human-friendly identifier the UI shows in the sidebar.
 * Examples from the dataset: `RF-AUTH-001`, `RF-CART-010`,
 * `RF-CUSTOM-035`. The regex locks the prefix structure so a typo'd
 * code can't sneak past Zod into the YAML.
 */
const RF_CODE_REGEX = /^RF-[A-Z]+-\d{3}$/;

const prioritySchema = z.enum(["H", "M", "L"]) satisfies z.ZodType<Priority>;
const statusSchema = z.enum([
  "pending",
  "in-analysis",
  "approved",
  "implemented",
  "verified",
]) satisfies z.ZodType<Status>;

/**
 * Shared refinements so `*CreateSchema` and `*UpdateSchema` agree on
 * the wire shape (regex, length, enum values). The list fields default
 * to `[]` so a payload that omits `fuentes` is still valid — the YAML
 * writer then emits `fuentes: []`.
 */
const codeField = z
  .string()
  .min(1, "code is required")
  .regex(RF_CODE_REGEX, "code must match /^RF-[A-Z]+-\\d{3}$/");

const titleField = z.string().min(1, "title is required");
const rationaleField = z.string().default("");
const moduleField = z.string().min(1, "module is required");
const verifiedByField = z.array(z.string()).default([]);
const fuentesField = z.array(z.string()).default([]);
const dependenciasField = z.array(z.string()).default([]);

// ─── Public schemas ──────────────────────────────────────────────────

/**
 * POST /api/rem/rf body. All fields required except those with
 * defaults. `uid` is server-assigned so the client never sends it.
 */
export const rfCreateSchema = z.object({
  code: codeField,
  title: titleField,
  rationale: rationaleField,
  priority: prioritySchema,
  status: statusSchema,
  module: moduleField,
  verifiedBy: verifiedByField,
  fuentes: fuentesField,
  dependencias: dependenciasField,
});

/**
 * PUT /api/rem/rf/[uid] body. Every field is optional except `uid`
 * (the URL path). RHF will send only the fields the user actually
 * edited; the endpoint merges them over the existing record.
 */
export const rfUpdateSchema = z.object({
  uid: z.string().regex(/^rf_\d{3}$/, "uid must match /^rf_\\d{3}$/"),
  code: codeField.optional(),
  title: titleField.optional(),
  rationale: rationaleField.optional(),
  priority: prioritySchema.optional(),
  status: statusSchema.optional(),
  module: moduleField.optional(),
  verifiedBy: verifiedByField.optional(),
  fuentes: fuentesField.optional(),
  dependencias: dependenciasField.optional(),
});

export type RfCreateInput = z.infer<typeof rfCreateSchema>;
export type RfUpdateInput = z.infer<typeof rfUpdateSchema>;