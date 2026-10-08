# Diagramas UML — Avance 3

Diagramas UML del capítulo 8 "Análisis Orientado a Objetos" de AtlasCommerce
(Avance 3, Ing. Software I — UNI).

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

## Renderizado

### Opción Docker (recomendada)

```bash
./render-uml.sh              # SVG + PDF
./render-uml.sh --clean      # limpia build/ y re-renderiza
./render-uml.sh --svg-only   # solo SVG
./render-uml.sh --pdf-only   # solo PDF
```

Salida:

- `build/svg/*.svg` — 16 diagramas vectoriales para insertar en el documento.
- `build/pdf/*.pdf` — 16 PDFs de respaldo.

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
