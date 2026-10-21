# Resource Budget — un comando pesado por bloque

> **Regla load-bearing — universal.** La máquina de trabajo del equipo
> se sobrecalienta y se apaga cuando se encadenan comandos pesados. El
> objetivo de esta regla es que el trabajo avance en bloques chicos y
> ordenados, donde el progreso nunca se pierde.

## Por qué existe

En sprints previos la máquina del usuario se apagó dos veces durante
sesiones largas que encadenaron comandos pesados. Una vez se perdió el
avance hasta el último reporte. Esta regla existe para que:

1. Ningún comando pesado arranque antes de que el anterior haya
   terminado.
2. El progreso sea recuperable bloque por bloque si algo se corta.
3. La carga del sistema nunca llegue al umbral de apagado térmico.

## Regla 1 — Un comando pesado por bloque

Cada bloque de ejecución es **un comando pesado como máximo**. Nunca dos
comandos pesados encadenados con `&&`, `;` o `|`. Esto aplica a la
secuencia de comandos que el agente envía al shell — no a la pipeline
interna de un solo comando (ej. `php artisan test --filter=...` es un
solo comando).

### Comandos pesados verificados

| Comando | Ámbito | Por qué es pesado |
|---|---|---|
| `scripts/docker-stack.sh build` | Docker | Reconstruye imágenes (CPU + I/O disco) |
| `scripts/docker-stack.sh up` | Docker | Construye imágenes Y arranca containers |
| `composer install` | Backend | Resuelve + baja composer deps |
| `bun install` | Frontend | Resuelve + baja deps JS |
| `bun astro build` (script `build`) | Frontend | Compila bundle Astro + Vite completo |
| `bun run typecheck` (`astro check`) | Frontend | Type-check + diagnostics sobre todo el codebase |
| `bun run lint` (`eslint .`) | Frontend | Lint sobre todo el codebase |
| `bun run test` (`vitest run`) | Frontend | Suite Vitest completa |
| `bun run test:e2e` (`playwright test`) | Frontend | Levanta browsers, corre flujo end-to-end |
| `php artisan test --compact` (sin filtro) | Backend | Suite completa de PHPUnit |
| `php artisan migrate --seed` / `migrate:fresh --seed` | Backend | Escribe + repuebla BD completa |

## Regla 2 — Docker en bloques separados

`scripts/docker-stack.sh up` **construye imágenes Y arranca containers**
en un solo comando. Mezclarlo con migraciones o seeders acumula I/O de
build + arranque + DB writes en paralelo, situación que ya provocó un
apagado térmico.

### Secuencia canónica

Cada paso en su propio bloque:

1. **Build** (si cambió Dockerfile o .env):
   `./scripts/docker-stack.sh build <stage> <store> [servicio]`
2. **Up / start**:
   `./scripts/docker-stack.sh up <stage> <store>` o `start`
3. **Espera de health checks**: leer el output del paso 2 y repetir
   `./scripts/docker-stack.sh status <stage> <store>` hasta que los
   servicios reporten healthy. **No asumir disponibilidad** antes de
   seguir.
4. **Migraciones + seeders** (si aplica):
   `./scripts/docker-stack.sh exec <stage> <store> backend /usr/local/bin/php artisan migrate --seed`

Logs, `status` y otros comandos de inspección van en bloques aparte.

## Regla 3 — Tests filtrados durante iteración

Durante la iteración se corre **un test específico o una clase**, no la
suite completa. La suite completa es cara y rara vez verifica una
edición puntual.

### Backend (PHPUnit)

```bash
php artisan test --compact --filter=OrderServiceTest
php artisan test --compact tests/Feature/OrderServiceTest.php
# Solo al cerrar la tarea, una vez por sesión
php artisan test --compact
```

La suite completa se corre UNA vez al cerrar la tarea, no durante la
iteración, y **no más de una vez por sesión**. Si falla, filtrar al
test específico y volver a iterar.

### Frontend (Vitest + Playwright)

```bash
bun run test -- src/components/cart/CartSummary.test.ts
bun run test:e2e -- tests/e2e/auth.spec.ts
# Solo al cerrar la tarea, una vez por sesión
bun run test
bun run test:e2e
```

Igual para `test:e2e`: durante iteración usar `<archivo>.spec.ts` o
`--grep "..."`.

## Regla 4 — Nada en background junto a algo pesado en foreground

Nunca dos procesos pesados simultáneos:

- No lanzar un `background: true` mientras corre algo pesado en
  primer plano.
- No correr `bun astro dev` ad-hoc (de todas formas prohibido por
  [`tooling/docker-stack.md`](docker-stack.md)).
- Si necesitás inspeccionar logs en vivo durante una tarea, esperá a
  que el bloque pesado termine y usá `./scripts/docker-stack.sh logs`
  en un bloque aparte.

## Regla 5 — Preflight de carga

Antes de arrancar un bloque pesado, verificar la carga del sistema.
**Si el load average es alto, esperar — no sumar más trabajo.**

```bash
uptime                      # load average 1m / 5m / 15m
df -h                       # espacio libre por filesystem
docker system df            # espacio usado por Docker
```

El umbral es el **número de cores**, no un múltiplo de ese número. Con
`nproc` = N:

| Load average | Estado | Acción |
|---|---|---|
| menor a 0,5 × N | Libre | Ejecutar el bloque |
| 0,5 × N a 1 × N | Carga normal | Ejecutar |
| mayor a 1 × N | Saturado | **No** sumar trabajo pesado; esperar |
| mayor a 2 × N | Estrés severo | Detener todo lo pesado |

Un load average por encima de 1 × N significa que hay más procesos
compitiendo por CPU que cores disponibles. A partir de ahí la máquina
no termina el trabajo más rápido por más que se le exija: sólo se
calienta más. Ese es exactamente el estado queprovoca el apagado, así
que el punto de corte correcto es 1 × N.

## Regla 6 — Reporte por bloque

Después de cada bloque, reportar qué comando corrió y qué resultado
**observaste**. Esto garantiza que, si la sesión se corta, ya quede
evidencia del avance.

Formato de reporte:

```text
<comando>: <resultado observado>
```

Ejemplos:

```text
bun run typecheck: 0 errors, 0 warnings (4.2s)
php artisan test --compact --filter=OrderServiceTest: 12 tests, 12 passed (3.1s)
scripts/docker-stack.sh build dev instrumentos-musicales-la-voz backend: OK, image rebuilt (47s)
```

**Prohibido reportar éxito sin resultado observado.** "OK", "listo",
"anduvo" sin métricas no cuentan — el próximo agente no puede
reconstruir qué pasó.

## Higiene de disco

Hubo un incidente ENOSPC (sin espacio en disco) en el sprint anterior.
Por eso la verificación de espacio libre es parte del preflight y se
repite después de cada bloque pesado:

```bash
df -h | head -10
docker system df
```

Si el espacio libre cae por debajo de 5 GiB:

1. **Cache de bun** (frontend): `bun pm cache rm`.
2. **Docker builder prune**: `docker builder prune`. **NO** correr
   `docker system prune -a` sin confirmar — borra imágenes.
3. **Cache de Composer** (backend): `composer clear-cache`. Si hay
   basura vieja, `rm -rf ~/.composer/cache/files`.
4. **Logs Docker**: si superan 2 GiB, podar selectivos, no global.

Cada acción de prune en su propio bloque, no mezclada con un build o
un test.

## Bloques canónicos que SÍ se pueden encadenar

Estos grupos están validados como seguros para correr de corrido. Una
fila = un bloque con varios comandos triviales. Filas separadas =
bloques separados.

| Dominio | Comandos que pueden ir juntos |
|---|---|
| Docker (inspección) | `docker system df` + `df -h` |
| Docker (read-only) | `./scripts/docker-stack.sh status <stage> <store>` + `ps` + `info` |
| Backend PHP (read-only) | `php artisan route:list` + `php artisan config:show app.name` |
| Backend PHP (read-only) | `composer show --direct` + `composer show <vendor/package>` |
| Frontend (read-only) | `ls frontend/node_modules/.bin` + `cat frontend/package.json` |
| Git | `git status` + `git diff --stat` + `git log --oneline -n 10` |
| Git | `git status --short` + `git rev-parse --show-toplevel` |

Son read-only o write-trivial, sub-segundo, se pueden ejecutar juntos.

## Excepciones — lo que SÍ se puede encadenar libremente

- Lectura de archivos (`Read`, `cat`, `head`, `tail`).
- Búsqueda de contenido (`grep`, `rg`, `Grep`).
- Git read-only (`git status`, `git log`, `git diff`, `git show`).
- Comandos triviales sub-segundo: `pwd`, `whoami`, `ls`, `date`,
  `command -v <bin>`.
- Los grupos de la tabla "Bloques canónicos".

Todo lo que NO está en excepciones o en bloques canónicos: tratarlo
como pesado, un comando por bloque.
