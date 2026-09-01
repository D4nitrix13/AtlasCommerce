/**
 * Manual zodResolver for react-hook-form.
 *
 * `frontend/package.json` does NOT list `@hookform/resolvers/zod`
 * (design risk #9 — the project forbids new packages). This file
 * implements the 5-line manual resolver the design promises: take
 * `values`, run the Zod schema's `safeParse`, and either resolve
 * with the parsed data or reject with a flattened issue map.
 *
 * react-hook-form's resolver contract (v7):
 *   - `(values, context, options) => Promise<ResolverSuccess | ResolverError>`
 *   - Success: `{ values, errors: {} }`
 *   - Failure: `{ values: {}, errors: { <field>: { type, message } } }`
 *
 * For our use case the Zod field paths match the form field names
 * 1:1 (we don't use nested objects), so the flattening is trivial.
 */

import type { z } from "zod";
import type {
  FieldError,
  FieldErrors,
  FieldValues,
  Resolver,
} from "react-hook-form";

/**
 * Build a react-hook-form resolver from a Zod schema. The generics
 * let the caller declare a separate input shape (form values) vs.
 * output shape (parsed/transformed) if the schema uses `.transform()`.
 * Our schemas don't, so both default to `TFieldValues`.
 */
export function zodResolver<
  TFieldValues extends FieldValues = FieldValues,
  TContext = unknown
>(
  schema: z.ZodType<TFieldValues, z.ZodTypeDef, TFieldValues>
): Resolver<TFieldValues, TContext> {
  return async (values: TFieldValues): Promise<
    | { values: TFieldValues; errors: Record<string, never> }
    | { values: Record<string, never>; errors: FieldErrors<TFieldValues> }
  > => {
    const result = schema.safeParse(values);
    if (result.success) {
      return { values, errors: {} };
    }
    const errors: FieldErrors<TFieldValues> = {};
    for (const issue of result.error.issues) {
      const path = issue.path.join(".") || "_root";
      // Don't overwrite a previous error for the same field; first
      // error wins to match the "show one error per field" UX.
      const existing = (errors as Record<string, FieldError | undefined>)[path];
      if (existing !== undefined) continue;
      (errors as Record<string, FieldError>)[path] = {
        type: issue.code,
        message: issue.message,
      };
    }
    return { values: {} as Record<string, never>, errors };
  };
}
