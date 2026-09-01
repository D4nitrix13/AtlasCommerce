/**
 * Re-export of the REM domain types from the production home
 * (`src/lib/rem/types.ts`).
 *
 * Why a re-export: IDEs and existing import paths surface
 * `src/types/<domain>.ts` first (see `frontend/AGENTS.md` §16). Keeping
 * the re-export here means callers can write
 * `import type { Requirement } from "@/types/rem"` while the canonical
 * source remains `lib/rem/types.ts` (so unit tests and other libs
 * don't pull in the re-export indirection).
 */

export type {
  Estabilidad,
  FieldKey,
  Kind,
  Priority,
  RemDataset,
  RemTree,
  Requirement,
  SearchHit,
  Stakeholder,
  Status,
  TreeNode,
} from "../lib/rem/types";
export { RemParseError } from "../lib/rem/types";
