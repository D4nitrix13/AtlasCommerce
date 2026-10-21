# Documentación Técnica — AtlasCommerce

Plataforma ecommerce **multi-instancia** desplegada como 3 stacks
`Docker` independientes (`panda-estampados-and-kitsune`, `mm-boutique`,
`morales-store`). Dentro de `panda-estampados-and-kitsune` corre
**multi-store** con 2 tenants (Panda Estampados + Kitsune) que
comparten backend `Laravel`, base de datos `PostgreSQL`, `Redis` y
panel administrativo. Las otras dos instancias son single-brand.

## Qué es el proyecto

| Marca | Dominio | Enfoque | Instancia |
| --- | --- | --- | --- |
| Panda Estampados | `panda-estampados.com` | B2B estampados, RFQ, bulk | `panda-estampados-and-kitsune` |
| Kitsune Store | `kitsune.com` | B2C anime, cultura japonesa | `panda-estampados-and-kitsune` |
| MM Boutique | `mm-boutique.com` | Single-tenant | `mm-boutique` |
| Morales Store | `morales-store.com` | Single-tenant | `morales-store` |

## Índice

### Empezar (1ra vez)

`getting-started/README.md` — setup paso a paso, ordenado.

### Entender la arquitectura

`architecture/README.md` — índice del directorio (overview, multi-store,
multi-instance, ADRs, etc).

### Backend (Laravel)

`backend/README.md` — índice del backend. Después `architecture/tech-backend.md`
y `architecture/tech-database.md` para el stack.

### Frontend (Astro + React)

`frontend/README.md` — índice del frontend. Después
`architecture/tech-frontend.md` para el stack.

### Workflow diario de dev

`development/README.md` — comandos del día a día, linting, testing,
git workflow.

### Producción y operaciones

`operations/README.md` — deploy, monitoring, secrets, runbooks.

### Stack tecnológico

`operations/tech-stack.md` — decisiones, tradeoffs y contexto del stack.
`architecture/tech-versions.md` — registro autoritativo de versiones
verificadas contra los lockfiles. Detalles por capa en
`architecture/tech-{backend,database,frontend,infrastructure}.md`.

### Base de datos

`database/README.md` — modelo de datos multi-store.

### Diagramas

`architecture/diagrams.md` — diagramas Archify. `uml/` — entregable
académico (PUML + PlantUML + renderizado).

### Estilo de la documentación

`DOCUMENTATION_STYLEGUIDE.md` — reglas de formato, naming convention,
prohibido markdown en el body. Obligatorio antes de crear/editar un
`.md`.

### Académico (UNI)

`requirements/STAKEHOLDERS.md`, `requirements/TASKS.md` —
entregables del proyecto universitario. NO es doc operativa.

## Convenciones rápidas

- Nombres técnicos en inglés (`Store`, `Astro`, `PUBLIC_API_URL`).
- Prosa en español, 80 chars por línea, sin markdown en el body
  del commit ni en el de los docs.
- Filenames en kebab-case: `data-flow.md` no `dataFlow.md` ni
  `data_flow.md`.
- Cada subdir tiene un README.md que explica qué hay adentro.

## Notas

- Las `agent-skills/` son para IAs (no devs humanos). Empezá por
  `getting-started/` y dejá `agent-skills/` para Claude Code.
- Si buscás una contraseña de admin dev, está en
  `getting-started/credentials.md`.
- Si buscás qué hacer cuando algo se rompe, está en
  `operations/runbooks/`.
