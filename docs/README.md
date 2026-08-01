# Documentación Técnica — Panda Estampados y Kitsune

Plataforma ecommerce multi-tienda administrada desde
un backend `Laravel` central, con tiendas públicas
separadas por dominio.

## Qué es el proyecto

Panda Estampados y Kitsune opera como una sola
plataforma técnica con dos marcas comerciales:

- **Panda Estampados** — orientada a empresas, clientes
  mayoristas/corporativos, productos personalizados
  y merchandising empresarial.
- **Kitsune Store** — orientada a clientes
  habituales/minoristas, productos de anime,
  videojuegos y cultura japonesa.

Ambas tiendas comparten backend, base de datos,
inventario y panel administrativo, pero se presentan
como tiendas independientes para el cliente final.

## Stack tecnológico

- **Frontend** — `Astro` 7, `React` 19,
  `Tailwind CSS` 4, `TypeScript` 6
- **Backend** — `Laravel` 13, `PHP` 8.3+,
  `Sanctum` Bearer tokens
- **Base de datos** — `PostgreSQL` (`PostGIS`)
- **Infraestructura** — `Docker`, `Caddy`, VPS
- **Testing** — `PHPUnit` 12, `Pint`, `Larastan`

## Cómo leer esta documentación

### 1. Arquitectura general

Si necesitas entender el sistema a nivel alto:

- [architecture/overview.md](architecture/overview.md "Arquitectura general")
  — Arquitectura general del sistema
- [architecture/multi-store.md](architecture/multi-store.md "Decisión multi-tienda")
  — Decisión multi-tienda a profundidad
- [architecture/decisions.md](architecture/decisions.md "Decisiones arquitectónicas")
  — Decisiones arquitectónicas documentadas (ADR)

### 2. Backend

Si vas a trabajar en la API `Laravel`:

- [backend/README.md](backend/README.md "Estructura del backend")
  — Estructura general del backend
- [backend/api/routes.md](backend/api/routes.md "Estrategia de rutas")
  — Estrategia de rutas API
- [backend/api/authentication.md](backend/api/authentication.md "Autenticación con Sanctum")
  — Autenticación con `Sanctum`
- [backend/auth/auth-2fa.md](backend/auth/auth-2fa.md "Autenticación de dos factores")
  — Autenticación de dos factores
- [backend/laravel/componentes-arquitectura.md](backend/laravel/componentes-arquitectura.md "Componentes de arquitectura")
  — Controllers, Services, Models, etc.
- [backend/laravel/composer-tools.md](backend/laravel/composer-tools.md "Herramientas de composer")
  — `Pint`, `Larastan`, IDE Helper
- [backend/stores/stores-module.md](backend/stores/stores-module.md "Módulo Stores")
  — Módulo Stores (multi-tienda)
- [backend/stores/store-context.md](backend/stores/store-context.md "Resolución de tienda")
  — Resolución de tienda actual
- [backend/stores/permissions-by-store.md](backend/stores/permissions-by-store.md "Permisos por tienda")
  — Permisos por tienda

### 3. Base de datos

Si necesitas entender el modelo de datos:

- [database/README.md](database/README.md "Organización de la BD")
  — Organización de la base de datos
- [database/data-model.md](database/data-model.md "Modelo de datos multi-tienda")
  — Modelo de datos multi-tienda
- [database/diagrams/ecommerce-er.mmd](database/diagrams/ecommerce-er.mmd "DER en Mermaid")
  — DER en `Mermaid`

### 4. Frontend

Si vas a trabajar en `Astro`/`React`:

- [frontend/README.md](frontend/README.md "Estructura del frontend")
  — Estructura general del frontend
- [frontend/estructura.md](frontend/estructura.md "Estructura detallada")
  — Estructura detallada de carpetas
- [frontend/storefront.md](frontend/storefront.md "Tienda pública multi-tienda")
  — Tienda pública multi-tienda
- [frontend/admin-panel.md](frontend/admin-panel.md "Panel administrativo")
  — Panel administrativo
- [frontend/api-client.md](frontend/api-client.md "Cliente API")
  — Cliente API (`api.ts`, `endpoints.ts`)

### 5. Operaciones

Si necesitas desplegar o administrar la infraestructura:

- [operations/README.md](operations/README.md "Índice de operaciones")
  — Índice de operaciones
- [operations/docker.md](operations/docker.md "Servicios Docker")
  — Servicios `Docker`
- [operations/caddy.md](operations/caddy.md "Reverse proxy con Caddy")
  — Reverse proxy con `Caddy`
- [operations/deployment.md](operations/deployment.md "Pasos de despliegue")
  — Pasos de despliegue
- [operations/environments.md](operations/environments.md "Variables de entorno")
  — Variables de entorno
- [operations/backups.md](operations/backups.md "Estrategia de respaldos")
  — Estrategia de respaldos

## Convenciones de documentación

- Los nombres técnicos (clases, archivos, rutas,
  endpoints) se mantienen en inglés.
- La documentación se escribe en español con tono
  técnico-profesional.
- Lo implementado se documenta como hecho; lo pendiente
  se marca explícitamente.
- Las recomendaciones arquitectónicas se identifican
  como tales.
