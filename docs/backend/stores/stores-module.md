# Módulo Stores — Multi-Tienda

## Propósito

El módulo `Stores` gestiona la configuración de cada
tienda (Panda Estampados, Kitsune) y las relaciones
entre tiendas y otros dominios del sistema.

## Estado del módulo

```txt
StoreSetting:
  - Migración: contemplada en el modelo ER
  - Modelo: creado como base estructural
  - Controlador: creado como base estructural
  - Lógica CRUD: pendiente

Store:
  - Migración: contemplada en el modelo ER
  - Modelo: creado como base estructural

StoreDomain:
  - Migración: contemplada en el modelo ER
  - Modelo: creado como base estructural

StoreSocialLink:
  - Migración: contemplada en el modelo ER
  - Modelo: creado como base estructural

ProductStore:
  - Migración: contemplada en el modelo ER
  - Modelo: creado como base estructural

UserStore:
  - Migración: contemplada en el modelo ER
  - Modelo: creado como base estructural
  - Role_id: contemplado en el modelo ER

CustomerStore:
  - Migración: contemplada en el modelo ER
  - Modelo: creado como base estructural

SalesChannel:
  - Migración: contemplada en el modelo ER
  - Modelo: creado como base estructural
```

La base estructural del módulo está creada. La lógica
completa de negocio, policies y validaciones debe
verificarse en el código.

## Entidades del módulo

### `Store`

Representa una tienda/marca comercial.

```php
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

### `StoreDomain`

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
  (panda-estampados.com)
- `primary` — boolean — Si es el dominio principal
- `active` — boolean — Si el dominio está activo

### `StoreSocialLink`

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

### `ProductStore`

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

### `UserStore`

Relación usuario-tienda con rol específico por tienda.

```php
class UserStore extends Model
{
    protected $fillable = [
        'user_id',
        'store_id',
        'role_id',
        'active',
        'assigned_at',
    ];
}
```

- `user_id` — FK — Usuario administrador
- `store_id` — FK — Tienda asociada
- `role_id` — FK — Rol del usuario en esta tienda
- `active` — boolean — Si la asignación está activa
- `assigned_at` — timestamp — Fecha de asignación

### `CustomerStore`

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

### `SalesChannel`

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

## Migraciones del módulo

Las siguientes migraciones están contempladas en
`database/migrations/stores/`:

```txt
create_stores_table
create_store_domains_table
create_store_social_links_table
create_product_stores_table
create_user_stores_table
create_customer_stores_table
create_sales_channels_table
create_store_settings_table
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
  - Descripción: Usuarios admin por tienda con rol
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

El controlador y el modelo están creados como base
estructural. La lógica completa de CRUD debe
verificarse en el código.

## Configuración de tienda

La tabla `store_settings` almacena configuración
clave-valor por tienda:

```php
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
