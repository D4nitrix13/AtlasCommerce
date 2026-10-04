# Feature: Finalización admin + account + checkout + reports

## Objetivo

Cerrar los gaps visibles del proyecto tras F1-F6: 15 páginas admin 0-líneas, 3
páginas account pendientes, TODO pendiente en `CheckoutController`, y refactor de
`admin/reports`. Cada slice es un commit work-unit, cada slice es un PR candidate.

## Por qué ahora

- Auditoría F0 (2026-09-26) y commits recientes (`c696613`, `e38830d`, `5d35a16`)
  cerraron storefront/account/UI primitives/checkout, pero dejaron huecos en
  admin/account/backend que ahora bloquean onboarding real de tenants.
- Las rutas backend existen y los controllers existen — lo que falta es la capa
  de presentación consistente con los design tokens y los patterns ya
  establecidos (F1-F6).

## Estado verificado (pre-slice)

```
Frontend pages stubs (<500 bytes):
  admin/returns/index.astro
  admin/purchases/index.astro
  admin/purchases/suppliers/index.astro
  admin/purchases/orders/index.astro
  admin/settings/index.astro
  admin/users/index.astro
  admin/system/notifications/index.astro
  admin/system/audits/index.astro
  admin/payments/index.astro
  admin/payments/methods/index.astro
  admin/payments/installments/index.astro
  admin/shipping/zones/index.astro
  admin/shipping/companies/index.astro
  admin/support/questions/index.astro
  admin/roles/index.astro

Account pages no existen:
  account/security.astro
  account/notifications.astro
  account/gdpr.astro

Backend TODOs/STUBs:
  app/Http/Controllers/Api/Storefront/Checkout/CheckoutController.php (1 TODO)
  app/Http/Controllers/Api/Admin/Metrics/TrafficSourcesController.php (stub)
```

## Scope — 4 slices con work-unit commits

Cada Slice es 1 PR independiente. Si el PR supera 400 changed lines, se subdivide
internamente antes de pushear. Convención: stacked-to-main (cada PR merge a main).

### Slice 1 — Admin pages críticas (15 stubs)
Orden por criticidad:

1. `admin/users/index.astro` — gestión de admins/usuarios internos (RF-AUTH-009)
2. `admin/roles/index.astro` — matriz roles↔permisos (RF-AUTH-008)
3. `admin/settings/{index,store,theme}.astro` — configuración tenant
4. `admin/settings/{taxes,currencies}.astro` — config fiscal/moneda
5. `admin/payments/{index,methods,installments}.astro` — métodos de pago
6. `admin/shipping/{zones,companies}.astro` — zonas de envío
7. `admin/system/{notifications,audits}.astro` — auditoria + notificaciones
8. `admin/support/questions/index.astro` — moderación Q&A
9. `admin/returns/index.astro` — devoluciones
10. `admin/purchases/{index,suppliers,orders}.astro` — compras B2B

Patrón a aplicar (consistente con F5.5 ya entregado):
- Layout `AdminLayout.astro` (ya existe)
- Componentes reutilizables: `ProductTable`, `FilterBar`, `BulkActionsBar`,
  `MetricCard`, `RecentOrdersTable` (ya existen)
- Estados obligatorios: loading/empty/error/disabled (AGENTS §11)
- i18n ES+EN completo
- Tamaño de archivo: ≤400 líneas (AGENTS §1)

### Slice 2 — Account restantes (security + notifications + gdpr + 2fa)
1. `account/security.astro` — cambio password + sesiones activas
2. `account/notifications.astro` — preferencias email/push
3. `account/gdpr.astro` — export/delete account (conecta con `AccountDataController`)
4. Mejorar `account/2fa.astro` — recovery codes, regenerate flow

### Slice 3 — Backend CheckoutController + tests
1. Cerrar el TODO en `CheckoutController.php`
2. Tests integración: cancelaciones, multi-tenant (3 instancias), RFQ → order → shipment
3. `TrafficSourcesController` (stub → datos reales o eliminaciòn)

### Slice 4 — Admin reports refactor
1. `admin/reports/index.astro` — actualmente 96 líneas, mejorar con:
   - gráficos Chart.js (ya usado en RevenueChart.astro)
   - export CSV
   - filtros por rango de fechas
2. Conectar con `Metrics/*` controllers existentes

## Constraints

- Conventional Commits en español, scopes: `admin`, `account`, `backend`, `reports`
- NO `Co-Authored-By`
- Tamaños AGENTS §1: simple ≤150, mediano ≤250, página ≤400
- Tailwind orden por intención (frontend AGENTS §9)
- Estados obligatorios: loading/empty/error/success/disabled (frontend AGENTS §11)
- i18n: ES+EN completo para cualquier string nueva
- TypeScript strict en props/types
- HTML semántico + accesibilidad (frontend AGENTS §17)
- Storybook stories para componentes nuevos (frontend AGENTS §22)
- PHP: strict_types, named args, constructor promotion (backend AGENTS §2-3)
- Pint format limpio, PHPStan pasa
- Tests con RefreshDatabase donde toque DB

## Acceptance criteria

- 4 work-unit commits, uno por slice
- Cada PR ≤400 changed lines (chained si planchunks si excede)
- i18n ES+EN completo en strings nuevas
- Estados loading/empty/error manejados en cada página
- Storybook verde
- TypeScript strict pasa
- Pint limpio, PHPStan pasa
- Build de Astro OK (sin errores TS/Astro)
- Lint OK

## Routing

Por scope aislado y tamaño:
- **Slice 1** se subdivide en 3 PRs internos (admin-users/roles, admin-settings,
  admin-rest) — cada uno <400 líneas.
- **Slice 2** es 1 PR.
- **Slice 3** es 1 PR (backend + tests).
- **Slice 4** es 1 PR.

Stacked-to-main chain strategy (cada PR merge a master en orden).

## Decisiones tomadas

- **NO** tocar backend funcional fuera del scope (los controllers ya están bien).
- **NO** eliminar el cache stub de `TrafficSourcesController` — se mejora el stub
  con datos estructurados o se documenta su razón de ser.
- **Mock data permitido** en frontend donde el endpoint aún no exista
  (consistente con práctica F1).
- **Sin tests nuevos en Slice 1** (smoke tests de Astro OK); Slice 3 sí requiere
  tests PHPUnit.
- **Storybook** nice-to-have en Slice 1, requerido en componentes nuevos.

## Verification al cierre

- `git log --oneline` muestra 4+ commits work-unit
- `git status` limpio
- Frontend: `pnpm typecheck && pnpm lint && pnpm format:check` verde
- Backend: `vendor/bin/pint --dirty --test && php artisan test --filter=...` verde
- Storybook: `pnpm storybook:build` verde
- Astro build OK

## Relevant Files (a tocar)

- `frontend/src/pages/[locale]/admin/{users,roles,settings,payments,shipping,system,support,returns,purchases}/index.astro` (15 archivos)
- `frontend/src/pages/[locale]/admin/settings/{store,taxes,currencies,theme}.astro` (4 archivos)
- `frontend/src/pages/[locale]/account/{security,notifications,gdpr}.astro` (3 archivos nuevos)
- `frontend/src/pages/[locale]/account/2fa.astro` (mejorar)
- `frontend/src/pages/[locale]/admin/reports/index.astro` (refactor)
- `frontend/src/i18n/{es,en}/*.json` (claves nuevas)
- `backend/app/Http/Controllers/Api/Storefront/Checkout/CheckoutController.php` (cerrar TODO)
- `backend/tests/Feature/Storefront/Checkout/*.php` (tests nuevos)
- `backend/app/Http/Controllers/Api/Admin/Metrics/TrafficSourcesController.php` (stub)

## Next steps (futuras tareas fuera de scope)

- Crear PRs y abrir chain
- Revisión + merge
- Tareas ortogonales: arreglar FTP credentials (logo), cerrar TODOs residuales