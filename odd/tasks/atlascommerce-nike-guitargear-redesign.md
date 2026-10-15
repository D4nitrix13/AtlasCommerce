# Plan: Vistas Nike + guitargear.com.mx — Sprint 2.1 cliente

> **Status**: en ejecución. Branch `feat/views-nike-guitargear-2.1` desde master (25d59bff).
>
> **Source del plan**: el usuario pidió "diseñar las vistas usando como referencia Nike y la página guitargear.com.mx" + "elaborar primero todas las vistas del cliente y de último las de los administradores".
>
> **Scope de este sprint (2.1)**: 6 vistas cliente. Las 4 vistas admin (T4) van en sprint posterior. Las 2 vistas de autenticación (Login, Signup) NO se tocan (ya migraron a Bonfire-style en sprints previos).

---

## 0. Por qué Nike + guitargear.com.mx (no Bonfire)

El sprint anterior aplicó lenguaje visual **Bonfire** (verde/rust colores branded, decorativos squiggle/blob, narrative editorial) sobre todas las vistas. Funciona bien para **Panda Estampados B2B** (estampados textiles) porque el patrón visual Bonfire es storytelling-largo con FAQ, customer stories y proceso Cotizar→Entrega.

Pero para **La Voz Instrumentos** (B2C nicho instrumentos musicales, primer cliente real SaaS), Bonfire no es el patrón ideal:
- **No tenemos customer stories verificables** (solo se acaba de lanzar La Voz)
- **El proceso Bonfire es B2B** (cotizar → producir → entregar); para B2C es ATC → checkout → envío
- **Necesitamos conversión rápida** (mobile-first, ATC prominent) inspirado en Nike
- **Necesitamos especificidad técnica** (pickups, maderas, marcas) inspirado en guitargear.com.mx

**Lo que el usuario decidió**: mezclar dos referencias para B2C nicho.
- **Nike** para hero storytelling, ATC prominent, mobile-first, sistema de tallas/colores en cards.
- **guitargear.com.mx** para catálogo por MARCA, descripción técnica prominente, filtros laterales bien organizados.

---

## 1. Tabla de equivalencias URL (lo que el usuario pidió)

Mapeo entre rutas de **AtlasCommerce**, **nike.com** (referencia conversión) y **guitargear.com.mx** (referencia catálogo instrumentos musicales). Cada fila es una vista del sprint y su equivalente en cada referencia.

| # | AtlasCommerce (nuestra URL) | nike.com (referencia conversión) | guitargear.com.mx (referencia catálogo) | Notas |
|---|---|---|---|---|
| 1 | `/es/` (home) | `nike.com/` | `guitargear.com.mx/` | Hero storytelling + categorías visuales + featured. La Voz tendrá hero nicho "instrumentos musicales" con CTA prominente. |
| 2 | `/es/products` (catálogo) | `nike.com/launches`<br>`nike.com/women` | `guitargear.com.mx/guitarras`<br>`guitargear.com.mx/amplificadores` | Filtros laterales (categoría, marca, precio). Cards con badge + ATC visible. Vista grid principal. |
| 3 | `/es/products/[slug]` (detalle) | `nike.com/air-max-90-mens-shoes` | `guitargear.com.mx/fender-stratocaster-player.html` | Gallery izq + info der. Specs técnicas prominentes (guitargear). Selector tamaño/color (Nike). Trust signals. |
| 4 | `/es/cart` (carrito) | `nike.com/cart` | `guitargear.com.mx/checkout/cart` (Magento) | Items con thumb + summary sticky der. Edit qty inline. CTA checkout prominente (Nike). Cupón / código referido (guitargear). |
| 5 | `/es/checkout` (checkout multi-step) | `nike.com/checkout` | `guitargear.com.mx/checkout` | Stepper horizontal (Address → Shipping → Payment → Review). Summary sticky der. Trust signals cerca del CTA (Nike). Múltiples métodos pago (este sprint prepara markup; pasarela real es T3). |
| 6 | `/es/account/orders` (mis órdenes) | `nike.com/member/orders` | `guitargear.com.mx/mi-cuenta/pedidos/` | Cards colapsables con thumbnail + número + status + total. Click expande a detalle. Re-order button (Nike). |
| 7 | `/es/account` (dashboard) | `nike.com/member` | `guitargear.com.mx/mi-cuenta` | (No es sprint 2.1 — ya existe Bonfire v3 funcional, se queda como está.) |
| 8 | `/es/account/orders/[id]` (detalle orden) | `nike.com/member/order/[id]` | `guitargear.com.mx/mi-cuenta/pedidos/[id]` | Detalle expandido de una orden. Status pill + line items + tracking. (No es sprint 2.1 — ya existe.) |
| 9 | `/es/account/addresses` (direcciones) | nike.com/member/profile (sub) | guitargear.com.mx/mi-cuenta/direcciones | (No es sprint 2.1 — ya existe.) |
| 10 | `/es/account/wishlist` (favoritos) | nike.com/member/wishlist | guitargear.com.mx/mi-cuenta/favoritos | (No es sprint 2.1 — ya existe.) |

### URLs que Nike usa y AtlasCommerce aún no tiene

| Idea | URL Nike | Aplicación a AtlasCommerce |
|---|---|---|
| Lanzamientos programados | `nike.com/launches/[slug]` | Para La Voz: "lanzamientos" de nuevo inventario (ediciones limitadas, modelos recién llegados). Se puede agregar como sección del home o como filtro en catálogo. |
| Búsqueda con filtros rápidos | `nike.com/search` | Ya existe `/es/search` — verificar UX mobile. |
| Miembro / perfil | `nike.com/member` | Ya existe `/es/account` — solo mejorar UX. |
| Wishlist | `nike.com/member/wishlist` | Ya existe `/es/account/wishlist` — verificar markup Nike. |
| Sub-tenants / sub-categorías (Nike Snkrs, Nike Kids) | `nike.com/snkrs`, `nike.com/kids` | Para La Voz: sub-categorías por tipo de instrumento (guitarras, bajos, amps, audio) con su propia landing. Se puede implementar como filtro con highlight en catálogo, no como URL dedicada (sprint futuro). |

### URLs que guitargear usa y AtlasCommerce aún no tiene

| Idea | URL guitargear | Aplicación a AtlasCommerce |
|---|---|---|
| Filtros por MARCA | `guitargear.com.mx/[marca]` | Para La Voz: lista de marcas (Fender, Gibson, Yamaha, etc.) con su landing. Se agrega como filtro en `/es/products`. |
| Comparar productos | `guitargear.com.mx/compare/[sku1,sku2]` | Útil para instrumentos que se comparan (mismo modelo distinto precio). Sprint futuro. |
| Blog/tutoriales | `guitargear.com.mx/blog` | Para La Voz: contenido educativo (cómo elegir primera guitarra, mantenimiento). Podría ser sección marketing. Sprint futuro. |
| Servicios (reparación, mantenimiento) | `guitargear.com.mx/servicios` | Para La Voz que es tienda física: servicios de calibración, reparación. Sprint futuro. |
| Reseñas de producto | `guitargear.com.mx/[producto].html#reviews` | Reviews aún no hay sistema; cuando exista, agregar al detalle producto. Sprint futuro (post-Track 3 pagos). |

---

## 2. Decisiones de UX que adoptamos de cada referencia

### De Nike (conversión)

| Patrón Nike | Aplicación AtlasCommerce | Vista |
|---|---|---|
| **Mega menu hover** (top nav) | Lo tiene ya el `StorefrontHeader.astro` (sprint Bonfire). Mantener. | Top nav en todas |
| **ATC prominent** arriba del fold | Botón grande primary color (var(--primary)), sticky en mobile | Detalle producto |
| **Color swatches visibles en cards** | `ProductCard` ya muestra color swatches | Cards catálogo |
| **Size selector tipo pills** | `ProductDetailVariants` ya lo implementa | Detalle producto |
| **Cards hover lift** | `-translate-y-0.5 + shadow-md` ya implementado | Catálogo |
| **Re-order button** en órdenes | Re-order desde card de orden | Mis órdenes |

### De guitargear (catálogo B2C nicho)

| Patrón guitargear | Aplicación AtlasCommerce | Vista |
|---|---|---|
| **Filtros laterales bien organizados** (categoría, marca, precio, atributos) | Ya existe `FiltersSidebar` con 4 grupos (Categoría, Precio, Color, Talla). Expandir a incluir Marca. | Catálogo |
| **Descripción técnica prominente** en el detalle (specs table) | Agregar `<table>` de especificaciones en `ProductDetailTabs` | Detalle producto |
| **Trust signals en cart/checkout** (envío gratis, garantía, devolución) | Trust signals visibles cerca del CTA | Cart, Checkout |
| **"Más vistos"** o **"top productos"** destacados arriba del catálogo | Sección de destacados en `/es/products` top | Catálogo |
| **Breadcrumb bien visible** | Mejorar jerarquía del breadcrumb | Detalle producto |

---

## 3. Diferenciación B2C nicho vs B2B Bonfire (sprint anterior)

La Voz es **B2C nicho** (instrumentos musicales), no B2B (estampados textiles). El sprint Bonfire anterior diseñó para B2B:

| Aspecto | Bonfire v3 (B2B) | Nike + guitargear (B2C nicho) |
|---|---|---|
| **Lenguaje visual** | Storytelling editorial (squiggles, blobs, decorativos, narrative) | Producto-first (cards densas, info clara, jerarquía tipo catálogo) |
| **CTA principal** | "Cotizar" / "Get a quote" (B2B: lead gen) | "Agregar al carrito" (B2C: conversión directa) |
| **Sección featured** | Customer stories + FAQ (B2B: confianza institucional) | Top productos + reviews (B2C nicho: social proof) |
| **Largo de scroll** | Largo con 9 secciones (hero + stats + categories + process + clients + materials + dark + stories + faq + cta) | Medio con 6 secciones (hero nicho + 2-3 categorías top + 4-8 productos featured + 2 carousels marca + cta) |
| **Tiempo entre carga y CTA** | Muchos segundos (scroll para llegar al CTA) | <2 segundos (CTA arriba del fold) |
| **Densidad de info** | Ligera (mucho whitespace) | Densa (catálogo informativo) |

**Implicación para el sprint**: NO removemos Bonfire de la home B2B Panda (sigue funcionando — estampados textiles storytelling encaja). SÍ adaptamos La Voz y los B2C genéricos (Kitsune, MM, Morales) al patrón Nike+guitargear.

---

## 4. Estructura de commits (preview)

| # | Branch + commit | Vista(s) | LOC estimado | Verificación |
|---|---|---|---|---|
| 1 | `feat/views-nike-guitargear-2.1` (este branch) | Preparación: crear componentes Nike-guitargear compartidos si faltan (e.g. `ProductSpecsTable.astro`) | ~150 | `bun run astro check` exit 0 |
| 2 | mismo | Home La Voz (home B2C nicho): hero nicho + categorías top + featured + cta final | ~200 | Astro build + render visual |
| 3 | mismo | Catálogo: filtros con marca agregada + vista grid densa + top productos | ~250 | Astro build |
| 4 | mismo | Detalle producto: gallery + variants + specs table prominente + ATC sticky | ~300 | Astro build |
| 5 | mismo | Cart: items densos + summary sticky + trust signals | ~150 | Astro build |
| 6 | mismo | Checkout: stepper limpio + summary sticky + trust signals near CTA | ~200 | Astro build |
| 7 | mismo | Mis órdenes: cards colapsables con re-order + tracking visual | ~150 | Astro build |
| 8 | PR a master | Squash + merge del sprint 2.1 | — | PR CI verde |

**Total LOC estimado**: ~1400, en 1 PR (porque cada vista es cohesiva y beneficia a las demás del sprint — ej. el componente `ProductSpecsTable.astro` se usa en home + catálogo + detalle; el `TrustSignalsCompact.astro` se usa en cart + checkout + mis órdenes).

## 5. Lo que NO se hace en este sprint

- Pasarela de pagos real (T3, sprint posterior).
- Migración de language gating por tenant (ya existe el switch tenantSlug, basta con aplicarlo).
- Vistas admin (T4, sprint posterior al Track 3).
- Reviews block (requiere modelo backend, sprint post-pagos).
- Login / Signup Bonfire-style (sprint previo ya migró).
- Animaciones scroll-driven Bonfire decorativos (se mantienen; las vistas ganadas en este sprint las acumulan encima sin romperlas).

---

## 6. Riesgos identificados

| Riesgo | Mitigación |
|---|---|
| **Las vistas existentes ya tienen Bonfire v3** (mucho código maduro). Riesgo de romper lo que funciona. | Cada cambio va con `bun run build` ANTES de commit. Si algo se rompe, revierto ese archivo. No toco archivos que no son del sprint. |
| **El mock data** de B2C nicho no existe para instrumentos musicales. | Crear `frontend/src/data/la-voz-mock.ts` con productos placeholder (guitarras, bajos, amps) hasta que llegue data real del cliente. |
| **El catálogo actual usa datos Panda/Kitsune genéricos** (figuras otaku). | Cambiar el data source SOLO para tenant `instrumentos-musicales-la-voz`. Para otros tenants (Panda, Kitsune, MM, Morales), mantener Bonfire que ya les funciona. |
| **El usuario quiere revisar el resultado rápido**. | Cada commit se hace PEQUEÑO y compilable. Pause para feedback del usuario después de Commit 1 (preparación) y antes de hacer squash final. |

---

## 7. Tracking de progreso

- [x] Crear branch `feat/views-nike-guitargear-2.1`
- [x] Crear este feature doc + tabla de equivalencias URL
- [ ] Preparación: componentes compartidos (SpecsTable, TrustSignalsCompact) + data La Voz mock
- [ ] Vista Home (B2C nicho La Voz)
- [ ] Vista Catálogo
- [ ] Vista Detalle producto
- [ ] Vista Cart
- [ ] Vista Checkout
- [ ] Vista Mis órdenes
- [ ] Squash + PR a master
