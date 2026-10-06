# F0.x follow-up: Customer Login Flow — Analysis

> **Estado (2026-10-05)**: Customer login NO existe. Customers son un
> modelo separado (Customer) sin flujo de auth propio. Login admin
> via `/api/v1/auth/login` solo acepta User (admin/staff). El frontend
> tiene `StorefrontLoginForm.tsx` y `RegisterForm.tsx` que REFERENCIAN
> `/v1/auth/register` y `/v1/auth/login`, pero el endpoint register
> NO existe en el backend (sólo login).

## El gap actual

| Pieza | Estado |
|---|---|
| `Customer` model con `user_id` FK | ✅ existe |
| `StorefrontLoginForm.tsx` UI | ✅ existe, apunta a `/v1/auth/login` |
| `RegisterForm.tsx` UI | ✅ existe, apunta a `/v1/auth/register` |
| `/api/v1/auth/register` backend | ❌ no existe |
| `/api/v1/auth/login` User-only | ✅ existe (User, no Customer) |
| Customer-side auth store | ❌ no existe (auth.ts es User-only) |
| Customer `Register` seeder | ❌ no existe |
| Account dashboard con Customer real | ❌ depende de lo anterior |

## Decisión de scope

Implementar Customer login end-to-end es un sprint separado (5+ commits
work-unit) que requiere:

### Backend (3 commits)

1. **Customer auth role + endpoint** (`role:customer` + register flow)
   - Nueva role `customer` en `Roles` (idempotent en `PermissionsCatalogSeeder`)
   - Nuevo `POST /api/v1/storefront/auth/register` que crea User (role=customer)
     + Customer profile linked via `customer.user_id`
   - Nuevo `POST /api/v1/storefront/auth/login` que autentica Customer
     via User y devuelve el customer_id en la response
   - Tests: register flow + login flow + role middleware (similar a F0.1)

2. **Customer-seeded User** (dev experience)
   - `CustomerAuthTestUserSeeder` que crea `cliente@panda-estampados.test`
     con role=customer y Customer profile asociado
   - Documentar credenciales en test helpers

3. **Order-by-customer enforcement** (security)
   - Reforzar que `GET /api/v1/orders?customer_id=X` valide ownership
   - Tests que verifiquen que un Customer A no puede ver orders de Customer B

### Frontend (2 commits)

4. **Customer-side auth store** (`stores/customer-auth.ts` o rename)
   - Separar de `stores/auth.ts` (User-only) — los dos stores coexisten
   - `customer-auth.ts`: login, logout, hydrate via `/storefront/auth/me`
   - `getCustomer()` que devuelve Customer (no User)

5. **Storefront auth pages + AccountAuthGuard update**
   - Activar `RegisterForm.tsx` (actualmente apunta a endpoint inexistente)
   - Activar `StorefrontLoginForm.tsx` con redirect post-login
   - Update `AccountAuthGuard.tsx` para usar el customer store
   - Update `getAccountDashboardData` para usar el customer store
   - Aceptación: `cliente@panda-estampados.test` login → ve su nombre real
     + sus orders reales

## Por qué se difiere

1. **Scope**: 5+ commits work-unit, cada uno con su propio review
   risk. El plan F0-F4 del documento padre ya cerró y pusheó (12
   commits, 16 tareas). Customer login merece su propio plan.

2. **Design decisions pendientes**:
   - ¿Customer login es un role de User (role=customer) o un
     sistema auth totalmente separado?
   - ¿Customer tiene que vincular a User OBLIGATORIAMENTE, o puede
     existir como Customer sin auth (compras guest)?
   - ¿Recuperación de password del Customer es via el flow User
     existente o separado?
   - ¿Customer login aplica también a RFQ B2B (Panda) o solo B2C
     (Kitsune)?

3. **Alternativa pragmática**: el admin (`admin@panda-estampados.com`)
   puede crear Customers via `CustomerController` (existe). Si se
   quiere que ese Customer pueda hacer pedidos, se le crea un User
   con role=admin y se le da acceso a la cuenta. Esto NO es un
   customer login (es admin enmascarado), pero funciona mientras no
   haya customer auth real.

## Recomendación

Sprint dedicado con su propio `odd/tasks/customer-auth.md` (similar a
`odd/tasks/dashboards-redesign-and-auth.md`). Estimación rough: 4-6 horas
de trabajo + 2-3 horas de review. No hacerlo de a poco entremedio de
otro trabajo — los 5 commits son interdependientes y un PR parcial
rompe la app (account dashboard no puede mostrar orders sin auth).

## Referencias

- `frontend/src/components/storefront/auth/RegisterForm.tsx` — UI
  existente, apunta a `/v1/auth/register` (no implementado)
- `frontend/src/components/storefront/auth/StorefrontLoginForm.tsx` —
  UI existente, comparte endpoint con admin
- `backend/app/Models/Customers/Customer.php:30` — `$user_id` FK
  preparado pero sin uso
- `odd/tasks/dashboards-redesign-and-auth.md` — contexto del plan F0-F4
  que NO incluía customer auth (era explícitamente out-of-scope)