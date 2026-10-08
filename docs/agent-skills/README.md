# `docs/agent-skills/`

Skills para IAs (Claude Code y otros agentes basados en LLM). NO es
documentación para humanos devs — si sos un dev humano buscando
info del proyecto, ignorá esta carpeta y empezá por
`docs/getting-started/README.md`.

## Contenido

Cada subdirectorio es un skill individual. La skill `SKILL.md` define
el scope, los triggers, y las convenciones que el agente debe
aplicar cuando el skill está activo.

Lista de skills incluidas en este proyecto:

- `adr-format` — cómo escribir Architecture Decision Records.
- `astro-frontend-structure` — organización de páginas, componentes
  e islands en Astro 7.
- `astro-themes-and-palette` — el sistema de theming por tienda
  (Panda esmeralda, Kitsune violeta, etc).
- `astro-vitest-component-testing` — testing de componentes Astro/React
  con vitest.
- `caddyfile-multi-tenant-routing` — cómo está ruteado Caddy para
  multi-instancia multi-store.
- `conventional-commits-and-prs` — formato de commits y PRs del
  proyecto (español, prose plano, no markdown).
- `docker-stack-orchestration` — el script `scripts/docker-stack.sh`
  y el ciclo de vida de los containers.
- `documentation-styleguide` — reglas de formato para `.md`
  (kebab-case filenames, español, 80 chars, etc).
- `engram-conventions` — qué guardar y qué NO guardar en Engram
  (memoria persistente de agentes).
- `git-monorepo-workflow` — cómo trabajar en este repo multi-app.
- `laravel-architecture-decisions` — convenciones Laravel
  específicas del proyecto.
- `laravel-feature-testing` — testing de features con PHPUnit +
  Sanctum stateful.
- `laravel-multi-tenant-patterns` — patrones de código para
  queries y models tenant-scoped.
- `playwright-e2e-patterns` — testing E2E con Playwright
  (login stateful, helpers compartidos).
- `prompts` — templates de prompts para tareas comunes.
- `quality-gate-pre-commit` — qué validar antes de commitear
  (linters, tests, typecheck).
- `redis-caching-patterns` — cuándo cachear, qué invalidar, cómo
  evitar stampedes.

## Cómo agregar una skill nueva

1. Creá la carpeta `docs/agent-skills/<nombre-kebab-case>/`.
2. Escribí `SKILL.md` con frontmatter YAML (name, description) +
   descripción de la skill, triggers, convenciones.
3. Usá prosa explicativa, no bullet points sueltos.
4. Si tenés scripts o templates, poné los en subcarpetas
   (`scripts/`, `templates/`) dentro de la skill.
