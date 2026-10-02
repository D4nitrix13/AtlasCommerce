/**
 * Minimal Node.js type declarations for the REM loader.
 *
 * Why this file exists: the project's strict TS config extends
 * `astro/tsconfigs/strict` and the project does not depend on
 * `@types/node` (the constraint for this change forbids new packages).
 * The REM loader only needs a tiny surface: `node:fs` for `readdirSync`
 * and `readFileSync`, `node:path` for `join`/`resolve`, and a global
 * `process.cwd()`. We declare those types here so the loader typechecks
 * cleanly without bringing in the full @types/node dependency.
 *
 * Scope is intentionally narrow — add types here only when the REM
 * code genuinely needs them. If the project later adopts @types/node,
 * this file can be deleted and the imports will keep working.
 */

declare module 'node:fs' {
  export function readdirSync(path: string): string[];
  export function readFileSync(path: string, encoding: 'utf-8'): string;
  export function existsSync(path: string): boolean;
  export function mkdtempSync(prefix: string): string;
  export function mkdirSync(path: string, options?: { recursive?: boolean }): void;
  export function rmSync(path: string, options?: { recursive?: boolean; force?: boolean }): void;
  export function writeFileSync(path: string, data: string): void;
  export function copyFileSync(src: string, dest: string): void;
}

declare module 'node:fs/promises' {
  export function readdir(path: string): Promise<string[]>;
  export function readFile(path: string, encoding: 'utf-8'): Promise<string>;
  export function writeFile(path: string, data: string, encoding: 'utf-8'): Promise<void>;
  export function copyFile(src: string, dest: string): Promise<void>;
  export function mkdir(path: string, options?: { recursive?: boolean }): Promise<void>;
  export function rename(src: string, dest: string): Promise<void>;
  export function unlink(path: string): Promise<void>;
  export function stat(path: string): Promise<{ size: number }>;
}

declare module 'node:path' {
  export function join(...parts: string[]): string;
  export function resolve(...parts: string[]): string;
  export function dirname(path: string): string;
}

declare module 'node:url' {
  export function fileURLToPath(url: string | URL): string;
}

declare module 'node:os' {
  export function tmpdir(): string;
}

declare const process: {
  cwd(): string;
  env: Record<string, string | undefined>;
};

declare const __dirname: string;

declare const require: NodeRequire;

type NodeRequire = (id: string) => unknown;

declare namespace NodeJS {
  interface ErrnoException extends Error {
    code?: string;
  }
}
