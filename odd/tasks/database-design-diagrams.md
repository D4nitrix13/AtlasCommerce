# Diseño de Base de Datos — Diagrama ER y Diccionario de Datos

## Objective

Crear el material de la sección **Diseño de la Base de Datos** (capítulo siguiente
al 8 "Análisis Orientado a Objetos") con dos piezas:

1. **Diagrama Entidad-Relaciones (DER)** en PlantUML, en versión completa y
   versión simplificada (`-simple`), con el núcleo de 16 entidades del sistema.
2. **Diccionario de datos** que documenta esas 16 entidades contra el esquema
   real de las migraciones Laravel.

Objetivo de forma: que ambos artefactos entren en **A4 vertical** dentro del
documento y se puedan defender oralmente sin Saturar la página.

## Problem

El proyecto tiene material del capítulo 8 (`docs/uml/`, 16 diagramas con
variante `-simple` para Word vertical). Para Diseño de BD no existe equivalente
académico:

- `docs/database/diagrams/ecommerce-er.mmd` es el ER **técnico** en Mermaid
  con **85 tablas y 1379 líneas**. Es correcto como documentación de ingeniería
  pero inservible para una página de documento: ni entra, ni se explica.
- No existe **diccionario de datos** en ningún formato.

## Why

- El documento académico necesita un DER legible, no el volcado del esquema.
- 85 tablas en una página变成 texto ilegible: el lector no distingue qué es el
  núcleo del dominio y qué es infraestructura.
- El diccionario de datos NO puede inventar el esquema: debe reflejar las
  migraciones reales, o el documento miente frente a la implementación.

## Scope

**In scope:**

- `docs/uml/9.1-diagrama-er.puml` — versión completa (16 entidades, atributos
  completos, PK/FK/multiplicidades).
- `docs/uml/9.1-diagrama-er-simple.puml` — versión simplificada para A4 vertical.
- `docs/uml/9.2-diccionario-datos.md` — diccionario completo de las 16 entidades.
- `docs/uml/9.2-diccionario-datos-simple.md` — diccionario resumido (solo
  columnas clave + descripción corta por entidad).
- `docs/uml/README.md` — agregar sección 9 al índice y documentar que el ER
  técnico completo sigue siendo `docs/database/diagrams/ecommerce-er.mmd`.

**Out of scope:**

- Regenerar o corregir `docs/database/diagrams/ecommerce-er.mmd` (queda
  desactualizado en 11 columnas — se reporta aparte, no se toca acá).
- Corregir `docs/database/data-model.md` (contradicciones con migraciones en
  `stores` y `store_domains` — se reporta aparte).
- Las 69 tablas restantes del esquema.
- Cambios de código, migraciones o modelos.

## Las 16 entidades núcleo

| # | Entidad (UML) | Tabla real | Origen |
|---|---|---|---|
| 1 | `TIENDA` | `stores` | raíz multi-tenant |
| 2 | `TIENDA_DOMINIO` | `store_domains` | resolución por dominio |
| 3 | `USUARIO` | `users` | identidad/admin |
| 4 | `ROL` | `roles` | autorización |
| 5 | `CLIENTE` | `customers` | comprador |
| 6 | `CLIENTE_DIRECCION` | `customer_addresses` | despacho |
| 7 | `CATEGORIA` | `categories` | jerárquica (auto-ref) |
| 8 | `MARCA` | `brands` | fabricante |
| 9 | `PRODUCTO` | `products` | catálogo |
| 10 | `PRODUCTO_VARIANTE` | `product_variants` | SKU concreto |
| 11 | `PEDIDO` | `orders` | transacción comercial |
| 12 | `DETALLE_PEDIDO` | `order_items` | líneas del pedido |
| 13 | `PAGO` | `payments` | cobro (N:M con factura) |
| 14 | `FACTURA` | `invoices` | documento fiscal |
| 15 | `ENVIO` | `shipments` | despacho |
| 16 | `INVENTARIO` | `inventories` | stock por almacén |

## Reglas de simplificación (versión `-simple`)

Heredadas de `odd/tasks/uml-diagrams-simplification.md`, adaptadas a ER:

- Cada entidad conserva **PK + 3-5 campos clave**; se cae el resto.
- Se conservan **TODAS las claves foráneas y sus multiplicidades** — eso ES la
  semántica del ER; sin eso el diagrama no es un ER.
- Se eliminan índices, defaults y notas de migración de las cajas.
- Se eliminan las entidades mostradas solo para integridad (`currencies`,
  `payment_methods`, `shipping_companies`, `sales_channels`, `warehouses`,
  `shipping_zones`, `custom_designs`) **del diagrama simple**, pero se anotan
  como FK externa para no mentir sobre la cardinalidad.
- Objetivo de bytes: ~55% del original,aspecto vertical (alto ≥ ancho).

## Restricciones y hallazgos que el diagrama DEBE respetar

Extraídos de las migraciones reales (`backend/database/migrations/**`):

1. **Todas las PK son `BIGSERIAL`** (`$table->id()`), no UUID. Los `.puml` del
   capítulo 8 usan `UUID` en las clases: es convención académica de ahí, pero
   el ER tiene que decir `BIGSERIAL` o contradice la BD.
2. **`CLIENTE` NO tiene `store_id`.** La relación cliente↔tienda es N:N vía
   pivot `customer_stores`. El ER simple lo marca así o miente.
3. **`ENVIO` NO tiene `store_id`** — se deriva vía `orders`.
4. **`PAGO` se relaciona con `FACTURA`, no con `PEDIDO`.** El vínculo es la
   pivot `invoice_payments` (N:M: un pago puede cubrir varias facturas).
5. **Dinero en centavos**: `*_cents` se declaran `unsignedBigInteger()`, que en
   **PostgreSQL produce `BIGINT` pelado, SIN `CHECK (>= 0)`**. Verificado en
   `vendor/.../Schema/Grammars/`: `modifyUnsigned()` solo está implementado en
   `MySqlGrammar`; `PostgresGrammar` no lo overridea, así que el modificador
   `unsigned` se descarta en la traducción a SQL. La no-negatividad la
   garantiza la capa de aplicación, no la base. Los únicos tipos no-BIGINT del
   núcleo son `stores.tax_rate decimal(5,2)` y los tres
   `exchange_rate_snapshot numeric(18,8)`.
6. **4 excepciones a la convención de FK** `<tabla_singular>_id`, que el
   diccionario debe documentar como excepción y no como invariante:
   `orders.user_creator_id`, `invoices.user_issuer_id`,
   `order_custom_designs.reviewed_by`, `orders.shipping_address_id`.
7. `categories.parent_id` es **auto-referencia** nullable → jerarquía recursiva.
8. RLS activo sobre `orders`, `invoices`, `payments` de este núcleo; el resto
   se aísla por middleware. Se documenta como nota, no como caja.

## Actionable Tasks

- [x] **T1** Extraer esquema real de las 16 tablas desde migraciones.
- [x] **T2** Crear este feature doc + espejo Engram `odd/database-design-diagrams/tasks`.
- [ ] **T3** Escribir `9.1-diagrama-er.puml` (completo).
- [ ] **T4** Escribir `9.1-diagrama-er-simple.puml` (simplificado).
- [ ] **T5** Escribir `9.2-diccionario-datos.md` (completo).
- [ ] **T6** Escribir `9.2-diccionario-datos-simple.md` (simplificado).
- [ ] **T7** Renderizar ambos `.puml` vía `render-uml.sh` / `render-simple-uml.sh`.
- [ ] **T8** Verificar aspecto A4 vertical de los SVG generados.
- [ ] **T9** Actualizar `docs/uml/README.md`.
- [ ] **T10** Commits por work-unit en rama de feature.

## Acceptance Criteria

- [ ] Ambos `.puml` renderizan sin error con PlantUML Docker (`-failfast2`).
- [ ] SVG completos y simples con **alto ≥ ancho**.
- [ ] Toda entidad del ER tiene su tabla real citada y coincide en PK/FK con
      las migraciones (verificado contra T1).
- [ ] El diccionario documenta las 4 excepciones de naming de FK.
- [ ] El ER simple NO sugiere que `CLIENTE` o `ENVIO` tengan `store_id`.
- [ ] `README.md` de `docs/uml/` lista la sección 9 y distingue el ER académico
      del ER técnico de 85 tablas.

## Verification

- `bash docs/uml/render-uml.sh` y `bash docs/uml/render-simple-uml.sh` terminan
  sin `ERROR` en el reporte.
- `awk` sobre el `<svg>` root: `height >= width`.
- Diff conceptual contra T1: cada FK del `.puml` existe en el esquema real.

## Decision Log

- **Alcance núcleo de 16 entidades** (decidido por el usuario sobre la opción
  "Núcleo ~16"). Cubre el recorrido completo: tienda → catálogo → cliente →
  pedido → pago → factura → envío, más inventario y seguridad.
- **Nomenclatura en español académico** (`TIENDA`, `PEDIDO`), siguiendo la
  tabla de equivalencia de `docs/database/README.md` y la convención de los
  `8.2.x-clases-*.puml`. Cada entidad lleva anotado su tabla física.
- **Numeración `9.1` / `9.2`** siguiendo la convención `8.x` del capítulo previo.
- **PlantUML, no Mermaid**: el entregable es el mismo que el capítulo 8 y entra
  en los mismos scripts de render. El Mermaid de 85 tablas queda como
  documentación de ingeniería, referenciado desde el README.
- **No se toca el `.mmd` ni `data-model.md`** aunque estén desactualizados:
  son deuda documental separada, y corregirlos acá mezclaría dos temas en un
  mismo cambio. Se reportan al usuario.

## Next Step

Delegar a un `general` la escritura de los 4 artefactos (T3-T6) con el esquema
de T1 embebido en el prompt, luego renderizar y verificar.