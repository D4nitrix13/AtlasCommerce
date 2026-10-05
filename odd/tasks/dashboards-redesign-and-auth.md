# Plan: Dashboards account/admin + auth role-based + security audit

## 🚨 AUDIT DE SEGURIDAD (encontrado el 2026-10-05)

### Bugs críticos de seguridad encontrados

Probando con curl SIN cookies (usuario anónimo):

| Path | Status | ¿Debe ser? | Bug |
|---|---|---|---|
| `GET /api/v1/admin/orders/recent` | 500 | 401 | API admin rota para anónimos |
| `GET /api/v1/account/addresses` | 401 | 401 | ✅ OK |
| `GET /es/admin` | **200** | 302 a login | **🚨 Cualquier user ve el shell del admin** |
| `GET /es/admin/dashboard` | **200** | 302 a login | **🚨 Dashboard admin accesible sin login** |
| `GET /es/admin/customers` | **200** | 302 a login | **🚨 Lista de clientes accesible** |
| `GET /es/account/wishlist` | **200** | 302 a login | **🚨 Wishlist accesible sin login** |
| `GET /es/account/security` | **200** | 302 a login | **🚨 Security (cambio pwd) accesible** |
| `GET /es/account/notifications` | **200** | 302 a login | **🚨 Notif inbox accesible** |
| `GET /es/account/gdpr` | **200** | 302 a login | **🚨 GDPR data export accesible** |
| `GET /es/account/addresses` | **200** | 302 a login | **🚨 Direcciones accesible** |

**Conclusión**: NO hay un `AccountAuthGuard` en el frontend. El comentario en `AccountLayout.astro:13` lo dice explícitamente:
> "Esta versión NO envuelve con AuthGuard (todavía no existe un customer-side AuthGuard unificado). En F2 se integrará cuando `/api/storefront/me` exista."

### Bug de logout

`AdminUserMenu.tsx:38` redirige a `${locale}/auth/login` después de logout. **El usuario espera ir a la raíz del sistema** (`/`, `/es/`, `/panda-estampados.com/`). El comportamiento actual es bug — la convención de UX es logout → home.

## Estado Actual (Audit)

### Account dashboard (`/es/account` para clientes)
- **Page**: `frontend/src/pages/[locale]/account/index.astro`
- **MOCK DATA**:
  - `import { CURRENT_USER, getRecentOrders } from '../../../data/account-mock'`
  - `CURRENT_USER.firstName = 'Daniel Benjamin'` (hardcoded, no es el user real)
  - `RecentOrdersList` muestra ordenes de mock
- 8 cards icon-based estáticos (QuickActionsGrid)
- Sin fetch de `/api/storefront/me` ni `/api/storefront/orders`
- Resultado: usuario `cliente@panda-estampados.com` ve "Daniel Benjamin" como saludo

### Admin dashboard (`/es/admin/dashboard` para admin)
- **Page**: `frontend/src/pages/[locale]/admin/dashboard/index.astro`
- **HARDCODEADO**:
  - `value="$48,295"`, `value="2,847"`, `value="1,432"`, `value="284K"`
- `RevenueChart` con datos hardcoded
- No llama a `/api/v1/admin/metrics/*` ni `/v1/admin/orders/recent`
- Pero el **backend SÍ tiene** estos endpoints listos (revenue, sales, traffic, customers, products, inventory, orders)

### Auth / Role checks (gap crítico de seguridad)
- `AuthGuard.tsx` (cliente) solo chequea `isAuthenticated()`, NO el role
- `AdminHomeRedirect.tsx` redirige según auth, NO role
- **NO hay backend role middleware** que rechace a customers en `/api/v1/admin/*`
- Frontend: clientes pueden acceder a UI admin (mi fix anterior evita el redirect automático, pero no protege la API)
- **Backend expone datos admin a cualquier user autenticado** (bug de seguridad real)

### Doc de referencia ya existe
- `docs/frontend/design-references.md` (775 líneas)
- **Patrón Baymard #58 Account Dashboard** (444 ejemplos)
- **Insights clave**:
  1. "Users are better off with a few somewhat long account pages" (no hacer 30 sub-páginas)
  2. "81% of sites don't use icon-based dashboard" (OPORTUNIDAD si lo hacemos bien)
  3. "Returning users are task-oriented, not explorers" (mostrar TAREAS, no links)
  4. "Most users want to see... tasks, not links" (acciones, no navegación)

## Plan de Implementación (8 fases)

### F0 — CRÍTICO: Auth + seguridad (5 commits, BLOQUEANTE)

El audit reveló múltiples huecos. F0 los cierra todos antes de cualquier otra cosa.

#### F0.1 — Backend: middleware role:admin en rutas admin
- Crear `backend/app/Http/Middleware/EnsureUserHasRole.php` (middleware genérico `role:admin,customer,...`)
- Alias en `bootstrap/app.php`: `$middleware->alias(['role' => EnsureUserHasRole::class])`
- Aplicar `role:admin` a TODO `Route::middleware(['store.domain', 'auth:sanctum'])->group(...)` que contenga rutas bajo `/api/v1/admin/*` en `backend/routes/api.php`
- **Acceptance**: cliente@panda-estampados.com con token Sanctum → `GET /api/v1/admin/orders/recent` → 403 Forbidden

#### F0.2 — Backend: fix 500 en `/api/v1/admin/*` sin auth
- Investigar por qué `/api/v1/admin/orders/recent` sin auth devuelve 500 en vez de 401
- Probable: middleware `store.domain` o `auth:sanctum` está throweando excepción en vez de retornar 401
- **Acceptance**: cualquier GET a `/api/v1/admin/*` sin auth → 401 Unauthorized

#### F0.3 — Frontend: AccountAuthGuard para páginas de cliente
- Crear `frontend/src/components/account/auth/AccountAuthGuard.tsx` (espejo del `admin/auth/AuthGuard.tsx`)
- Aplicar a las 8 páginas account: `wishlist`, `orders`, `orders/[id]`, `security`, `notifications`, `gdpr`, `addresses`
- Lógica: si NO autenticado → redirect a `${locale}/account/login?redirect=${currentPath}`; si autenticado → render children
- **Acceptance**: usuario anónimo navegando a `/es/account/security` → 302 a login (no 200 con shell vacío)

#### F0.4 — Frontend: AccountLayout envuelve con AccountAuthGuard
- Modificar `frontend/src/layouts/AccountLayout.astro` para incluir `<AccountAuthGuard>` wrap
- Las 8 páginas account que usan `AccountLayout` quedan protegidas automáticamente
- **Acceptance**: las páginas account protegidas via layout (single source of truth)

#### F0.5 — Frontend: AdminGuard y AdminLayout role-check
- `frontend/src/components/admin/auth/AuthGuard.tsx`: agregar `if (user.role.slug !== 'admin') redirect to /${locale}/account`
- `frontend/src/components/admin/AdminHomeRedirect.tsx`: misma lógica
- **Acceptance**: cliente que tipea `/es/admin/dashboard` manualmente → redirect a `/es/account`

#### F0.6 — Logout redirige a raíz del sistema
- `frontend/src/components/admin/auth/AdminUserMenu.tsx:38`: cambiar `${localePrefix}/auth/login` → `${localePrefix}/` (raíz)
- `frontend/src/components/storefront/auth/StorefrontLoginForm.tsx` (si tiene logout, similar)
- Buscar y arreglar otros lugares que redirigen a login post-logout
- **Acceptance**: después de logout, el user es redirigido a `/` (home del sistema)

### F1 — Account dashboard: de MOCK a data real
1 commit. Desbloquea el user real en el dashboard.

#### F1.1 — Migrar a fetch real
- `frontend/src/pages/[locale]/account/index.astro`:
  - Eliminar `import { CURRENT_USER, getRecentOrders } from '../../../data/account-mock'`
  - Agregar server-side fetch (Astro soporta cookies automáticamente):
    ```ts
    const me = await fetch('${API_URL}/v1/auth/me', { headers: { Cookie: Astro.request.headers.get('Cookie') } })
    const orders = await fetch('${API_URL}/v1/account/orders?limit=5', { headers: { Cookie } })
    ```
  - Manejar estados: loading (skeleton), error (mensaje + retry), empty (CTA)
- Crear helper `getAccountDashboardData(context)` en `frontend/src/lib/server/account.ts` (encapsula la lógica de fetch con cookies)
- `DashboardWelcomeBanner.astro`: ya recibe `userName`/`variant` como props, no cambios
- **Acceptance**: cliente@panda-estampados.com ve "Hola, Cliente Panda" + sus 5 últimas órdenes reales

### F2 — Account dashboard: rediseño task-oriented (Baymard #58)
3 commits. Refactor visual significativo.

**Nicho**: arrancar con **Panda Estampados (B2B)**. La sección 7 del doc (`docs/frontend/design-references.md`) tiene las adaptaciones B2B. Cuando Panda esté listo, hacer Kitsune (B2C) basado en Panda pero con cambios de color (#7c3aed violeta), variant kitsune, y las features B2C (wishlist más prominente, recommendations, etc).

#### F2.1 — Especificar layout
- Crear `docs/frontend/account-dashboard-spec.md` con layout específico basado en Baymard #58 (Apple ref)
- **Versión inicial**: para Panda Estampados (B2B, paleta esmeralda)
- Jerarquía: "Few somewhat long account pages" → 1 página con secciones, no 8 cards
- Secciones (en orden de task-orientation):
  1. **Profile summary** (top): nombre real, store, member since, avatar
  2. **Active orders** (hero): los 3 últimos con status, ETA, tracking link
  3. **Quick actions row** (inline): 4-5 acciones compactas (no card grid)
  4. **Saved items** (collapsed): wishlist count, link "Ver todo"
  5. **Addresses quick view** (collapsed): 1 dirección default, link "Gestionar"
  6. **Notifications inbox** (collapsed): count no leídas, link "Ver todas"
  7. **Security snapshot** (inline): 2FA status, last login

#### F2.2 — Implementar F2.1
- Refactor `QuickActionsGrid.astro` → `QuickActionsBar.astro` (inline, no grid)
- Crear `OrderStatusCard.astro` (componente con status, ETA, tracking)
- Refactor `RecentOrdersList.astro` → 3 cards verticales con estado prominente
- `DashboardWelcomeBanner.astro`: ya soporta variants (kitsune/panda/neutral) — no cambios
- Aplicar i18n keys nuevas: `account.dashboard.activeOrders`, `account.dashboard.profile`, etc.

#### F2.3 — E2E test
- Playwright test del flujo customer:
  - Login como cliente@panda-estampados.com
  - Verificar que el dashboard muestra "Cliente Panda" (no "Daniel Benjamin")
  - Verificar que las 3 secciones principales son visibles
  - Verificar que NO hay 404 en los endpoints de fetch

### F3 — Admin dashboard: de hardcoded a datos reales
2-3 commits. Similar a F1 pero con más endpoints.

#### F3.1 — Crear helpers de fetch
- `frontend/src/lib/server/admin-dashboard.ts` con funciones:
  - `getMetricsOverview()` → fetch `/v1/admin/metrics/revenue/yearly` + `/v1/admin/orders/recent` en paralelo
  - `getRecentOrders(limit)` → fetch `/v1/admin/orders/recent?limit=N`
  - `getLowStock()` → fetch `/v1/admin/inventory/low_stock`
  - `getTrafficSources()` → fetch `/v1/admin/metrics/traffic/sources`
- Todas con `credentials: 'include'` o server-side fetch con cookies

#### F3.2 — Refactor admin dashboard page
- `frontend/src/pages/[locale]/admin/dashboard/index.astro`:
  - Eliminar valores hardcoded
  - Usar los helpers de F3.1
  - Mantener `RevenueChart.astro` pero pasarle data real (formato esperado)
  - Mantener `MetricCard.astro` (ya es genérico)

#### F3.3 — Filtros de rango de fechas
- Selector de rango (today, 7d, 30d, 90d, custom)
- Filtros aplicados a metrics y orders
- URL state: `?range=7d` (shareable, backable)
- (Nice-to-have, no bloqueante)

### F4 — Documentación y consolidación
2 commits.

#### F4.1 — Actualizar odd task
- Editar `odd/tasks/admin-account-finalization.md` (existe) con el F0-F3 ya hecho
- Crear `odd/tasks/dashboard-redesign.md` con el spec de F2.1 + F3.1

#### F4.2 — Eliminar archivos mock obsoletos
- `frontend/src/data/account-mock.ts`: ¿se sigue usando en algún lado? Si no, eliminar
- `frontend/src/data/admin-dashboard.ts`: idem
- Documentar en el PR qué cambió y por qué

## Acceptance criteria global

| Criterio | Verificación |
|---|---|
| Cliente NO puede leer datos admin via API | `curl /api/v1/admin/orders/recent` con token de cliente → 403 |
| Cliente NO puede acceder a /es/admin/* | Login como cliente, navegar a /es/admin/dashboard → redirect a /es/account |
| Cliente VE su info real en dashboard | `cliente@panda-estampados.com` login → h1 dice "Cliente Panda" |
| Admin VE datos reales en dashboard | `admin@panda-estampados.com` login → métricas reales (no hardcoded) |
| Dashboard sigue patrón Baymard #58 | Task-oriented, no icon-grid-genérico |
| No hay N+1 queries | Chart data y KPI cards se hidratan en paralelo |

## Riesgos identificados

1. **F2.2 → F2.3**: si el `OrderStatusCard` necesita endpoints que no existen (tipo `orders/{id}/tracking`), hay que crearlos en backend. Si no existen, usar mock solo para esos campos con TODO.
2. **F0.1 → compatibilidad**: clientes que ya estaban logueados y con tokens Sanctum previos podrían recibir 403 inesperados. Mitigation: cachear el role en el JWT de Sanctum.
3. **F1/F3 → hydration timing**: la primera carga del dashboard puede tener 100-300ms extra. Mitigation: skeletons durante loading, y server-side rendering para que el HTML llegue con la data ya en el body.
4. **F2.1 → cambios visuales breaking**: la sidebar + AccountLayout ya existen y tienen su estilo. El cambio NO debe romperlos. Mitigation: mantener los componentes actuales como wrappers, solo cambiar el contenido.

## Decisiones del usuario (consolidadas)

| # | Pregunta | Decisión |
|---|---|---|
| 1 | ¿F0.1 antes o después de F0.2? | **F0.1 primero** (backend, defensa real) |
| 2 | ¿Account dashboard para B2C o B2B primero? | **Panda Estampados (B2B) primero**. Kitsune después, basado en Panda, con cambios de color y funcionalidades |
| 3 | ¿F2.1 y F3.1 en mismo sprint? | Mi recomendación: **mismo sprint**, scopes similares |
| 4 | ¿Aplicar F0 antes de F1-F3? | **SÍ**, F0 es bloqueante (seguridad) |

## Archivos a tocar (resumen)

| Path | Acción | Fases |
|---|---|---|
| `backend/app/Http/Middleware/EnsureUserHasRole.php` | crear | F0.1 |
| `backend/bootstrap/app.php` | agregar alias | F0.1 |
| `backend/routes/api.php` | aplicar role:admin a /admin/* | F0.1, F0.2 |
| `frontend/src/components/account/auth/AccountAuthGuard.tsx` | crear | F0.3 |
| `frontend/src/layouts/AccountLayout.astro` | envolver con AccountAuthGuard | F0.4 |
| `frontend/src/components/admin/auth/AuthGuard.tsx` | agregar role check | F0.5 |
| `frontend/src/components/admin/AdminHomeRedirect.tsx` | agregar role check | F0.5 |
| `frontend/src/components/admin/auth/AdminUserMenu.tsx` | cambiar logout redirect a / | F0.6 |
| `frontend/src/lib/server/account.ts` | crear helper server-side | F1.1 |
| `frontend/src/pages/[locale]/account/index.astro` | migrar de MOCK a fetch | F1.1 |
| `docs/frontend/account-dashboard-spec.md` | crear spec para Panda (B2B) | F2.1 |
| `frontend/src/components/account/dashboard/QuickActionsBar.astro` | crear | F2.2 |
| `frontend/src/components/account/dashboard/OrderStatusCard.astro` | crear | F2.2 |
| `frontend/src/components/account/dashboard/RecentOrdersList.astro` | refactor | F2.2 |
| `frontend/src/lib/server/admin-dashboard.ts` | crear helper server-side | F3.1 |
| `frontend/src/pages/[locale]/admin/dashboard/index.astro` | migrar de hardcoded a fetch | F3.2 |
| `odd/tasks/admin-account-finalization.md` | actualizar | F4.1 |
| `odd/tasks/dashboard-redesign.md` | crear | F4.1 |
| `frontend/src/data/account-mock.ts` | eliminar | F4.2 |
| `frontend/src/data/admin-dashboard.ts` | eliminar | F4.2 |

| Path | Acción | Fases |
|---|---|---|
| `backend/app/Http/Middleware/EnsureUserHasRole.php` | crear | F0.1 |
| `backend/bootstrap/app.php` | agregar alias | F0.1 |
| `backend/routes/api.php` | aplicar role:admin a /admin/* | F0.1 |
| `frontend/src/components/admin/auth/AuthGuard.tsx` | agregar role check | F0.2 |
| `frontend/src/components/admin/AdminHomeRedirect.tsx` | agregar role check | F0.2 |
| `frontend/src/lib/server/account.ts` | crear helper server-side | F1.1 |
| `frontend/src/pages/[locale]/account/index.astro` | migrar de MOCK a fetch | F1.1 |
| `docs/frontend/account-dashboard-spec.md` | crear spec | F2.1 |
| `frontend/src/components/account/dashboard/QuickActionsBar.astro` | crear | F2.2 |
| `frontend/src/components/account/dashboard/OrderStatusCard.astro` | crear | F2.2 |
| `frontend/src/components/account/dashboard/RecentOrdersList.astro` | refactor | F2.2 |
| `frontend/src/lib/server/admin-dashboard.ts` | crear helper server-side | F3.1 |
| `frontend/src/pages/[locale]/admin/dashboard/index.astro` | migrar de hardcoded a fetch | F3.2 |
| `odd/tasks/admin-account-finalization.md` | actualizar | F4.1 |
| `odd/tasks/dashboard-redesign.md` | crear | F4.1 |
| `frontend/src/data/account-mock.ts` | eliminar | F4.2 |
| `frontend/src/data/admin-dashboard.ts` | eliminar | F4.2 |

## Orden de ejecución recomendado

```
F0.1 → F0.2 → F0.3 → F0.4 → F0.5 → F0.6 → F1.1 → F2.1 → F2.2 → F2.3 → F3.1 → F3.2 → F3.3 (opcional) → F4.1 → F4.2
```

**F0 (6 commits) es BLOQUEANTE** — la app tiene holes de seguridad graves. Después de F0, F1-F4 pueden hacerse en cualquier orden.

## Estado de ejecución (2026-10-05)

| Fase | Estado | Commit |
|---|---|---|
| F0.1 | ✅ done | `d606782` EnsureUserHasRole middleware + role:admin,staff |
| F0.2 | ✅ done | `9a76413` redirectGuestsTo(null) — fix 500 → 401 |
| F0.3 | ✅ done | `dfd0164` AccountAuthGuard component |
| F0.4 | ✅ done | `05ec63b` AccountLayout wrappea guard + addresses migrate |
| F0.5 | ✅ done | `808bb2e` Role-check en AuthGuard + AdminHomeRedirect |
| F0.6 | ✅ done | `a7e8d34` Logout redirige a / (raíz) |
| F1.1 | ✅ done | `ffa93d1` Dashboard account usa data real (helper) |
| F2.1 | ✅ done | `b82caad` Spec docs/frontend/account-dashboard-spec.md |
| F2.2 | ✅ done | `a99f81b` Refactor task-oriented del dashboard account |
| F2.3 | ✅ done | `64ce5a6` E2E test del dashboard account |
| F3.1 | ✅ done | `1ef7bf9` Helper + page refactor del admin dashboard |
| F3.2 | ✅ done | `1ef7bf9` (mismo commit que F3.1) |
| F3.3 | ⏸️ skipped (opcional, date range filters) |
| F4.1 | ✅ done | Este commit (actualización del plan) |
| F4.2 | ✅ done | Este commit — cleanup de mocks obsoletos |

Total commits: 10. Estimación original era 17-19 — menos commits
gracias a consolidation (F2.2 fue 1 solo, F3.1+F3.2 fueron 1 solo,
F4.1+F4.2 fueron 1 solo).

Tests añadidos: 41 nuevos (13 backend + 28 frontend), todos verde.
9 tests pre-existentes siguen rotos (AuthGuard.test.tsx + 4 más desde
commit 49687c6 que cambió /auth/login → /account/login sin sincronizar
los tests) — cleanup aparte, no introducidos por este plan.

F0.1 nota: el plan literal decía `role:admin` estricto. La
implementación aceptó admin,staff porque CustomerControllerTest usa
staff con permission:customers.view y bloquearlos rompía 9 tests
existentes. Customers (modelo distinto de User) usan auth propia y
ya están bloqueados por auth:sanctum.

F3.3 skipped: nice-to-have. El chart actual muestra solo el current
year del endpoint /revenue/yearly. Para F3.3 habría que agregar
`?range=7d|30d|90d` query param y un selector UI.

F4.2 parcial: `account-mock.ts` retiene mocks de sessions, recovery
codes, notif prefs, deletion — los pages correspondientes
(/account/security, /account/notifications, /account/gdpr) no
tienen endpoints reales todavía. Solo se removieron CURRENT_USER
y RECENT_ORDERS_MOCK (orphan tras F1.1). Se eliminó
`data/admin-dashboard.ts` (orphan tras F3).

## Estimación rough

| Fase | Commits | Tiempo |
|---|---|---|
| F0.1 | 1 | 30min |
| F0.2 | 1 | 30min (investigar root cause) |
| F0.3 | 1 | 20min (espejo de AuthGuard existente) |
| F0.4 | 1 | 15min (envolver AccountLayout) |
| F0.5 | 1 | 15min (mismo que F0.3) |
| F0.6 | 1 | 15min (cambiar redirect en AdminUserMenu) |
| F1.1 | 1 | 1h |
| F2.1 | 1 doc | 30min |
| F2.2 | 2-3 | 2-3h |
| F2.3 | 1 | 30min |
| F3.1 | 1 | 1h |
| F3.2 | 1 | 1h |
| F3.3 | 1 | 1h (opcional) |
| F4.1 | 1-2 doc | 30min |
| F4.2 | 1 | 15min |
| **Total** | **17-19 commits** | **~10-12h** |

## Mi recomendación

Empezar **hoy** con F0.1 + F0.2 (seguridad). Después F1.1 (user real). Después seguir con F2.x y F3.x en paralelo según bandwidth.

Preguntame si querés que arranque con F0.1 o si querés ajustar algo del plan primero.
