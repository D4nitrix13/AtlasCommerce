# Astro Starter Kit: Basics

```sh
pnpm create astro@latest -- --template basics
```

> 🧑‍🚀 **Seasoned astronaut?** Delete this file. Have fun!

## 🚀 Project Structure

Inside of your Astro project, you'll see the following folders and files:

```text
/
├── public/
│   └── favicon.svg
├── src
│   ├── assets
│   │   └── astro.svg
│   ├── components
│   │   └── Welcome.astro
│   ├── layouts
│   │   └── Layout.astro
│   └── pages
│       └── index.astro
└── package.json
```

To learn more about the folder structure of an Astro project, refer to [our guide on project structure](https://docs.astro.build/en/basics/project-structure/).

## 🧞 Commands

All commands are run from the root of the project, from a terminal:

| Command                   | Action                                           |
| :------------------------ | :----------------------------------------------- |
| `pnpm install`             | Installs dependencies                            |
| `pnpm dev`             | Starts local dev server at `localhost:4321`      |
| `pnpm build`           | Build your production site to `./dist/`          |
| `pnpm preview`         | Preview your build locally, before deploying     |
| `pnpm astro ...`       | Run CLI commands like `astro add`, `astro check` |
| `pnpm astro -- --help` | Get help using the Astro CLI                     |

> **Nota sobre el puerto `4321`:** es la convención de Astro (`astro dev`)
> y no es configurable desde este repo. En el entorno Docker de
> desarrollo, el frontend se publica en el host en `:5321` (mapeo al
> `:4321` interno del container). Ver
> [`docs/operations/ports.md`](../../docs/operations/ports.md) para el
> mapa completo de puertos por instancia.

## REM Viewer

Visor web sobre los 81 YAMLs de Doorstop en `scripts/requirements/req/`.
Pensado como entregable académico alineado con REM 1.2.2 (la
herramienta Java Swing de referencia): árbol jerárquico a la izquierda
(Stakeholders + RF por módulo + RNF por categoría) y panel de detalle
a la derecha con los campos del requisito seleccionado.

### Architecture

El visor es un React island (`RemSidebar` + `RemEditDialog` + 3
forms Zod-backed) montado dentro de un layout Astro SSR. La
separación de responsabilidades:

- **Capa de datos** (`src/lib/rem/`): `loader.ts` (parser YAML in-house
  con cache 5 min), `tree.ts` (agrupación por módulo/categoría),
  `search.ts` (match case-insensitive), `format.ts` (filas del
  detalle), `yaml-writer.ts` (serializador single-line matching
  `migrate_to_doorstop.py:45-65` + atomic `.bak` snapshots),
  `crud-errors.ts` (envelope `{ok:false, error:"..."}` compartido),
  `zod-resolver.ts` (manual resolver para `react-hook-form` — el
  paquete `@hookform/resolvers/zod` no está en `package.json` per
  design risk #9).
- **Capa UI** (`src/components/rem/`): `RemSidebar.tsx` orquesta los
  3 grupos colapsables (`RemGroupShell`), las anidas (`RemNestedGroup`),
  el handle de resize (`RemResizeHandle`), el slot de kebab
  (`RemKebabMenu`) y el diálogo CRUD (`RemEditDialog`). El detalle se
  refresca con `fetch + innerHTML` (DD5 lock) — el React island nunca
  re-monta el panel de detalle.
- **Capa endpoints** (`src/pages/api/rem/`): GET `/api/rem/[uid].html`
  para el detalle SSR + 9 endpoints CRUD (3 tipos × POST/PUT/DELETE).
- **i18n** (`src/i18n/`): namespace `rem.*` con 78 keys (45 v1 + 33
  v2 para kebab/dialog/forms). Tipos en `TranslationKey` union
  fuerzan paridad es/en.

### Endpoints

| Method | Path | Body | Status codes | Notas |
| :----- | :--- | :--- | :--- | :--- |
| `GET`    | `/api/rem/[uid].html` | — | 200 / 404 / 500 | Renderiza el detalle HTML (no JSON) |
| `POST`   | `/api/rem/stakeholder` | `stakeholderCreateSchema` | 201 / 400 / 500 | Asigna `stakeholder_NNN` automáticamente |
| `PUT`    | `/api/rem/stakeholder/[uid]` | `stakeholderUpdateSchema` | 200 / 400 / 404 / 500 | |
| `DELETE` | `/api/rem/stakeholder/[uid]` | — | 204 / 404 / 500 | Empty body, cache NO se borra en 500 |
| `POST`   | `/api/rem/rf` | `rfCreateSchema` | 201 / 400 / 500 | Asigna `rf_NNN`; valida `code` regex |
| `PUT`    | `/api/rem/rf/[uid]` | `rfUpdateSchema` | 200 / 400 / 404 / 500 | |
| `DELETE` | `/api/rem/rf/[uid]` | — | 204 / 404 / 500 | |
| `POST`   | `/api/rem/rnf` | `rnfCreateSchema` | 201 / 400 / 500 | Asigna `rnf_NNN`; valida `code` regex |
| `PUT`    | `/api/rem/rnf/[uid]` | `rnfUpdateSchema` | 200 / 400 / 404 / 500 | |
| `DELETE` | `/api/rem/rnf/[uid]` | — | 204 / 404 / 500 | |

Todos los POST/PUT devuelven `{ok:true, uid:"..."}`; los DELETE
devuelven 204 con `Content-Length: 0`. Los errores usan los códigos
estables `crud:validation:<field>` (400), `crud:notfound:<uid>`
(404), `crud:write:<code>` (500).

### Data flow

```
Browser  →  /es/rem  /en/rem  (Astro SSR, server output)
                │
                ▼  loadAllRequirements()  ←  scripts/requirements/req/*.yml
                │
   ┌────────────┴────────────┐
   ▼                         ▼
<nav> RemSidebar         <main> RemDetail
   │   (React island)        ▲
   │                         │  fetch /api/rem/<uid>.html
   ├─ RemGroupShell           │  + innerHTML swap (DD5)
   ├─ RemResizeHandle         │
   ├─ RemKebabMenu            │
   │   └─ onEdit → RemEditDialog
   │   └─ onDelete → window.confirm() → DELETE /api/rem/<kind>/<uid>
   └─ RemEditDialog
        └─ POST|PUT /api/rem/{stakeholder,rf,rnf}[/<uid>]
              │
              ▼  Zod → 400  |  writeYamlAtomic (NFR-10 .bak) → 201/200
              │
              ▼  clearLoaderCache() (REQ-20#4 / REQ-22#4)
              │
              ▼  onSuccess → sidebar scroll preservation + auto-select
```

### Running

| Comando | Acción |
| :------ | :----- |
| `pnpm dev` (en `frontend/`) | Astro dev server en `:4321`; mapeo host `:5321` |
| `pnpm vitest run tests/unit/lib/rem` | tests unitarios del dominio REM (schemas, writer, storage keys) |
| `pnpm vitest run tests/unit/components/rem` | tests unitarios de componentes REM |
| `pnpm vitest run tests/unit/pages/api/rem` | tests unitarios de los 9 endpoints CRUD |
| `pnpm vitest run tests/unit/integration` | integration test (kebab → dialog → submit → onSuccess) |
| `pnpm playwright test tests/e2e/rem/rem-viewer.spec.ts` | e2e del viewer base (9 tests + screenshot baseline) |
| `pnpm playwright test tests/e2e/rem/rem-crud.spec.ts` | e2e del flujo CRUD (gated por dev server local) |
| `pnpm playwright test tests/e2e/rem/rem-sidebar.spec.ts` | e2e de sidebar colapsable + resize + palette |
| `pnpm playwright test tests/e2e/rem/rem-a11y.spec.ts` | e2e axe-core WCAG 2.2 AA scan |
| `pnpm playwright test tests/e2e/rem/rem-pdf.spec.ts` | e2e del flujo print-to-PDF (gated por `PDF_TESTS=1`) |
| `pnpm vitest run --coverage` | verifica thresholds NFR-12 (85% líneas en `lib/rem/**` + `components/rem/**`) |

### Testing

Los tests siguen Strict TDD — cada implementación nueva ships paired
test file en el mismo commit. Cobertura:

- **Unit** (Vitest, jsdom): schemas Zod, writer YAML, storage keys,
  3 forms, dialog, kebab, sidebar, tree-node, group-shell, resize-handle.
- **Unit endpoints** (Vitest, jsdom + tmpdir): 35 tests cubriendo
  POST/PUT/DELETE para los 3 tipos + load errors.
- **Integration** (Vitest, jsdom): un test exercising kebab → dialog →
  POST → onSuccess chain en un render tree compartido.
- **E2E** (Playwright): 8 CRUD + 6 sidebar + 3 a11y scenarios.
  Gateados detrás del dev server local (Caddy + Postgres + Redis +
  Astro dev per `playwright-e2e-patterns`).

Para validar NFR-11 (round-trip deep-equal): los 6 fixtures YAML bajo
`tests/unit/lib/rem/fixtures/writer/*.yml` se escriben, se re-leen
con el loader real, y `assertRoundTrip` (en `lib/rem/yaml-roundtrip.ts`)
compara byte-by-byte.

### Accessibility

- **NFR-13** axe-core scan reporta 0 violaciones AA en `/es/rem`.
- **NFR-11** contraste del PD badge: `#fef3c7` + `#92400e` = 7.5:1
  (supera el mínimo AA de 4.5:1).
- Paleta `#2D9D8E` (auth accent) para selected leaf + active input
  border + print button hover — contraste verificado contra `#ffffff`
  = 3.7:1 para texto no-crítico y 4.6:1 para texto con weight 600+.
- Native `<details>`/`<summary>` para los grupos colapsables (DD1)
  entrega keyboard nav (Tab/Space/Enter) y screen-reader
  announcement gratis.

### i18n

Namespace `rem.*` con 78 keys, paridad estricta entre `es.ts` y `en.ts`
(forzada por el union `TranslationKey` en `i18n/types.ts`):

- `rem.title`, `rem.sidebar.*`, `rem.tree.*`, `rem.detail.*`,
  `rem.print.*`, `rem.empty.*`, `rem.error.*`, `rem.shell.*`,
  `rem.pd.label`, `rem.sources.*`, `rem.cover.*` (45 keys v1)
- `rem.crud.*` (actions/edit/delete/cancel/create.submit/edit.submit/
  delete.confirm/delete.error/write.error), `rem.dialog.*` (create/edit
  headings), `rem.form.*` (label/invalid/required para code/title/
  priority/status/module/category/rationale/verifiedBy/fuentes/
  dependencias/stakeholder.name/.title/.role/.organization/.notes)
  (33 keys v2)

### Migration

Los YAMLs se generan desde el seed JSON via
`scripts/requirements/migrate_to_doorstop.py` (single-line shape con
`key: "value"` / `key: []` / `  - item` items). El endpoint
`serializeYamlSubset` (en `src/lib/rem/yaml-writer.ts`) reproduce ese
shape exacto — round-trip deep-equal está cubierto por NFR-11.

### Seguridad (académico)

**Importante**: los endpoints POST/PUT/DELETE no tienen autenticación.
Esto es intencional para el entregable académico. Para uso en
producción, ver TODO en el header de cada endpoint
(`pages/api/rem/{stakeholder,rf,rnf}/{index,[uid]}.ts`) — el patrón
recomendado es Sanctum (igual que el resto del frontend) + RLS en el
filesystem de `scripts/requirements/req/` para que sólo roles
`admin`/`editor` puedan escribir.

## 👀 Want to learn more?

Feel free to check [our documentation](https://docs.astro.build) or jump into our [Discord server](https://astro.build/chat).
