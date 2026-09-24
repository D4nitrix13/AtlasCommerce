# Documentación Técnica — Panda Estampados y Kitsune

Plataforma ecommerce multi-tienda administrada desde un backend
`Laravel` central, con tiendas públicas separadas por dominio.

## Qué es el proyecto

Panda Estampados y Kitsune opera como una sola plataforma técnica
con dos marcas comerciales:

- **Panda Estampados** — Empresas, mayoristas, productos
  personalizados y merchandising.
- **Kitsune Store** — Anime, videojuegos, cultura japonesa, retail.

Ambas tiendas comparten backend, base de datos, inventario y panel
administrativo, pero se presentan como tiendas independientes para
el cliente final.

## Cómo leer esta documentación

### Empezar de cero

```txt
1. getting-started/README.md        # Setup completo paso a paso
2. getting-started/prerequisites.md # Instalar PHP, Node, Docker, Caddy
3. getting-started/installation.md # Clonar repo + composer/pnpm install
4. getting-started/databases.md    # Levantar Postgres + Redis
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
- development/testing.md            # PHPUnit + Playwright
- development/linting.md            # Linters
- development/git-workflow.md      # Commits + PRs
```

### Quiero entender la arquitectura

```txt
- architecture/overview.md          # Arquitectura general
- architecture/multi-store.md       # Multi-tienda a fondo
- architecture/flow.md              # Diagramas de flujo
- architecture/decisions.md         # ADR (decisiones documentadas)
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
- backend/auth/auth-2fa.md          # 2FA
- backend/laravel/componentes-arquitectura.md
- backend/laravel/composer-tools.md # Pint + Larastan
- backend/stores/stores-module.md    # Multi-tienda
- backend/stores/store-context.md
- backend/stores/permissions-by-store.md
- development/releases/v2.0.0.md   # Release notes (última versión)

- database/README.md                # DB
- database/data-model.md            # ER

- frontend/README.md                # Frontend
- frontend/estructura.md
- frontend/storefront.md
- frontend/admin-panel.md
- frontend/api-client.md

- operations/README.md              # Índice
- operations/docker.md
- operations/caddy.md
- operations/deployment.md
- operations/environments.md
- operations/backups.md
```

### Estilo de la documentación

La guía oficial está en
[DOCUMENTATION_STYLEGUIDE.md](DOCUMENTATION_STYLEGUIDE.md "DOCUMENTATION_STYLEGUIDE.md").

Reglas resumidas:

- 80 caracteres por línea.
- Español con nombres técnicos en inglés.
- Bloques de código con lenguaje explícito.
- Encabezados sin saltos de nivel.

## Convenciones

- Los nombres técnicos (clases, archivos, rutas, endpoints) se
  mantienen en inglés.
- La documentación se escribe en español con tono técnico.
- Lo implementado se documenta como hecho; lo pendiente se marca
  explícitamente.
- Las recomendaciones arquitectónicas se identifican como tales.
