# Contributing — AtlasCommerce

> **Software propietario.** Ver [`LICENSE`](LICENSE) antes de contribuir.
> Al participar en este repositorio (issues, PRs, discusiones), aceptás los
> términos de licenciamiento del proyecto.
>
> **Quality Gate.** Este proyecto trata el código como producto, no como descarte.
> Todo cambio debe cumplir los principios definidos aquí. Si no los cumplís, el
> cambio no se acepta.

AtlasCommerce es la plataforma monorepo sobre la que corren:

- **Panda Estampados** (`panda-estampados.com`) — B2B / corporativo.
- **Kitsune Store** (`kitsune.com`) — B2C / cultura japonesa.
- **MM Boutique** (`mm-boutique.com`) — single-tenant.
- **Morales Store** (`morales-store.com`) — single-tenant.

Las reglas que siguen aplican a toda la organización multi-tienda. Cambios
que rompen el contrato core (RLS multi-tenant, multi-instancia, CI
quality-gate) requieren revisión explícita del mantenedor antes de mergear.

Antes de empezar, leé también:

- [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md) — cómo tratarnos.
- [`SECURITY.md`](SECURITY.md) — cómo reportar vulnerabilidades (canal
  privado, NO issue público).
- [`SUPPORT.md`](SUPPORT.md) — dónde pedir ayuda.

---

## 1. Principios rectores

| Principio             | Significado práctico                                                       |
| --------------------- | -------------------------------------------------------------------------- |
| **SOLID**             | Una clase, una responsabilidad. Inyección por constructor.                 |
| **DRY**               | Repetir 3+ veces la misma lógica → abstraer.                               |
| **KISS**              | La solución más simple que resuelve el problema gana.                      |
| **YAGNI**             | No construyas lo que "podría necesitarse".                                 |
| **Clean Code**        | Nombres que explican intención. Funciones < 40 líneas. Una cosa por vez.  |
| **Clean Architecture**| Dominio separado de infraestructura. Servicios para lógica de negocio.     |
| **Separation of Concerns** | UI ≠ lógica de negocio ≠ persistencia ≠ transporte.                  |
| **Alta cohesión**     | Lo que vive junto, cambia junto.                                           |
| **Bajo acoplamiento** | Hablar entre módulos por contratos (interfaces, tipos), no por concretos.  |

---

## 2. Restricciones obligatorias

Antes de pedir review de un cambio, verificá que:

- [ ] No hay errores de sintaxis ni lógicos.
- [ ] No hay dependencias innecesarias.
- [ ] No hay código muerto.
- [ ] No hay código duplicado.
- [ ] No hay variables, imports ni métodos sin usar.
- [ ] No hay métodos largos (umbral: 40 líneas) ni clases Dios.
- [ ] No hay comentarios que excusen código malo.
- [ ] No hay código temporal olvidado, TODO o FIXME sin ticket.

---

## 3. Convenciones del proyecto

Estas convenciones están **automatizadas** en CI. Si las violás, el quality gate
falla antes de llegar a review.

### Backend (PHP / Laravel)

- **Tipado estricto** — `declare(strict_types=1);` en todo archivo nuevo.
- **Parámetros nombrados** — todo `where()`, `find()`, `assertX()` usa
  argumentos nombrados: `->where(column: 'x', value: 1)`.
- **Constructor property promotion** — sin `__construct()` vacío.
- **Return types explícitos** — `function foo(): array`.
- **Form Requests** — toda validación en clases `*Request`, no en controllers.
- **API Resources** — toda respuesta JSON formateada con `*Resource`.
- **Services** — lógica de negocio en `app/Services/{Domain}/{Name}Service.php`.
- **Transacciones** — operaciones multi-tabla dentro de `DB::transaction()`.
- **Eager loading** — sin N+1. `->with([...])` o `->load([...])` donde aplique.
- **Named arguments** — obligatorio en llamadas multi-argumento.
- **Single quotes** — comillas simples por defecto.
- **Trailing commas** — en arrays, argumentos y parámetros multilínea.
- **Imports ordenados alfabéticamente** (clases, funciones, constantes).

### Frontend (Astro / React / TypeScript)

Ver [`frontend/AGENTS.md`](frontend/AGENTS.md) para las reglas completas
(tamaño de archivos, nomenclatura, orden interno, etc.). Resumen crítico:

- **TypeScript estricto** — `astro/tsconfigs/strict`, sin `any` salvo justificación.
- **Componentes < 400 líneas** — si pasa, dividir.
- **Funciones < 40 líneas**.
- **Máximo 2-3 niveles de anidación** — retorno anticipado.
- **Booleanos como preguntas** — `isOpen`, `hasProducts`, `canEdit`.
- **Handlers** — `handle + verbo`.
- **Estados de UI obligatorios** — loading, error, empty, success, disabled.
- **Sin lógica de negocio en el frontend** — el cálculo confiable viene de Laravel.
- **Componentes de UI en `src/components/ui/`**, dominio en `src/components/{domain}/`.
- **Accesibilidad** — `<button>` para acciones, `<label>` para inputs, `alt`
  en `<img>`.
- **Storybook** — todo componente UI nuevo debe traer su `*.stories.ts` con
  la variant default y al menos un caso edge (loading, empty, error, con
  datos). Ver [`docs/frontend/storybook.md`](docs/frontend/storybook.md
  "docs/frontend/storybook.md").

### SQL / Migraciones

- **Foreign keys explícitas** en toda relación.
- **Índices** en columnas usadas en `WHERE`, `JOIN`, `ORDER BY`.
- **Soft deletes** donde aplique (`deleted_at`).
- **Nombres snake_case** para tablas y columnas.
- **Money en centavos** (`unsignedBigInteger`) — no `decimal(12,2)`.
- **Migraciones idempotentes** — reversibles con `down()`.

---

## 4. Seguridad

Verificá siempre:

- Validación de toda entrada externa (`FormRequest`, `Zod`).
- Sanitización en respuestas JSON cuando hay HTML embebido.
- Autorización con `Policies` / `Gates` / middleware RBAC.
- Autenticación Sanctum para rutas `/api/*`.
- Sin SQL crudo sin `bindings`. Sin concatenación de strings en queries.
- Sin secretos en código. `.env` no se commitea.
- Manejo seguro de uploads (validar tipo MIME, no solo extensión).

---

## 5. Tests

Cada cambio **debe** traer tests:

| Tipo de cambio          | Tests requeridos                                  |
| ----------------------- | ------------------------------------------------- |
| Bug fix                 | Test que reproduce el bug + verifica el fix.      |
| Nueva feature           | Tests del flujo feliz + flujo alternativo + edge. |
| Refactor                | Tests existentes verdes antes y después.          |
| Cambio de schema        | Test de migración + test del modelo resultante.   |
| Nuevo endpoint API      | Test feature: happy + auth + validación + RBAC.   |
| Nuevo componente UI     | Test de render + estados (loading/error/empty).   |

**No se aceptan features sin tests.** Un PR sin tests es rechazado de plano.

---

## 6. Autoevaluación antes de commit

Respondete en voz alta:

1. ¿Existe una solución más simple?
2. ¿Existe una solución más mantenible?
3. ¿Existe una solución más segura?
4. ¿Existe una solución más eficiente?
5. ¿Estoy reutilizando código existente?
6. ¿Estoy introduciendo deuda técnica?
7. ¿Estoy rompiendo una abstracción existente?

Si alguna respuesta es **sí**, aplicá la mejora antes de commitear.

---

## 7. Workflow de cambio

```txt
1. Branch desde master/main          → git switch -c tipo/scope-descripcion
2. Implementar + tests               → TDD cuando aplique
3. Local: pint + phpstan + tests     → ver comandos abajo
4. Local: eslint + prettier + check  → ver comandos abajo
5. Self-audit según §6               → checklist mental
6. Commit con conventional commits   → tipo(scope): descripción
7. Push + PR                         → CI corre quality gate automático
8. Review + ajustes                  → responder comentarios con cambios atómicos
9. Merge                             → squash o rebase según convenga
```

---

## 8. Comandos locales

### Backend

```bash
cd backend

# Formatear código
composer format                  # aplica Pint
vendor/bin/pint --dirty          # solo archivos modificados

# Análisis estático
composer analyse                 # PHPStan level 7

# Tests
composer test                    # toda la suite
php artisan test --compact --filter=testName   # un test específico

# Pre-commit completo
composer format && composer analyse && composer test
```

### Frontend

```bash
cd frontend

# TypeScript
pnpm run typecheck               # astro check

# Lint
pnpm run lint                    # eslint
pnpm run lint:fix                # eslint --fix

# Formateo
pnpm run format                  # prettier --write
pnpm run format:check            # prettier --check (CI)

# Storybook (UI primitives)
pnpm run storybook               # dev server en http://localhost:6006
pnpm run storybook:build         # build estático para CI/Chromatic
pnpm run storybook:test          # smoke test del build

# Pre-commit completo
pnpm run typecheck && pnpm run lint && pnpm run format:check
```

### Documentación

```bash
# Markdown lint
markdownlint '**/*.md' --ignore node_modules --ignore vendor
```

---

## 9. Criterio de bloqueo

Un PR se **rechaza** cuando:

- Rompe tests existentes.
- No incluye tests para código nuevo.
- Introduce deuda técnica evitable.
- Disminuye la calidad del proyecto.
- Incumple los principios de arquitectura.
- Introduce riesgos de seguridad.
- Tiene problemas de rendimiento no resueltos.
- Es difícil de mantener o extender.

> **Es preferible retrasar una entrega antes que incorporar código que comprometa
> la estabilidad, seguridad o mantenibilidad del sistema.**

---

## 10. Referencias

- [`backend/AGENTS.md`](backend/AGENTS.md) — Reglas auto-generadas de Laravel Boost
  (autoritativas para el backend).
- [`frontend/AGENTS.md`](frontend/AGENTS.md) — Reglas de frontend (tamaño,
  nomenclatura, estructura).
- [`.github/workflows/quality-gate.yml`](.github/workflows/quality-gate.yml) — Pipeline
  de quality gate.
- [`backend/pint.json`](backend/pint.json) — Configuración de Pint.
- [`backend/phpstan.neon`](backend/phpstan.neon) — Configuración de PHPStan (level
  7).
- [`frontend/eslint.config.js`](frontend/eslint.config.js) — Configuración de ESLint.
- [`frontend/.prettierrc.json`](frontend/.prettierrc.json) — Configuración de Prettier.
- [`docs/backend/TASKS.md`](docs/backend/TASKS.md) — Roadmap del backend.
