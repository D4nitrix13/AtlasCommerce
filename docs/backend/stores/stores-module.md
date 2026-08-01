# Módulo Stores — Multi-Tienda

## Propósito

El módulo `Stores` gestiona la configuración de cada
tienda (Panda Estampados, Kitsune) y las relaciones
entre tiendas y otros dominios del sistema.

## Estado actual

- `StoreSetting` model
  - Estado: Creado (vacío, sin implementación)
- `StoreSettingController`
  - Estado: Creado (vacío, sin implementación)
- Migración `store_settings`
  - Estado: Creada
- Migraciones multi-tienda
  - Estado: **Pendiente** — `store_domains`,
    `store_social_links`, `product_stores`,
    `user_stores`, `customer_stores`
- Models multi-tienda
  - Estado: **Pendiente** — `Store`, `StoreDomain`,
    `StoreSocialLink`, `ProductStore`, `UserStore`,
    `CustomerStore`
- `SalesChannel`
  - Estado: **Pendiente**

**El módulo `Stores` está preparado pero no
implementado.** La estructura base existe pero falta la
lógica completa.

## Entidades del módulo

### `Store` (futuro)

Representa una tienda/marca comercial.

```php
// app/Models/Stores/Store.php (futuro)

class Store extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo',
        'email',
        'phone',
        'whatsapp',
        'address',
        'description',
        'primary_color',
        'secondary_color',
        'maintenance',
        'active',
    ];
}
```

- `id` — PK — Identificador único
- `currency_id` — FK — Moneda principal de la tienda
- `name` — string — Nombre de la tienda
- `slug` — string — Slug único (panda-estampados,
  kitsune)
- `logo` — string — URL del logo
- `email` — string — Correo de contacto
- `phone` — string — Teléfono
- `whatsapp` — string — Número de WhatsApp
- `address` — string — Dirección
- `description` — text — Descripción de la tienda
- `primary_color` — string — Color primario (hex)
- `secondary_color` — string — Color secundario (hex)
- `maintenance` — boolean — Modo mantenimiento
- `active` — boolean — Tienda activa

### `StoreDomain` (futuro)

Dominios asociados a una tienda.

```php
class StoreDomain extends Model
{
    protected $fillable = [
        'store_id',
        'domain',
        'primary',
        'active',
    ];
}
```

- `store_id` — FK — Tienda asociada
- `domain` — string — Dominio
  (pandas-estampados.com)
- `primary` — boolean — Si es el dominio principal
- `active` — boolean — Si el dominio está activo

### `StoreSocialLink` (futuro)

Redes sociales por tienda.

```php
class StoreSocialLink extends Model
{
    protected $fillable = [
        'store_id',
        'platform',
        'url',
        'active',
    ];
}
```

### `ProductStore` (futuro)

Relación producto-tienda para publicar productos.

```php
class ProductStore extends Model
{
    protected $fillable = [
        'product_id',
        'store_id',
        'slug',
        'public_price',
        'public_description',
        'visible',
        'featured',
        'sort_order',
        'published_at',
    ];
}
```

### `UserStore` (futuro)

Relación usuario-tienda para control de acceso.

```php
class UserStore extends Model
{
    protected $fillable = [
        'user_id',
        'store_id',
        'active',
    ];
}
```

### `CustomerStore` (futuro)

Relación cliente-tienda para clasificación.

```php
class CustomerStore extends Model
{
    protected $fillable = [
        'customer_id',
        'store_id',
        'customer_type',
        'active',
    ];
}
```

### `SalesChannel` (futuro)

Canales de venta: Web, WhatsApp, Instagram, Admin,
tienda física.

```php
class SalesChannel extends Model
{
    protected $fillable = [
        'name',
        'description',
        'active',
    ];
}
```

## Migraciones necesarias

Las siguientes migraciones deben crearse en
`database/migrations/stores/`:

```txt
create_stores_table
create_store_domains_table
create_store_social_links_table
create_product_stores_table
create_user_stores_table
create_customer_stores_table
create_sales_channels_table
```

## Relación con otros módulos

- **Catalog**
  - Relación: `product_stores`
  - Descripción: Qué productos se publican en cada
    tienda
- **Sales**
  - Relación: `orders.store_id`
  - Descripción: Pedidos por tienda
- **Sales**
  - Relación: `invoices.store_id`
  - Descripción: Facturas por tienda
- **Marketing**
  - Relación: `coupons.store_id`
  - Descripción: Cupones por tienda
- **Marketing**
  - Relación: `promotions.store_id`
  - Descripción: Promociones por tienda
- **Support**
  - Relación: `support_tickets.store_id`
  - Descripción: Tickets por tienda
- **Support**
  - Relación: `product_reviews.store_id`
  - Descripción: Reseñas por tienda
- **System**
  - Relación: `audits.store_id`
  - Descripción: Auditorías por tienda
- **System**
  - Relación: `notifications.store_id`
  - Descripción: Notificaciones por tienda
- **Carts**
  - Relación: `carts.store_id`
  - Descripción: Carritos por tienda
- **Auth**
  - Relación: `user_stores`
  - Descripción: Usuarios admin por tienda
- **Customers**
  - Relación: `customer_stores`
  - Descripción: Clientes por tienda

## Controlador actual

```php
// app/Http/Controllers/Api/Stores/StoreSettingController.php

class StoreSettingController extends Controller
{
    // CRUD básico de configuración de tienda
}
```

**Nota:** El controlador y el modelo están creados pero
vacíos. La implementación completa está pendiente.

## Configuración de tienda

La tabla `store_settings` almacena configuración
clave-valor por tienda:

```php
// migrations/stores/2026_06_22_032054_create_store_settings_table.php
Schema::create('store_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->constrained();
    $table->string('key');
    $table->text('value')->nullable();
    $table->timestamps();
});
```

Ejemplo de configuraciones:

- `store_name` — "Kitsune Store" — Nombre de la tienda
- `store_email` — "<info@kitsune.com>" — Correo de
  contacto
- `currency` — "NIO" — Moneda
- `tax_rate` — "15" — Tasa de impuesto
- `whatsapp_number` — "+505 8888 1234" — Número de
  WhatsApp
