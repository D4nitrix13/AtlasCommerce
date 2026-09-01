/**
 * REM viewer — Zod schema for stakeholders.
 *
 * Source of truth for `POST /api/rem/stakeholder` /
 * `PUT /api/rem/stakeholder/[uid]`. The stakeholder shape has no
 * `code` (only RF / RNF have codes); only `name` is required, the
 * other text fields can be empty per the v1 loader's `?? ""` defaults.
 */

import { z } from "zod";

const nameField = z.string().min(1, "name is required");
const titleField = z.string().default("");
const roleField = z.string().default("");
const organizationField = z.string().default("");
const notesField = z.string().default("");

export const stakeholderCreateSchema = z.object({
  name: nameField,
  title: titleField,
  role: roleField,
  organization: organizationField,
  notes: notesField,
});

export const stakeholderUpdateSchema = z.object({
  uid: z
    .string()
    .regex(
      /^stakeholder_\d{3}$/,
      "uid must match /^stakeholder_\\d{3}$/",
    ),
  name: nameField.optional(),
  title: titleField.optional(),
  role: roleField.optional(),
  organization: organizationField.optional(),
  notes: notesField.optional(),
});

export type StakeholderCreateInput = z.infer<typeof stakeholderCreateSchema>;
export type StakeholderUpdateInput = z.infer<typeof stakeholderUpdateSchema>;