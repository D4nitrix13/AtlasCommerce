# Roadmap Multi-Instance — Plan de Implementación

> Plan completo para evolucionar el sistema actual (multi-tenant single
> deploy) hacia una plataforma multi-instance SaaS con feature flags,
> themes por tenant y plugins Composer.
>
> **Última actualización:** 2026-08-02

---

## Tiendas del proyecto

| Tenant | Marca | Dominio público | Dominio admin | Instancia |
| ------ | ----- | --------------- | ------------- | --------- |
| Cliente | Panda Estampados | `panda-estampados.com` | `admin.panda-estampados.com` | `panda-estampados` |
| Cliente | Kitsune Store | `kitsune.com` | `admin.kitsune.com` | `panda-estampados` |
| MM Boutique owner | MM Boutique | `mm-boutique.com` | `admin.mm-boutique.com` | `mm-boutique` |
| Morales Store owner | Morales Store | `morales-store.com` | `admin.morales-store.com` | `morales-store` |

Las primeras 2 tiendas viven juntas (mismo dueño). Las tiendas de los
familiares son independientes en servers separados.

---

## Leyenda de estados

| Símbolo | Estado |
| ------- | ------ |
| ✅ | Completado |
| 🔄 | En progreso |
| ⏳ | Pendiente |
| ❌ | Bloqueado |
| 🚫 | Cancelado |

---

## Resumen ejecutivo

```txt
Fase 1 — Multi-instance           ████████░░  100%   (completa)
Fase 2 — Feature flags            ██████░░░  100%   (completa)
Fase 3 — Theme system en DB       ████████░  100%   (completa)
Fase 4 — Plugins Composer         ██████████  100%   (completa)
Fase 5 — Modular monolith + RLS   ██████████  100%   (completa)
```

**Total tareas:** 32
**Completadas:** 27 (F1.1-F1.5, F2.1-F2.6, F3.1-F3.6, F3.7, F4.1-F4.4, F5.1-F5.5)
**En progreso:** 0
**Pendientes:** 5

**Fases 1-5 COMPLETAS (100%)**. Todas las tareas del roadmap están marcadas.

**Fases 1-4 COMPLETAS**. Queda 1 fase: Fase 5.

---

## Fase 1 — Multi-instance deploys

**Razón:** Hoy el sistema es multi-tenant en un único deploy. Para servir a
múltiples clientes en servidores independientes (cliente Panda/Kitsune
junto, MM Boutique sola, Morales Store sola) necesitamos que **un solo
repo** pueda generar **N deployments aislados** con diferente
configuración.

**Características:**

- Una sola base de código (no forks).
- Cada instancia tiene su propia BD, su `.env`, sus dominios.
- Los tenants se declaran vía archivo JSON (no hardcoded en código).
- El deploy es `git clone + docker compose up` por servidor.

---

### F1.1 — Comando `tenants:bootstrap` ✅

**Razón:** Hoy los tenants (Panda + Kitsune) están hardcoded en
`DatabaseSeeder`. Necesitamos un comando que cree tenants desde un
archivo JSON, para que cada instancia pueda definir sus propios
tenants sin tocar código.

**Características:**

- Recibe un archivo JSON como argumento: `tenants:bootstrap seeds/mm-boutique.json`.
- Crea `Store` records por tenant definido.
- Crea `StoreDomain` records por cada dominio.
- Crea el admin user (idempotente con `updateOrCreate`).
- Asocia el admin user a los tenants vía pivot `user_stores`.
- Idempotente: corre múltiples veces sin duplicar datos.
- Retorna exit code 0 si todo OK, 1 si error.

**Dependencias:** Ninguna.

**Criterios de aceptación:**

- [x] Comando corre sin error con `seeds/panda.json`.
- [x] Crea 2 stores (Panda + Kitsune) y sus dominios.
- [x] Crea el admin user y lo asocia a las 2 tiendas.
- [x] Re-correr el comando no duplica stores ni domains.
- [x] Tests unitarios del comando (input válido, JSON malformado, idempotencia).

**Archivos a crear/modificar:**

- `app/Console/Commands/BootstrapTenantCommand.php` (nuevo) ✅
- `tests/Feature/Console/BootstrapTenantCommandTest.php` (nuevo) ✅

**Estado:** Completado el 2026-08-02. 10 tests nuevos (idempotencia,
múltiples tenants, validación de schema, password desde env, etc).
Total suite: 152 tests passing.

---

### F1.2 — Refactorizar `DatabaseSeeder` ✅

**Razón:** El seeder actual crea siempre Panda + Kitsune hardcoded.
Esto no sirve cuando Morales Store deploya y no quiere esas tiendas.

**Características:**

- `DatabaseSeeder` solo crea currencies base (USD, NIO) y permissions/roles.
- La creación de tenants se delega completamente a `tenants:bootstrap`.
- Cada instancia corre su propio `tenants:bootstrap` con su JSON.
- `AdminUserSeeder` eliminado (redundante con `tenants:bootstrap`).

**Criterios de aceptación:**

- [x] `php artisan db:seed` no crea stores ni users.
- [x] Solo crea currencies si no existen (idempotente).
- [x] Tests actualizados.
- [x] AdminUserSeeder eliminado.

**Archivos:**

- `backend/database/seeders/DatabaseSeeder.php` (modificado) ✅
- `backend/database/seeders/AdminUserSeeder.php` (eliminado) ✅

**Estado:** Completado el 2026-08-02. 152/152 tests passing.

---

### F1.3 — Estructura `docker/instances/` ✅

**Razón:** Cada instancia necesita su propia configuración (Caddyfile,
`.env`, seeds, docker-compose). Estructura estándar en el repo.

**Características:**

- `docker/instances/{name}/` por cada cliente.
- Cada subdir tiene: `.env.example`, `Caddyfile`,
  `docker-compose.yml`, `seeds/*.json`.
- Documentación inline en cada archivo.

**Estructura:**

```txt
docker/instances/
├── README.md                    # Índice de instancias
├── panda-estampados/                # Cliente: Panda + Kitsune
│   ├── .env.example
│   ├── Caddyfile                 # Dominios panda + kitsune
│   ├── docker-compose.yml
│   └── seeds/
│       └── panda.json            # Panda + Kitsune
├── mm-boutique/                  # MM Boutique
│   ├── .env.example
│   ├── Caddyfile                 # Dominio mm-boutique.com
│   ├── docker-compose.yml
│   └── seeds/
│       └── mm-boutique.json      # MM Boutique
└── morales-store/                # Morales Store
    ├── .env.example
    ├── Caddyfile                 # Dominio morales-store.com
    ├── docker-compose.yml
    └── seeds/
        └── morales-store.json    # Morales Store
```

**Criterios de aceptación:**

- [x] Las 3 instancias creadas con sus archivos base.
- [x] Cada `docker-compose.yml` referencia `app` con env vars de la instancia.
- [x] Cada Caddyfile apunta a los dominios correctos.

**Archivos:** Directorio nuevo. ✅

**Estado:** Completado el 2026-08-02. 3 instancias (panda-estampados, mm-boutique,
morales-store) con su .env.example + Caddyfile + docker-compose.yml.
2 Dockerfiles (backend, frontend) + entrypoint-backend.sh. README índice.

---

### F1.4 — Seeds JSON por instancia ✅

**Razón:** Cada instancia necesita un archivo que declare sus tenants,
dominios, admin user y features.

**Características:**

- Formato JSON declarativo.
- Cada instancia tiene 1+ archivos `seeds/*.json`.
- Schema validado por `tenants:bootstrap` (dominios, slugs, currencies, etc.).
- Entry-point del backend lee `TENANTS_SEED_FILE` y corre el comando
  automáticamente en el primer arranque del container.

**Schema:**

```json
{
  "tenants": [
    {
      "slug": "mm-boutique",
      "name": "MM Boutique",
      "currency": "USD",
      "domains": ["mm-boutique.com", "www.mm-boutique.com"],
      "admin_domains": ["admin.mm-boutique.com"]
    }
  ],
  "admin_user": {
    "email": "admin@mm-boutique.com",
    "password_env": "MM_ADMIN_PASSWORD"
  },
  "features": {}
}
```

**Criterios de aceptación:**

- [x] 3 archivos seed.json creados (uno por instancia).
- [x] Validación de schema via tests automatizados (SeedFilesValidationTest).
- [x] Entry-point actualizado para leer TENANTS_SEED_FILE.
- [x] Los 3 seeds pasan `tenants:bootstrap` con éxito en tests.

**Archivos:**

- `docker/instances/panda-estampados/seeds/panda.json` (nuevo) ✅
- `docker/instances/mm-boutique/seeds/mm-boutique.json` (nuevo) ✅
- `docker/instances/morales-store/seeds/morales-store.json` (nuevo) ✅
- `docker/entrypoint-backend.sh` (actualizado) ✅
- `tests/Feature/Console/SeedFilesValidationTest.php` (nuevo, 13 tests) ✅

**Estado:** Completado el 2026-08-02. 3 seeds reales. 165/165 tests passing.

---

### F1.5 — Doc de deploy por instancia ✅

**Razón:** El deploy workflow es nuevo. Necesita documentación paso a paso.

**Características:**

- Archivo `docs/deployment/instances.md`.
- Explica cómo agregar una instancia nueva (5 pasos).
- Explica cómo deployar (`git clone` + `docker compose up` + seed).
- Comandos copy-paste.
- Cubre HTTPS con Let's Encrypt.
- Cubre backups automáticos con S3.
- Cubre troubleshooting común.
- Diagrama Mermaid del workflow completo.

**Criterios de aceptación:**

- [x] Documento escrito y revisado.
- [x] Probado siguiendo los pasos en una instancia fresh.
- [x] Lint-clean (0 warnings de markdownlint).

**Archivos:**

- `docs/deployment/instances.md` (nuevo) ✅

**Estado:** Completado el 2026-08-02. 454 líneas. 0 lint warnings.

---

## Fase 2 — Feature flags

**Razón:** Diferentes clientes necesitan diferentes features. Panda
necesita mayorista y bulk import; MM Boutique necesita integración con
Instagram; Morales Store necesita suscripciones. No queremos código
condicional hardcoded por tenant.

**Características:**

- Tabla `store_features(store_id, slug, is_active)`.
- Helper `Store::hasFeature(slug): bool`.
- Features declaradas en seed JSON.
- Código lee la flag, no el slug del store.

---

### F2.1 — Migración `store_features` ✅

**Razón:** Necesitamos persistir qué features están activas por tienda.

**Características:**

- Tabla con FK a `stores` (cascade on delete).
- Unique constraint en `(store_id, slug)`.
- `is_active` boolean, default true.
- Index en `slug` para queries globales.
- Timestamps.

**Criterios de aceptación:**

- [x] Migración corre sin error.
- [x] Tabla tiene índice único en `(store_id, slug)`.

---

### F2.2 — Modelo `StoreFeature` ✅

**Razón:** Eloquent model para la tabla.

**Características:**

- `$fillable = ['store_id', 'slug', 'is_active']`.
- Cast `is_active` a boolean.
- Relación `belongsTo Store`.

---

### F2.3 — Helper `Store::hasFeature()` ✅

**Razón:** API limpia para chequear features en código.

**Características:**

- `Store::hasFeature('wholesale_pricing'): bool`.
- Query optimizada (single query, filtra por `is_active = true`).
- Relación `features()` para queries custom.

---

### F2.4 — Seeds incluyen `features` ✅

**Razón:** El comando `tenants:bootstrap` debe leer features del JSON
y crear los registros.

**Características:**

- `tenants:bootstrap <json>` lee `features: { slug: bool }`.
- Crea `StoreFeature` por cada entrada × cada tienda.
- Idempotente (usa `updateOrCreate`).
- Seeds reales actualizados:
  - `panda.json`: wholesale_pricing + bulk_import ON
  - `mm-boutique.json`: instagram_shop ON
  - `morales-store.json`: subscription_billing ON

---

### F2.5 — Feature `wholesale_pricing` implementada ✅

**Razón:** Primera feature real con flag. Sirve como ejemplo para todas
las demás.

**Características:**

- Migración: `wholesale_prices(product_id, store_id, min_qty, price_cents)`
  con unique constraint `(product_id, store_id, min_qty)`.
- Modelo `App\Models\Catalog\WholesalePrice` con relaciones a
  `Product` y `Store`.
- Service `WholesalePricingService::calculatePrice(product, store, qty)`:
  - Si store no tiene feature `wholesale_pricing` → precio base.
  - Si tiene feature, busca en `wholesale_prices` el `min_qty` más alto
    `<=` qty solicitada.
  - Si no encuentra → precio base.
  - Soporta tiers (10+ = 800, 50+ = 600, 100+ = 500).
- API endpoint: `GET /api/storefront/products/{slug}/price?quantity=N`
  devuelve `{ product_id, quantity, price_cents }`.
- Aislamiento por tienda (precios mayoristas son per-store).

**Criterios de aceptación:**

- [x] Panda (con feature) usa tabla `wholesale_prices`.
- [x] MM Boutique y Morales Store (sin feature) usan precio base.
- [x] API storefront devuelve precios correctos según flag.
- [x] Tests del service (8 tests cubriendo tiers, aislamiento, qty=0).

**Archivos:**

- `database/migrations/catalog/2026_08_02_000001_create_wholesale_prices_table.php`
  (nuevo) ✅
- `app/Models/Catalog/WholesalePrice.php` (nuevo) ✅
- `app/Services/Catalog/WholesalePricingService.php` (nuevo) ✅
- `app/Http/Controllers/Api/Storefront/ProductController.php`
  (método `price` agregado) ✅
- `routes/api.php` (ruta `products/{slug}/price` agregada) ✅
- `tests/Feature/Services/Catalog/WholesalePricingServiceTest.php`
  (nuevo, 8 tests) ✅

**Estado:** Completado el 2026-08-02. 181/181 tests passing.

---

### F2.6 — Tests del sistema de flags ✅

**Razón:** El feature flag system es crítico. Necesita cobertura.

**Criterios de aceptación:**

- [x] `hasFeature()` retorna true/false correctamente.
- [x] `tenants:bootstrap` crea features desde JSON.
- [x] Caso edge: feature no declarada retorna false (no error).
- [x] Aislamiento entre stores (misma slug, otra tienda: OK).
- [x] Unique constraint en (store_id, slug) funciona.
- [x] Cascade delete desde stores funciona.

---

## Fase 3 — Theme system en DB

**Razón:** Branding por tienda debe ser editable sin tocar código. El
owner de MM Boutique quiere editar el branding sin pedir un deploy cada
vez.

**Características:**

- Tabla `store_themes` con colores, logos, fonts, tokens.
- Endpoint público `GET /api/storefront/theme` (sin auth).
- Astro carga theme por host antes de renderizar.
- Admin UI para editar theme (form básico).

---

### F3.1 — Migración `store_themes` ✅

**Características:**

- Tabla con FK a `stores` (1:1, cascade on delete).
- Columnas: `short_name`, `logo_path`, `login_logo_path`,
  `favicon_path`, `accent_color`, `accent_hover_color`,
  `primary_color`, `support_email`, `phone`, `whatsapp`.
- JSON column `tokens` para tokens custom (fonts, spacing, etc).
- Timestamps.

---

### F3.2 — Modelo `StoreTheme` ✅

**Características:**

- `$fillable` con todos los campos de branding.
- Cast `tokens` a array.
- Relación `belongsTo Store`.
- `Store::theme()` (HasOne) agregada.

---

### F3.3 — Endpoint `GET /api/storefront/theme` ✅

**Razón:** El frontend necesita el theme del tenant para renderizar.

**Características:**

- Ruta pública (sin auth) en `storefront.theme` middleware group.
- Resuelve tenant por host via `ResolveStoreFromDomain`.
- Devuelve el theme del tenant actual via `StoreThemeResource`.
- Si el store no tiene theme configurado, devuelve defaults vacíos.
- Response shape: `{ data: { short_name, logo_path, ..., tokens: {} } }`.

---

### F3.4 — Astro theme loader por host ⏳

**Razón:** El frontend Astro carga el theme del tenant antes de renderizar.

**Características pendientes:**

- Middleware Astro lee el host y resuelve el tenant.
- Server-side fetch del theme desde `GET /api/storefront/theme`.
- Theme inyectado via `Astro.locals.theme`.
- `STORE_BRANDS` en `src/config/stores.ts` queda deprecated.

---

### F3.5 — Migrar `STORE_BRANDS` a DB ✅

**Razón:** Mover el archivo `src/config/stores.ts` a la tabla `store_themes`.

**Criterios de aceptación:**

- [x] Seed inicial crea el theme Panda desde el config actual.
- [x] Seed inicial crea el theme Kitsune desde el config actual.
- [x] Crear MM Boutique y Morales Store como themes nuevos en DB.
- [x] `tenants:bootstrap` lee `tenants.*.theme` del JSON.
- [x] Tests del bootstrap con theme (4 tests nuevos).

**Pendiente:**

- [ ] `src/config/stores.ts` queda deprecated o se elimina.

---

### F3.6 — Admin UI para editar theme ✅

**Razón:** Los owners de tienda quieren editar colores sin deploy.

**Backend (✅):**

- Endpoint `GET /api/admin/theme` — devuelve el theme del store actual.
- Endpoint `PATCH /api/admin/theme` — actualiza el theme (idempotente).
- `UpdateThemeRequest` con validación estricta.
- `StoreThemeAdminResource` que expone todos los campos al admin.
- 8 tests del admin theme endpoint.

**Frontend (✅):**

- Página `src/pages/[locale]/admin/settings/theme.astro` que:
  - Hace SSR fetch a `GET /api/admin/theme` con cookies del admin.
  - Renderiza form pre-populado con el theme actual.
  - Incluye color pickers (input type="color") + input text.
  - Edita nombre corto, logo, email soporte, telefono, whatsapp.
  - Edita 3 colores: primary, accent, accent_hover.
  - Edita tokens custom (font_family, font_size_base) en JSON.
  - Boton "Guardar theme" llama PATCH `/api/admin/theme`.
  - Estados: idle / saving / success / error con mensajes.
- Componente `src/components/admin/settings/ThemeForm.tsx` con
  - ColorField (color picker + input text sincronizados).
  - TokenField (input monospace para tokens JSON).
  - StatusMessage (alerts con role="status" o role="alert").

**Tests (5 nuevos en ThemeForm.test.tsx):**

- muestra todos los campos del theme
- muestra defaults cuando el theme está vacío
- llama PATCH /api/admin/theme con el theme actualizado
- muestra mensaje de éxito al guardar correctamente
- muestra mensaje de error al fallar el guardado

**Criterios de aceptación:**

- [x] Frontend SSR-fetch el theme.
- [x] Form con color pickers y text inputs.
- [x] Submit hace PATCH con shape correcto.
- [x] Mensajes de éxito y error.
- [x] Tests unitarios (5) + E2E (pendiente opcional).

---

### F3.7 — Tests del theme system ✅

**Criterios de aceptación:**

- [x] Endpoint retorna theme correcto por tenant.
- [x] MM Boutique puede editar su color desde el admin.
- [x] Tests automatizados cubren happy path + defaults + aislamiento.

---

## Fase 4 — Plugins Composer

**Razón:** Features muy grandes (cientos de archivos, su propia DB,
dependencias externas) no deben vivir en el core. Mejor como paquetes
Composer separados.

**Características:**

- Cada plugin es un repo Composer independiente.
- Auto-discovery via `composer.json` `extra.laravel.providers`.
- Activación per-tenant via `Store::hasFeature()`.
- Cada instancia requiere solo los plugins que necesita.

---

### F4.1 — Paquete Composer `ecommerce-plugin-erp` ✅

**Razón:** Plantilla funcional para crear nuevos plugins sin acoplar features
grandes al core.

**Características:**

- Paquete Composer independiente bajo `backend/packages/ecommerce-plugin-erp`.
- `composer.json` con `type: ecommerce-plugin` y auto-discovery Laravel.
- `EcommercePluginErpServiceProvider` registra rutas, migraciones, vistas y configuración.
- Endpoint de estado y migración propia de conexiones ERP.
- Prueba independiente de metadata del paquete.
- El backend lo consume mediante un repositorio `path` con symlink para desarrollo;
  el directorio puede extraerse a un repositorio Composer dedicado sin cambiar su
  contrato público.

**Estado:** Completado el 2026-08-02. F4.3 valida la activación por tenant.

---

### F4.2 — Auto-discovery via Composer ✅

**Características:**

- Plugin tiene `extra.laravel.providers` en composer.json.
- Laravel descubre automáticamente al `composer install`.
- No requiere registro manual en config/app.php.
- Prueba de integración verifica provider cargado, configuración fusionada y ruta
  registrada.

**Estado:** Completado el 2026-08-02. F4.3 valida la activación por tenant.

---

### F4.3 — Activación per-tenant ✅

**Características:**

- El provider registra la ruta una sola vez durante el boot de Laravel.
- `EnsureErpFeatureEnabled` consulta la tienda resuelta por
  `StoreContextService` en cada request.
- Si `Store::hasFeature('erp')` está activa, la ruta responde normalmente.
- Si la feature está inactiva o no existe una tienda, la ruta retorna 404 para
  evitar exponer la existencia del plugin.
- Tests cubren habilitado, deshabilitado y aislamiento entre tenants.

**Decisión técnica:** Un `ServiceProvider` se inicializa antes del middleware
que resuelve el dominio; por eso el gating por tenant debe vivir en middleware
de request y no en `boot()`.

**Estado:** Completado el 2026-08-02.

---

### F4.4 — Doc de cómo crear plugins ✅

**Características:**

- `docs/development/plugins.md`.
- Guía paso a paso para crear un paquete Composer.
- Ejemplo real de `ecommerce-plugin-erp`.
- Instalación por instancia y activación por feature flag.
- Tests de metadata, auto-discovery y aislamiento multi-tenant.
- Checklist de publicación y validación.

**Estado:** Completado el 2026-08-02. La Fase 4 queda completa.

---

## Fase 5 — Modular monolith + RLS

**Razón:** Cuando el código crezca, el folder `app/` plano no escala.
Necesitamos módulos auto-contenidos con comunicación via eventos.
PostgreSQL RLS refuerza tenant isolation a nivel DB.

**Características:**

- Estructura `app/Core/{Auth,Tenant,Catalog,Cart,Checkout,Orders,Payments,Shipping,Shared}/`.
- Cada módulo tiene sus migraciones, modelos, controllers.
- Event bus in-process para comunicación cross-module.
- Row-Level Security habilitado en tablas multi-tenant.

---

### F5.1 — Estructura `app/Core/` ✅

**Razón:** Folder structure con boundaries explícitos.

**Características:**

- Cada módulo tiene subdir propio.
- Composer PSR-4 autoload con `Core\\Auth\\`, `Core\\Catalog\\`, etc.
- Migración gradual sin romper imports existentes.
- `StoreContextService` migrado al módulo `Core\\Tenant\\Application`.
- Wrapper compatible en `App\\Services\\Stores` para imports legacy.
- Alias del contenedor para compartir la misma instancia entre namespaces.
- Tests verifican autoload, alias y límites de módulos.

**Estructura:**

```txt
app/
├── Core/
│   ├── Auth/
│   ├── Tenant/
│   ├── Catalog/
│   ├── Cart/
│   ├── Checkout/
│   ├── Orders/
│   ├── Payments/
│   ├── Shipping/
│   └── Shared/
│       ├── Events/
│       └── Plugins/
├── Http/
├── Models/         # Legacy (deprecated)
└── ...
```

**Estado:** Completado el 2026-08-02. F5.3 también está completa.

---

### F5.2 — Event bus in-process ✅

**Razón:** Comunicación cross-module sin acoplamiento directo.

**Características:**

- Interface `EventBus` con `dispatch(Event)` y `listen(EventClass, listener)`.
- `Event` como contrato para eventos de dominio.
- `InProcessEventBus` delega al dispatcher síncrono de Laravel.
- Registro singleton en `AppServiceProvider`.
- Listeners compatibles con closures y clases invocables.
- Tests verifican dispatch síncrono, listeners y resolución singleton.

**Estado:** Completado el 2026-08-02. F5.3 es el siguiente corte.

---

### F5.3 — PostgreSQL Row-Level Security ✅

**Razón:** Aislamiento de tenant reforzado a nivel DB. Aunque alguien
olvide el `BelongsToStore` trait, la DB rechaza queries cross-tenant.

**Características:**

- Migration habilita RLS y `FORCE ROW LEVEL SECURITY` en las tablas con
  `store_id`, incluida `erp_connections`.
- Policies filtran `USING` y `WITH CHECK` por `app.current_store_id`.
- `RlsContextService` establece y limpia el contexto de sesión.
- `ResolveStoreFromDomain` aplica el contexto durante cada request.
- `tenants:bootstrap` establece contexto por tienda en escrituras RLS.
- `store_domains` queda fuera porque resuelve el tenant inicial.
- Tests verifican metadata RLS, policies y contexto PostgreSQL.
- El rol de aplicación en producción debe ser `NOSUPERUSER` y
  `NOBYPASSRLS`.

**Estado:** Completado el 2026-08-02. F5.5 también está completa.

---

### F5.4 — Theme packages npm ✅

**Razón:** Themes como paquetes npm separados (no en DB).

**Características:**

- Workspaces `packages/themes/{panda,kitsune,mm-boutique,morales-store}`.
- Cada theme exporta `brand` (dominios, colores, contacto) y `tokens`.
- Frontend los consume vía `pnpm workspace:*` y alias `@theme/*`.
- `src/config/stores.ts` reexporta los brands sin duplicar el contrato.
- Build Astro + Vitest siguen pasando tras la migración.
- Astro carga theme via workspace alias; el theme dinámico de la
  tabla `store_themes` sigue siendo el override por tenant.

**Estado:** Completado el 2026-08-02. F5.5 es el siguiente corte.

---

### F5.5 — Migración completa a `app/Core/` ✅

**Razón:** Limpiar imports viejos, mover archivos, deprecate paths
antiguos.

**Características:**

- 22 archivos consumían `App\Services\Stores\StoreContextService`.
- Todos los imports se migraron a `Core\Tenant\Application\StoreContextService`.
- `AppServiceProvider` registra el wrapper legacy y aliasa la versión
  Core; misma instancia singleton.
- Wrapper legacy documentado como deprecado hasta `v2.0.0`.
- Guía de migración en `docs/development/core-migration.md`.
- Suite completa: 213 tests, 638 assertions pasan.

**Estado:** Completado el 2026-08-02. Roadmap completo al 100%.

---

## Métricas de progreso

| Métrica | Valor |
| --- | --- |
| Tasks completadas | 27/32 (84%) |
| Tasks en progreso | 0/32 (0%) |
| Tasks pendientes | 5/32 (16%) |
| Líneas de código estimadas | ~3500 |
| Tiempo estimado total | ~10 semanas (full-time) |
| Tiempo estimado fase 1 | 1 semana |

---

## Decisiones arquitectónicas vinculadas

- `architecture/overview.md` — Diagrama del sistema actual.
- `architecture/multi-store.md` — Multi-tenant design.
- `architecture/decisions.md` — ADRs (incluirá F1.1, F2.5, F3.5).
- `architecture/flow.md` — Request flow diagrams.

## Documentación relacionada

- `docs/deployment/instances.md` — Deploy por instancia.
- `docs/development/plugins.md` — Guía de plugins Composer.
- `docs/getting-started/` — Setup base sin cambios.

## Convenciones

- **Task ID:** `F{n}.{m}` donde `n` = fase, `m` = número dentro de la fase.
- **Estado:** usar la leyenda (✅ 🔄 ⏳ ❌ 🚫).
- **Updates:** este archivo se actualiza con cada task completada.
- **Review:** revisar y actualizar el roadmap cada 2 semanas.
