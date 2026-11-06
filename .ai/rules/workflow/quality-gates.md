# Quality gates — qué debe estar verde antes de entregar

> Regla load-bearing para todo cambio que se commitea/pushea. Define el
> gate de entrega y cómo tratar el ruido preexistente del repo. Los
> números de error de este doc envejecen; el gate es **diferencial**
> (0 problemas nuevos propios), no absolutos.

## Gate de entrega (en orden)

1. **Tests** — correr el subset afectado y la suite del módulo:
   - Backend: `./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune backend /usr/local/bin/php artisan test --compact --filter=<Clase>` (el host NO corre tests: `DB_HOST=postgres` no resuelve y PHP del host no tiene `pdo_pgsql`). Filtrar por clase/archivo exacto — `--filter=Catalog` es demasiado amplio y cuelga (>10 min).
   - Frontend: `bun run test` (vitest) desde el worktree/checkout con `bun install` previo si hace falta. **Nunca npm/pnpm.**
2. **Typecheck** — `bun run typecheck` (0 errores; los hints ts6133 preexistentes no son gate).
3. **Lint scoped** — `bunx eslint <archivos tocados>`. Gate = **0 errores propios nuevos**. `eslint .` completo reporta ~55k problemas preexistentes y **nunca** es gate. Para atribuir dudas: `git stash && bun run lint && git stash pop` (comparar contra HEAD, no contra números de docs). Warnings `max-lines-per-function`/`complexity`/`jsx-a11y` de la misma clase que el código preexistente no bloquean. Ojo: `// eslint-disable-next-line import/no-unresolved` es ERROR en este config (regla no definida) — no copiarlo.
4. **Formato backend** — `php vendor/bin/pint --dirty` desde el **host** (el container no tiene git; `--dirty` falla adentro). Si pint toca archivos, re-correr los tests afectados.
5. **Self-audit** — `CONTRIBUTING.md` §"Autoevaluación" antes del commit.

## CI

- CI de master tiene fallos **preexistentes** (PHPStan del pipeline de imágenes, ESLint baseline, ~20 tests backend: `ProductSeederTest`, `BackfillVariantFinishCommandTest`, `ProductAttributeValueControllerTest`, `PasswordResetRecoveryTest` — absorbe `odd/tasks/tech-stack-upgrade.md`). El gate es **no introducir fallos nuevos** vs el baseline, no CI verde absoluto.
- Antes de mergear: `gh pr checks` — un fallo nuevo bloquea; un fallo preexistente se reporta como tal en el PR.

## Trampas de verificación

- Interrumpir tests con `RefreshDatabase` deja locks `idle in transaction (aborted)` en Postgres que bloquean la corrida siguiente (ver `tooling/dev-data-safety.md` para la limpieza).
- Los tests HTTP del storefront por Host header son poco confiables (`ResolveStoreFromDomain` + `localhost:8000`); para contratos de shape preferir tests a nivel resource — deterministas.
- Verificación visual (preview `:5321`) no reemplaza los tests: los volúmenes de caché de Astro sirven HTML viejo (fix en `tooling/docker-stack.md` § caché).
