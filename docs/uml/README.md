# Diagramas de Análisis y Diseño — capítulos 8 y 9

Diagramas del capítulo 8 "Análisis Orientado a Objetos" (UML) y del capítulo 9
"Diseño de la Base de Datos" (diagrama entidad-relación y diccionario de
datos) de AtlasCommerce (Ing. Software I — UNI).

## Estructura de archivos

### 8.1 — Diagramas de casos de uso (1 diagrama)

| Archivo | Descripción |
|---------|-------------|
| `8.1-casos-de-uso.puml` | Diagrama general con 4 actores y 18 CU |

### 8.1.1 — Plantillas de Coleman (18 tablas)

Ver `8.1.1-coleman/` — un archivo `.md` por cada caso de uso, más `ALL-COLEMANS.md` consolidado y `README.md` índice.

### 8.2 — Diagramas de clases (7 diagramas, divididos por módulos)

| Archivo | Subsección | Paquetes incluidos |
|---------|------------|---------------------|
| `8.2.1-clases-nucleo.puml` | Núcleo del sistema | Núcleo Multitenant + Identidad y Acceso |
| `8.2.2-clases-catalogo.puml` | Catálogo | Catálogo + Personalización |
| `8.2.3-clases-clientes-marketing.puml` | Clientes y marketing | Clientes + Marketing |
| `8.2.4-clases-operaciones.puml` | Operaciones | Carrito/Checkout + Inventario + Pedidos y Ventas |
| `8.2.5-clases-pagos-envios.puml` | Pagos y envíos | Pagos + Envíos |
| `8.2.6-clases-soporte-sistema.puml` | Soporte y sistema | Soporte + Devoluciones + Sistema |
| `8.2-clases-modulo-pedidos.puml` | Módulo de Pedidos (vista reducida) | Solo Pedidos |

### 8.3 — Diagramas de actividades (3 diagramas)

| Archivo | Subsección | Proceso |
|---------|------------|---------|
| `8.3.1-actividad-checkout-pago.puml` | Proceso de checkout y pago | HU-03/04/05 |
| `8.3.2-actividad-gestion-pedidos.puml` | Gestión de pedidos por el operador | HU-10 |
| `8.3.3-actividad-control-stock.puml` | Control de stock | HU-09, RF-INV |

### 8.4 — Diagramas de secuencia (3 diagramas)

| Archivo | Subsección | Escenario |
|---------|------------|-----------|
| `8.4.1-secuencia-registro-2fa.puml` | Registro e inicio de sesión con 2FA | HU-02 |
| `8.4.2-secuencia-checkout-pasarela.puml` | Checkout completo con pasarela de pago | HU-04/05 |
| `8.4.3-secuencia-gestion-pedido.puml` | Gestión de pedido por el operador | HU-10 |

### 8.5 — Diagramas de estados (2 diagramas)

| Archivo | Subsección | Entidad |
|---------|------------|---------|
| `8.5.1-estado-pedido.puml` | Entidad Pedido | Pedido |
| `8.5.2-estado-2fa.puml` | Ciclo de verificación 2FA | Usuario/Credencial2FA |

### 9.1 — Diagrama entidad-relación (2 diagramas)

| Archivo | Subsección | Contenido |
|---------|------------|-----------|
| `9.1-diagrama-er.puml` | DER completo del núcleo | 16 entidades y 25 relaciones con todos los atributos (vertical, `build/svg/9.1-diagrama-er.svg`) |
| `9.1-diagrama-er-simple.puml` | Versión A4 vertical | Mismas 16 entidades y mismas 25 relaciones con multiplicidades, cada entidad reducida a PK y campos clave (`build/svg-simple/9.1-diagrama-er-simple.svg`) |

### 9.2 — Diccionario de datos (2 documentos)

| Archivo | Subsección | Contenido |
|---------|------------|-----------|
| `9.2-diccionario-datos.md` | Diccionario completo | Las 16 entidades con campo, tipo SQL, nulo, default, clave y descripción, más convenciones, excepciones de naming, claves foráneas externas y trazabilidad |
| `9.2-diccionario-datos-simple.md` | Versión resumida | PK, claves foráneas y campos de negocio representativos de cada una de las 16 entidades |

Las 16 entidades del núcleo son `TIENDA`, `TIENDA_DOMINIO`, `USUARIO`, `ROL`,
`CLIENTE`, `CLIENTE_DIRECCION`, `CATEGORIA`, `MARCA`, `PRODUCTO`,
`PRODUCTO_VARIANTE`, `PEDIDO`, `DETALLE_PEDIDO`, `PAGO`, `FACTURA`, `ENVIO` e
`INVENTARIO`.

## Renderizado

### Opción Docker (recomendada)

```bash
./render-uml.sh              # SVG + PDF
./render-uml.sh --clean      # limpia build/ y re-renderiza
./render-uml.sh --svg-only   # solo SVG
./render-uml.sh --pdf-only   # solo PDF
```

Salida:

- `build/svg/*.svg` — 18 diagramas vectoriales para insertar en el documento.
- `build/pdf/*.pdf` — 18 PDFs de respaldo.
- `build/svg-simple/*.svg` y `build/pdf-simple/*.pdf` — variantes A4 verticales
  de los archivos `-simple.puml`.

Los diagramas del capítulo 9 usan los mismos dos scripts y los mismos
directorios de salida que los del capítulo 8 — no hizo falta modificar ningún
glob, porque los archivos nuevos entran automáticamente:

- `render-uml.sh` expande `docs/uml/*.puml`, así que recoge
  `9.1-diagrama-er.puml` sin configuración adicional.
- `render-simple-uml.sh` filtra con `-name '*-simple.puml'`, así que recoge
  `9.1-diagrama-er-simple.puml` sin configuración adicional.

Teniendo en cuenta que el glob de `render-uml.sh` no excluye los archivos
`-simple.puml`, `build/svg/` y `build/pdf/` terminan conteniendo también esas
variantes además de los diagramas completos. Los conteos de las líneas
anteriores se refieren a los diagramas del entregable —16 del capítulo 8 y 2
del capítulo 9—, no al total de archivos presentes en `build/`.

### Opción VS Code

Instalá la extensión [PlantUML de jebbs](https://marketplace.visualstudio.com/items?itemName=jebbs.plantuml)
y abrí cualquier `.puml` — preview en vivo y exportás con `Export Current Diagram`.

## Requisitos

- **Docker** (recomendado): la imagen `plantuml/plantuml` ya trae Java + Graphviz.
- O bien: **Java 11+** y **PlantUML** standalone.

## Cambios respecto al Avance 1 (medidas aplicadas por la profe)

1. **Diagramas 8.3 y 8.4 divididos en sub-secciones** (8.3.1, 8.3.2, 8.3.3; 8.4.1, 8.4.2, 8.4.3) según el índice del documento.
2. **Diagramas 8.5 divididos en sub-secciones** (8.5.1 para Pedido, 8.5.2 para 2FA).
3. **Diagrama de clases 8.2 dividido en 7 sub-diagramas** (8.2.1 a 8.2.6 + 8.2 vista reducida del módulo Pedidos) porque el sistema tiene muchos módulos y clases.
4. **Cada clase tiene métodos visibles** (no se ocultan con `hide methods`) para diferenciarlo de un diagrama entidad-relación.
5. **Multiplicidades explícitas en ambos extremos** de cada relación, con roles cuando aplica.

## Decisiones de modelado

- Multitenant con `Tienda` como aggregate root.
- Producto global, publicado por tienda vía `ProductoTienda`.
- Stock global entre marcas (decisión de operación actual, ver `docs/database/data-model.md`).
- 6 tipos de pago incluyendo SINPE Móvil y PayPal.
- Impuestos Nicaragua: IVA 15% + retención IR 1-2% B2B.
- `DEVUELTO` y `REEMBOLSADO` son sub-estados modelados vía entidades separadas (`Devolucion` + `Reembolso`).
- Personalización separada en `opción` (cliente) y `técnica` (taller).
- 4 estados de Pedido alineados con la implementación real (no 10 académicos).

### Decisiones de modelado del DER (capítulo 9)

El DER de este capítulo es **académico** y cubre un núcleo de 16 entidades. El
DER técnico completo del sistema, con las 85 tablas reales, se mantiene en
`docs/database/diagrams/ecommerce-er.mmd` (Mermaid): ese archivo no se modifica
desde aquí y sigue siendo la fuente de verdad de ingeniería.

- **Claves primarias `BIGSERIAL`, no UUID.** Todas las tablas se crean con
  `$table->id()`, que en PostgreSQL produce `bigserial`. Los diagramas de clases
  del capítulo 8 usan `UUID` como convención académica del modelo de objetos;
  el DER declara `BIGSERIAL` porque es lo que implementa realmente la
  migración. No es una contradicción entre UML y DER: el primero describe el
  modelo conceptual, el segundo la base de datos existente.
- **`CLIENTE` no tiene `store_id`.** Su relación con `TIENDA` es N:N a través
  de la tabla puente `customer_stores`, porque el mismo cliente puede comprar
  en más de una tienda.
- **`ENVIO` no tiene `store_id`.** La tienda se deriva a través de `PEDIDO`.
- **`PAGO` no se relaciona con `PEDIDO`.** Se vincula con `FACTURA` mediante la
  tabla puente `invoice_payments`, en relación N:M, de modo que un solo pago
  puede cubrir varias facturas.
- **Dinero en centavos.** Los importes se guardan en columnas `*_cents` de tipo
  `BIGINT`. Detalle técnico: `unsignedBigInteger()` en PostgreSQL genera
  `BIGINT` sin restricción `CHECK >= 0` —solo MySQL implementa el modificador
  `unsigned`—, así que la no-negatividad la asegura la capa de aplicación y no
  la base de datos.
- **Nomenclatura de claves foráneas.** Se sigue la convención
  `<tabla_en_singular>_id`, con cuatro excepciones de nombre semántico:
  `orders.user_creator_id`, `invoices.user_issuer_id`,
  `order_custom_designs.reviewed_by` y `orders.shipping_address_id`.

## Nota de trazabilidad con la implementación

> **Trazabilidad con la implementación.** Los nombres de clases en este diagrama
> siguen convención UML académica en español (ej. `Usuario` ↔ `User`, `Pedido` ↔
> `Order`, `Pago` ↔ `Payment`, `LíneaPedido` ↔ `OrderItem`). La implementación
> Laravel 13 del sistema tiene 78 modelos que mantienen una relación 1:1 con
> las clases aquí modeladas.
>
> **Simplificaciones intencionales del modelo académico** (para alinearse con la
> realidad de la implementación sin perder rigurosidad UML):
>
> - **Montos en `Decimal`**: en la implementación se almacenan en `Integer` cents
>   (ej. `subtotal_cents`, `tax_cents`, `total_cents`) para evitar errores de
>   punto flotante. UML abstrae esto como `Decimal`. Adicionalmente, existe
>   `exchange_rate_snapshot` y `total_in_base_currency_cents` para soporte
>   multi-moneda con tasa congelada al momento del pedido.
>
> - **Máquina de estados de Pedido**: el modelo académico de 10 estados
>   (PENDIENTE_PAGO, PAGADO, CONFIRMADO, EN_PRODUCCION, PREPARADO_ENVIO,
>   ENVIADO, ENTREGADO + CANCELADO, DEVUELTO, REEMBOLSADO) corresponde a la
>   implementación con 4 estados (`Pending`, `Processing`, `Completed`,
>   `Cancelled`) según la tabla:
>
>   | Estado UML | Estado real (OrderStatus enum) |
>   |------------|-------------------------------|
>   | PENDIENTE_PAGO | Pending |
>   | PAGADO + CONFIRMADO + EN_PRODUCCION + PREPARADO_ENVIO + ENVIADO | Processing |
>   | ENTREGADO | Completed |
>   | CANCELADO | Cancelled |
>   | DEVUELTO + REEMBOLSADO | Cancelled + entidades `Devolucion` + `Reembolso` |
>
>   Cada transición queda registrada en `HistorialEstadoPedido` (auditoría) con
>   `estadoAnterior`, `estadoNuevo`, `cambiadoPorUsuarioId` y `cambiadoEn`.
>
> - **Pago ↔ Factura**: en el modelo académico, `Pedido` se relaciona con `Pago`
>   directamente. En la implementación, `Pago` se relaciona con `Customer` y se
>   vincula a `Factura` (no a `Pedido` directamente) mediante la tabla pivot
>   `PagoFactura` con campos `appliedAmountCents` y `appliedAt`. Esto permite que
>   un solo pago cubra múltiples facturas (ej. pago grande que cubre 3 invoices).
>
> - **Entidades presentes en la implementación pero no en el UML simplificado**:
>   `OrderStatusHistory`, `OrderCustomDesign`, `InvoiceStatusHistory`,
>   `SalesChannel`, `StoreTheme`, `WholesalePrice`, `Notificacion`,
>   `PreguntaProducto`, `ResenaProducto`, `CodigoRecuperacion`, `CuentaSocial`,
>   `TokenResetContrasena`, `ItemCarritoMayorista`, `DisenoPersonalizado`.
>   La mayoría están incluidas en los diagramas 8.2.x.

## Trazabilidad del DER con la implementación

El diagrama entidad-relación del capítulo 9 mantiene la equivalencia
académico ↔ implementación documentada en `docs/database/README.md` (sección
«DER → Laravel»): cada entidad del modelo conceptual en español corresponde a
su tabla física real —por ejemplo `TIENDA` ↔ `stores`, `PEDIDO` ↔ `orders`,
`PAGO` ↔ `payments`—.

Esa correspondencia es legible en el propio `.puml`: cada entidad declara su
tabla física real como alias de PlantUML (por ejemplo
`entity "TIENDA" as stores {`), de modo que el diagrama muestra el nombre
académico y el físico en la misma caja. La tabla de equivalencia completa,
con la migración de origen de cada tabla, está en las notas de trazabilidad de
`9.2-diccionario-datos.md`.
