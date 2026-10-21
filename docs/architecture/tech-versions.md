# Versiones del stack tecnológico — AtlasCommerce v1.0.0

> **Fuente de verdad: los lockfiles, no los manifiestos.**
>
> `backend/composer.json` y `frontend/package.json` declaran rangos
> (`^13.8`, `^12.5.12`), no versiones instaladas. La versión real vive
> únicamente en `backend/composer.lock` y `frontend/bun.lock`.
>
> Regla práctica: para citar una versión en documentación, leela del
> lockfile. Si no está en el lockfile, no existe.

Última verificación: 2026-10-20.

---

## Tabla 1. Stack tecnológico del AtlasCommerce v1.0.0 por capa

| Capa | Tecnología | Versión real | Rol dentro del sistema | Requerimiento |
|---|---|---|---|---|
| Backend | PHP | **8.4.26** | Lenguaje de ejecución del backend; soporte de Eloquent, de las colas y del análisis estático | RNF-MAIN-052 |
| Backend | Laravel | **13.32.0** | Framework del backend: enrutamiento, ORM, validación, colas, caché, localización y generación de correo | RF-SALES-050, RNF-PERF-021 |
| Backend | Laravel Sanctum | **4.3.3** | Emisión de tokens de acceso con habilidades y alcance por tienda para la API | RNF-SEC-010, RNF-SEC-015 |
| Backend | Eloquent (ORM de Laravel) | **13.32.0** | Mapeo objeto-relacional del dominio, relaciones con carga anticipada y apertura de sesión con contexto RLS | RNF-MT-001, RNF-PERF-024 |
| Backend | Laravel Queue con driver Redis | **13.32.0** / Redis **7.4** | Ejecución asíncrona de facturación, notificaciones, imágenes y reportes | RNF-PERF-021, RNF-SCAL-033 |
| Backend | PHPUnit | **12.5.33** | Pruebas unitarias y de integración del backend, con cobertura mínima exigida | RNF-MAIN-050 |
| Frontend | Astro | **7.3.8** | Generador de sitio con renderizado híbrido para la tienda pública y el panel administrativo | RNF-PERF-023, RNF-COMP-090 |
| Frontend | React | **19.3.0** | Componentes interactivos del catálogo, carrito, checkout y panel administrativo | RF-CART-012, RNF-USA-040 |
| Frontend | TypeScript | **6.0.3** | Tipado estático del código de frontend | RNF-MAIN-052 |
| Frontend | Tailwind CSS | **4.3.3** | Sistema de estilos de utilidad con tema configurable por tienda | RF-STOR-131 |
| Frontend | Vitest | **4.1.11** | Pruebas unitarias de componentes y de funciones de interfaz | RNF-MAIN-050 |
| Frontend | Playwright | **1.64.0** | Automatización de recorridos críticos de compra y administración | RNF-MAIN-050 |
| Datos | PostgreSQL | **18** | Almacén relacional con Row Level Security multitenant, base del modelo de datos | RNF-MT-001 |
| Datos | PostGIS | **3.6** | Cálculo geográfico y tipos espaciales sobre PostgreSQL 18 | RF-SHIP-080 |
| Datos | Redis | **7.4** | Caché de aplicación, almacén de sesiones y cola de trabajos en segundo plano | RNF-PERF-020, RNF-PERF-021 |
| Infraestructura | Docker Compose | **>= 2.x** (sin pin) | Orquestación de los servicios de cada instancia de tienda, con redes y volúmenes independientes | RNF-MT-003, RNF-SCAL-032 |
| Infraestructura | Caddy | **2.11.7** | Terminación TLS automática, HSTS, redirección a HTTPS y virtual hosting por Host | RNF-SEC-013, RNF-MT-002 |
| Infraestructura | Nginx | **1.27-alpine** | Proxy inverso hacia PHP-FPM, servicio de estáticos y límite de peticiones | RNF-SEC-016 |
| Infraestructura | vsftpd | **`latest` sin pin** | Entrega de medios subidos por el usuario fuera del contenedor de aplicación | RF-CUSTOM-034 |
| Infraestructura | OpenTelemetry (PHP) | SDK **1.15.0**, exporter-otlp **1.4.0** | Trazas distribuidas y métricas de negocio instrumentadas desde la aplicación | RNF-OBS-071, RNF-OBS-072 |
| Infraestructura | GitHub Actions | 4 workflows, actions v4 | Integración continua con análisis estático, verificación de estilo y ejecución de pruebas | RNF-MAIN-051, RNF-MAIN-052 |

---

## Dónde se verifica cada versión

| Componente | Fuente autoritativa |
|---|---|
| PHP | `docker/instances/development/*/backend/Dockerfile.development` (`php:8.4-fpm-alpine`) |
| Laravel, Sanctum, PHPUnit, OpenTelemetry | `backend/composer.lock`, campo `packages[].version` |
| Eloquent, Queue | `backend/composer.lock`: se resuelven vía `laravel/framework`, no son paquetes independientes |
| Astro, React, TypeScript, Tailwind, Vitest, Playwright | `frontend/bun.lock`, sección `packages` |
| PostgreSQL, PostGIS, Redis, Nginx, vsftpd | `docker/instances/development/*/docker-compose.yml` |
| Caddy | `caddy version` en el host. Caddy corre como servicio systemd, fuera del repositorio |
| GitHub Actions | `.github/workflows/{backend,frontend,docs,infra}.yml` |

---

## Extensiones de PostgreSQL realmente instaladas

La tabla del marco teórico declara cinco. La instalación real difiere.

| Extensión | Declarada | Instalada |
|---|---|---|
| `pgcrypto` | Sí | Sí |
| `citext` | Sí | Sí |
| `pg_trgm` | Sí | Sí |
| `postgis` | Sí | Sí |
| `uuid-ossp` | Sí | **No** |
| `unaccent` | No | Sí |
| `pg_stat_statements` | No | Sí |

Fuente: `database/postgresql/init/001-extensions.sql`.

`uuid-ossp` está declarada pero no instalada; la generación de UUID se
resuelve hoy con `pgcrypto`. `unaccent` y `pg_stat_statements` están
instaladas y no figuran en el marco teórico.

---

## Discrepancias detectadas

Estas diferencias quedaron pendientes de corrección en la documentación
existente. No se modificaron esos archivos en esta verificación.

1. **Laravel 13.8 está desactualizado.** La versión resuelta es 13.32.0.
   El `^13.8` de `composer.json` es el piso del rango, no lo instalado.
   Afecta a `docs/operations/tech-stack.md` y `docs/backend/README.md`.

2. **Sanctum figura como "13".** Es un error de copiado de la fila de
   Laravel. La versión real es 4.3.3.

3. **Caddy figura como 2.8.** La versión real en el host es **2.11.7**.
   `docs/getting-started/prerequisites.md` dice v2.11.x: ninguno de los
   dos coincide.

4. **La CI corre una versión de PHP distinta a producción.**
   `.github/workflows/backend.yml` usa `php-version: '8.5'`; el runtime
   es 8.4.26. Los tests pasan contra una versión mayor que la que corre
   en producción.

5. **`uuid-ossp` no está instalada**, pese a estar declarada.

6. **Playwright figura como `latest`** en la documentación. Está fijado
   en 1.64.0 dentro del lockfile; `latest` es un label frágil para una
   herramienta de E2E.

7. **Docker Compose y vsftpd no tienen versión fijada.** Compose solo
   declara el requisito `>= 2.x`; vsftpd usa `fauria/vsftpd:latest`.
   Ninguno es reproducible.

---

## Estado de OpenTelemetry

Implementado en el código, pero **inactivo por defecto**.

- SDK presente: `backend/app/Tracing/Tracer.php`,
  `backend/app/Tracing/TracerSpan.php`,
  `backend/app/Http/Middleware/HttpTracingMiddleware.php`.
- Configuración: `backend/config/tracing.php`.
- Pruebas: `backend/tests/Unit/Tracing/TracerTest.php`.
- El valor por defecto es `exporter => noop` con `OTEL_ENABLED=false`,
  por lo que no emite trazas salvo que se activen las variables.
- **No hay collector OTLP en `docker/`**. Está implementado el SDK
  cliente, no la infraestructura de recolección.

---

## Pipeline de CI real

Los workflows existentes son cuatro, separados por capa:

| Workflow | Alcance |
|---|---|
| `backend.yml` | Análisis estático, estilo y pruebas del backend |
| `frontend.yml` | Typecheck, lint, unitarias, E2E y smoke de Storybook |
| `docs.yml` | Verificación de documentación |
| `infra.yml` | Validación de Docker Compose y configuración |

`backend.yml` corre `vendor/bin/phpunit --exclude-group social-auth`:
los tests de OAuth se excluyen por falta de credenciales en CI. La
cobertura completa que asume la documentación no se ejecuta.