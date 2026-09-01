/**
 * REM viewer — domain types.
 *
 * Source of truth for the TypeScript shape of:
 *   - one functional / non-functional requirement (RF / RNF)
 *   - one stakeholder
 *   - the full dataset loaded from Doorstop YAMLs
 *   - the hierarchical tree the sidebar renders
 *   - the typed parse error thrown when a YAML is malformed
 *
 * DP1=A: 8 REM 1.2.2 fields are optional on `Requirement` so the
 * viewer keeps working if any YAML predates the schema extension. The
 * loader fills them with safe defaults (`version="1.0"`, empty lists,
 * etc.) — see `loader.ts`.
 *
 * v2 additions (REQ-13/14/15 storage keys + REQ-19#2 RemWriteResult)
 * live at the bottom of this file so the v1 contract stays readable.
 *
 * No `any`. The strict-TS gate in `tsconfig.json` forbids it.
 */

// ─── Primitive unions ────────────────────────────────────────────────

/** Functional vs non-functional — discriminated by UID prefix. */
export type Kind = "rf" | "rnf";

/** REM 1.2.2 priority scale: H/M/L → 1–5 in the UI. */
export type Priority = "H" | "M" | "L";

/** Lifecycle of a requirement. Mirrors the YAML `status` enum. */
export type Status =
  | "pending"
  | "in-analysis"
  | "approved"
  | "implemented"
  | "verified";

/** REM 1.2.2 stability marker: "alta" when verified, "PD" otherwise. */
export type Estabilidad = "alta" | "PD";

// ─── Stakeholder ─────────────────────────────────────────────────────

export interface Stakeholder {
  uid: string;
  name: string;
  title: string;
  role: string;
  organization: string;
  notes: string;
}

// ─── Requirement ─────────────────────────────────────────────────────

/**
 * One functional or non-functional requirement loaded from
 * `scripts/requirements/req/*.yml`.
 *
 * The 8 DP1=A fields are optional because the loader must work against
 * the legacy schema (DP1=B fallback). `format.ts` degrades to the "PD"
 * badge when they are missing.
 */
export interface Requirement {
  uid: string;
  code: string;
  title: string;
  rationale: string;
  priority: Priority;
  status: Status;
  module?: string;
  category?: string;
  verifiedBy: string[];
  links: string[];
  kind: Kind;

  // ─── DP1=A REM 1.2.2 fields (all optional) ────────────────────────
  /** Version of this requirement (default "1.0"). */
  version?: string;
  /** Stakeholder UIDs that own this requirement. */
  autores?: string[];
  /** Source UIDs (e.g. external specs that originate the requirement). */
  fuentes?: string[];
  /** Other requirement UIDs this one depends on. */
  dependencias?: string[];
  /** Alias of `priority` for the REM 1.2.2 detail panel. */
  importancia?: Priority;
  /** Alias of `priority` for the REM 1.2.2 detail panel. */
  urgencia?: Priority;
  /** Alias of `status` for the REM 1.2.2 detail panel. */
  estado?: Status;
  /** Derived from `verifiedBy.length > 0` → "alta" else "PD". */
  estabilidad?: Estabilidad;
}

// ─── Dataset + tree ──────────────────────────────────────────────────

export interface RemDataset {
  rf: Requirement[];
  rnf: Requirement[];
  stakeholders: Stakeholder[];
}

/**
 * Recursive node shape for the sidebar tree.
 *
 * `kind` is optional because a leaf that holds a stakeholder has no
 * grouping; the same shape is used for RF groups (key=`module`) and
 * RNF groups (key=`category`).
 */
export interface TreeNode {
  /** Stable key — `stakeholder`, group name, or requirement UID. */
  id: string;
  /** Human label rendered in the sidebar. */
  label: string;
  /** Optional nested children (groups) — absent on leaves. */
  children?: TreeNode[];
  /** Payload of the leaf — the requirement or stakeholder it represents. */
  data?: Requirement | Stakeholder;
}

/** Top-level shape returned by `tree.buildTree()`. */
export interface RemTree {
  stakeholders: TreeNode[];
  functional: TreeNode[];
  nonFunctional: TreeNode[];
}

// ─── Field keys for the detail panel ─────────────────────────────────

/**
 * Allowed field names in the REM detail row spec. The detail panel
 * reads from this union so it can never index a non-existent field.
 */
export type FieldKey =
  | "code"
  | "title"
  | "module"
  | "category"
  | "priority"
  | "status"
  | "importancia"
  | "urgencia"
  | "estado"
  | "estabilidad"
  | "verifiedBy"
  | "fuentes"
  | "dependencias"
  | "rationale"
  | "uid"
  | "name"
  | "role"
  | "organization"
  | "notes";

// ─── Search ──────────────────────────────────────────────────────────

export interface SearchHit {
  uid: string;
  score: number;
  /** First matched field — useful for highlighting in the UI. */
  matchedField: FieldKey | null;
}

// ─── Error ───────────────────────────────────────────────────────────

/**
 * Thrown by the loader when a YAML file fails to parse.
 * `path` is the absolute file path so the UI can show "REQ-10#3:
 * No se pudieron cargar los requisitos · Código: <file>".
 */
export class RemParseError extends Error {
  public readonly path: string;
  public readonly cause: unknown;

  public constructor(path: string, cause: unknown) {
    const reason = cause instanceof Error ? cause.message : String(cause);
    super(`Failed to parse REM YAML at ${path}: ${reason}`);
    this.name = "RemParseError";
    this.path = path;
    this.cause = cause;
  }
}

// ─── v2 — sidebar storage keys + write result ─────────────────────────

/**
 * Top-level sidebar group identifier. Used as the suffix of the
 * `localStorage` key that persists the collapse state. v2 introduces
 * 3 collapsible top-level groups per REQ-13 (stakeholders, RF, RNF).
 */
export type RemGroupId = "stakeholders" | "rf" | "rnf";

/**
 * Stable namespace prefix for every `localStorage` key v2 reads or
 * writes on the sidebar tree. Keeps the keys namespaced so a future
 * tenant accent / theme change does not collide.
 */
export const REM_SIDEBAR_STORAGE_PREFIX = "rem.viewer." as const;

/**
 * localStorage key for a top-level group's open/closed state.
 * Example: `expandedStorageKey("rf") === "rem.viewer.expanded.rf"`.
 */
export function expandedStorageKey(groupId: RemGroupId): string {
  return `${REM_SIDEBAR_STORAGE_PREFIX}expanded.${groupId}`;
}

/**
 * localStorage key for a nested group (RF `module`, RNF `category`)
 * open/closed state. Stakeholders never call this — they stay flat.
 * Example: `nestedExpandedStorageKey("rf", "Carrito") === "rem.viewer.expanded.rf.Carrito"`.
 */
export function nestedExpandedStorageKey(
  groupId: "rf" | "rnf",
  bucketName: string,
): string {
  return `${REM_SIDEBAR_STORAGE_PREFIX}expanded.${groupId}.${bucketName}`;
}

/** localStorage key for the user-persisted sidebar width (REQ-15). */
export const SIDEBAR_WIDTH_STORAGE_KEY =
  `${REM_SIDEBAR_STORAGE_PREFIX}sidebar.width` as const;

// ─── v2 — write-side types (shared with Zod schemas) ──────────────────

/** All fields except `uid` (server-assigned by the POST handler). */
export type RemCreateInput<T extends { uid: string }> = Omit<T, "uid">;

/**
 * All fields optional except `uid` (URL path on PUT). Lets the
 * `RemEditDialog` send only the fields the user actually edited.
 */
export type RemUpdateInput<T extends { uid: string }> = Partial<
  Omit<T, "uid">
> & { uid: T["uid"] };

/**
 * Stable error codes the new CRUD endpoints emit. Strings (not a
 * class) so they survive `JSON.stringify` to the React island. The
 * `crud:validation` prefix carries the field name when known; the
 * `crud:write` prefix carries the underlying cause (`EACCES`, etc.).
 */
export type RemCrudErrorCode =
  | "crud:validation"
  | `crud:validation:${string}`
  | `crud:notfound:${string}`
  | `crud:write:${string}`;

/**
 * Discriminated union the new Astro endpoints return to the React
 * island. The status code is the HTTP code; the body is the
 * `RemWriteResult` JSON. Success carries the new UID; failure carries
 * a stable error code the UI renders verbatim.
 */
export type RemWriteResult =
  | { ok: true; uid: string }
  | { ok: false; error: RemCrudErrorCode };
