# Upgrade de stack a últimas versiones (seguridad y fallos críticos)

> **Status**: planificado — **para otra sesión** (decisión del usuario
> 2026-11-05: la prioridad actual son las vistas del cliente; este plan
> es la última tarea del día).
>
> **Objetivo**: PHP, Laravel **y cada tecnología del proyecto** en su
> última versión estable, para cerrar vulnerabilidades conocidas y
> fallos críticos. Foco explícito del usuario: versiones de PHP y Laravel.

## Ya hecho (2026-11-05, mismo día)

Se mergearon 6 bumps de Dependabot en master (verificados con
`composer install` + suite backend):

| Paquete | De → A | PR |
|---|---|---|
| laravel/framework | 13.32.0 → 13.39.0 | #173 |
| sentry/sentry-laravel | 4.27.0 → 4.29.0 | #179 |
| spatie/laravel-medialibrary | 11.23.3 → 11.23.11 | #178 |
| league/flysystem-ftp | 3.35.3 → 3.36.0 | #177 |
| dedoc/scramble | 0.13.45 → 0.13.52 | #176 |
| predis/predis | 3.6.1 → 3.7.0 | #175 |

PRs cerrados que esta sesión debe retomar:
- **#172** larastan 3.10.0 → 3.13.2 (dev) — bump diferido aquí.
- **#174** phpunit 12.5.33 → **13.4.5 (MAYOR)** — requiere verificación
  completa de la suite; el named-args de assertions cambia entre majors
  (verificado: `assertGreaterThan(expected:, actual:)` ya falla en 12.5).

## Inventario de versiones actuales (2026-11-05)

| Tecnología | Versión actual | Nota |
|---|---|---|
| PHP (runtime, container) | 8.4.27 | constraint `^8.4` |
| Laravel | 13.39.0 | recién bumpado; `^13.8` |
| PHPUnit | 12.5.x | `^12.5.12` — pendiente mayor 13 |
| larastan | 3.10.x | `^3.0` — pendiente 3.13 |
| Astro | 7.3.8 | `^7.3.8` |
| React / react-dom | 19.3.0 | |
| Tailwind CSS | 4.3.3 | |
| TypeScript | 6.0.3 | |
| @astrojs/node / react / check | 11.1.7 / 6.0.0 / 0.9.10 | |
| Bun | 1.4.2 | instalado en `~/.bun/bin/bun` (NO está en PATH) |
| PostgreSQL/PostGIS | postgis/postgis:18-3.6 | imagen compose |
| Redis | redis:7-alpine | |
| nginx | 1.27-alpine | proxy :9000 API |
| vsftpd / mailpit / pgadmin / redisinsight / filebrowser | tags fijos/latest | bajo riesgo, actualizar igual |

## Riesgos / fallos conocidos que esta sesión debe dejar resueltos

1. **CI de master en rojo (preexistente, verificado 2026-11-05)**:
   - Backend CI: PHPStan/larastan en el pipeline de imágenes
     (`VariantFinishDeriver`, `BackfillVariantFinishCommand`, generators
     de `ProductVariant`, `product_dir/variant_dir`).
   - Frontend CI: ESLint con baseline enorme (`bun run lint` = `eslint .`
     reporta ~55k problemas históricos). Decidir el gate (baseline
     congelada + 0 errores nuevos, o limpieza).
2. **20 tests backend fallando de antes de los bumps** (mismo resultado
   con lock viejo y nuevo — verificado): `ProductSeederTest` (8),
   `BackfillVariantFinishCommandTest` (8), `ProductAttributeValueControllerTest`
   (4), `PasswordResetRecoveryTest` (1). El resto de la suite está verde
   (1478 passed).
3. **`composer audit` / `bun audit` / advisories de PHP**: ejecutar al
   inicio y cerrar cada CVE con el bump correspondiente; es el criterio
   de éxito del pedido del usuario.

## Plan de ejecución (orden sugerido)

1. **Auditoría**: `composer audit` (backend), `bun audit` (frontend),
   revisar advisories de PHP 8.4.x y Laravel 13.x. Anotar CVEs abiertos.
2. **Backend patch/minor**: `composer update` completo (trae larastan
   3.13 y el resto de libs); correr suite completa.
3. **PHPUnit 13 (mayor)**: migrar assertions con named-args problemáticos,
   correr suite completa; es el bump mayor más riesgoso del lado dev.
4. **PHP runtime**: ver si existe 8.5.x estable; actualizar el Dockerfile
   del backend y **recrear** los containers (`scripts/docker-stack.sh`
   — un restart NO recarga envs/imagen).
5. **Infra**: postgis 18-3.6, redis 7, nginx 1.27 a sus últimas;
   recrear stack completo y verificar el storefront (proxy :9000, FTP).
6. **Frontend**: `bun update` (astro, react, tailwind, typescript,
   @astrojs/*). Mayores (Astro 8, Tailwind 5, etc.) solo si existen y
   con build + tests + storybook build.
7. **CI verde**: arreglar los 20 tests preexistentes y los errores de
   PHPStan del pipeline de imágenes; acordar el gate de lint frontend.
8. **Verificación final**: suite backend completa (0 fallos),
   `bun run test` + `astro check` + `bun run build`, smoke e2e
   (PLP, PDP, login con 2FA), `composer audit`/`bun audit` limpios.

## Trampas conocidas (de memoria de proyecto)

- `bun` solo con ruta absoluta `~/.bun/bin/bun`; `bun run build` necesita
  `PATH="$HOME/.bun/bin:$PATH"` (el `prebuild` invoca `bun` sin ruta).
- Tests backend vía `./scripts/docker-stack.sh exec dev panda-estampados-and-kitsune
  backend /usr/local/bin/php artisan test --compact --filter=<ClaseExacta>`;
  los filtros amplios cuelgan. Interrumpir con `RefreshDatabase` deja
  locks en Postgres (`pg_terminate_backend` sobre la BD de test).
- Tests HTTP del storefront son frágiles (middleware `store.domain`);
  preferir tests a nivel resource.
- `SANCTUM_STATEFUL_DOMAINS` incluye `host:puerto`; si se recrea el
  backend, la config se recarga pero verificar el `Set-Cookie` con curl.
- `.ai/rules/tooling/{package-manager,docker-stack,resource-budget}.md`
  aplican tal cual durante la sesión de upgrade.

## Aceptación

- `composer audit` y `bun audit` sin vulnerabilidades abiertas (o
  documentadas con justificación).
- Todas las tecnologías del inventario en su última versión estable
  registrada (tabla actualizada al cierre).
- Suite backend 0 fallos (los 20 preexistentes resueltos), frontend
  tests + astro check + build verdes, CI verde.
- Sin regresiones en el storefront (smoke PLP/PDP/login).
