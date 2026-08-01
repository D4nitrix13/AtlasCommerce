# AGENTS.md — Panda Estampados y Kitsune

Instrucciones para agentes de IA que trabajan en este proyecto.

## Documentation Style

Toda documentación en `docs/` debe seguir la guía de estilo:

[docs/DOCUMENTATION_STYLEGUIDE.md](docs/DOCUMENTATION_STYLEGUIDE.md "docs/DOCUMENTATION_STYLEGUIDE.md")

Reglas clave:

- Máximo 80 caracteres por línea.
- Todos los bloques de código con lenguaje especificado.
- Sin encabezados duplicados dentro del mismo archivo.
- Enlaces internos con título.
- Nombres técnicos en `código inline`.
- No presentar funcionalidades pendientes como implementadas.

## Project Structure

```txt
├── backend/              Laravel 13 API
├── frontend/             Astro 7 + React 19
├── docs/                 Documentación técnica
├── docker/               Configuración Docker
└── database/             Archivos de PostgreSQL
```

## Languages

- Backend: PHP 8.3+, Laravel 13
- Frontend: TypeScript 6, Astro 7, React 19
- Database: PostgreSQL 18 (PostGIS)
- Infra: Docker, Caddy

## Conventions

- Documentación en español, nombres técnicos en inglés.
- Commits convencionales sin atribución a IA.
- Respuestas cortas y directas.
