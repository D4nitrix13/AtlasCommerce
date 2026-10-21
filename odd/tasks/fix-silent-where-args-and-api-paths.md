# Fix: named args silenciosos en `where()` + prefijos de API

## Objetivo

Eliminar los filtros que devuelven cero filas sin error en el backend,
blindar la convención que los permitió, y alinear los prefijos de API
del frontend con las rutas reales del backend.

## Problema

Laravel define `where($column, $operator = null, $value = null)`. Al
pasar solo `column:` y `value:` como named arguments, PHP deja
`$operator = null` y Laravel ejecuta
`if (is_null($operator)) { [$value, $operator] = [$operator, '=']; }`.
El valor se corre al slot del operador y `$value` queda `NULL`,
generando `where "col" = null`.

Resultado: cero filas, sin excepción, sin log.

Verificado en container:
`where(column:, value:)` → `NULL`; posicional → `2`.

## Por qué

- 44 scopes rotos devuelven vacío siempre. Paneles que los usan
  muestran cero resultados y nadie detecta que el filtro nunca corrió.
- `backend/AGENTS.md` recomienda named arguments como convención dura
  sin advertir este caso.
- El frontend pide `/api/storefront/assets/` pero la ruta real es
  `/api/v1/storefront/assets/` (falta `v1`), en 6 archivos.

## Alcance

### T1 — 44 scopes con `where(column:, value:)` de 2 argumentos
Agregar `operator: '='` para volver a la forma documentada de 3
argumentos. **Ruta: delegada** (20+ archivos, trigger de escritura).

Archivos con casos confirmados: `backend/app/Models/**`.

### T2 — Blindar la convención
`backend/AGENTS.md` sección "Parámetros nombrados": advertir
explícitamente que `where()` con 2 named args falla en silencio y que la
forma válida es `where(column:, operator:, value:)`.
**Ruta: inline** (1 archivo, corrección mecánica).

### T3 — Prefijo de API del frontend
`/api/storefront/` → `/api/v1/storefront/` en los strings hardcodeados.
**Ruta: delegada** (6 archivos, trigger de escritura).

Archivos: `frontend/src/config/stores.ts`,
`frontend/src/layouts/{Layout,StorefrontLayout,AuthLayout}.astro`,
`frontend/src/lib/theme.ts`.

### T4 — Diagnóstico del FTP
El controller de assets lee de `Storage::disk('ftp')`. El FTP no responde
en el puerto 21. Verificar si el container está arriba.
**Ruta: inline** (diagnóstico read-only).

## Fuera de alcance

- No se cambian los tests existentes.
- No se reescribe `docs/operations/tech-stack.md` (discrepancias de
  versiones ya documentadas en `docs/architecture/tech-versions.md`).
- No se toca la convención de named arguments en general: solo se
  documenta el caso peligroso.

## Criterios de aceptación

- [ ] Cero ocurrencias de `where(column:` sin `operator:` en `backend/app/`
- [ ] Los scopes afectados devuelven las filas esperadas
- [ ] `bun run typecheck` y `bun run lint` pasan tras T3
- [ ] `plantuml -checkonly` sigue pasando en los 8 diagramas tocados

## Checks aplicables

- Backend: tests filtrados por modelo afectado. Suite completa solo al
  cerrar, una vez.
- Frontend: `bun run typecheck`, `bun run lint`.
- Docs: `markdownlint`.

## Progreso

- [x] **T0** — Diagnóstico completo y causa raíz verificada en container
- [x] **T1** — Scopes rotos corregidos: **41** (no 44; el conteo real era 41)
  en 18 modelos. Conteo final `0`. `vendor/bin/pint --test` → passed.
  Equivalencia verificada contra la DB real con 6 scopes, sembrando filas
  en transacción con `ROLLBACK`: forma de 3 args y posicional dan las
  mismas filas; la forma rota daba `where "status" is null` → 0.
- [x] **T2** — `backend/AGENTS.md` sección 1: agregada la trampa de
  `where()` con 2 named arguments, con ejemplo del síntoma invisible.
- [x] **T3** — Prefijos de API: **8** archivos corregidos (no 5).
  `rg "/api/storefront/assets"` → **0**. `bun run typecheck` → **0 errores**.
- [~] **T4** — FTP parcialmente resuelto
- [x] **Commit**

## T4 — FTP: qué quedó y qué falta

**Resuelto**: el container `instrumentos-musicales-la-voz-dev-ftp` no
tenía ninguna red Docker (`{}`), pese a que el compose la declara en la
línea 97. Por eso el backend no resolvía `ftp`. Conectado a
`atlas-commerce-instrumentos-musicales-la-voz-dev` con alias `ftp`;
`getent hosts ftp` devuelve `172.18.0.11`.

Se usó `docker network connect` en lugar de
`docker-stack.sh up` porque `up` hace *build + start* y reconstruye
imágenes sin necesidad: el defecto era pertenencia a la red, no la
imagen. `restart` no reengancha redes porque reutiliza el container.

**Pendiente (requiere al equipo)**: el login al FTP ahora falla con
`Unable to login/authenticate with FTP`. Las variables `FTP_USERNAME` y
`FTP_PASSWORD` están presentes en `environment/.env`, así que es
credencial incorrecta o usuario no provisionado en el volumen de
datos. **No se tocaron credenciales.** Hasta que se resuelva, los
assets del tenant siguen dando 404: `logo.svg`, `favicon.webp` y las
imágenes de producto.

## Corrección al análisis original

El diagnóstico inicial atribuyó el fallo al atajo
`func_num_args() === 2` de `Builder::where()`. **Es incorrecto**: con
named arguments PHP devuelve 3, así que ese atajo no se dispara. El
cambio de valor ocurre en `invalidOperator()` en
`Illuminate/Database/Query/Builder.php:974`. La conclusión era correcta,
la ruta interna no.

## Evidencia

- `where(column:, value:)` → `NULL`; posicional → `2`. Mismo query.
- Conteo de scopes rotos: 44 (estimado) → 41 (real) → **0** (final).
- `bun run typecheck` → 0 errores, 0 warnings, 40 hints.
- `vendor/bin/pint --test` → passed en los 18 modelos tocados.