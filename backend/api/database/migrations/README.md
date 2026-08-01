# Database Migrations - Professional Ecommerce

Este directorio contiene las migraciones de base de datos del backend Laravel para un sistema ecommerce profesional. La estructura está organizada por módulos para que el proyecto sea más limpio, escalable y fácil de mantener.

## Objetivo

El objetivo de esta estructura es separar las tablas según el proceso de negocio al que pertenecen:

* autenticación y permisos;
* clientes y direcciones;
* catálogo de productos;
* inventario;
* compras y proveedores;
* carrito y listas de deseos;
* pedidos, facturación y pagos;
* envíos;
* promociones;
* impuestos;
* devoluciones;
* soporte;
* auditoría y configuración del sistema.

Esta organización ayuda a evitar una migración gigante difícil de mantener y permite ubicar rápidamente cada parte del esquema de base de datos.

## Estructura general

```text
migrations/
├── 0001_01_01_000000_create_users_table.php
├── 0001_01_01_000001_create_cache_table.php
├── 0001_01_01_000002_create_jobs_table.php
├── auth/
├── carts/
├── catalog/
├── customers/
├── inventory/
├── marketing/
├── payments/
├── purchases/
├── returns/
├── sales/
├── settings/
├── shipping/
├── support/
├── system/
└── taxes/
```

## Nota importante sobre la tabla `users`

Actualmente existen dos migraciones que pueden intentar crear la tabla `users`:

```text
0001_01_01_000000_create_users_table.php
auth/2026_06_22_032006_create_users_table.php
```

Esto puede generar un error como:

```text
SQLSTATE[42P07]: Duplicate table: relation "users" already exists
```

Antes de ejecutar las migraciones, se debe elegir una sola estrategia.

### Opción recomendada

Mantener la migración principal de Laravel:

```text
0001_01_01_000000_create_users_table.php
```

Y convertir la migración del módulo `auth` en una migración de modificación, por ejemplo:

```text
auth/2026_06_22_032006_add_ecommerce_fields_to_users_table.php
```

Esa migración debería agregar campos como:

```text
role_id
first_name
last_name
phone
is_active
```

### Opción alternativa

Eliminar o no usar la migración default de Laravel para `users` y dejar solo la migración personalizada dentro de `auth/`.

No se recomienda tener dos migraciones creando la misma tabla.

## Módulos

### `auth/`

Contiene las migraciones relacionadas con autenticación, usuarios, roles y permisos.

Tablas principales:

```text
roles
permissions
permission_role
users
```

Este módulo permite controlar qué usuarios existen en el sistema y qué acciones puede realizar cada rol.

Ejemplos de roles:

```text
admin
seller
inventory_manager
support_agent
customer
```

---

### `customers/`

Contiene la información comercial de los clientes.

Tablas principales:

```text
customers
customer_addresses
```

La tabla `customers` representa al comprador dentro del ecommerce. La tabla `customer_addresses` permite guardar varias direcciones por cliente, por ejemplo dirección de envío y dirección de facturación.

---

### `catalog/`

Contiene el catálogo de productos del ecommerce.

Tablas principales:

```text
categories
brands
products
product_images
product_variants
attributes
attribute_product
attribute_variant
```

Este módulo permite manejar productos simples y productos con variantes.

Ejemplo:

```text
Product: Oversize T-Shirt
Variants:
- Black / S
- Black / M
- White / S
- White / M
```

Las tablas pivote `attribute_product` y `attribute_variant` permiten asignar características como color, talla, material, capacidad, peso u otros atributos.

---

### `inventory/`

Contiene el control de stock y almacenes.

Tablas principales:

```text
warehouses
inventories
inventory_movements
```

Este módulo permite conocer el stock actual, stock mínimo y stock reservado de cada producto o variante. También registra entradas, salidas, ajustes, pérdidas y devoluciones mediante movimientos de inventario.

---

### `purchases/`

Contiene el módulo de compras a proveedores.

Tablas principales:

```text
suppliers
purchases
purchase_items
```

Sirve para registrar proveedores, compras realizadas y productos comprados. Este módulo alimenta el inventario y permite controlar costos de adquisición.

---

### `carts/`

Contiene el carrito de compra y listas de deseos.

Tablas principales:

```text
carts
cart_items
wishlists
wishlist_items
```

El carrito representa productos que el cliente desea comprar. La wishlist permite guardar productos favoritos para comprarlos después.

---

### `sales/`

Contiene pedidos, facturas e historial comercial.

Tablas principales:

```text
orders
order_items
order_status_histories
invoices
invoice_items
invoice_status_histories
```

Este módulo separa correctamente los conceptos de pedido y factura:

```text
order   = intención de compra o venta confirmada
invoice = documento de cobro o comprobante de venta
```

Esto permite que un pedido tenga estados como pendiente, confirmado, preparado, enviado o entregado, mientras que la factura puede tener estados de pago como pendiente, pagada parcialmente, pagada, anulada o reembolsada.

---

### `payments/`

Contiene métodos de pago, pagos, abonos, cuotas y transacciones.

Tablas principales:

```text
payment_methods
payments
invoice_payments
payment_installments
payment_transactions
```

Este módulo es clave para manejar pagos parciales.

Ejemplo:

```text
Invoice #00045
Total: C$ 5,000
Paid: C$ 3,000
Pending balance: C$ 2,000

Payments:
1. C$ 1,500 - Cash
2. C$ 1,500 - Bank transfer
```

La tabla `invoice_payments` relaciona pagos con facturas. La tabla `payment_installments` permite dividir una factura en cuotas. La tabla `payment_transactions` guarda información técnica de pasarelas de pago.

---

### `shipping/`

Contiene empresas de envío, zonas, envíos y seguimiento.

Tablas principales:

```text
shipping_companies
shipping_zones
shipments
shipment_trackings
```

Este módulo permite registrar el envío asociado a un pedido, su costo, estado, código de seguimiento y eventos de rastreo.

---

### `marketing/`

Contiene promociones y cupones.

Tablas principales:

```text
coupons
coupon_usages
promotions
product_promotion
```

Permite crear descuentos por código, promociones por producto y registrar qué cliente usó cada cupón.

---

### `taxes/`

Contiene impuestos aplicables a productos.

Tablas principales:

```text
taxes
product_tax
```

Este módulo permite configurar impuestos como IVA u otros cargos aplicables según el producto.

---

### `returns/`

Contiene devoluciones y reembolsos.

Tablas principales:

```text
return_requests
return_request_items
refunds
```

Permite gestionar solicitudes de devolución, productos devueltos y reembolsos al cliente.

---

### `support/`

Contiene reseñas, preguntas y tickets de soporte.

Tablas principales:

```text
product_reviews
product_questions
support_tickets
support_ticket_messages
```

Este módulo ayuda a manejar la atención al cliente, preguntas sobre productos y opiniones de compradores.

---

### `system/`

Contiene auditoría y notificaciones internas.

Tablas principales:

```text
notifications
audits
```

La tabla `audits` registra acciones importantes realizadas por los usuarios, por ejemplo creación de productos, cambios en facturas, anulación de pagos o cambios de inventario.

---

### `settings/`

Contiene configuración general de tienda y moneda.

Tablas principales:

```text
store_settings
currencies
exchange_rates
```

Permite manejar configuración global como nombre de tienda, logo, correo, teléfono, moneda principal y tasas de cambio.

## Orden recomendado de migración

El orden importa porque varias tablas dependen de otras mediante llaves foráneas.

Orden lógico recomendado:

```text
1. auth
2. customers
3. catalog
4. inventory
5. purchases
6. carts
7. sales
8. payments
9. shipping
10. marketing
11. taxes
12. returns
13. support
14. system
15. settings
```

## Ejecución de migraciones

Si las migraciones se mantienen dentro de subcarpetas, se recomienda ejecutarlas por módulo usando `--path`.

Ejemplo:

```bash
php artisan migrate --path=database/migrations/auth
php artisan migrate --path=database/migrations/customers
php artisan migrate --path=database/migrations/catalog
php artisan migrate --path=database/migrations/inventory
php artisan migrate --path=database/migrations/purchases
php artisan migrate --path=database/migrations/carts
php artisan migrate --path=database/migrations/sales
php artisan migrate --path=database/migrations/payments
php artisan migrate --path=database/migrations/shipping
php artisan migrate --path=database/migrations/marketing
php artisan migrate --path=database/migrations/taxes
php artisan migrate --path=database/migrations/returns
php artisan migrate --path=database/migrations/support
php artisan migrate --path=database/migrations/system
php artisan migrate --path=database/migrations/settings
```

Si también se necesitan las migraciones base de Laravel de la raíz, se pueden ejecutar antes:

```bash
php artisan migrate --path=database/migrations/0001_01_01_000001_create_cache_table.php
php artisan migrate --path=database/migrations/0001_01_01_000002_create_jobs_table.php
```

La migración default de `users` solo debe ejecutarse si no existe otra migración creando la tabla `users`.

## Comando de revisión

Para verificar los archivos existentes:

```bash
find database/migrations -type f | sort
```

Para ver el estado de las migraciones:

```bash
php artisan migrate:status
```

## Reiniciar base de datos en desarrollo

Durante desarrollo se puede reiniciar la base de datos con:

```bash
php artisan migrate:fresh
```

Si se usan subcarpetas, puede ser necesario volver a ejecutar las migraciones por módulo usando `--path`.

## Recomendaciones de mantenimiento

1. No crear dos migraciones para la misma tabla.
2. Mantener nombres de tablas en inglés y plural.
3. Mantener campos en `snake_case`.
4. Crear primero las tablas base y después las tablas dependientes.
5. Usar `foreignId()` para llaves foráneas.
6. Usar `timestamps()` en tablas operativas.
7. Usar `softDeletes()` en tablas donde no conviene borrar registros definitivamente.
8. No borrar migraciones ya ejecutadas en producción.
9. Crear una nueva migración para cada cambio futuro.
10. Documentar cada módulo cuando se agreguen nuevas tablas.

## Convención de nombres usada

Ejemplos:

```text
roles
permissions
users
customers
customer_addresses
products
product_variants
orders
invoices
payments
shipments
return_requests
support_tickets
audits
store_settings
```

Esta convención sigue un estilo compatible con Laravel y facilita la creación de modelos Eloquent.

## Relación con el frontend

Este backend está pensado para trabajar como API con un frontend separado, por ejemplo:

```text
Backend: Laravel API
Frontend: Astro
Database: PostgreSQL
```

El panel administrativo puede consumir estas tablas mediante endpoints API organizados por módulos:

```text
/api/customers
/api/products
/api/inventory
/api/orders
/api/invoices
/api/payments
/api/shipments
/api/reports
/api/settings
```

## Estado actual

La estructura actual ya representa una base completa para un ecommerce profesional. Antes de continuar, se recomienda resolver el posible duplicado de la tabla `users` y luego completar el contenido interno de cada migración con sus columnas, índices y relaciones correspondientes.
