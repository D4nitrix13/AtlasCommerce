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
| Cliente | Panda Estampados | `pandas-estampados.com` | `admin.pandas-estampados.com` | `panda-kitsune` |
| Cliente | Kitsune Store | `kitsune.com` | `admin.kitsune.com` | `panda-kitsune` |
| Mamá | MM Boutique | `mm-boutique.com` | `admin.mm-boutique.com` | `mm-boutique` |
| Tía | Morales Store | `morales-store.com` | `admin.morales-store.com` | `morales-store` |

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
Fase 1 — Multi-instance           █████░░░░░  60%   (esta semana)
Fase 2 — Feature flags            ░░░░░░░░░░   0%
Fase 3 — Theme system en DB       ░░░░░░░░░░   0%
Fase 4 — Plugins Composer         ░░░░░░░░░░   0%
Fase 5 — Modular monolith + RLS   ░░░░░░░░░░   0%
```

**Total tareas:** 32
**Completadas:** 5 (F1.1, F1.2, F1.3, F1.4, F1.5)
**En progreso:** 0
**Pendientes:** 27

**Fase 1 completa en 60%** — sigue F1.5 ✅ y queda lo que originalmente
se pensó como parte de F1.3 (seed files ya creados como F1.4) y F1.5
(doc de deploy, recién completado).

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
├── panda-kitsune/                # Cliente: Panda + Kitsune
│   ├── .env.example
│   ├── Caddyfile                 # Dominios panda + kitsune
│   ├── docker-compose.yml
│   └── seeds/
│       └── panda.json            # Panda + Kitsune
├── mm-boutique/                  # Mamá
│   ├── .env.example
│   ├── Caddyfile                 # Dominio mm-boutique.com
│   ├── docker-compose.yml
│   └── seeds/
│       └── mm-boutique.json      # MM Boutique
└── morales-store/                # Tía
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

**Estado:** Completado el 2026-08-02. 3 instancias (panda-kitsune, mm-boutique,
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

- `docker/instances/panda-kitsune/seeds/panda.json` (nuevo) ✅
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

### F2.1 — Migración `store_features` ⏳

**Razón:** Necesitamos persistir qué features están activas por tienda.

**Características:**

- Tabla con FK a `stores`.
- Unique constraint en `(store_id, slug)`.
- `is_active` boolean.
- Timestamps.

**Criterios de aceptación:**

- [ ] Migración corre sin error.
- [ ] Tabla tiene índice único en `(store_id, slug)`.

---

### F2.2 — Modelo `StoreFeature` ⏳

**Razón:** Eloquent model para la tabla.

**Características:**

- `$fillable = ['store_id', 'slug', 'is_active']`.
- Cast `is_active` a boolean.

---

### F2.3 — Helper `Store::hasFeature()` ⏳

**Razón:** API limpia para chequear features en código.

**Características:**

- `Store::hasFeature('wholesale_pricing'): bool`.
- Query optimizada (un solo query, usa relación cargada si está).
- Cacheable a futuro con `Cache::remember()`.

---

### F2.4 — Seeds incluyen `features` ⏳

**Razón:** El comando `tenants:bootstrap` debe leer features del JSON
y crear los registros.

**Características:**

- `tenants:bootstrap <json>` lee `features: { slug: bool }`.
- Crea `StoreFeature` por cada entrada.

---

### F2.5 — Feature `wholesale_pricing` implementada ⏳

**Razón:** Primera feature real con flag. Sirve como ejemplo para todas
las demás.

**Características:**

- Migración: `wholesale_prices(product_id, store_id, min_qty, price_cents)`.
- Service `WholesalePricingService::calculatePrice(product, store, qty)`.
- Si `store.hasFeature('wholesale_pricing')` → usa tabla `wholesale_prices`.
- Si no → usa `products.price_cents` normal.
- UI en admin: tabla de precios mayoristas por producto.
- API expone precios mayoristas si la feature está activa.

**Criterios de aceptación:**

- [ ] Panda ve precios mayoristas en `/admin/products/{id}/wholesale`.
- [ ] MM Boutique y Morales Store NO ven esta opción en el admin.
- [ ] API storefront devuelve precios correctos según flag.
- [ ] Tests del service.

---

### F2.6 — Tests del sistema de flags ⏳

**Razón:** El feature flag system es crítico. Necesita cobertura.

**Criterios de aceptación:**

- [ ] `hasFeature()` retorna true/false correctamente.
- [ ] `tenants:bootstrap` crea features desde JSON.
- [ ] Caso edge: feature no declarada retorna false (no error).

---

## Fase 3 — Theme system en DB

**Razón:** Branding por tienda debe ser editable sin tocar código. Tu
mamá quiere su color rosa para MM Boutique, no quiere pedirte un deploy
cada vez.

**Características:**

- Tabla `store_themes` con colores, logos, fonts, tokens.
- Endpoint público `GET /api/storefront/theme` (sin auth).
- Astro carga theme por host antes de renderizar.
- Admin UI para editar theme (form básico).

---

### F3.1 — Migración `store_themes` ⏳

**Características:**

- Tabla con FK a `stores` (1:1).
- JSON column con `tokens` (colores, fonts, spacing).
- `logo_url`, `favicon_url`, etc.

---

### F3.2 — Modelo `StoreTheme` ⏳

**Características:**

- `$fillable = ['store_id', 'tokens', 'logo_path', ...]`.
- Cast `tokens` a array/json.
- Relación inversa: `Store::theme()`.

---

### F3.3 — Endpoint `GET /api/storefront/theme` ⏳

**Razón:** El frontend necesita el theme del tenant para renderizar.

**Características:**

- Ruta pública (sin auth).
- Resuelve tenant por host via `ResolveStoreFromDomain`.
- Devuelve el theme del tenant actual.
- Cacheable (5 min).

---

### F3.4 — Astro theme loader por host ⏳

**Razón:** El frontend Astro carga el theme del tenant antes de renderizar.

**Características:**

- Middleware Astro lee el host y resuelve el tenant.
- Server-side fetch del theme (no exponer el endpoint públicamente
  en prod sin auth).
- Theme inyectado via `Astro.locals.theme`.

---

### F3.5 — Migrar `STORE_BRANDS` a DB ⏳

**Razón:** Mover el archivo `src/config/stores.ts` a la tabla `store_themes`.

**Criterios de aceptación:**

- [ ] Seed inicial crea el theme Panda desde el config actual.
- [ ] Seed inicial crea el theme Kitsune desde el config actual.
- [ ] Crear MM Boutique y Morales Store como themes nuevos en DB.
- [ ] `src/config/stores.ts` queda deprecated o se elimina.

---

### F3.6 — Admin UI para editar theme ⏳

**Razón:** Mamá quiere editar colores sin deploy.

**Características:**

- Página `/admin/settings/theme`.
- Form con color pickers (Tailwind palette).
- Save → `PATCH /api/admin/store-theme`.
- Preview en tiempo real (opcional, fase posterior).

---

### F3.7 — Tests del theme system ⏳

**Criterios de aceptación:**

- [ ] Endpoint retorna theme correcto por tenant.
- [ ] Astro carga el theme correcto por host.
- [ ] MM Boutique puede editar su color desde el admin.

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

### F4.1 — Repo `ecommerce-plugin-{feature}` ⏳

**Razón:** Plantilla para crear nuevos plugins.

**Características:**

- Repo separado `D4nitrix13/ecommerce-plugin-{feature}`.
- `composer.json` con `type: ecommerce-plugin`.
- ServiceProvider que auto-registra rutas, migrations, views.
- Tests independientes.

---

### F4.2 — Auto-discovery via Composer ⏳

**Características:**

- Plugin tiene `extra.laravel.providers` en composer.json.
- Laravel descubre automáticamente al `composer install`.
- No requiere registro manual en config/app.php.

---

### F4.3 — Activación per-tenant ⏳

**Características:**

- Plugin Provider chequea `app('current_store')->hasFeature(...)`.
- Si la feature no está activa, el provider no registra rutas.
- Aislamiento real entre instancias.

---

### F4.4 — Doc de cómo crear plugins ⏳

**Características:**

- `docs/development/plugins.md`.
- Plantilla paso a paso.
- Ejemplo de un plugin real (ej: `ecommerce-plugin-erp`).

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

### F5.1 — Estructura `app/Core/` ⏳

**Razón:** Folder structure con boundaries explícitos.

**Características:**

- Cada módulo tiene subdir propio.
- Composer PSR-4 autoload con `Core\Auth\`, `Core\Catalog\`, etc.
- Migración gradual (no rompe imports existentes).

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

---

### F5.2 — Event bus in-process ⏳

**Razón:** Comunicación cross-module sin acoplamiento directo.

**Características:**

- Interface `EventBus` con `dispatch(Event)` y `listen(EventClass, listener)`.
- Implementación in-process (sync) por default.
- Listeners registrados en ServiceProvider de cada módulo.

---

### F5.3 — PostgreSQL Row-Level Security ⏳

**Razón:** Aislamiento de tenant reforzado a nivel DB. Aunque alguien
olvide el `BelongsToStore` trait, la DB rechaza queries cross-tenant.

**Características:**

- Migration que habilita RLS en tablas multi-tenant.
- Policies que filtran por `store_id`.
- Backend setea `app.current_store_id` en cada request.
- Tests de aislamiento a nivel DB.

---

### F5.4 — Theme packages npm ⏳

**Razón:** Themes como paquetes npm separados (no en DB).

**Características:**

- `@theme/panda`, `@theme/mm-boutique`, `@theme/morales-store` como workspaces.
- Cada theme tiene sus componentes, tokens, assets.
- Astro carga theme via workspace alias.

---

### F5.5 — Migración completa a `app/Core/` ⏳

**Razón:** Limpiar imports viejos, mover archivos, deprecate paths
antiguos.

**Características:**

- Aliases PSR-4 actualizados.
- Documentación de migración para contribuidores.
- Tag de versión `v2.0.0`.

---

## Métricas de progreso

| Métrica | Valor |
| --- | --- |
| Tasks completadas | 0/32 (0%) |
| Tasks en progreso | 1/32 (3%) |
| Tasks pendientes | 31/32 (97%) |
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

- `docs/deployment/instances.md` (a crear en F1.5)
- `docs/development/plugins.md` (a crear en F4.4)
- `docs/getting-started/` — Setup base sin cambios.

## Convenciones

- **Task ID:** `F{n}.{m}` donde `n` = fase, `m` = número dentro de la fase.
- **Estado:** usar la leyenda (✅ 🔄 ⏳ ❌ 🚫).
- **Updates:** este archivo se actualiza con cada task completada.
- **Review:** revisar y actualizar el roadmap cada 2 semanas.
