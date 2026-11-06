# Vistas principales del ADMIN — brief por sesión

> **Modelo** (mismo que `vistas-cliente-sessions.md`): cada sesión trabaja
> **UNA área del admin, una sola responsabilidad**. Solo las **esenciales**
> para operar la tienda; el resto queda fuera de este lote.
> Los detalles de posición/componentes los define el usuario en cada
> sesión individual (sección `Detalles del usuario` al final).
>
> **Estado**: definido. Detalles del usuario: pendientes.

## Mapa de esenciales (5 sesiones)

| Sesión | Área | Rutas bajo `/es/admin/` |
|---|---|---|
| A1 | Dashboard | `index`, `dashboard/**` |
| A2 | Catálogo | `catalog/**` (products, variants, categories, brands, attributes) |
| A3 | Pedidos y clientes | `sales/**` (orders, invoices) + `customers/**` |
| A4 | Inventario | `inventory/**` (stock, movements, warehouses) |
| A5 | Configuración y accesos | `settings/**` (store, theme, currencies, taxes) + `users/**` + `roles/**` |

**Fuera de este lote** (segundo orden, no tocar): `marketing/**`
(cupones/promociones), `support/**` (preguntas/reseñas/tickets),
`payments/**`, `purchases/**`, `reports/**`, `returns/**`,
`shipping/**`, `system/**` (auditorías/notificaciones).

## Protocolo común (todas las sesiones)

1. Antes de editar: `AGENTS.md` raíz → `.ai/rules/index.md` →
   `frontend/AGENTS.md`. Frontend con **`bun`**
   (`~/.bun/bin/bun`, NO en PATH); containers por `scripts/docker-stack.sh`.
2. **¡Worktree propio por sesión!** (`~/Code/AtlasCommerce-worktrees/<area>`,
   nunca `/tmp`). Las sesiones en paralelo sobre el MISMO worktree se
   pisan el HEAD y el container sirve la rama equivocada — ya pasó.
   Flujo de entrega de cada sesión: commit en su rama
   `feat/admin/<area>` → push → PR → merge a master.
3. **Archivos compartidos** (solo aditivo, keys namespaced):
   `frontend/src/i18n/locales/{es,en}.ts` + `types.ts` (claves
   `admin.<area>.*`), `frontend/src/lib/endpoints.ts`, stores de auth.
   `stores/auth.ts` manda A5; el resto solo lo lee.
4. **Auth del admin**: login/2FA con el contrato de
   `.ai/rules/workflow/browser-auth-contract.md` (no renombrar el flujo
   del challenge). SSR autenticado reenvía `Cookie` + `Origin` en los
   `apiFetch`. Datos reales por API admin (`/api/v1/admin/...`); nada
   de mocks (`data/catalog-mock.ts` está vacío a propósito).
5. **Cierre**: `~/.bun/bin/bun run test` · `bun run astro check` ·
   `bun run build` (con `PATH="$HOME/.bun/bin:$PATH"`) · lint por archivo
   (`bunx eslint <archivos>`, 0 errores nuevos vs HEAD — el baseline del
   repo es enorme) · capturas 1440 y 390.
6. Commits convencionales en español; ~400 líneas por PR. **No** mergear
   sin que el usuario lo pida.

---

## Sesión A1 — DASHBOARD

**Responsabilidad única**: la vista de entrada del admin — qué está
pasando en la tienda.

**Rutas / archivos en scope**
- `frontend/src/pages/[locale]/admin/index.astro`,
  `admin/dashboard/index.astro`
- `frontend/src/components/admin/dashboard/**`, `data/dashboard-kpis.ts`

**Estado actual**: dashboard rediseñado en un sprint previo (plan
[`dashboards-redesign-and-auth.md`](dashboards-redesign-and-auth.md));
verificar contra la API real (KPIs de ventas, pedidos recientes, stock
bajo) y quitar cualquier mock residual.

**Tareas**
1. Auditar qué datos vienen de la API vs mocks.
2. Aplicar los **detalles del usuario**.
3. Estados: cargando / vacío / error de API; accesos rápidos a las
   áreas esenciales (catálogo, pedidos, inventario).
4. Responsive + i18n `admin.dashboard.*`.
5. Cierre común.

---

## Sesión A2 — CATÁLOGO

**Responsabilidad única**: productos, variantes, imágenes, categorías,
marcas y atributos.

**Rutas / archivos en scope**
- `admin/catalog/**`: `index`, `products/{index,create,[id]}`,
  `variants/index`, `categories/{index,[id]}`, `brands/{index,[id]}`,
  `attributes/index`
- `frontend/src/components/admin/catalog/**` (galería de imágenes,
  editores de atributos/tallas, `useProductGallery.ts`,
  `ProductSpecsEditor.tsx`)

**Estado actual**: la zona más avanzada — el detalle del producto lee
del API (`d5b0f90a`), editores de atributos select/tallas listos
(`56e84f01`, `68b7adc6`), galería admin con `is_primary`/`is_hover`/
reorden/upload idempotente (E1.5/E1.6). Contratos de imagen documentados
en memoria: `PUT images/order` exige el juego completo de ids (4
resoluciones), borrar foto = DELETE por cada fila.

**Tareas**
1. Auditar el flujo completo producto → variante → imagen → atributos.
2. Aplicar los **detalles del usuario**.
3. Estados vacío/error y validaciones de formularios.
4. Creación de producto (`products/create`) con el mismo contrato.
5. Cierre común.

---

## Sesión A3 — PEDIDOS Y CLIENTES

**Responsabilidad única**: operación comercial — pedidos, facturas y
quiénes compran.

**Rutas / archivos en scope**
- `admin/sales/**`: `index`, `orders/{index,[id]}`, `invoices/{index,[id]}`
- `admin/customers/index.astro`
- componentes admin de ventas/clientes (los que existan)

**Estado actual**: páginas creadas (mockup 12 del plan de vistas —
admin orders); auditar cuánto lee de la API real (endpoint de órdenes
del admin) y qué está mockeado. El detalle del pedido es la página
clave: líneas, estado, totales, transiciones de estado.

**Tareas**
1. Auditar vs API real (`/api/v1/admin/sales/orders` y afines).
2. Aplicar los **detalles del usuario**.
3. Flujo de estados del pedido (qué transiciones permite el backend);
   listado con filtros/paginación.
4. Facturas: listado + detalle.
5. Clientes: listado con datos de contacto y pedidos.
6. Cierre común.

---

## Sesión A4 — INVENTARIO

**Responsabilidad única**: stock, movimientos y depósitos.

**Rutas / archivos en scope**
- `admin/inventory/**`: `index`, `stock/index`, `movements/index`,
  `warehouses/{index,[id]}`

**Estado actual**: páginas creadas; auditar datos reales (el stock vive
en `product_variants.stock`). Movimientos = historial de ajustes de
stock (verificar si el backend expone el historial o si hay que
proponerlo — si falta endpoint, reportarlo como hallazgo, no
implementarlo sin confirmación).

**Tareas**
1. Auditar vs API real; listar gaps de API como hallazgos.
2. Aplicar los **detalles del usuario**.
3. Stock por producto/variante con ajuste manual si el backend lo soporta.
4. Depósitos (warehouses): listado + detalle si aplica al modelo actual.
5. Cierre común.

---

## Sesión A5 — CONFIGURACIÓN Y ACCESOS

**Responsabilidad única**: cómo se comporta la tienda y quién administra.

**Rutas / archivos en scope**
- `admin/settings/**`: `index`, `store/index`, `theme.astro`,
  `currencies/index`, `taxes/index`
- `admin/users/index.astro`, `admin/roles/index.astro`
- `frontend/src/stores/auth.ts` (dueña de esta sesión)

**Estado actual**: theme editor por tenant (`admin/settings/theme.astro`)
existe; el resto auditar contra la API. Usuarios/roles = acceso del
equipo admin (roles_id del user; ver `admin-account-finalization.md`).

**Tareas**
1. Auditar settings/usuarios vs API real.
2. Aplicar los **detalles del usuario**.
3. Store: datos de la tienda, monedas, impuestos editables.
4. Theme: branding por tenant sin romper el theming actual
   (`frontend/packages/themes/*`).
5. Usuarios/roles: alta/baja del equipo, asignación de rol.
6. Cierre común.

---

## Detalles del usuario (por sesión)

> El usuario entrega acá (o por mensaje) los cambios concretos por área.

- **A1 (Dashboard)**: _pendiente_
- **A2 (Catálogo)**: _pendiente_
- **A3 (Pedidos y clientes)**: _pendiente_
- **A4 (Inventario)**: _pendiente_
- **A5 (Configuración y accesos)**: _pendiente_
