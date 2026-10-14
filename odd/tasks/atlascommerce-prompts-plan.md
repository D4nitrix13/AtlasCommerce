# Plan multi-prompt v2 — Nike inspiration (próximas tandas)

> **Status**: las 10 vistas Bonfire v3 (Sprint anterior) están mergeadas en master. Próxima tanda usa **Nike** inspiration para vistas B2C y mantiene **Bonfire** para vistas B2B/admin.
> **Versión**: v2. Reemplaza la categorización del plan v1 que era 100% Bonfire.

## Razonamiento de la división Nike vs Bonfire

| Tenant | Estilo de vistas | Estilo aplicado |
|---|---|---|
| Panda Estampados (B2B) | Editorial denso, trust signals, decorativos Bonfire (squiggles, blobs) | **Bonfire** (sprint anterior, ya mergeado) |
| Kitsune Store (B2C) | Action sports minimalista, producto hero, whitespace | **Nike** (sprints nuevos) |
| MM Boutique (B2C) | Boutique minimalista, grid limpio | **Nike** (sprints nuevos) |
| Morales Store (B2C) | Retail general, grid eficiente | **Nike** (sprints nuevos) |
| Admin (cualquier tenant) | Densidad informativa, datos, charts | **Bonfire base** con acentos Nike (CTAs negros) |

## Categorización de prompts

### Sprint Nike 1 — Home redo + Auth (1 prompt + 1 auth batch)

**Cuándo**: primero porque el usuario pidió la home redo.

**Prompt Nike 1 — Home + Auth (4 vistas en 1 prompt por la estrecha relación)**:
- Home (B2C y B2B branches)
- Login cliente B2C
- Signup cliente B2C
- Mis pedidos cliente B2C (Nike aesthetic)

**Por qué agrupado**: home + auth son los entry points del flow de compra; encajan visualmente con la misma estética minimalista.

### Sprint Nike 2 — Catálogo + Producto + Cart (1 prompt)

**Cuándo**: después de Nike 1.

**Prompt Nike 2 — Catálogo + Producto + Cart (3 vistas)**:
- Catálogo (grid limpio, sin decorativos)
- Detalle producto (gallery dominante, sticky ATC)
- Carrito (lista minimalista, summary simple)

**Por qué agrupado**: son el flow de discovery → decisión → conversion prep. Comparten el mismo hero visual (producto grande, CTA negro).

### Sprint Nike 3 — Checkout + Account dashboard (1 prompt)

**Cuándo**: después de Nike 2.

**Prompt Nike 3 — Checkout + Dashboard (2 vistas)**:
- Checkout multi-step (stepper limpio, sin decorativos)
- Account dashboard Nike-style (KPIs minimalistas)

### Sprint Bonfire 4 — RFQ + Bulk Bonfire refresh (1 prompt, opcional)

**Cuándo**: opcional, si se quiere reimplementar las vistas B2B con Bonfire editorial.

**Por qué no Nike**: el RFQ y Bulk son B2B y la densidad de Bonfire funciona mejor que Nike minimalista.

### Sprint Bonfire 5 — Admin views (7-8 prompts categorizados)

**Cuándo**: siguiente bloque grande después de las Nike.

**Cuándo agrupar por hub**:

**Prompt B-5a — Admin overview (3 vistas)**:
- Dashboard admin
- Reports overview
- Payments overview

**Por qué agrupado**: las 3 son dashboards/resúmenes con KPI cards y charts.

**Prompt B-5b — Admin sales (5 vistas)**:
- Orders list
- Order detail
- Invoices list
- Invoice detail
- New order manual

**Por qué agrupado**: todas son list+detail del mismo dominio (ventas).

**Prompt B-5c — Admin catalog (10 vistas)**:
- Products list, create, edit
- Variants list
- Categories tree + edit
- Brands list + edit
- Attributes (nuevo)
- Collections (nuevo)

**Por qué agrupado**: 10 vistas del mismo dominio (catálogo). Aunque son muchas, comparten los mismos primitives (ProductForm, CategoryTree, etc.).

**Prompt B-5d — Admin customers + marketing (10 vistas)**:
- Customers list + detail
- Segments + groups + reviews (nuevos)
- Coupons + promotions + campaigns + gift-cards + loyalty (marketing)

**Por qué agrupado**: customers + marketing comparten perfilado de audiencia.

**Prompt B-5e — Admin inventory + purchases (8 vistas)**:
- Stock + warehouses + movements + transfers + adjustments + low-stock (inventory)
- Purchases orders + suppliers + receiving

**Por qué agrupado**: supply chain completo.

**Prompt B-5f — Admin operations (8 vistas)**:
- Shipping hub + zones + companies + rates
- Support tickets + macros
- System health + jobs + webhooks + error-log

**Por qué agrupado**: operations y platform management.

**Prompt B-5g — Admin settings + stores + users (8 vistas)**:
- Settings hub + store + theme + taxes + currencies + gateways + integrations
- Stores list + edit + create
- Users list + detail + roles

**Por qué agrupado**: platform management y permisos.

### Sprint Nike 4 — Login + Signup B2C (1 prompt)

**Cuándo**: antes o después del sprint admin, según urgencia.

**Por qué agrupado**: 2 vistas relacionadas al mismo flow.

### Sprint Nike 5 — Login admin (1 prompt)

**Cuándo**: idem anterior.

**Por qué agrupado**: 3 vistas (login admin, 2FA, session expired) cohesivas.

## Resumen de prompts a ejecutar (orden)

1. **Nike 1**: Home + Login + Signup + Mis pedidos (4 vistas B2C, 1 prompt)
2. **Nike 2**: Catálogo + Detalle + Cart (3 vistas B2C, 1 prompt)
3. **Nike 3**: Checkout + Dashboard (2 vistas B2C, 1 prompt)
4. **Nike 4**: Login + Signup B2C adicional (si se quieren rehacer, 1 prompt)
5. **Nike 5**: Login admin + 2FA + session expired (3 vistas admin, 1 prompt)
6. **Bonfire 4**: RFQ + Bulk refresh (opcional, 1 prompt)
7. **Bonfire 5a-g**: Admin views (7 prompts, ~50 vistas)

**Total**: ~12 prompts + 2 prompts opcionales = ~14 prompts para cubrir todas las vistas pendientes.

## Criterio de tamaño de cada prompt

- **5-8 vistas por prompt** es el sweet spot
- **Vistas complejas** (catalog, settings) bajan a 5
- **Vistas simples** (auth, list views) suben a 8
- **Home y vistas críticas** van solas (1 vista por prompt, prompt dedicado) para máxima calidad

## Plan de ejecución

Para cada prompt:

1. **Yo (orchestrator)** actualizo el prompt v2 con la lista de vistas
2. **El usuario** pasa el prompt a una IA externa (la misma que hizo la primera tanda)
3. **La IA externa** devuelve mockups en un ZIP
4. **Yo (orchestrator)** extraigo el ZIP y delego a un writer de implementación
5. **El writer** implementa las vistas en una branch con commits work-unit
6. **Yo (orchestrator)** abro PR y notifico al usuario
7. **El usuario** mergea o pide ajustes

## Decisión de cuándo arrancar

El usuario dijo "primero las 10 vistas" → ya hecho. Siguiente paso lógico:
- Nike 1 (Home + Auth + Mis pedidos) → la vista principal (que no le gusta) y auth
- Nike 2 (Catálogo + Producto + Cart) → flow de compra
- Nike 3 (Checkout + Dashboard) → cierre del flow

Pero antes de Nike 1, **necesito el archivo `atlascommerce-views.zip` actualizado** con los nuevos mockups Nike. El usuario debe:
1. Tomar el prompt v2 actualizado
2. Pasarlo a la IA externa
3. Devolver el ZIP con los mockups
4. Yo ejecuto la implementación

## Plan document

odd slash tasks slash atlascommerce design prompt v2 punto md — prompt Nike v2 actualizado
odd slash tasks slash atlascommerce prompts plan punto md — este archivo
docs slash frontend slash design references nike punto md — design system Nike v1
docs slash frontend slash design references bonfire punto md — design system Bonfire v1 (historico)
