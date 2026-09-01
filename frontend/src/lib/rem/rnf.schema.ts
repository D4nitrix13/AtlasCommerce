/**
 * REM viewer — Zod schema for non-functional requirements (RNF).
 *
 * Source of truth for `POST /api/rem/rnf` / `PUT /api/rem/rnf/[uid]`.
 * Mirror of `rf.schema.ts`; the only difference is `category` instead
 * of `module` and no `verifiedBy` (RNFs are not "verified by" anyone
 * in REM 1.2.2).
 */

import { z } from "zod";

import type { Priority, Status } from "./types";

const RNF_CODE_REGEX = /^RNF-[A-Z]+-\d{3}$/;

const prioritySchema = z.enum(["H", "M", "L"]) satisfies z.ZodType<Priority>;
const statusSchema = z.enum([
  "pending",
  "in-analysis",
  "approved",
  "implemented",
  "verified",
]) satisfies z.ZodType<Status>;

const codeField = z
  .string()
  .min(1, "code is required")
  .regex(RNF_CODE_REGEX, "code must match /^RNF-[A-Z]+-\\d{3}$/");

const titleField = z.string().min(1, "title is required");
const rationaleField = z.string().default("");
const categoryField = z.string().min(1, "category is required");
const fuentesField = z.array(z.string()).default([]);
const dependenciasField = z.array(z.string()).default([]);

export const rnfCreateSchema = z.object({
  code: codeField,
  title: titleField,
  rationale: rationaleField,
  priority: prioritySchema,
  status: statusSchema,
  category: categoryField,
  fuentes: fuentesField,
  dependencias: dependenciasField,
});

export const rnfUpdateSchema = z.object({
  uid: z.string().regex(/^rnf_\d{3}$/, "uid must match /^rnf_\\d{3}$/"),
  code: codeField.optional(),
  title: titleField.optional(),
  rationale: rationaleField.optional(),
  priority: prioritySchema.optional(),
  status: statusSchema.optional(),
  category: categoryField.optional(),
  fuentes: fuentesField.optional(),
  dependencias: dependenciasField.optional(),
});

export type RnfCreateInput = z.infer<typeof rnfCreateSchema>;
export type RnfUpdateInput = z.infer<typeof rnfUpdateSchema>;