# Documentación Técnica — AtlasCommerce

Plataforma ecommerce **multi-instancia** desplegada como 3
stacks `Docker` independientes (`panda-estampados-and-kitsune`,
`mm-boutique`, `morales-store`). Dentro de la instancia
`panda-estampados-and-kitsune` corre **multi-store** con 2
tenants (Panda Estampados + Kitsune) que comparten backend
`Laravel`, base de datos `PostgreSQL`, `Redis` y panel
administrativo. Las otras dos instancias son single-brand.

## Qué es el proyecto

AtlasCommerce se compone de 4 marcas comerciales repartidas
entre las 3 instancias:

| Marca | Dominio público | Enfoque | Instancia |
| --- | --- | --- | --- |
| **Panda Estampados** | `panda-estampados.com` | B2B — estampados, RFQ, bulk | `panda-estampados-and-kitsune` |
| **Kitsune Store** | `kitsune.com` | B2C — anime, cultura japonesa | `panda-estampados-and-kitsune` |
| **MM Boutique** | `mm-boutique.com` | Single-tenant | `mm-boutique` |
| **Morales Store** | `morales-store.com` | Single-tenant | `morales-store` |

Cada instancia es una unidad aislada con su propia BD, Redis,
backend y frontend. Ver
[architecture/multi-instance-roadmap.md](architecture/multi-instance-roadmap.md
"architecture/multi-instance-roadmap.md") para la historia del
despliegue multi-instancia.

## Cómo leer esta documentación

### Empezar de cero

```txt
1. getting-started/README.md        # Setup completo paso a paso
2. getting-started/prerequisites.md # Instalar PHP, Node, Docker, Caddy
3. getting-started/installation.md # Clonar repo + composer/pnpm install
4. getting-started/databases.md     # Levantar Postgres + Redis
5. getting-started/backend-setup.md # Configurar Laravel
6. getting-started/frontend-setup.md # Configurar Astro
7. getting-started/local-domains.md # Hosts locales multi-tienda
8. operations/caddy.md             # Reverse proxy (dev + prod)
9. getting-started/seed.md         # Datos iniciales
```

### Ya está todo instalado, quiero desarrollar

```txt
- development/README.md            # Workflow diario
- development/backend.md           # Comandos Laravel
- development/frontend.md          # Comandos Astro
- development/testing.md           # PHPUnit + Playwright
- development/linting.md           # Linters
- development/git-workflow.md      # Commits + PRs
- development/core-migration.md    # Migración app/Core/
- development/plugins.md           # Plugins Composer
- development/sessions-log.md      # Log de sesiones recientes
```

### Quiero entender la arquitectura

```txt
- architecture/overview.md          # Arquitectura general
- architecture/multi-store.md       # Multi-tienda a fondo
- architecture/multi-instance-roadmap.md # Roadmap multi-instancia
- architecture/flow.md              # Diagramas de flujo
- architecture/decisions.md         # ADR (decisiones documentadas)
- architecture/assets-naming-convention.md # Nombres de assets
- architecture/architecture-diagrams.md # Diagramas con Archify
```

### Stack tecnológico

```txt
- stack/README.md                   # Vista general
- stack/backend.md                  # PHP + Laravel
- stack/frontend.md                 # Astro + React
- stack/database.md                 # PostgreSQL + Redis
- stack/infrastructure.md           # Docker + Caddy + K8s
```

### Detalles por capa

```txt
- backend/README.md                 # Estructura del backend
- backend/api/routes.md             # Rutas API
- backend/api/authentication.md     # Sanctum
- backend/auth/auth-2fa.md          # 2FA (TOTP, Email OTP, WebAuthn)
- backend/auth/recovery-methods.md  # Métodos de recuperación
- backend/laravel/componentes-arquitectura.md
- backend/laravel/composer-tools.md # Pint + Larastan
- backend/stores/stores-module.md   # Multi-tienda
- backend/stores/store-context.md
- backend/stores/permissions-by-store.md
- backend/cursor-pagination.md
- backend/emails-i18n.md
- backend/n-plus-one-prevention.md
- backend/test-coverage.md
- development/releases/v2.0.0.md   # Release notes (última versión)

- database/README.md                # DB
- database/data-model.md            # ER

- frontend/README.md                # Frontend
- frontend/estructura.md
- frontend/storefront.md
- frontend/admin-panel.md
- frontend/api-client.md
- frontend/design-references.md     # Baymard + nicho
- frontend/storybook.md            # Storybook 10 (UI primitives)
- frontend/image-optimization.md
- frontend/browsers-support.md

- operations/README.md              # Índice
- operations/docker.md
- operations/caddy.md
- operations/deployment.md
- operations/environments.md
- operations/backups.md
- operations/secret-rotation.md
- operations/oauth-providers.md
- operations/sla-and-healthchecks.md
- operations/observability-runbook.md
```

### Diagramas de arquitectura

Los diagramas visuales (flowchart, sequence, data-flow,
lifecycle) se generan con la skill **Archify**. Ver
[architecture/architecture-diagrams.md](architecture/architecture-diagrams.md
"architecture/architecture-diagrams.md") para cómo generarlos
y dónde está el diagrama actual.

```txt
.archify/architecture-atlascommerce-<fecha>/
└── atlascommerce-architecture.html  # diagrama standalone
```

### Estilo de la documentación

La guía oficial está en
[DOCUMENTATION_STYLEGUIDE.md](DOCUMENTATION_STYLEGUIDE.md "DOCUMENTATION_STYLEGUIDE.md").

Reglas resumidas:

- 80 caracteres por línea.
- Español con nombres técnicos en inglés.
- Bloques de código con lenguaje explícito.
- Encabezados sin saltos de niveles.
- Estados: `Implementado`, `Pendiente`, `Recomendado`, `Fase futura`.

## Convenciones

- Los nombres técnicos (clases, archivos, rutas, endpoints) se
  mantienen en inglés.
- La documentación se escribe en español con tono técnico.
- Lo implementado se documenta como hecho; lo pendiente se marca
  explícitamente.
- Las recomendaciones arquitectónicas se identifican como tales.
- Las menciones a PRs y commits van con su SHA corto entre
  paréntesis cuando aporta trazabilidad.
