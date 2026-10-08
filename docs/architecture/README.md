# `docs/architecture/`

Diseño conceptual del sistema a nivel macro: cómo encajan las
instancias multi-tenant, los stores, los datos y el código. NO
incluye detalles de implementación (esos viven en `backend/` y
`frontend/`).

## Contenido

- `overview.md` — arquitectura general del sistema.
- `data-flow.md` — diagramas de flujo entre cliente, storefront,
  admin, API y workers.
- `multi-store.md` — multi-tienda dentro de la misma instancia.
- `multi-instance.md` — multi-instancia (3 stacks Docker
  independientes).
- `decisions.md` — ADRs (Architecture Decision Records) con
  justificación de las elecciones.
- `diagrams.md` — índice de diagramas generados con Archify.
- `assets-naming-convention.md` — cómo se nombran y almacenan
  los assets visuales.
- `tech-stack.md` — vista general de las tecnologías usadas.
- `tech-backend.md` — stack del backend (Laravel, PHP, extensiones).
- `tech-database.md` — stack de la base de datos (Postgres, Redis).
- `tech-frontend.md` — stack del frontend (Astro, React, Vite).
- `tech-infrastructure.md` — Docker, Caddy, futuras opciones K8s.

## Audiencia

Devs que necesitan entender "por qué" antes de tocar código. Empezá
por `overview.md`, después `data-flow.md`, después el área específica
que te interese.
