# Diagramas UML — Avance 3

Diagramas UML del capítulo 8 "Análisis Orientado a Objetos" de AtlasCommerce
(Avance 3, Ing. Software I — UNI).

## Archivos

| # | Archivo | Sección | Descripción |
|---|---------|---------|-------------|
| 01 | `8.1-casos-de-uso.puml` | 8.1 | Diagrama de casos de uso con 4 actores y 18 CU |
| 02 | `8.2-clases-sistema-completo.puml` | 8.2 | Diagrama de clases completo del sistema |
| 03 | `8.2-clases-modulo-pedidos.puml` | 8.2 | Vista reducida centrada en el módulo de Pedidos |
| 04 | `8.3-actividad-checkout-pago.puml` | 8.3 | Proceso de checkout y pago (HU-03/04/05) |
| 05 | `8.3-actividad-gestion-pedidos.puml` | 8.3 | Proceso de gestión de pedidos (HU-10) |
| 06 | `8.3-actividad-control-stock.puml` | 8.3 | Proceso de control de stock (HU-09, RF-INV) |
| 07 | `8.4-secuencia-registro-2fa.puml` | 8.4 | Secuencia de registro + 2FA (HU-02) |
| 08 | `8.4-secuencia-checkout-pasarela.puml` | 8.4 | Secuencia de checkout + pasarela (HU-04/05) |
| 09 | `8.4-secuencia-gestion-pedido.puml` | 8.4 | Secuencia de gestión de pedido (HU-10) |
| 10 | `8.5-estado-pedido.puml` | 8.5 | Diagrama de estados de Pedido (alineado con código) |
| 11 | `8.5-estado-2fa.puml` | 8.5 | Diagrama de estados del ciclo 2FA de Usuario |
| — | `8.1.1-coleman/*.md` | 8.1.1 | 18 plantillas de Coleman (una por CU) |

## Renderizado

### Opción Docker (recomendada — no requiere instalar nada)

```bash
# Renderizar todo (PNG + PDF)
./render-uml.sh

# Limpiar build/ y re-renderizar desde cero
./render-uml.sh --clean

# Solo PNG (más rápido para Word)
./render-uml.sh --png-only

# Solo PDF
./render-uml.sh --pdf-only
```

Salida:

- `build/png/*.png` — imágenes individuales para insertar en el documento Word.
- `build/pdf/*.pdf` — un PDF por diagrama (vectorial, alta calidad).

### Opción CLI nativa (si tenés PlantUML + Java instalados)

```bash
# Renderizar un diagrama a PNG
plantuml -tpng 8.1-casos-de-uso.puml

# Renderizar todos a PNG y PDF
plantuml -tpng -tpdf *.puml
```

### Opción VS Code

Instalá la extensión [PlantUML de jebbs](https://marketplace.visualstudio.com/items?itemName=jebbs.plantuml)
y abrí cualquier `.puml` — ves el preview en vivo y exportás con click derecho
`Export Current Diagram`.

## Requisitos del sistema

- **Docker** (recomendado): la imagen `plantuml/plantuml` ya trae Java + Graphviz.
- O bien: **Java 11+** y **PlantUML** (`apt install plantuml` en Debian/Ubuntu,
  `brew install plantuml` en macOS).

## Decisiones de modelado

Las decisiones de diseño están documentadas en la conversación del Avance 3.
Resumen breve:

- Multitenant con `Tienda` como aggregate root.
- Producto global, publicado por tienda vía `ProductoTienda`.
- Stock global entre marcas (decisión de operación actual, ver `docs/database/data-model.md`).
- 6 tipos de pago incluyendo SINPE Móvil y PayPal.
- Impuestos Nicaragua: IVA 15% + retención IR 1-2% B2B.
- `DEVUELTO` y `REEMBOLSADO` son sub-estados modelados vía entidades separadas (`Devolucion` + `Reembolso`).
- Personalización separada en `opción` (cliente) y `técnica` (taller).

---

## Nota de trazabilidad con la implementación (recomendado para el documento)

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
>   `PagoFactura` con campos `montoAplicado` y `aplicadoEn`. Esto permite que
>   un solo pago cubra múltiples facturas (ej. pago grande que cubre 3 invoices).
>
> - **Entidades nuevas agregadas al modelo académico** (presentes en la
>   implementación): `CanalVenta`, `HistorialEstadoPedido`, `Reembolso`,
>   `PagoFactura`, `StoreTheme`, `PrecioMayorista`, `Notificacion`,
>   `PreguntaProducto`, `ResenaProducto`, `CodigoRecuperacion`, `CuentaSocial`,
>   `TokenResetContrasena`, `ItemCarritoMayorista`, `DisenoPersonalizado`.
