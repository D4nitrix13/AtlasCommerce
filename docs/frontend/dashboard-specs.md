# AtlasCommerce — Spec: Account Dashboard rediseño task-oriented (F2)

> **Estado**: spec de diseño, F2.1 de `odd/tasks/dashboards-redesign-and-auth.md`.
> **Nicho inicial**: Panda Estampados (B2B mayorista de estampados).
> **Referencia autoritativa**: Baymard #58 (Account Dashboard, ejemplo
> 16998-apple) + 5 sitios del benchmark.
> **Versión Kitsune (B2C)**: spec aparte (este doc es el template;
> F2.2 será la implementación Panda, Kitsune sale cuando Panda esté
> verde y solo cambia variant + color + features B2C).

## 1. Filosofía de diseño

Baymard #58 (444 ejemplos auditados, ejemplo flagship `16998-apple`) llega
a una conclusión que contradice la intuición del 81% de los sites:

> "Users are better off with a few somewhat long account pages rather
> than a deep multilayer navigation hierarchy."
> — Baymard Institute, Account Dashboard research

El dashboard actual de AtlasCommerce tiene **8 cards icon-based** en un
grid (QuickActionsGrid). El audit clasifica eso como patrón "icon-based
dashboard", que usa el 81% de los sites Y que el 19% restante abandona
porque:

- Returning users son **task-oriented**, no explorers (Baymard §2.6 #4).
  Quieren ejecutar tareas rápido, no ver un mosaico de links.
- Tentar a explorar introduce **decisiones innecesarias** que
  ralentizan el flujo crítico.
- "Icon-only" mata la **affordance** de la acción — el cliente tiene
  que recordar qué hace cada icono.

El rediseño sigue el patrón Baymard #58 (Apple / GitHub / Linear /
Notion / Stripe Dashboard como referencias): **UNA página larga con
secciones jerarquizadas**, no un grid de cards. Cada sección expone
**TAREAS** (no links), con contenido visible y acciones inline.

Para Panda B2B esto encaja mejor que el patrón B2C porque el cliente
B2B recurrente quiere ver rápido: "qué pedidos están activos",
"qué facturas vencen pronto", "qué cotizaciones tengo pendientes".
Esas son **tareas con datos**, no links a sub-páginas.

## 2. Anti-patrones a evitar

Referencia: `docs/frontend/design-references.md` §8.3 (Anti-patrones B2B)
y §2.6 (Insights Baymard).

```txt
❌ NO HACER                              ✅ HACER EN SU LUGAR
─────────────────────────────────────────────────────────────────
Grid de 8 cards icon-only                 Secciones con tareas + datos
"Welcome, John" genérico                  "Bienvenido de vuelta, {name}" + contexto
Recomendaciones algorítmicas              Tareas pendientes del usuario
Llamados a explorar la nav               Quick actions inline (no sub-página)
Sidebar de nav en cada sub-página         Sidebar fijo (ya existe en AccountLayout)
Hero / banner decorativo                 Profile summary compacto (3 líneas máx)
Wishlist como lista plana                Wishlist = pedidos recurrentes (B2B)
NET 30 oculto en sub-página              "Próximos a vencer" visible en hero
Múltiples CTAs por sección                Un CTA primario por sección
```

## 3. Layout (jerarquía vertical, single page)

Panda B2B, paleta esmeralda (`--primary: #059669`, ver
`design-refs.md` §4.1). Mobile-first con breakpoint lg donde la
sidebar izquierda aparece fija (la sidebar ya está provista por
`AccountLayout` — F2.2 NO la toca, solo cambia el contenido del slot).

```text
┌─────────────────────────────────────────────────────────────────┐
│  SECTION 1 — Profile Summary (top, compacto)                    │
│  Avatar (40px) | Nombre real + email | Store + member since     │
│  (1 línea, ≤72px alto)                                          │
├─────────────────────────────────────────────────────────────────┤
│  SECTION 2 — Pedidos activos (HERO, ocupa más espacio)          │
│  3 cards horizontales, cada uno con:                            │
│  - order_number, status pill, ETA, tracking link                │
│  - Acción inline: "Ver detalle" o "Reorder bulk"                 │
│  (CTA primario: "Ver todos los pedidos" → /account/orders)      │
├─────────────────────────────────────────────────────────────────┤
│  SECTION 3 — Cotizaciones pendientes (B2B-specific)              │
│  Lista compacta de RFQs abiertas (count + 1 ejemplo)            │
│  "Tenés 2 cotizaciones esperando tu aprobación"                  │
│  CTA: "Revisar cotizaciones" → /rfq                              │
│  (Si count=0, sección oculta — no inventar data)                │
├─────────────────────────────────────────────────────────────────┤
│  SECTION 4 — Facturas pendientes (B2B-specific)                  │
│  Card única: total a pagar + fecha de vencimiento más próxima   │
│  "Factura #INV-001 — vence en 3 días (NET 30)"                  │
│  CTA: "Ver facturas" → /account/orders?filter=unpaid            │
│  (Si count=0, oculto)                                           │
├─────────────────────────────────────────────────────────────────┤
│  SECTION 5 — Quick actions (inline row, NO card grid)           │
│  4-5 acciones en pills/botones inline:                          │
│  [Nueva cotización] [Reorder último] [Aprobar diseño]           │
│  [Centro de ayuda] [Ver catálogo]                                │
│  Cada acción es un link directo, no requiere abrir sub-página.   │
├─────────────────────────────────────────────────────────────────┤
│  SECTION 6 — Direcciones (collapsed, default)                   │
│  Header: "Direcciones de envío (3)" + link "Gestionar"           │
│  Contenido colapsado: 1 dirección default con badge "Default".   │
│  Click expand muestra las otras 2.                               │
├─────────────────────────────────────────────────────────────────┤
│  SECTION 7 — Seguridad (snapshot inline)                        │
│  2FA status badge + last login info + devices count              │
│  CTA: "Revisar seguridad" → /account/security                    │
│  (Solo 1 línea, no expand — los detalles viven en /security)    │
├─────────────────────────────────────────────────────────────────┤
│  SECTION 8 — Notificaciones (collapsed)                          │
│  Header: "Notificaciones" + badge "3 sin leer" + link "Ver todas"│
│  Contenido: 1 preview de la más reciente (1 línea).             │
├─────────────────────────────────────────────────────────────────┤
│  Footer del dashboard (sutil)                                   │
│  Links secundarios: Soporte, Términos, Privacidad, Cerrar sesión│
└─────────────────────────────────────────────────────────────────┘
```

**Total alto aproximado**: 800-1100px en desktop, ~1400px en mobile
(sin sidebar). Una pantalla y media de scroll, dos pantallas en
mobile. Eso es "somewhat long" según Baymard — no deep navigation.

## 4. Especificación por sección

### 4.1 Profile Summary

- **Posición**: top de la página (primera interacción visual).
- **Componente**: `ProfileSummary.astro` (nuevo en F2.2).
- **Contenido** (3 líneas máx, 72px alto total):
  - Avatar: 40px círculo, iniciales del firstName + lastName[0].
    Border `--primary` 2px para Panda. Sin imagen real (placeholder).
  - Línea 1: `Bienvenido de vuelta, {firstName}` (h1, `text-2xl` bold).
  - Línea 2: `email@empresa.com · Empresa S.A. · Cliente desde Sep 2025`
    (text-sm, `text-slate-500`).
- **Variant**: `panda` usa saludo "Bienvenido de vuelta" (formal
  B2B). `kitsune` usará "おかえりなさい" (decisión de F2.x Kitsune).
- **i18n keys** (nuevas):
  - `account.dashboard.profile.greeting.panda` = "Bienvenido de vuelta, {name}"
  - `account.dashboard.profile.memberSinceLabel` = "Cliente desde"
  - `account.dashboard.profile.companyLabel` (cuando aplique)

### 4.2 Pedidos activos (HERO)

- **Posición**: segunda sección (más prominente que el resto).
- **Componente**: `OrderStatusCard.astro` (nuevo en F2.2), uno por pedido.
- **Layout**: 3 cards en grid horizontal (`grid grid-cols-1 md:grid-cols-3 gap-4`).
- **Card content**:
  - Header: `order_number` (e.g. "ORD-001") + status pill (color semántico).
  - ETA: "Entrega estimada: 15 Oct 2026" (calculado, no inventado).
  - Items count: "3 productos" (no desglose, es un hero).
  - Total: "C$ 12,500.00" (formato según Currency del user).
  - Acción primaria: "Ver detalle" (link a /account/orders/[id]).
  - Acción secundaria condicional (B2B-specific): "Reorder bulk" si
    la order tiene items con wholesale pricing.
- **Empty state**: si NO hay pedidos, mostrar CTA "Hacé tu primera
  cotización" (B2B) en vez del grid. NO mostrar cards vacías.
- **i18n keys**:
  - `account.dashboard.orders.heading` = "Pedidos activos"
  - `account.dashboard.orders.etaLabel` = "Entrega estimada"
  - `account.dashboard.orders.viewDetail` = "Ver detalle"
  - `account.dashboard.orders.reorderBulk` = "Reorder bulk"
  - `account.dashboard.orders.empty` = "Todavía no tenés pedidos activos"
  - `account.dashboard.orders.emptyCta` = "Hacé tu primera cotización"
  - `account.dashboard.orders.viewAll` = "Ver todos los pedidos"
- **Datos**: `Order[]` del endpoint `/api/v1/orders?limit=3&status=active`
  (F2.2 — el backend ya tiene `OrderController::index`).

### 4.3 Cotizaciones pendientes (B2B)

- **Posición**: tercera sección (después de pedidos, antes de facturas).
- **Componente**: `RfqSnapshot.astro` (nuevo en F2.2).
- **Lógica condicional**: si `count = 0`, sección **oculta** completa
  (no placeholder). Si `count > 0`:
  - Header: "Tenés {count} cotizaciones esperando tu aprobación"
  - Preview: la más reciente (1 línea: "RFQ-2026-042 · 5 productos ·
    hace 2 días · vence en 3 días").
  - CTA: "Revisar cotizaciones" → /rfq (página existente, F2.2 sólo linkea).
- **i18n keys**:
  - `account.dashboard.rfqs.heading` = "Cotizaciones pendientes"
  - `account.dashboard.rfqs.summary` = "Tenés {count} cotizaciones"
    pendientes"
  - `account.dashboard.rfqs.viewAll` = "Revisar cotizaciones"
- **Datos**: por ahora se simula con un count hardcoded (F2.2 — el
  endpoint `/api/v1/storefront/rfq` existe, falta decisión de qué
  filtrar para "pendientes de aprobación del cliente").

### 4.4 Facturas pendientes (B2B)

- **Posición**: cuarta sección.
- **Componente**: `InvoiceSnapshot.astro` (nuevo en F2.2).
- **Empty state**: ocultar si `count = 0` (no hay nada que recordar).
- **Cuando hay**:
  - Card con: número de factura, total, fecha de vencimiento próxima.
  - "Factura #INV-2026-089 — C$ 45,000.00 — vence en 3 días (NET 30)".
  - CTA: "Ver facturas" → /account/orders?filter=unpaid (placeholder
    de URL — F2.2 confirma el endpoint real).
- **i18n keys**:
  - `account.dashboard.invoices.heading` = "Facturas pendientes"
  - `account.dashboard.invoices.dueLabel` = "vence en {days} días (NET 30)"
  - `account.dashboard.invoices.viewAll` = "Ver facturas"
- **Datos**: placeholder por ahora. Backend NO expone endpoints de
  invoice para clientes todavía (RF-SALES-054 cubre admin; customer-side
  está fuera de scope hasta F4+). F2.2 deja el componente con shape y
  maneja `invoices = []` sin romper el render.

### 4.5 Quick actions (inline row)

- **Posición**: quinta sección.
- **Componente**: `QuickActionsBar.astro` (nuevo en F2.2 — REEMPLAZA
  `QuickActionsGrid.astro` actual).
- **Layout**: pills/botones en `flex flex-wrap gap-2`. NO grid.
- **Acciones Panda B2B** (5):
  1. **Nueva cotización** → /rfq/new
  2. **Reorder último pedido** → /account/orders?reorder=last
  3. **Aprobar diseño pendiente** → /account/orders?filter=design-pending
  4. **Centro de ayuda** → /account/tickets
  5. **Ver catálogo** → /products
- **Variante Kitsune (B2C)**: cambiar a "Track order", "Return", "Browse",
  "Wishlist", "Reviews". Definido en F2.x Kitsune, fuera de scope.
- **i18n keys**:
  - `account.dashboard.quickActions.heading` = "Acciones rápidas"
  - 5 keys por acción: `.rfq`, `.reorder`, `.approveDesign`, `.help`, `.catalog`
- **Por qué NO un grid de cards**: las acciones son **lineales y
  frecuentes** (un comprador B2B hace 3-5 cotizaciones por semana).
  El grid icon-based obliga a decidir cuál clickear; la pill inline
  es escaneable de un vistazo.

### 4.6 Direcciones (collapsed)

- **Posición**: sexta sección.
- **Componente**: `AddressesSnapshot.astro` (nuevo en F2.2).
- **Layout**: header colapsable. Default abierto si tiene 1 dirección
  (la más común); cerrado si tiene 2+ (no saturar la vista inicial).
- **Contenido cuando expandido**:
  - Lista de las 3 primeras con badge "Default" en la principal.
  - Cada una: tipo (sede/almacén/oficina/evento) + dirección resumida.
  - Link "Ver todas" si hay más de 3.
- **CTA en header**: "Gestionar" → /account/addresses.
- **i18n keys**:
  - `account.dashboard.addresses.heading` = "Direcciones"
  - `account.dashboard.addresses.count` = "{count} registradas"
  - `account.dashboard.addresses.default` = "Predeterminada"
  - `account.dashboard.addresses.viewAll` = "Gestionar"
  - `account.dashboard.addresses.empty` = "Sin direcciones registradas"
- **Datos**: `Address[]` del endpoint existente `/api/v1/account/addresses`.

### 4.7 Seguridad (snapshot inline)

- **Posición**: séptima sección.
- **Componente**: `SecuritySnapshot.astro` (nuevo en F2.2).
- **Layout**: 1 línea de información. NO expandible (detalles en
  /account/security).
- **Contenido**:
  - 2FA badge: "2FA activo (TOTP)" o "Sin 2FA" con color semántico.
  - Last login: "Último acceso: hace 3 horas desde MacBook Pro · Chrome"
  - Devices: "1 dispositivo activo" (link a /account/security para ver todos).
- **i18n keys**:
  - `account.dashboard.security.heading` = "Seguridad"
  - `account.dashboard.security.twofaActive` = "2FA activo (TOTP)"
  - `account.dashboard.security.twofaInactive` = "Sin 2FA — activá ahora"
  - `account.dashboard.security.lastLogin` = "Último acceso: hace {time}"
  - `account.dashboard.security.viewDetail` = "Revisar seguridad"
- **Datos**: `GET /api/v1/auth/2fa/totp/status` (existe) +
  `GET /api/v1/auth/recovery-codes` (existe).

### 4.8 Notificaciones (collapsed)

- **Posición**: octava sección.
- **Componente**: `NotificationsSnapshot.astro` (nuevo en F2.2).
- **Empty state**: ocultar si `count = 0`.
- **Contenido cuando hay**:
  - Header: "Notificaciones" + badge "{count} sin leer".
  - Preview: la más reciente (1 línea truncada).
  - CTA: "Ver todas" → /account/notifications.
- **i18n keys**:
  - `account.dashboard.notifications.heading` = "Notificaciones"
  - `account.dashboard.notifications.unread` = "{count} sin leer"
  - `account.dashboard.notifications.viewAll` = "Ver todas"
- **Datos**: `GET /api/v1/account/notifications?limit=1` (placeholder —
  el endpoint NO existe todavía, F2.2 lo deja como `unread_count: 0`
  y la sección queda oculta hasta que esté).

## 5. Tokens visuales (Panda B2B)

Referencia: `design-refs.md` §4. Paleta esmeralda.

```css
/* Panda (default para esta spec) */
--primary: #059669;        /* emerald-600 */
--primary-hover: #047857;  /* emerald-700 */
--secondary: #10b981;      /* emerald-500 */
--accent: #f59e0b;         /* amber-500 (highlight cuenta) */

/* Status pills (semánticos) */
--status-pending: #fbbf24;     /* amber-400 */
--status-processing: #7c3aed;  /* violet-600 (en proceso) */
--status-shipped: #06b6d4;     /* cyan-500 (en camino) */
--status-delivered: #059669;  /* emerald-600 (entregado) */
--status-cancelled: #ef4444;  /* red-500 */
```

**Tipografía**: Inter (ya en uso). `text-2xl` para el saludo, `text-base`
para headers de sección, `text-sm` para metadata.

**Spacing**: `space-y-8` entre secciones, `space-y-4` dentro de cada
sección, `gap-4` entre cards de pedidos.

**Border radius**: `rounded-2xl` para cards grandes (section containers),
`rounded-md` para pills/badges.

## 6. Responsive

- **Mobile (<md)**: 1 columna, secciones apiladas. Order cards
  verticales. Quick actions en 2 filas (no overflow horizontal).
- **Tablet (md-lg)**: 2 columnas para orders cards. Quick actions
  en 1 fila con wrap.
- **Desktop (lg+)**: 3 columnas para orders cards. Sidebar fija
  izquierda (provista por AccountLayout).

La sidebar fija en desktop **NO se toca** — la provee `AccountLayout`
(commit F0.4). F2.2 sólo cambia el contenido del slot.

## 7. Estados por sección

Cada sección maneja tres estados (Baymard §11 + AGENTS.md §11):

| Sección | Loading | Empty | Error |
|---|---|---|---|
| Profile | hidden (siempre tiene data post-auth) | hidden | fallback "—" |
| Pedidos | 3 skeletons | CTA "Primera cotización" | "Reintentar" button |
| Cotizaciones | skeleton | **oculta** (count=0) | oculta + toast |
| Facturas | skeleton | **oculta** | oculta + toast |
| Quick actions | hidden (estáticas) | n/a | n/a |
| Direcciones | skeleton | header "Sin direcciones" | hidden + toast |
| Seguridad | skeleton inline | hidden | fallback "—" |
| Notificaciones | skeleton | **oculta** | oculta + toast |

**Regla**: empty ≠ error. Si el endpoint devuelve `[]` (count=0), la
sección se oculta o muestra CTA positivo. Si devuelve 5xx, se oculta
y muestra un toast (no rompe el resto del dashboard).

## 8. i18n keys — summary

Todas las keys nuevas del F2 se agregan a `frontend/src/i18n/locales/{es,en}.json`
y se tipan en `frontend/src/i18n/types.ts` (sincronizado según AGENTS.md).

```ts
// Sections nuevas (F2)
'account.dashboard.profile.greeting.panda': string;
'account.dashboard.profile.greeting.kitsune': string; // ya existe
'account.dashboard.profile.memberSinceLabel': string;
'account.dashboard.profile.companyLabel': string;
'account.dashboard.orders.heading': string;
'account.dashboard.orders.etaLabel': string;
'account.dashboard.orders.viewDetail': string;
'account.dashboard.orders.reorderBulk': string;
'account.dashboard.orders.empty': string;
'account.dashboard.orders.emptyCta': string;
'account.dashboard.orders.viewAll': string;
'account.dashboard.rfqs.heading': string;
'account.dashboard.rfqs.summary': string;
'account.dashboard.rfqs.viewAll': string;
'account.dashboard.invoices.heading': string;
'account.dashboard.invoices.dueLabel': string;
'account.dashboard.invoices.viewAll': string;
'account.dashboard.addresses.heading': string;
'account.dashboard.addresses.count': string;
'account.dashboard.addresses.default': string;
'account.dashboard.addresses.viewAll': string;
'account.dashboard.addresses.empty': string;
'account.dashboard.security.heading': string;
'account.dashboard.security.twofaActive': string;
'account.dashboard.security.twofaInactive': string;
'account.dashboard.security.lastLogin': string;
'account.dashboard.security.viewDetail': string;
'account.dashboard.notifications.heading': string;
'account.dashboard.notifications.unread': string;
'account.dashboard.notifications.viewAll': string;
'account.dashboard.quickActions.heading': string; // ya existe
'account.dashboard.quickActions.rfq': string; // B2B
'account.dashboard.quickActions.reorder': string; // B2B
'account.dashboard.quickActions.approveDesign': string; // B2B
'account.dashboard.quickActions.help': string;
'account.dashboard.quickActions.catalog': string;
```

## 9. Compatibilidad con el dashboard actual

El dashboard actual (F1.1) usa `QuickActionsGrid.astro` (8 cards
icon-based). F2.2 lo reemplaza por `QuickActionsBar.astro` (5 acciones
inline). El componente viejo se BORRA en F2.2 (no coexisten). El
`QuickActionsGrid.astro` NO se reusa en otra página según
`grep -r QuickActionsGrid frontend/src` — verificar antes de borrar.

`RecentOrdersList.astro` se MANTIENE (reusado en /account/orders).
F2.2 NO lo toca. La sección 4.2 (OrderStatusCard) es un NUEVO componente
para la vista HERO del dashboard — coexiste con RecentOrdersList
(usado en la lista completa de /account/orders).

`DashboardWelcomeBanner.astro` se REEMPLAZA por `ProfileSummary.astro`
(F2.2). El componente viejo se borra si grep no encuentra otros
consumers (debería ser el único uso en index.astro).

## 10. Acceptance criteria

- Panda B2B ve "Bienvenido de vuelta, {firstName}" en hero, no
  "Daniel Benjamin" hardcoded (cubierto por F1.1).
- 3 order cards visibles cuando hay pedidos activos. ETA real del
  backend (no placeholder).
- Quick actions en pill row, NO grid icon-based (8 cards → 5 pills).
- RFQ section oculta si count=0, visible con count si >0.
- Invoice section oculta si count=0 (incluye durante el MVP si el
  endpoint no existe).
- 2FA status real del endpoint `/api/v1/auth/2fa/totp/status`.
- Mobile (375px): todo cabe sin scroll horizontal, secciones stack.
- TypeScript + astro check limpios.
- Vitest: 5+ tests del helper de fetch del dashboard (F1.1 ya
  estableció el patrón).

## 11. Out of scope (F2.1)

- Implementación de los componentes (F2.2 hace el refactor).
- Endpoints backend que NO existen (invoice customer-side, notifications
  count para cliente) — F2.2 los deja como `count=0` y oculta la
  sección. Crear el endpoint es tarea de F4+ o posterior.
- Variante Kitsune (B2C): este doc es Panda-first. Kitsune sale
  después con sus propias adaptaciones (wishlist prominente, etc).
- Tests E2E de Playwright (F2.3 los hace con flow customer).
- Migración de datos / seed data para demo (separado).

## 12. Referencias

- `docs/frontend/design-references.md` §2.6 (Baymard insights)
- `docs/frontend/design-references.md` §3.4 (Panda B2B nicho)
- `docs/frontend/design-references.md` §4.1 (paleta Panda esmeralda)
- `docs/frontend/design-references.md` §5.1 (URL Baymard account dashboard)
- `docs/frontend/design-references.md` §6.1 (icon-based cards anti-pattern)
- `docs/frontend/design-references.md` §8.3 (B2B anti-patrones)
- Baymard #58: `https://baymard.com/ecommerce-design-examples/58-account-dashboard`
- Apple ref: `baymard.com/ecommerce-design-examples/58-account-dashboard/16998-apple`
- `odd/tasks/dashboards-redesign-and-auth.md` F2.1 + F2.2 + F2.3
