# Feature: Storefront / Account / Admin UI — implementación

## Objetivo

Reducir el gap entre el plan visual documentado (`docs/frontend/design-references.md`,
775 líneas) y la realidad del frontend. El audit de 2026-09-26 encontró 54/65 rutas
críticas del design-refs sin implementar.

## Por qué

El proyecto tiene excelente base arquitectural (i18n ES+EN completo, SearchPage
bien factorizado, admin shell terminado, theme system, multi-tenant infrastructure)
pero la mayoría de las vistas no existen o son stubs vacíos. Sin UI primitives
(Button, Input, etc. están vacíos) no se pueden construir las páginas coherentemente.

## F0 — Audit (COMPLETADO 2026-09-26, sub-agent)

Ver reporte completo en observación #367 y memoria `atlascommerce/docs-refactor-batch-2026-09-26`.
Top findings:

- 12/19 `components/ui/` son archivos vacíos (Button, Input, Alert, Modal, Card,
  Badge, Table, Dropdown, Select, Textarea, Breadcrumb, Pagination)
- 31 archivos admin en `pages/[locale]/admin/**/*.astro` están 0-líneas
- Storefront: 6/8 rutas críticas sin implementar (home, products, products/[slug],
  cart, checkout, track) — solo `/search` está completo
- Account: 18/22 vistas sin implementar; solo AddressesManager existe
- Account Dashboard cliente es el gap #1 (Baymard §2.6 #4 + §6.1, oportunidad del 81%)
- Bugs: paleta auth hardcoded (#2D9D8E) ignora tenant; AdminSidebar links sin `[locale]/`
- 3 carpetas paralelas (`components/storefront/`, `components/public/`,
  `components/atlas-commerce/`) — confusión arquitectural

## Scope — Esta sesión (F1)

**In-scope** (slices aislados, cada uno en su propio commit work-unit):

### F1.1 — UI primitives (12 componentes vacíos)
Todos en `frontend/src/components/ui/`. Implementar siguiendo `frontend/AGENTS.md`
§1 (150/250/400 líneas), §9 (orden Tailwind), §11 (estados), §17 (accesibilidad).

| Componente | Tipo | Notas |
|---|---|---|
| `Button.astro` | Astro | Primary, secondary, danger, ghost. Tamaños sm/md/lg. Estados loading/disabled. |
| `Input.astro` | Astro | text/email/password/number/tel. Label asociado. Estados error con mensaje. |
| `Textarea.astro` | Astro | Igual que Input pero multilinea. |
| `Select.astro` | Astro | Wrapper accesible de `<select>` con label. |
| `Alert.astro` | Astro | Variants: info, success, warning, danger. |
| `Badge.astro` | Astro | Variants: pending, processing, shipped, delivered, cancelled, neutral. |
| `Card.astro` | Astro | Header/body/footer slots. Padding opciones. |
| `Modal.astro` | Astro | Backdrop + dialog. Focus trap. Cerrar con ESC. |
| `Table.astro` | Astro | thead/tbody slots. Header sticky opcional. |
| `Dropdown.astro` | Astro/TSX | Menu trigger + items. Cierra con click outside. |
| `Breadcrumb.astro` | Astro | Lista de links con separator. |
| `Pagination.astro` | Astro | Wrapper accesible del componente `Pagination.tsx` existente. |

### F1.2 — Storefront Header + Footer
- `frontend/src/components/storefront/layout/StorefrontHeader.astro` — Logo + nav
  (home, products, search, account/login, cart con badge count) + LanguageSwitcher.
- `frontend/src/components/storefront/layout/StorefrontFooter.astro` — Branding
  instance-aware + nav secundario + copyright.
- `frontend/src/layouts/StorefrontLayout.astro` — compóne Header + slot + Footer,
  aplica paleta del tenant via `getBrandBySlug`.
- Eliminar carpetas vacías (`components/public/`, `components/atlas-commerce/`)
  según AGENTS §16 — todo consolida en `storefront/`.

### F1.3 — Account dashboard icon-based (Baymard §6.1)
- `frontend/src/pages/[locale]/account/index.astro` — Dashboard con QuickActionCards.
- `frontend/src/components/account/dashboard/QuickActionCard.astro` — el patrón
  exacto de design-refs §6.1, badges opcionales, íconos grandes.
- `frontend/src/components/account/dashboard/RecentOrdersList.astro` — Lista
  mini de últimos 5 orders con status pill (`Badge` semántico §6.4).
- `frontend/src/components/account/layout/AccountSidebar.astro` — Nav post-login
  (Profile, Orders, Addresses, Wishlist, Security, Tickets, Notifications, GDPR).
- `frontend/src/layouts/AccountLayout.astro` — compóne Sidebar + slot.
- i18n: añadir claves en ES+EN para todas las labels.

### F1.4 — Storefront ProductCard + ProductGrid + Cart + Home + Product list
- `components/storefront/products/ProductCard.astro` — el patrón B2C con badges
  ("Novedades", "Oferta", "Últimas unidades") + hover state.
- `components/storefront/products/ProductGrid.astro` — Grid responsive con skeleton.
- `components/storefront/cart/CartItem.astro` — Imagen + title + qty controls + price + remove.
- `components/storefront/cart/CartSummary.astro` — Subtotal + envío + total.
- `pages/[locale]/index.astro` — Home con hero, featured products, categorías.
- `pages/[locale]/products/index.astro` — Lista con ProductGrid + filtros básicos.
- `pages/[locale]/cart.astro` — Reemplazar stub actual con items reales + summary.
- Mock data en `src/data/products.ts` (SOLO para esta fase; reemplazar cuando
  haya endpoint backend).

## Scope — Siguientes sesiones (F2+)

Documentado en `docs/frontend/design-references.md` y memorizado, plan de ataque:

- **F2**: Account restantes (orders, orders/[id] tracking §6.3, wishlist "My
  Collection" §6.2, security, notifications, gdpr, addresses B2B §3.4)
- **F3**: Storefront Panda Estampados (B2B bulk order, RFQ flow §7, design
  approval, NET 30 invoices, address multi-sucursal §3.4)
- **F4**: Storefront restantes (product detail page completa, checkout flow
  completo, search refinements)
- **F5**: Admin (31 páginas 0-líneas + dashboard + reports refactor + DataTable
  + FilterBar + BulkActions + ExportCSV)
- **F6**: Bug fixes transversales (AdminSidebar locale, paleta auth multi-tenant,
  consolidar public/ + atlas-commerce/)

## Constraints

- Conventional Commits en español, scope `frontend`
- NO usar `Co-Authored-By`
- Tamaños: simple ≤150 líneas, mediano ≤250, página ≤400 (AGENTS §1)
- Tailwind orden por intención (AGENTS §9)
- Estados obligatorios: loading/empty/error/success/disabled (AGENTS §11)
- i18n: añadir claves en `frontend/src/i18n/{es,en}/` para cualquier nueva string
- Storybook stories para componentes nuevos (setup ya hecho, commit `4cee016`)
- NO modificar backend
- NO tocar archivos fuera del scope asignado a cada slice

## Acceptance criteria

- 4 commits work-unit (uno por slice)
- Cada componente nuevo tiene estados loading/empty/error/disabled
- Storybook corre sin errores con las nuevas stories
- i18n ES+EN completo para strings nuevas
- Markdownlint pasa en la documentación (si creo .md)
- Pasan tests existentes (vitest/jest si aplica)

## Routing — Esta sesión

3 writers en paralelo vía `general` agent, con scope **estrictamente aislado**:

- **Writer A** (foundation): F1.1 + F1.2 — UI primitives + Storefront layout
- **Writer B** (account): F1.3 — Account dashboard + sidebar + layout
- **Writer C** (storefront flow): F1.4 — ProductCard + Cart + Home + Products list

Cada writer hace su slice completo y un commit independiente.

## Decisiones tomadas

- **NO elimino** las carpetas `components/public/` ni `components/atlas-commerce/`
  en esta sesión (lo marco en F6 porque requiere decisiones adicionales de
  imports + paths).
- **Mock data** permitido para esta fase; reemplazo de endpoint cuando exista.
- **Storybook stories** son nice-to-have, no bloqueante en esta sesión.
- **Sin tests** nuevos esta sesión (los autores podrían agregar tests smoke si
  quieren, pero no es acceptance criterion).
