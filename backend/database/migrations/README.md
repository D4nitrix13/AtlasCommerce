# Migraciones de AtlasCommerce

Este directorio contiene las migraciones de base de datos del store definido
en `APP_NAME`.

La estructura separa las migraciones por responsabilidad para evitar
archivos demasiado grandes, facilitar el mantenimiento del esquema y
permitir ubicar rápidamente cada parte de la base de datos.

Cada carpeta representa un área principal del sistema:

- autenticación, usuarios, roles, permisos y seguridad 2FA;
- clientes y direcciones;
- catálogo de productos;
- inventario;
- compras a proveedores;
- carritos y listas de deseos;
- ventas, pedidos y facturación;
- pagos, abonos, cuotas y transacciones;
- envíos y seguimiento;
- promociones y cupones;
- impuestos;
- devoluciones y reembolsos;
- soporte;
- auditoría, notificaciones y configuración del sistema.

## Convención de nombres

El proyecto sigue una convención compatible con Laravel:

```text
Entidades / modelos: inglés, singular y PascalCase
Controladores: inglés, PascalCase y sufijo Controller
Controladores API: dentro de App\Http\Controllers\Api
Tablas: inglés, plural y snake_case
Columnas: inglés y snake_case
Llaves foráneas: nombre_del_modelo_id cuando sea posible
```

Ejemplos:

```text
Model: User
Table: users
Controller: UserController

Model: TwoFactorSetting
Table: two_factor_settings
Controller: TwoFactorAuthController
```

Los nombres nuevos del proyecto deben mantenerse preferiblemente en inglés.
Las tablas antiguas o documentación previa pueden estar en español, pero
el código nuevo debe favorecer nombres consistentes con Laravel.

Si una tabla existente usa un nombre diferente, por ejemplo `usuario` con
llave primaria `id_usuario`, las nuevas migraciones deben respetar esa
tabla real al crear llaves foráneas. En este proyecto la tabla principal
de usuarios es:

```text
users
```

y su llave primaria es:

```text
id
```

Por eso las tablas relacionadas deben usar:

```text
user_id
```

## Estructura general actual

```text
database/migrations/
├── 0001_01_01_000001_create_cache_table.php
├── 0001_01_01_000002_create_jobs_table.php
├── 2026_06_25_230734_create_personal_access_tokens_table.php
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

Las migraciones raíz se consideran infraestructura general de Laravel:

```text
cache
jobs
personal_access_tokens
```

La migración `personal_access_tokens` pertenece a Laravel Sanctum y puede
quedarse en la raíz porque es una tabla técnica para tokens API, no una
entidad propia del store.

## Carga de migraciones en subcarpetas

Laravel carga automáticamente las migraciones ubicadas directamente en
`database/migrations`.

Como este proyecto organiza migraciones por módulos en subcarpetas, el
`AppServiceProvider` debe cargar las carpetas de primer nivel dentro de
`database/migrations`.

Ejemplo:

```php
foreach (
    glob(
        pattern: database_path(path: "migrations/*"),
        flags: GLOB_ONLYDIR
    ) ?: [] as $directory
) {
    $this->loadMigrationsFrom(paths: $directory);
}
```

Esta configuración carga carpetas como:

```text
database/migrations/auth
database/migrations/catalog
database/migrations/sales
```

No se recomienda crear carpetas anidadas por tabla, por ejemplo:

```text
database/migrations/auth/two_factor_settings
database/migrations/auth/otp_challenges
```

La regla recomendada es:

```text
una carpeta por módulo
una migración por tabla
```

## Nota sobre la tabla `users`

No deben existir dos migraciones creando la misma tabla `users`.

En este proyecto la migración de usuarios está dentro del módulo `auth/`:

```text
database/migrations/auth/2026_06_22_032005_create_users_table.php
```

Por eso no debe existir otra migración en la raíz creando también:

```text
users
```

Ejemplo de conflicto:

```text
database/migrations/0001_01_01_000000_create_users_table.php
database/migrations/auth/2026_06_22_032005_create_users_table.php
```

Eso puede generar un error como:

```text
SQLSTATE[42P07]: Duplicate table: relation "users" already exists
```

La estrategia actual del proyecto es mantener `users` dentro del módulo `auth/`.

## Infraestructura de Laravel

Las migraciones de infraestructura pueden permanecer en la raíz.

Tablas principales:

```text
cache
cache_locks
jobs
job_batches
failed_jobs
personal_access_tokens
```

La tabla `personal_access_tokens` es utilizada por Laravel Sanctum para
autenticación API mediante tokens Bearer.

Ejemplo de uso:

```http
Authorization: Bearer TOKEN
```

No se recomienda duplicar esta tabla ni moverla si ya fue creada y migrada correctamente.

## Módulo `auth`

Contiene las migraciones relacionadas con autenticación, usuarios, roles,
permisos y seguridad de acceso.

Tablas principales:

```text
roles
permissions
permission_role
users
password_reset_tokens
sessions
two_factor_settings
two_factor_methods
otp_challenges
recovery_codes
passkey_credentials
```

Este módulo permite controlar qué usuarios existen en el sistema, qué rol
tiene cada usuario, qué permisos posee cada rol y qué métodos de
autenticación adicional tiene cada cuenta.

Ejemplos de roles:

```text
admin
seller
inventory_manager
support_agent
customer
```

## Seguridad 2FA gratuita y profesional

El sistema puede implementar autenticación multifactor sin depender de
proveedores de pago usando:

```text
Email OTP
TOTP
Passkeys / WebAuthn
Recovery Codes
```

No se incluyen SMS ni llamadas como métodos principales porque normalmente
requieren proveedores externos de pago.

La implementación actual usa una migración por tabla:

```text
database/migrations/auth/2026_06_25_231319_create_two_factor_settings_table.php
database/migrations/auth/2026_06_25_231320_create_two_factor_methods_table.php
database/migrations/auth/2026_06_25_231320_create_otp_challenges_table.php
database/migrations/auth/2026_06_25_231320_create_recovery_codes_table.php
database/migrations/auth/2026_06_25_231321_create_passkey_credentials_table.php
```

No se recomienda volver a crear una migración gigante como:

```text
create_two_factor_auth_tables
```

Para este proyecto es más mantenible usar una migración separada por cada
tabla 2FA.

## Tabla `two_factor_settings`

Guarda la configuración general de 2FA por usuario.

Responsabilidades:

```text
- indicar si 2FA está habilitado;
- definir el método preferido;
- marcar si 2FA es obligatorio;
- registrar fecha de activación.
```

Campos principales:

```text
id
user_id
enabled
preferred_method
required
enabled_at
created_at
updated_at
```

Métodos permitidos:

```text
email_otp
totp
passkey
```

Relación principal:

```text
two_factor_settings.user_id -> users.id
```

## Tabla `two_factor_methods`

Guarda los métodos 2FA configurados por cada usuario.

Responsabilidades:

```text
- registrar si el usuario usa email OTP;
- registrar si el usuario usa TOTP;
- registrar si el usuario tiene passkeys activas;
- guardar el secreto cifrado para TOTP;
- saber si el método ya fue verificado.
```

Campos principales:

```text
id
user_id
method
display_name
destination
encrypted_secret
active
verified
verified_at
created_at
updated_at
```

Notas importantes:

```text
email_otp -> destination guarda el correo.
totp      -> encrypted_secret guarda el secreto cifrado.
passkey   -> la credencial real se guarda en passkey_credentials.
```

El secreto TOTP no debe guardarse en texto plano. En Laravel se recomienda usar:

```php
Crypt::encryptString($secret);
```

y para leerlo:

```php
Crypt::decryptString($encryptedSecret);
```

Relación principal:

```text
two_factor_methods.user_id -> users.id
```

## Tabla `otp_challenges`

Guarda los códigos temporales enviados por correo.

Responsabilidades:

```text
- generar PIN temporal para login 2FA;
- controlar expiración del código;
- limitar intentos fallidos;
- marcar un código como usado;
- registrar IP y user agent de la solicitud.
```

Campos principales:

```text
id
user_id
method
purpose
code_hash
destination
attempts
max_attempts
used
expires_at
used_at
request_ip
user_agent
created_at
updated_at
```

Métodos permitidos:

```text
email_otp
```

Propósitos permitidos:

```text
login_2fa
enable_2fa
change_2fa
```

El PIN real nunca debe guardarse directamente. Debe guardarse con hash.

Ejemplo:

```php
$codeHash = Hash::make($code);
```

Validación:

```php
Hash::check($submittedCode, $challenge->code_hash);
```

Relación principal:

```text
otp_challenges.user_id -> users.id
```

## Tabla `recovery_codes`

Guarda códigos de recuperación para usuarios que pierden acceso a su segundo factor.

Responsabilidades:

```text
- permitir recuperación segura de cuenta;
- guardar códigos de un solo uso;
- marcar cada código como usado;
- registrar cuándo se utilizó.
```

Campos principales:

```text
id
user_id
code_hash
used
used_at
created_at
updated_at
```

Cada código debe guardarse con hash, igual que un OTP.

Ejemplo de código visible para el usuario:

```text
<PREFIX>-82KJ-19QD
```

Relación principal:

```text
recovery_codes.user_id -> users.id
```

## Tabla `passkey_credentials`

Guarda credenciales públicas WebAuthn / Passkeys.

Responsabilidades:

```text
- permitir login con biometría, PIN del dispositivo o llave física;
- guardar la credencial pública;
- registrar el contador de firmas;
- identificar el dispositivo registrado;
- desactivar credenciales antiguas o perdidas.
```

Campos principales:

```text
id
user_id
credential_id
public_key
sign_count
transports
aaguid
device_name
last_used_at
active
created_at
updated_at
```

Las passkeys no guardan contraseñas. Guardan una llave pública asociada a
una credencial del dispositivo.

Relación principal:

```text
passkey_credentials.user_id -> users.id
```

## Modelos recomendados para autenticación

```text
App\Models\Auth\User
App\Models\Auth\TwoFactorSetting
App\Models\Auth\TwoFactorMethod
App\Models\Auth\OtpChallenge
App\Models\Auth\RecoveryCode
App\Models\Auth\PasskeyCredential
```

Comandos recomendados:

```bash
php artisan make:model Auth/TwoFactorSetting
php artisan make:model Auth/TwoFactorMethod
php artisan make:model Auth/OtpChallenge
php artisan make:model Auth/RecoveryCode
php artisan make:model Auth/PasskeyCredential
```

Con Docker:

```bash
docker compose exec app php artisan make:model Auth/TwoFactorSetting
docker compose exec app php artisan make:model Auth/TwoFactorMethod
docker compose exec app php artisan make:model Auth/OtpChallenge
docker compose exec app php artisan make:model Auth/RecoveryCode
docker compose exec app php artisan make:model Auth/PasskeyCredential
```

## Controladores recomendados para API Auth

Los controladores API deben estar dentro de:

```text
app/Http/Controllers/Api/Auth
```

No usar:

```text
app/Http/Controllers/Auth
```

excepto si el proyecto usara Blade o autenticación web tradicional.

Controladores recomendados:

```text
App\Http\Controllers\Api\Auth\AuthController
App\Http\Controllers\Api\Auth\TwoFactorAuthController
App\Http\Controllers\Api\Auth\TotpController
App\Http\Controllers\Api\Auth\PasskeyController
App\Http\Controllers\Api\Auth\RecoveryCodeController
```

Comandos recomendados:

```bash
php artisan make:controller Api/Auth/AuthController
php artisan make:controller Api/Auth/TwoFactorAuthController
php artisan make:controller Api/Auth/TotpController
php artisan make:controller Api/Auth/PasskeyController
php artisan make:controller Api/Auth/RecoveryCodeController
```

Con Docker:

```bash
docker compose exec app php artisan make:controller Api/Auth/AuthController
docker compose exec app php artisan make:controller Api/Auth/TwoFactorAuthController
docker compose exec app php artisan make:controller Api/Auth/TotpController
docker compose exec app php artisan make:controller Api/Auth/PasskeyController
docker compose exec app php artisan make:controller Api/Auth/RecoveryCodeController
```

## Paquetes recomendados para autenticación

Para tokens API:

```bash
composer require laravel/sanctum
```

Para TOTP y generación de QR:

```bash
composer require pragmarx/google2fa-laravel bacon/bacon-qr-code
```

Para Passkeys / WebAuthn en Laravel moderno:

```bash
composer require laravel/passkeys
```

Cliente JavaScript recomendado para el frontend:

```bash
pnpm add @laravel/passkeys
```

Con Docker:

```bash
docker compose exec app composer require laravel/sanctum
docker compose exec app composer require pragmarx/google2fa-laravel bacon/bacon-qr-code
docker compose exec app composer require laravel/passkeys
```

## Módulo `customers`

Contiene la información comercial de los clientes.

Tablas principales:

```text
customers
customer_addresses
```

La tabla `customers` representa al comprador dentro del store. La tabla
`customer_addresses` permite guardar varias direcciones por cliente, por
ejemplo dirección de envío y dirección de facturación.

## Módulo `catalog`

Contiene el catálogo de productos del store.

Tablas principales:

```text
categories
brands
attributes
products
product_images
product_variants
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

Las tablas pivote `attribute_product` y `attribute_variant` permiten
asignar características como color, talla, material, capacidad, peso u
otros atributos.

## Módulo `inventory`

Contiene el control de stock y almacenes.

Tablas principales:

```text
warehouses
inventories
inventory_movements
```

Este módulo permite conocer el stock actual, stock mínimo y stock reservado
de cada producto o variante. También registra entradas, salidas, ajustes,
pérdidas y devoluciones mediante movimientos de inventario.

## Módulo `purchases`

Contiene el módulo de compras a proveedores.

Tablas principales:

```text
suppliers
purchases
purchase_items
```

Sirve para registrar proveedores, compras realizadas y productos comprados.
Este módulo alimenta el inventario y permite controlar costos de
adquisición.

## Módulo `carts`

Contiene el carrito de compra y listas de deseos.

Tablas principales:

```text
carts
cart_items
wishlists
wishlist_items
```

El carrito representa productos que el cliente desea comprar. La wishlist
permite guardar productos favoritos para comprarlos después.

## Módulo `sales`

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

Esto permite que un pedido tenga estados como pendiente, confirmado,
preparado, enviado o entregado, mientras que la factura puede tener
estados de pago como pendiente, pagada parcialmente, pagada, anulada o
reembolsada.

## Módulo `payments`

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

La tabla `invoice_payments` relaciona pagos con facturas. La tabla
`payment_installments` permite dividir una factura en cuotas. La tabla
`payment_transactions` guarda información técnica de pasarelas de pago.

## Módulo `shipping`

Contiene empresas de envío, zonas, envíos y seguimiento.

Tablas principales:

```text
shipping_companies
shipping_zones
shipments
shipment_trackings
```

Este módulo permite registrar el envío asociado a un pedido, su costo,
estado, código de seguimiento y eventos de rastreo.

## Módulo `marketing`

Contiene promociones y cupones.

Tablas principales:

```text
coupons
promotions
coupon_usages
product_promotion
```

Permite crear descuentos por código, promociones por producto y registrar
qué cliente usó cada cupón.

## Módulo `taxes`

Contiene impuestos aplicables a productos.

Tablas principales:

```text
taxes
product_tax
```

Este módulo permite configurar impuestos como IVA u otros cargos
aplicables según el producto.

## Módulo `returns`

Contiene devoluciones y reembolsos.

Tablas principales:

```text
return_requests
return_request_items
refunds
```

Permite gestionar solicitudes de devolución, productos devueltos y
reembolsos al cliente.

## Módulo `support`

Contiene reseñas, preguntas y tickets de soporte.

Tablas principales:

```text
product_reviews
product_questions
support_tickets
support_ticket_messages
```

Este módulo ayuda a manejar la atención al cliente, preguntas sobre
productos y opiniones de compradores.

## Módulo `system`

Contiene auditoría y notificaciones internas.

Tablas principales:

```text
notifications
audits
```

La tabla `audits` registra acciones importantes realizadas por los usuarios,
por ejemplo creación de productos, cambios en facturas, anulación de pagos,
cambios de inventario o cambios de configuración 2FA.

Ejemplos de acciones relacionadas con 2FA:

```text
TWO_FACTOR_ENABLED
TWO_FACTOR_DISABLED
TWO_FACTOR_METHOD_ADDED
TWO_FACTOR_METHOD_REMOVED
OTP_CHALLENGE_CREATED
OTP_CHALLENGE_VERIFIED
OTP_CHALLENGE_FAILED
RECOVERY_CODE_USED
PASSKEY_REGISTERED
PASSKEY_DISABLED
```

## Módulo `settings`

Contiene configuración general de tienda y moneda.

Tablas principales:

```text
store_settings
currencies
exchange_rates
```

Permite manejar configuración global como nombre de tienda, logo, correo,
teléfono, moneda principal y tasas de cambio.

## Orden recomendado de migración

El orden importa porque varias tablas dependen de otras mediante llaves foráneas.

Orden lógico recomendado:

```text
1. Infraestructura raíz de Laravel
2. auth
3. customers
4. catalog
5. inventory
6. purchases
7. carts
8. sales
9. payments
10. shipping
11. marketing
12. taxes
13. returns
14. support
15. system
16. settings
```

La migración de 2FA debe ejecutarse después de que exista la tabla `users`.

Dentro del módulo `auth`, el orden lógico es:

```text
1. roles
2. permissions
3. users
4. permission_role
5. two_factor_settings
6. two_factor_methods
7. otp_challenges
8. recovery_codes
9. passkey_credentials
```

## Ejecución de migraciones

Si `AppServiceProvider` carga automáticamente las subcarpetas de
`database/migrations`, se puede ejecutar:

```bash
php artisan migrate
```

Con Docker:

```bash
docker compose exec app php artisan migrate
```

También se pueden ejecutar migraciones por módulo usando `--path`.

Ejemplo local:

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

Ejemplo con Docker:

```bash
docker compose exec app php artisan migrate --path=database/migrations/auth
docker compose exec app php artisan migrate --path=database/migrations/customers
docker compose exec app php artisan migrate --path=database/migrations/catalog
docker compose exec app php artisan migrate --path=database/migrations/inventory
docker compose exec app php artisan migrate --path=database/migrations/purchases
docker compose exec app php artisan migrate --path=database/migrations/carts
docker compose exec app php artisan migrate --path=database/migrations/sales
docker compose exec app php artisan migrate --path=database/migrations/payments
docker compose exec app php artisan migrate --path=database/migrations/shipping
docker compose exec app php artisan migrate --path=database/migrations/marketing
docker compose exec app php artisan migrate --path=database/migrations/taxes
docker compose exec app php artisan migrate --path=database/migrations/returns
docker compose exec app php artisan migrate --path=database/migrations/support
docker compose exec app php artisan migrate --path=database/migrations/system
docker compose exec app php artisan migrate --path=database/migrations/settings
```

Para ejecutar solo las migraciones raíz de infraestructura:

```bash
php artisan migrate --path=database/migrations/0001_01_01_000001_create_cache_table.php
php artisan migrate --path=database/migrations/0001_01_01_000002_create_jobs_table.php
php artisan migrate --path=database/migrations/2026_06_25_230734_create_personal_access_tokens_table.php
```

Con Docker:

```bash
docker compose exec app php artisan migrate --path=database/migrations/0001_01_01_000001_create_cache_table.php
docker compose exec app php artisan migrate --path=database/migrations/0001_01_01_000002_create_jobs_table.php
docker compose exec app php artisan migrate --path=database/migrations/2026_06_25_230734_create_personal_access_tokens_table.php
```

## Comandos de revisión

Para verificar los archivos existentes:

```bash
find database/migrations -type f | sort
```

Para detectar nombres de archivo duplicados:

```bash
find database/migrations -type f -name "*.php" -printf "%f\n" | sort | uniq -d
```

Para detectar migraciones duplicadas creando la misma tabla:

```bash
find database/migrations -type f -name "*create_*_table.php" -printf "%f\n" \
  | sed -E 's/^[0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_create_(.*)_table\.php$/\1/' \
  | sort \
  | uniq -d
```

Para ver el estado de las migraciones:

```bash
php artisan migrate:status
```

Con Docker:

```bash
docker compose exec app php artisan migrate:status
```

Para listar tablas en PostgreSQL:

```bash
psql -U postgres -d store -c "\dt"
```

## Reiniciar base de datos en desarrollo

Durante desarrollo se puede reiniciar la base de datos con:

```bash
php artisan migrate:fresh
```

Con Docker:

```bash
docker compose exec app php artisan migrate:fresh
```

Si hay tablas parciales creadas por un fallo previo, se pueden eliminar
manualmente antes de volver a ejecutar las migraciones.

Ejemplo para limpiar tablas 2FA:

```sql
DROP TABLE IF EXISTS passkey_credentials CASCADE;
DROP TABLE IF EXISTS recovery_codes CASCADE;
DROP TABLE IF EXISTS otp_challenges CASCADE;
DROP TABLE IF EXISTS two_factor_methods CASCADE;
DROP TABLE IF EXISTS two_factor_settings CASCADE;
```

## Recomendaciones de mantenimiento

1. No crear dos migraciones para la misma tabla.
2. Mantener una carpeta por módulo y una migración por tabla.
3. No crear carpetas anidadas por entidad dentro de los módulos.
4. Mantener nombres de tablas en inglés y plural.
5. Mantener modelos, entidades y controladores en inglés.
6. Mantener campos en `snake_case`.
7. Crear primero las tablas base y después las tablas dependientes.
8. Usar `foreignId()` para llaves foráneas cuando el tipo coincida con la
   tabla relacionada.
9. Si la tabla relacionada usa una PK personalizada, definir explícitamente la referencia.
10. Usar `timestamps()` en tablas operativas.
11. Usar `softDeletes()` en tablas donde no conviene borrar registros definitivamente.
12. No borrar migraciones ya ejecutadas en producción.
13. Crear una nueva migración para cada cambio futuro.
14. Documentar cada módulo cuando se agreguen nuevas tablas.
15. No guardar códigos OTP, recovery codes ni secretos TOTP en texto plano.
16. Registrar en auditoría los cambios importantes de seguridad.
17. No duplicar tablas generadas por paquetes como Sanctum o Passkeys.
18. Mantener `personal_access_tokens` como tabla técnica de Sanctum.

## Convención de nombres usada

Ejemplos:

```text
roles
permissions
users
customers
customer_addresses
categories
brands
attributes
products
product_images
product_variants
orders
invoices
payments
shipments
return_requests
support_tickets
audits
store_settings
two_factor_settings
two_factor_methods
otp_challenges
recovery_codes
passkey_credentials
personal_access_tokens
```

Esta convención sigue un estilo compatible con Laravel y facilita la
creación de modelos Eloquent.

## Relación con el frontend

Este backend está pensado para trabajar como API con un frontend separado.

Stack esperado:

```text
Backend: Laravel API
Frontend: Astro + TypeScript + Tailwind
Database: PostgreSQL
```

El panel administrativo puede consumir estas tablas mediante endpoints API
organizados por módulos:

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

En el frontend, el prefijo `/api` debe venir desde:

```text
PUBLIC_API_URL
```

Ejemplo:

```env
PUBLIC_API_URL=http://localhost:9000/api
```

Los endpoints internos de `src/lib/endpoints.ts` no deben incluir `/api`.

Correcto:

```text
/auth/login
/products
/orders
```

Incorrecto:

```text
/api/auth/login
/api/products
/api/orders
```

## Endpoints recomendados para autenticación

```text
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
```

## Endpoints recomendados para seguridad 2FA

```text
GET    /api/auth/2fa
POST   /api/auth/2fa/email/send
POST   /api/auth/2fa/email/verify
POST   /api/auth/2fa/totp/setup
POST   /api/auth/2fa/totp/verify
POST   /api/auth/2fa/passkeys/register/options
POST   /api/auth/2fa/passkeys/register
GET    /api/auth/2fa/recovery-codes
POST   /api/auth/2fa/recovery-codes/regenerate
POST   /api/auth/2fa/disable
```

## Estado actual del esquema

La estructura actual representa una base completa para un store profesional.

Estado esperado:

```text
- no hay duplicados de users;
- personal_access_tokens está en raíz como tabla técnica de Sanctum;
- las migraciones 2FA están separadas por tabla dentro de auth/;
- auth contiene usuarios, roles, permisos y seguridad 2FA;
- las demás áreas están separadas por módulos funcionales.
```

Antes de continuar con controladores y endpoints, se recomienda:

```text
1. Ejecutar php artisan migrate:status.
2. Confirmar que users.id existe.
3. Confirmar que personal_access_tokens existe si Sanctum está instalado.
4. Confirmar que las tablas 2FA migran correctamente.
5. Crear modelos Eloquent para las tablas 2FA.
6. Crear controladores API en App\Http\Controllers\Api\Auth.
7. Registrar rutas en routes/api.php.
```
