/**
 * REM viewer — shared CRUD error helpers.
 *
 * All 9 Astro endpoints (3 item types × POST/PUT/DELETE) emit the same
 * JSON envelope `{ ok: false, error: "<code>" }` and the same set of
 * status codes (400 / 404 / 500). Centralizing `errorBody` here keeps
 * the wire contract single-sourced — a fix to the error format
 * touches one function, not nine endpoints.
 *
 * Status mapping (per spec §"Endpoints contract"):
 *   - `crud:validation[:<field>]` → 400
 *   - `crud:notfound:<uid>` → 404
 *   - `crud:write:<code>` → 500
 *   - anything else → 500 (defensive default)
 */

import type { RemCrudErrorCode } from "./types";

const JSON_HEADERS = {
  "Content-Type": "application/json; charset=utf-8",
} as const;

/**
 * Build a `Response` with the canonical JSON error envelope.
 *
 * @param code - One of the `crud:*` error codes (or a custom string
 *   the endpoint wants to surface verbatim).
 * @param status - The HTTP status to send. Pass `400` for validation,
 *   `404` for not-found, `500` for write errors.
 */
export function errorBody(
  code: RemCrudErrorCode | string,
  status: number,
): Response {
  return new Response(JSON.stringify({ ok: false, error: code }), {
    status,
    headers: JSON_HEADERS,
  });
}

/**
 * Map an error code to its default status. Lets the endpoint do
 * `crudErrorResponse(code)` when it wants the default, or override
 * the status explicitly via `errorBody(code, 500)`.
 */
export function crudErrorResponse(code: RemCrudErrorCode | string): Response {
  const status = statusFor(code);
  return errorBody(code, status);
}

function statusFor(code: string): number {
  if (code.startsWith("crud:validation")) {
    return 400;
  }
  if (code.startsWith("crud:notfound")) {
    return 404;
  }
  return 500;
}

/**
 * Build a successful JSON response with `{ ok: true, ...payload }`.
 * Used by POST (returns the new UID) and PUT (returns the updated
 * item); DELETE uses a 204 with empty body and skips this helper.
 */
export function crudSuccessResponse(payload: Record<string, unknown>): Response {
  return new Response(JSON.stringify({ ok: true, ...payload }), {
    status: 200,
    headers: JSON_HEADERS,
  });
}