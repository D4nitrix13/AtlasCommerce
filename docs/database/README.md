# Base de Datos — PostgreSQL

## Visión general

El sistema usa una instancia única de **PostgreSQL 18 con PostGIS**
para almacenar todos los datos de ambas tiendas (`Panda Estampados`
y `Kitsune`). Las tablas transaccionales incluyen `store_id` para
filtrar datos por tienda.

## Configuración

- **Motor**: `PostgreSQL 18`
- **Extensión**: `PostGIS` (`postgis/postgis:18-3.6`)
- **Collation**: Configuración por defecto
- **Host (producción)**: Interno en Docker (no expuesto)
- **Puerto**: `5432` (interno)

## Organización de migraciones

Las migraciones se organizan por dominio de negocio en
`database/migrations/`:

```txt
database/migrations/
├── auth/                          # 2FA, passkeys, tokens
├── carts/                         # Carritos, wishlists
├── catalog/                       # Productos, categorías, marcas
├── customers/                     # Clientes, direcciones
├── inventory/                     # Stock, movimientos, almacenes
├── marketing/                     # Cupones, promociones
├── payments/                      # Métodos de pago, transacciones
├── purchases/                     # Proveedores, compras
├── returns/                       # Devoluciones, reembolsos
├── sales/                         # Órdenes, facturas
├── settings/                      # Configuración, monedas
├── shipping/                      # Envíos, zonas, empresas
├── stores/                        # Multi-tienda
├── support/                       # Tickets, reseñas, preguntas
├── system/                        # Auditorías, notificaciones
├── taxes/                         # Impuestos
├── 0001_01_01_000001_create_cache_table.php
├── 0001_01_01_000002_create_jobs_table.php
└── 2026_06_25_230734_create_personal_access_tokens_table.php
```

## DER (Diagrama Entidad-Relación)

El DER completo se encuentra en
`docs/database/diagrams/ecommerce-er.mmd` formato Mermaid.

### Equivalencia español ↔ inglés

El DER usa nombres en español como modelo conceptual.
La implementación en `Laravel` usa nombres en inglés:

### DER → Laravel

- `TIENDA` → `stores`
- `TIENDA_DOMINIO` → `store_domains`
- `TIENDA_RED_SOCIAL` → `store_social_links`
- `USUARIO_TIENDA` → `user_stores`
- `CLIENTE_TIENDA` → `customer_stores`
- `PRODUCTO_TIENDA` → `product_stores`
- `CANAL_VENTA` → `sales_channels`
- `ROL` → `roles`
- `PERMISO` → `permissions`
- `ROL_PERMISO` → `role_permissions`
- `USUARIO` → `users`
- `CLIENTE` → `customers`
- `CLIENTE_DIRECCION` → `customer_addresses`
- `CATEGORIA` → `categories`
- `MARCA` → `brands`
- `PRODUCTO` → `products`
- `PRODUCTO_IMAGEN` → `product_images`
- `PRODUCTO_VARIANTE` → `product_variants`
- `ATRIBUTO` → `attributes`
- `ALMACEN` → `warehouses`
- `INVENTARIO` → `inventories`
- `MOVIMIENTO_INVENTARIO` → `inventory_movements`
- `PROVEEDOR` → `suppliers`
- `COMPRA` → `purchases`
- `DETALLE_COMPRA` → `purchase_items`
- `CARRITO` → `carts`
- `CARRITO_DETALLE` → `cart_items`
- `WISHLIST` → `wishlists`
- `WISHLIST_DETALLE` → `wishlist_items`
- `PEDIDO` → `orders`
- `DETALLE_PEDIDO` → `order_items`
- `PEDIDO_ESTADO_HISTORIAL` → `order_status_histories`
- `PEDIDO_USUARIO_CREADOR` → `PEDIDO.id_usuario_creador`
- `FACTURA` → `invoices`
- `DETALLE_FACTURA` → `invoice_items`
- `FACTURA_ESTADO_HISTORIAL` → `invoice_status_histories`
- `FACTURA_USUARIO_EMISOR` → `FACTURA.id_usuario_emisor`
- `METODO_PAGO` → `payment_methods`
- `PAGO` → `payments`
- `FACTURA_PAGO` → `invoice_payments`
- `CUOTA_PAGO` → `payment_installments`
- `TRANSACCION_PAGO` → `payment_transactions`
- `EMPRESA_ENVIO` → `shipping_companies`
- `ZONA_ENVIO` → `shipping_zones`
- `ENVIO` → `shipments`
- `SEGUIMIENTO_ENVIO` → `shipment_trackings`
- `CUPON` → `coupons`
- `USO_CUPON` → `coupon_usages`
- `PROMOCION` → `promotions`
- `PRODUCTO_PROMOCION` → `product_promotions`
- `IMPUESTO` → `taxes`
- `PRODUCTO_IMPUESTO` → `product_taxes`
- `DEVOLUCION` → `return_requests`
- `DETALLE_DEVOLUCION` → `return_request_items`
- `REEMBOLSO` → `refunds`
- `RESENA_PRODUCTO` → `product_reviews`
- `PREGUNTA_PRODUCTO` → `product_questions`
- `TICKET_SOPORTE` → `support_tickets`
- `TICKET_MENSAJE` → `support_ticket_messages`
- `NOTIFICACION` → `notifications`
- `AUDITORIA` → `audits`
- `MONEDA` → `currencies`
- `TIPO_CAMBIO` → `exchange_rates`
- `USUARIO_2FA_CONFIGURACION` → `two_factor_settings`
- `USUARIO_2FA_METODO` → `two_factor_methods`
- `OTP_DESAFIO` → `otp_challenges`
- `CODIGO_RECUPERACION_2FA` → `recovery_codes`
- `PASSKEY_CREDENCIAL` → `passkey_credentials`

## Entidades principales

### Globales (sin `store_id`)

- `users` — Usuarios del sistema
- `roles` — Roles de acceso
- `permissions` — Permisos granulares
- `role_permissions` — Relación rol-permiso
- `currencies` — Monedas
- `exchange_rates` — Tipos de cambio
- `brands` — Marcas
- `categories` — Categorías (jerarquía)
- `warehouses` — Almacenes
- `payment_methods` — Métodos de pago
- `shipping_companies` — Empresas de envío
- `shipping_zones` — Zonas de envío
- `taxes` — Impuestos
- `personal_access_tokens` — Tokens Sanctum
- `cache` — Cache `Laravel`
- `jobs` — Cola de trabajos

### Multi-tienda (con `store_id`)

- `stores` — Configuración de tiendas (sin `store_id`)
- `store_domains` — Dominios por tienda
- `store_social_links` — Redes sociales por tienda
- `store_settings` — Configuración clave-valor
- `user_stores` — Usuarios admin por tienda
  (con `role_id`)
- `customer_stores` — Clientes por tienda
- `product_stores` — Publicación de productos por tienda
- `sales_channels` — Canales de venta
- `orders` — Pedidos por tienda
- `invoices` — Facturas por tienda
- `carts` — Carritos por tienda
- `wishlists` — Wishlists por tienda
- `coupons` — Cupones por tienda
- `promotions` — Promociones por tienda
- `product_reviews` — Reseñas por tienda
- `product_questions` — Preguntas por tienda
- `support_tickets` — Tickets por tienda
- `notifications` — Notificaciones por tienda
- `audits` — Auditorías por tienda
- `payments` — Pagos por tienda

### Transaccionales (sin `store_id`)

Relacionadas con tablas que sí lo tienen:

- `products` — Productos (globales, publicados vía `product_stores`)
- `product_images` — Imágenes de productos
- `product_variants` — Variantes de productos
- `product_attributes` — Atributos de productos
- `attributes` — Definición de atributos
- `inventories` — Stock por almacén-producto
- `inventory_movements` — Movimientos de stock
- `order_items` — Líneas de pedido
- `order_status_histories` — Historial de estados de pedido
- `invoice_items` — Líneas de factura
- `invoice_status_histories` — Historial de estados de factura
- `cart_items` — Líneas de carrito
- `wishlist_items` — Líneas de wishlist
- `purchases` — Compras a proveedores
- `purchase_items` — Líneas de compra
- `shipments` — Envíos
- `shipment_trackings` — Seguimiento de envíos
- `invoice_payments` — Relación factura-pago
- `payment_transactions` — Transacciones de pago
- `payment_installments` — Cuotas de pago
- `coupon_usages` — Uso de cupones
- `product_promotions` — Relación producto-promoción
- `product_taxes` — Relación producto-impuesto
- `return_requests` — Solicitudes de devolución
- `return_request_items` — Líneas de devolución
- `refunds` — Reembolsos
- `support_ticket_messages` — Mensajes de tickets

### 2FA / Seguridad

- `two_factor_settings` — Configuración 2FA por usuario
- `two_factor_methods` — Métodos 2FA registrados
- `otp_challenges` — Retos OTP con hash
- `recovery_codes` — Códigos de recuperación
- `passkey_credentials` — Credenciales WebAuthn (futuro)

## Inventario compartido

Las tablas `inventories` y `warehouses` no tienen `store_id`.
El inventario es global porque ambas tiendas venden del
mismo almacén.

```txt
Producto: "Camiseta Anime"
├── Almacén: Principal
├── Stock total: 100 unidades
├── Panda vende: 40 unidades
└── Kitsune vende: 60 unidades
    (stock se reduce del total global)
```

Si en el futuro se necesita inventario por tienda, se debe
crear una relación `warehouse_store` o modificar la estructura
de `inventories`.
