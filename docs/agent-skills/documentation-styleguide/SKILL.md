---
name: documentation-styleguide
description: Format and writing rules for Markdown in the project. 80 chars per line, Spanish prose with English technical names, code blocks with language, no heading level skipping. Triggers: creating/editing any .md file in docs/, AGENTS.md, README.md.
---

# Documentation Styleguide

Source: `docs/DOCUMENTATION_STYLEGUIDE.md` (790 lines).

> The skill content is in English for better LLM matching. The agent
> applies it writing documentation in Spanish per project convention.

## Scope

These rules apply to:

- `docs/`
- `README.md`
- `AGENTS.md`
- Any new `.md` file in the project.

## Principles

- **Clear**: explain each topic without detours.
- **Technical**: real file, folder, module names.
- **Maintainable**: avoid duplication.
- **Precise**: separate implemented from pending.
- **Consistent**: same format everywhere.

## Language

- **Spanish** for prose.
- **English** for technical names: `Store`, `ProductStore`, `StoreDomain`,
 `Laravel`, `Astro`, `PostgreSQL`, `PUBLIC_API_URL`, `/api/auth/login`,
 `docs/architecture/overview.md`.

## Maximum line: 80 characters

Each line ≤ 80 chars. To avoid `MD013/line-length`.

**Wrong:**

```md
El sistema cuenta con una arquitectura que permite
administrar varias tiendas desde una sola plataforma
centralizada.
```

**Right:**

```md
El sistema administra varias tiendas desde una sola plataforma
centralizada.
```

## Paragraphs

Manual line breaks when exceeding 80 chars. Blank line only between
different paragraphs.

```md
Panda y Kitsune son tiendas diferentes para el cliente, pero forman parte
de una misma operación administrativa.
```

## Lists

Each item ≤ 80 chars. If it continues, indent with 2 spaces.

```md
- El sistema utiliza `Caddy` como reverse proxy para enrutar múltiples
 dominios hacia el frontend, el admin y la API.
```

## Headings

- One single `#` per file.
- Hierarchy without skips:

```md
# Title
## Section
### Subsection
#### Detail
```

## Duplicate headings

Each ADR must have the identifier in subheadings to avoid `MD024`:

```md
## ADR-001: Una sola base de datos

### ADR-001 - Contexto
### ADR-001 - Decisión
### ADR-001 - Consecuencias

## ADR-002: Un solo backend

### ADR-002 - Contexto
### ADR-002 - Decisión
```

## Code blocks - always with language

❌ Without language:

````md
```
content
```
````

✅ With language:

````md
```txt
content
```
````

### Allowed languages

| Use | Language |
| -------------------- | ----------- |
| Plain text | `txt` |
| Terminal commands | `bash` |
| PHP | `php` |
| TypeScript | `ts` |
| Astro | `astro` |
| JSON | `json` |
| Env vars | `env` |
| YAML | `yaml` |
| SQL | `sql` |
| Mermaid | `mermaid` |
| Dockerfile | `dockerfile`|
| Caddyfile | `caddyfile` |

## Folder structures

Always `txt` block:

```md
```txt
docs/
├── architecture/
├── backend/
├── database/
└── operations/
```
```

## Terminal commands

`bash` block. If exceeds 80 chars, split with `\`:

```bash
php artisan test \
 --compact \
 tests/Feature/Auth/EmailOtpTwoFactorTest.php
```

## Environment variables

`env` block:

```env
PUBLIC_API_URL=http://localhost:9000/api
APP_ENV=local
APP_DEBUG=true
```

Inline with backticks: `` `PUBLIC_API_URL` ``.

## Diagrams

- **Mermaid** → `mermaid` block.
- **ASCII** → `txt` block.

## Inline code

Always backticks for technical names:

- Paths: `` `docs/frontend/storefront.md` ``.
- Endpoints: `` `/api/products` ``.
- Variables: `` `PUBLIC_API_URL` ``.
- Classes: `` `Store` ``.
- Tables: `` `products` ``.
- Short commands: `` `pnpm dev` ``.
- Modules: `` `auth` ``.

## Bold and italics

- `**bold**` for important concepts.
- `*italic*` for soft emphasis.

> Don't highlight everything. If everything is bold, nothing stands out.

## Internal links

**Always** with title:

```md
[text](path "path")
```

❌ Without title:

```md
[architecture/overview.md](architecture/overview.md)
```

✅ With title:

```md
[architecture/overview.md](architecture/overview.md "architecture/overview.md")
```

Example in prose:

```md
Consulta la arquitectura general en
[architecture/overview.md](architecture/overview.md "architecture/overview.md").
```

## Long links

If they break 80 chars, use reference:

```md
Consulta la [guía de estilo][documentation-styleguide].

[documentation-styleguide]: DOCUMENTATION_STYLEGUIDE.md "DOCUMENTATION_STYLEGUIDE.md"
```

## External links

With title when useful:

```md
Consulta la documentación de
[Laravel](https://laravel.com/docs "Laravel Documentation").
```

## Tables

Only when clearer than a list. If they exceed 80 chars, convert to
subsections.

❌ Long table:

```md
| File | Description that's too long and exceeds the recommended limit easily |
| ------- | ----------------------------------------------------------------- |
```

✅ Subsections:

```md
### `docs/architecture/overview.md`

Describe the general architecture of the system.

### `docs/architecture/multi-store.md`

Explains how Panda and Kitsune work within the same platform.
```

## Implementation states

Use clear labels:

- `Implementado`
- `Pendiente`
- `Recomendado`
- `Fase futura`
- `Debe verificarse en el código`

```md
El módulo `Passkeys` está preparado para fase futura, pero todavía no debe
documentarse como funcionalidad completa.
```

## Tone

Direct, technical, natural. Avoid:

- "En la actualidad"
- "Cabe destacar"
- "Es importante mencionar"
- "Asimismo"
- "Por consiguiente"

❌ Filler:

```md
Es importante mencionar que el sistema cuenta con una arquitectura que
permite administrar tiendas.
```

✅ Direct:

```md
El sistema administra varias tiendas desde una sola plataforma.
```

## Architecture documentation

Cover:

1. What decision was made.
2. Why.
3. What alternatives existed.
4. What consequences it has.
5. What parts are implemented.
6. What parts are recommendations or future phase.

## Multi-store

Anchor phrase:

```txt
Panda y Kitsune son tiendas diferentes para el cliente, pero forman parte
de una misma operación administrativa.
```

- `Store` = technical store entity.
- `store_id` = data filtering per store.

## Frontend

- `src/components/storefront` = public store.
- `src/components/admin` = admin panel.
- `src/lib/api.ts` = HTTP client.
- `src/lib/endpoints.ts` = centralized endpoints.
- `PUBLIC_API_URL` always with `/api`.
- Internal endpoints without `/api`.

## Backend

- API controllers in `app/Http/Controllers/Api`.
- Auth controllers in `app/Http/Controllers/Api/Auth`.
- Business logic in `app/Services`.
- JSON responses with `API Resources`.
- Complex validations with `Form Requests`.

## Database

- `TIENDA` → `stores`, `TIENDA_DOMINIO` → `store_domains`.
- Tables with tenant scope → document `store_id`.

## Operations

- VPS, `Docker`, `Caddy`, domains, subdomains, HTTPS, internal network,
 exposed services, non-exposed services.

## ADR format

```md
## ADR-XXX: Title

### ADR-XXX - Contexto
### ADR-XXX - Alternativas consideradas
### ADR-XXX - Decisión
### ADR-XXX - Consecuencias
```

## Allowed exceptions (80 chars)

- Long URLs.
- Commands that lose clarity when split.
- Auto-generated routes.
- Terminal outputs copied.
- Small tables where reading is better than a list.

The rule remains 80 chars; exceptions are few.

## Pre-save checklist

```txt
[ ] One single main title with #.
[ ] No duplicate headings.
[ ] Lines ≤ 80 chars when reasonable.
[ ] Code blocks with language.
[ ] Internal links with title.
[ ] Technical names with backticks.
[ ] Don't document pending things as implemented.
[ ] No repetitive filler phrases.
[ ] Folder structures in txt block.
[ ] Commands in bash block.
[ ] Mermaid in mermaid block.
```

## Trigger patterns

Activate when:

- Creating or editing any `.md` in `docs/`.
- Creating or editing `README.md`, `AGENTS.md`.
- Creating an ADR.
- Documenting a new module.
- CI fails with `markdownlint`.

## Related skills

- `adr-format` - specific format for architectural decisions.
- `quality-gate-pre-commit` - runs `markdownlint` before commit.
