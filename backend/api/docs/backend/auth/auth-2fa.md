# Configuración de Autenticación y 2FA

Este documento resume la configuración de autenticación API y seguridad 2FA del ecommerce definido en `APP_NAME`.

El objetivo es preparar una base profesional de autenticación para Laravel API usando:

```text
Laravel Sanctum
Email OTP
TOTP con QR
Recovery Codes
Passkeys / WebAuthn
```

El backend usa Laravel API con PostgreSQL. El frontend Astro consumirá los endpoints mediante tokens Bearer.

## Instalación de Sanctum

Sanctum se usa para autenticar la API mediante tokens personales enviados en el header HTTP:

```http
Authorization: Bearer TOKEN
```

Comando ejecutado:

```bash
composer require laravel/sanctum
```

Instalación de la capa API de Laravel:

```bash
php artisan install:api
```

Este comando instala la configuración base para API y Sanctum.

Luego se ejecutaron las migraciones:

```bash
php artisan migrate
```

La migración importante de Sanctum es:

```text
database/migrations/2026_06_25_230734_create_personal_access_tokens_table.php
```

Esta migración crea la tabla:

```text
personal_access_tokens
```

Esta tabla puede quedarse en la raíz de `database/migrations` porque es infraestructura técnica de Sanctum, no una entidad propia del ecommerce.

## Instalación de paquetes para 2FA

Para TOTP y generación de QR se recomendó:

```bash
composer require pragmarx/google2fa-laravel bacon/bacon-qr-code
```

Uso esperado:

```text
pragmarx/google2fa-laravel -> generar y validar códigos TOTP
bacon/bacon-qr-code        -> generar QR para apps autenticadoras
```

Para Passkeys / WebAuthn se recomendó:

```bash
composer require laravel/passkeys
```

Publicación de migraciones y configuración de Passkeys:

```bash
php artisan vendor:publish --tag=passkeys-migrations
php artisan vendor:publish --tag=passkeys-config
```

Importante: el proyecto decidió mantener una tabla propia llamada:

```text
passkey_credentials
```

Por eso no se debe duplicar la estrategia creando también una tabla:

```text
passkeys
```

Regla aplicada:

```text
No usar al mismo tiempo:
- passkeys
- passkey_credentials
```

La estrategia actual del proyecto es:

```text
passkey_credentials
```

## Verificación de extensiones PHP

Se revisaron extensiones necesarias para Laravel, PostgreSQL, QR y seguridad:

```bash
php -m | grep -Ei "pdo_pgsql|pgsql|gd|sodium|zip|intl"
```

Extensiones esperadas:

```text
pdo_pgsql
pgsql
gd
sodium
zip
intl
```

Si faltan extensiones en Arch Linux o CachyOS, se pueden instalar paquetes base:

```bash
sudo pacman -Syu php php-pgsql php-gd php-sodium composer
```

Luego se revisa `/etc/php/php.ini` y se activan las extensiones necesarias.

## Migraciones 2FA

Primero se intentó crear una migración grande para todas las tablas 2FA, pero se decidió mejorar la arquitectura.

La decisión final fue:

```text
una carpeta por módulo
una migración por tabla
```

Por eso las migraciones 2FA quedaron dentro de:

```text
database/migrations/auth/
```

Comandos usados para crear migraciones separadas:

```bash
php artisan make:migration create_two_factor_settings_table --path=database/migrations/auth
php artisan make:migration create_two_factor_methods_table --path=database/migrations/auth
php artisan make:migration create_otp_challenges_table --path=database/migrations/auth
php artisan make:migration create_recovery_codes_table --path=database/migrations/auth
php artisan make:migration create_passkey_credentials_table --path=database/migrations/auth
```

Tablas creadas:

```text
two_factor_settings
two_factor_methods
otp_challenges
recovery_codes
passkey_credentials
```

Esta estructura facilita el mantenimiento porque cada tabla tiene su propia migración y cada módulo mantiene sus responsabilidades separadas.

## Estrategia para Passkeys

Laravel Passkeys puede publicar sus propias migraciones. Sin embargo, en este proyecto se decidió mantener una tabla personalizada:

```text
passkey_credentials
```

Esto permite controlar mejor el diseño del ecommerce y mantener consistencia con el resto de tablas 2FA.

No se debe mantener una tabla adicional `passkeys` si el sistema ya usa `passkey_credentials`.

## Limpieza de tablas parciales

Cuando una migración falla, PostgreSQL puede dejar tablas parcialmente creadas.

Para limpiar tablas 2FA fallidas se puede entrar a PostgreSQL:

```bash
psql -U postgres -d ecommerce
```

Y ejecutar:

```sql
DROP TABLE IF EXISTS passkey_credentials CASCADE;
DROP TABLE IF EXISTS recovery_codes CASCADE;
DROP TABLE IF EXISTS otp_challenges CASCADE;
DROP TABLE IF EXISTS two_factor_methods CASCADE;
DROP TABLE IF EXISTS two_factor_settings CASCADE;
DROP TABLE IF EXISTS passkeys CASCADE;
```

Salir de `psql`:

```sql
\q
```

Luego se vuelve a ejecutar:

```bash
php artisan migrate
```

## Modelos 2FA

Comandos usados para crear modelos:

```bash
php artisan make:model Auth/TwoFactorSetting
php artisan make:model Auth/TwoFactorMethod
php artisan make:model Auth/OtpChallenge
php artisan make:model Auth/RecoveryCode
php artisan make:model Auth/PasskeyCredential
```

Modelos creados:

```text
app/Models/Auth/TwoFactorSetting.php
app/Models/Auth/TwoFactorMethod.php
app/Models/Auth/OtpChallenge.php
app/Models/Auth/RecoveryCode.php
app/Models/Auth/PasskeyCredential.php
```

El modelo `User` real del proyecto está en:

```text
app/Models/Auth/User.php
```

Por eso la configuración de autenticación debe apuntar a:

```php
App\Models\Auth\User
```

## Configuración del modelo User

Se revisó `config/auth.php`.

Debe apuntar al modelo real:

```php
'model' => App\Models\Auth\User::class,
```

Si se usa variable de entorno:

```env
AUTH_MODEL=App\Models\Auth\User
```

Luego se limpia la configuración:

```bash
php artisan config:clear
```

## Relaciones en el modelo User

El modelo `User` debe tener relaciones con las tablas 2FA:

```text
twoFactorSetting
twoFactorMethods
otpChallenges
recoveryCodes
passkeyCredentials
```

Estas relaciones permiten consultar desde el usuario su configuración de seguridad, métodos disponibles, retos OTP, códigos de recuperación y credenciales Passkey.

## Notificación Email OTP

Se creó una notificación para enviar códigos OTP por correo:

```bash
php artisan make:notification Auth/EmailOtpNotification
```

Archivo generado:

```text
app/Notifications/Auth/EmailOtpNotification.php
```

Esta notificación se usa para enviar un código de seguridad al correo del usuario.

Canal usado:

```php
return ['mail'];
```

El correo se captura localmente con Mailpit durante desarrollo.

## Configuración local de correo

Variables recomendadas en `.env` para Mailpit:

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="no-reply@store.com""
MAIL_FROM_NAME="${APP_NAME}"
```

Panel web de Mailpit:

```text
http://127.0.0.1:8025
```

Después de cambiar `.env`, limpiar configuración:

```bash
php artisan config:clear
```

## Configuración 2FA

Se creó manualmente el archivo:

```text
config/two_factor.php
```

Motivo:

```text
Centralizar valores configurables de OTP y recovery codes.
```

Variables agregadas a `.env`:

```env
TWO_FACTOR_EMAIL_OTP_DIGITS=6
TWO_FACTOR_EMAIL_OTP_EXPIRES_MINUTES=10
TWO_FACTOR_EMAIL_OTP_MAX_ATTEMPTS=5
TWO_FACTOR_RECOVERY_CODES_COUNT=8
TWO_FACTOR_RECOVERY_CODES_PREFIX=STORE
```

Después:

```bash
php artisan config:clear
```

## Servicios de autenticación

Se creó manualmente la carpeta:

```bash
mkdir -p app/Services/Auth
```

Archivos creados:

```text
app/Services/Auth/EmailOtpService.php
app/Services/Auth/RecoveryCodeService.php
```

Responsabilidad de `EmailOtpService`:

```text
- generar código OTP;
- guardar hash del código;
- registrar expiración;
- registrar IP y user agent;
- enviar notificación por correo;
- verificar código enviado por el usuario.
```

Responsabilidad de `RecoveryCodeService`:

```text
- generar códigos de recuperación;
- guardar cada código con hash;
- invalidar códigos anteriores al regenerar;
- verificar códigos de un solo uso.
```

Laravel no trae un comando oficial estándar `make:service`.

Si el proyecto tiene disponible un generador genérico como `make:class`, se podría usar para crear servicios. Si no existe, crear manualmente la carpeta y los archivos es correcto.

La carpeta `app/Services` es una convención común para separar lógica de negocio que no debería vivir dentro de los controladores.

## Controladores API de 2FA

Comandos usados:

```bash
php artisan make:controller Api/Auth/TwoFactorAuthController
php artisan make:controller Api/Auth/RecoveryCodeController
```

Archivos creados:

```text
app/Http/Controllers/Api/Auth/TwoFactorAuthController.php
app/Http/Controllers/Api/Auth/RecoveryCodeController.php
```

Responsabilidad de `TwoFactorAuthController`:

```text
- mostrar estado actual de 2FA;
- enviar Email OTP;
- verificar Email OTP;
- activar Email OTP como método 2FA;
- desactivar 2FA.
```

Responsabilidad de `RecoveryCodeController`:

```text
- mostrar cantidad de recovery codes;
- regenerar recovery codes;
- devolver los códigos en texto plano solo al momento de generarlos.
```

Los controladores API deben estar en:

```text
app/Http/Controllers/Api/Auth/
```

No en:

```text
app/Http/Controllers/Auth/
```

La carpeta `app/Http/Controllers/Auth/` se reserva normalmente para autenticación web tradicional con Blade, no para una API separada.

## Rutas API de 2FA

Se modificó:

```text
routes/api.php
```

Rutas agregadas:

```text
GET    /api/auth/2fa
POST   /api/auth/2fa/email/send
POST   /api/auth/2fa/email/verify
GET    /api/auth/2fa/recovery-codes
POST   /api/auth/2fa/recovery-codes/regenerate
POST   /api/auth/2fa/disable
```

Todas estas rutas deben estar protegidas con:

```php
auth:sanctum
```

Importante: en `routes/api.php` no se deben usar parámetros nombrados en métodos de la Facade `Route`.

Incorrecto:

```php
Route::middleware(middleware: 'auth:sanctum');
Route::apiResource(name: 'users', controller: UserController::class);
Route::get(uri: '/2fa', action: [TwoFactorAuthController::class, 'show']);
```

Correcto:

```php
Route::middleware('auth:sanctum');
Route::apiResource('users', UserController::class);
Route::get('/2fa', [TwoFactorAuthController::class, 'show']);
```

Motivo: `Route` es una Facade y Laravel espera argumentos posicionales internamente. Usar argumentos nombrados puede generar errores como:

```text
Undefined array key 0
```

## Limpieza y verificación

Después de cambios en rutas, configuración o clases:

```bash
php artisan optimize:clear
```

Para ver rutas API:

```bash
php artisan route:list --path=api
```

Para ver rutas de autenticación:

```bash
php artisan route:list --path=api/auth
```

Para revisar migraciones:

```bash
php artisan migrate:status
```

Para ejecutar migraciones:

```bash
php artisan migrate
```

En desarrollo, si no importa borrar datos:

```bash
php artisan migrate:fresh
```

## Pruebas básicas con API

Para probar Email OTP primero se necesita un token Sanctum válido.

Ejemplo para enviar OTP:

```bash
curl -X POST http://localhost:8000/api/auth/2fa/email/send \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TU_TOKEN"
```

Luego revisar Mailpit:

```text
http://127.0.0.1:8025
```

Ejemplo para verificar OTP:

```bash
curl -X POST http://localhost:8000/api/auth/2fa/email/verify \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TU_TOKEN" \
  -d '{
    "code": "123456"
  }'
```

Ejemplo para regenerar recovery codes:

```bash
curl -X POST http://localhost:8000/api/auth/2fa/recovery-codes/regenerate \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TU_TOKEN"
```

## Resumen de archivos creados o modificados

Archivos creados:

```text
app/Models/Auth/TwoFactorSetting.php
app/Models/Auth/TwoFactorMethod.php
app/Models/Auth/OtpChallenge.php
app/Models/Auth/RecoveryCode.php
app/Models/Auth/PasskeyCredential.php
app/Notifications/Auth/EmailOtpNotification.php
app/Services/Auth/EmailOtpService.php
app/Services/Auth/RecoveryCodeService.php
app/Http/Controllers/Api/Auth/TwoFactorAuthController.php
app/Http/Controllers/Api/Auth/RecoveryCodeController.php
config/two_factor.php
```

Archivos modificados:

```text
app/Models/Auth/User.php
config/auth.php
routes/api.php
.env
database/migrations/README.md
```

Migraciones 2FA creadas:

```text
database/migrations/auth/create_two_factor_settings_table.php
database/migrations/auth/create_two_factor_methods_table.php
database/migrations/auth/create_otp_challenges_table.php
database/migrations/auth/create_recovery_codes_table.php
database/migrations/auth/create_passkey_credentials_table.php
```

## Estado actual

La configuración actual deja lista la base para:

```text
Email OTP
Recovery Codes
Relaciones Eloquent 2FA
Rutas API protegidas con Sanctum
Servicios separados de los controladores
```

Pendiente para fases siguientes:

```text
TOTP con QR
Passkeys / WebAuthn
Integrar 2FA dentro del flujo real de login
Middleware para exigir 2FA en usuarios administradores
Eventos de auditoría para acciones de seguridad
```
