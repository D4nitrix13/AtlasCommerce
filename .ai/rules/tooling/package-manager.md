# Frontend Package Manager — bun ONLY

> **Regla load-bearing.** El frontend de AtlasCommerce usa **bun**
> exclusivamente. NO usar `pnpm`, NO usar `npm`. Cualquier comando
> debe ser `bun run <script>`, `bun add`, `bunx <bin>`.

## Por qué bun (no pnpm/npm)

Documentado en `docs/operations/tech-stack.md` §"pnpm → bun"
(commit `51deaddf`). Razones:

- **Más rápido**: bun corre scripts y resuelve deps significativamente
  más rápido que pnpm/npm.
- **Runtime + package manager + test runner en uno**: bun ejecuta TS
  directamente (sin step de build) y corre vitest sin pnpm exec.
- **Lockfile JSON text** (`bun.lock`): más legible y merge-friendly
  que `pnpm-lock.yaml` o `package-lock.json`.

## Comandos equivalentes

| Acción | bun | pnpm (NO USAR) |
|---|---|---|
| Install deps | `bun install` | `pnpm install` |
| Install frozen | `bun install --frozen-lockfile` | `pnpm install --frozen-lockfile` |
| Add dep | `bun add <pkg>` | `pnpm add <pkg>` |
| Add dev dep | `bun add -d <pkg>` | `pnpm add -D <pkg>` |
| Run script | `bun run <script>` | `pnpm run <script>` |
| Exec binario | `bunx <bin>` | `pnpm exec <bin>` |
| Run vitest | `bun run test` o `bun vitest` | `pnpm test` |

## Verificación pre-commit

Antes de commitear cambios en `frontend/`:

```bash
bun run typecheck      # astro check (TS strict)
bun run lint          # eslint
bun run test          # vitest
```

`composer precommit` para backend (no usar bun para eso).

## Migración completada

El proyecto YA migró de pnpm a bun. El lockfile debería ser
`bun.lock`. Si ves `pnpm-lock.yaml` o `package-lock.json` en
`frontend/`, es drift — reportar y bumpear a bun.

## Cómo verificar que estás usando bun

```bash
which bun pnpm npm
```

`bun` debería aparecer primero en `$PATH` (o estar disponible). Si
solo `pnpm` está disponible, hay un problema de environment —
reportar antes de usar `pnpm exec`.