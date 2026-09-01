/**
 * REM viewer — YAML writer.
 *
 * Write-back for the read-side parser in `loader.ts:111-209`. Together
 * they form the closed loop the CRUD endpoints need (read → mutate →
 * write → re-read deep-equal per NFR-11).
 *
 * Design §9 / NFR-10: writes are atomic via `writeFile + rename`,
 * with a `.bak.<ts>` snapshot in `os.tmpdir()` so a torn write never
 * corrupts the live dataset. The `.bak` is removed on success and
 * restored on failure (NFR-10 sibling-untouched invariant).
 *
 * Format guarantee: `serializeYamlSubset(record)` emits the EXACT
 * single-line shape `migrate_to_doorstop.py:45-65` produces — `key: "value"`
 * for scalars, `key: []` or indented `- item` for lists. The v1
 * parser already skips `#` lines so we don't add a header comment
 * (preserves NFR-11 byte-equality after a single round trip minus
 * the comment lines).
 */

import { copyFile, mkdir, rename, unlink, writeFile } from "node:fs/promises";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";

/**
 * Serialize a single record (the inner block of a Doorstop YAML)
 * into the exact shape `migrate_to_doorstop.py:45-65` produces.
 *
 * @param uid - The Doorstop UID used as the YAML's root key.
 * @param fields - The flat map of field → value to serialize.
 *   - `string`  → `key: "escaped-value"` (single-line, double-quoted).
 *   - `string[]` → `key: []` when empty; `key:` + indented `- item` otherwise.
 *   - `boolean` → `key: true` / `key: false`.
 *   - `number`  → `key: <n>`.
 *   - Anything else is coerced via `String(value)` and double-quoted.
 *
 * Returns the YAML string WITHOUT the `# comment` header — `loader.ts`
 * already skips `#` lines, so adding one on round-trip would defeat
 * NFR-11 byte-equality.
 */
export function serializeYamlSubset(
  uid: string,
  fields: Record<string, unknown>,
): string {
  const lines: string[] = [`${uid}:`];
  for (const [key, raw] of Object.entries(fields)) {
    if (Array.isArray(raw)) {
      if (raw.length === 0) {
        lines.push(`  ${key}: []`);
      } else {
        lines.push(`  ${key}:`);
        for (const item of raw) {
          lines.push(`    - ${escapeListItem(item)}`);
        }
      }
    } else if (typeof raw === "boolean") {
      lines.push(`  ${key}: ${raw ? "true" : "false"}`);
    } else if (typeof raw === "number" && Number.isFinite(raw)) {
      lines.push(`  ${key}: ${raw}`);
    } else if (raw === null || raw === undefined) {
      lines.push(`  ${key}: ""`);
    } else {
      lines.push(`  ${key}: "${escapeScalar(String(raw))}"`);
    }
  }
  return lines.join("\n") + "\n";
}

/**
 * Escape a string so it can be safely wrapped in double quotes.
 * Matches `migrate_to_doorstop.py:63`: escape backslashes first, then
 * double quotes. We do NOT touch newlines or other control chars —
 * the migration script emits single-line values and the schema
 * doesn't accept multi-line fields (NFR-09 conservative lock).
 */
function escapeScalar(value: string): string {
  return value.replace(/\\/g, "\\\\").replace(/"/g, '\\"');
}

/**
 * A list item is a bare YAML scalar — no quotes unless it contains a
 * character that would otherwise confuse the parser. The migration
 * script uses bare items for everything (UIDs, RNF codes). We mirror
 * the rule: quote only when the item has a leading/trailing space or
 * a colon (which would otherwise be read as a map entry).
 */
function escapeListItem(value: unknown): string {
  const str = String(value);
  if (/^[\s]|[:\s]$/.test(str) || str === "") {
    return `"${escapeScalar(str)}"`;
  }
  return str;
}

/**
 * Atomically write `content` to `filePath`:
 *   1. Snapshot the existing file (if any) to `os.tmpdir()/.<name>.bak.<ts>`.
 *   2. Write the new content to `filePath + ".tmp"`.
 *   3. Rename `filePath + ".tmp"` over `filePath` (atomic on POSIX).
 *
 * On any throw, the caller invokes `rollbackFromBackup` to restore the
 * snapshot. The snapshot lives in `os.tmpdir()` so it never appears
 * in git diffs.
 */
export async function writeYamlAtomic(
  filePath: string,
  content: string,
): Promise<{ backupPath: string | null }> {
  // 1. Snapshot (only if the file already exists).
  let backupPath: string | null = null;
  let backupAttempted = false;
  try {
    await mkdir(dirname(filePath), { recursive: true });
    const stamp = Date.now();
    backupPath = join(
      tmpdir(),
      `.${filePath.replace(/[\\/]/g, "_")}.bak.${stamp}`,
    );
    await copyFile(filePath, backupPath);
    backupAttempted = true;
  } catch (cause) {
    // ENOENT on copyFile → file didn't exist (create flow). That's fine.
    const code = (cause as NodeJS.ErrnoException).code;
    if (code !== "ENOENT") {
      // EACCES / ENOSPC / etc. — surface to caller; no rollback needed
      // because we haven't written anything yet.
      throw cause;
    }
    backupPath = null;
  }

  // 2 + 3. Write + atomic rename.
  const tmpPath = `${filePath}.tmp`;
  try {
    await writeFile(tmpPath, content, "utf-8");
    await rename(tmpPath, filePath);
  } catch (cause) {
    // Best-effort cleanup of the partial `.tmp` so it doesn't pollute
    // the directory. Failure to clean up is non-fatal — the rename
    // step is what matters for atomicity.
    await unlink(tmpPath).catch(() => {
      // ignore
    });
    // Re-throw with the backup path attached so the caller can rollback.
    const wrapped = cause instanceof Error ? cause : new Error(String(cause));
    (wrapped as Error & { backupPath: string | null }).backupPath =
      backupAttempted ? backupPath : null;
    throw wrapped;
  }

  return { backupPath: backupAttempted ? backupPath : null };
}

/**
 * Restore the snapshot produced by `writeYamlAtomic` after a torn write.
 * Idempotent: returns silently when `backupPath === null` (no snapshot
 * was taken — file did not exist before the failed write).
 */
export async function rollbackFromBackup(
  filePath: string,
  backupPath: string | null,
): Promise<void> {
  if (backupPath === null) {
    return;
  }
  try {
    await copyFile(backupPath, filePath);
  } finally {
    await unlink(backupPath).catch(() => {
      // ignore — tmpdir is OS-managed
    });
  }
}

/**
 * Companion to `writeYamlAtomic`: remove the `.bak` snapshot on
 * success. Callers should invoke this once the endpoint knows the
 * write stuck. Idempotent (unlink of a missing file is a no-op).
 */
export async function discardBackup(backupPath: string | null): Promise<void> {
  if (backupPath === null) {
    return;
  }
  await unlink(backupPath).catch(() => {
    // ignore
  });
}