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

### URL

- `/es/rem` y `/en/rem` — viewer por locale (usa el routing
  `[locale]/` del frontend).
- `/es/rem?id=rf_001` — deep link que abre directamente un
  requisito. El parámetro inválido cae al primer item del dataset
  (fallback `stakeholder_001`).

### Fuente de datos

Los YAMLs viven en `scripts/requirements/req/` (un archivo por
requisito, schema Doorstop). El frontend los lee en SSR vía
`src/lib/rem/loader.ts` (Node `fs` + parser YAML in-house porque
`yaml` no es dependencia directa). El dataset queda cacheado en
memoria 5 minutos.

### Comandos

| Comando | Acción |
| :------ | :----- |
| `pnpm vitest run tests/unit/rem` | tests unitarios del dominio REM (loader, tree, search) |
| `pnpm vitest run tests/unit/components/rem` | tests unitarios de componentes REM |
| `pnpm playwright test tests/e2e/rem/rem-viewer.spec.ts` | e2e del viewer (9 tests + screenshot baseline) |
| `pnpm playwright test tests/e2e/rem/rem-pdf.spec.ts` | e2e del flujo print-to-PDF (gated por `PDF_TESTS=1`) |
| `pnpm vitest run --coverage` | verifica thresholds NFR-05 (85% líneas en `lib/rem/**` + `components/rem/**`) |

### Cobertura de requisitos

- `src/lib/rem/{types,loader,tree,search,format}.ts` — dominio REM.
- `src/components/rem/*` — UI del visor.
- `src/pages/[locale]/rem/index.astro` — ruta SSR.
- `src/pages/api/rem/[uid].html.ts` — endpoint para refrescar el
  detalle sin recargar la página.
- `src/styles/rem-print.css` — `@media print` para el PDF.
- `src/i18n/locales/{es,en}.ts` — namespace `rem.*` (45 keys).

## 👀 Want to learn more?

Feel free to check [our documentation](https://docs.astro.build) or jump into our [Discord server](https://astro.build/chat).
