# Autenticación de Dos Factores (2FA)

## Problema que resuelve

La autenticación de dos factores agrega una capa de
seguridad adicional más allá de la contraseña. Incluso
si las credenciales de un usuario son comprometidas, un
atacante no podría acceder sin el segundo factor de
verificación.

Este sistema está diseñado para usuarios **ya
autenticados con `Laravel Sanctum`**. El 2FA actualmente
protege acciones específicas (activación, verificación)
pero aún no está integrado dentro del flujo de login.

## Métodos implementados

- **Email OTP**
  - Estado: Implementado
  - Descripción: Código numérico de 6 dígitos enviado
    por correo electrónico
- **Recovery Codes**
  - Estado: Implementado
  - Descripción: Códigos de uso único para emergencias
    (PREFIX-XXXX-XXXX)
- **TOTP**
  - Estado: Preparado
  - Descripción: Stub de controlador y migraciones
    listas. Pendiente: integrar
    `pragmarx/google2fa-laravel` y
    `bacon/bacon-qr-code`
- **Passkeys / WebAuthn**
  - Estado: Preparado
  - Descripción: Stub de controlador y tabla
    `passkey_credentials`. Pendiente: integrar
    `laravel/passkeys`

## Arquitectura general

```txt
┌─────────────────────────────────────────────────────────┐
│                 Rutas API (routes/api.php)              │
│            Protegidas con middleware auth:sanctum       │
└────────────────────────┬────────────────────────────────┘
                         │
             ┌───────────┴─────────────────┐
             ▼                             ▼
┌─────────────────────────┐   ┌─────────────────────────┐
│ TwoFactorAuthController │   │ RecoveryCodeController  │
│ - show                  │   │ - index                 │
│ - sendEmailOtp          │   │ - regenerate            │
│ - verifyEmailOtp        │   └────────┬────────────────┘
│ - disable               │            │
└────────┬────────────────┘            │
         │                             │
         ▼                             ▼
┌─────────────────────┐   ┌─────────────────────────┐
│  EmailOtpService    │   │  RecoveryCodeService    │
│  - send()           │   │  - regenerate()         │
│  - verify()         │   │  - verify()             │
└────────┬────────────┘   └────────────┬────────────┘
         │                             │
         ▼                             ▼
┌─────────────────────┐   ┌─────────────────────────┐
│  OtpChallenge       │   │  RecoveryCode           │
│  code_hash (bcrypt) │   │  code_hash (bcrypt)     │
└─────────────────────┘   └─────────────────────────┘
```

## Endpoints

- `GET` `/api/auth/2fa`
  - Controlador: `TwoFactorAuthController@show`
  - Descripción: Muestra el estado actual de 2FA: si
    está habilitado, método preferido y métodos
    registrados
- `POST` `/api/auth/2fa/email/send`
  - Controlador: `TwoFactorAuthController@sendEmailOtp`
  - Descripción: Genera un código OTP, lo guarda como
    hash y lo envía por correo
- `POST` `/api/auth/2fa/email/verify`
  - Controlador: `TwoFactorAuthController@verifyEmailOtp`
  - Descripción: Verifica el código OTP ingresado. Si es
    correcto, activa 2FA y registra el método
- `GET` `/api/auth/2fa/recovery-codes`
  - Controlador: `RecoveryCodeController@index`
  - Descripción: Retorna estadísticas de recovery codes:
    total, usados y restantes
- `POST` `/api/auth/2fa/recovery-codes/regenerate`
  - Controlador: `RecoveryCodeController@regenerate`
  - Descripción: Elimina códigos anteriores, genera
    nuevos y los devuelve una sola vez
- `POST` `/api/auth/2fa/disable`
  - Controlador: `TwoFactorAuthController@disable`
  - Descripción: Desactiva 2FA: actualiza settings y
    desactiva todos los métodos registrados

Todas las rutas requieren un token `Sanctum` válido en
el header `Authorization: Bearer {token}`.

## Tablas del sistema

### `two_factor_settings`

Configuración global de 2FA por usuario. Relación 1:1
con `users`.

- `user_id` — FK (unique) — Referencia al usuario
- `enabled` — boolean — Si 2FA está activo
- `preferred_method` — enum/null — Método preferido:
  `email_otp`, `totp`, `passkey`
- `required` — boolean — Si 2FA es obligatorio (para
  uso futuro con middleware)
- `enabled_at` — timestamp/null — Fecha y hora de
  activación

### `two_factor_methods`

Métodos 2FA individuales registrados por un usuario.
Relación N:1 con `users`.

- `user_id` — FK — Referencia al usuario
- `method` — enum — Tipo: `email_otp`, `totp`, `passkey`
- `display_name` — string/null — Nombre legible para el
  usuario
- `destination` — string/null — Destino (email para
  OTP, etc.)
- `encrypted_secret` — text/null — Secreto encriptado
  (para TOTP futuro)
- `active` — boolean — Si el método está activo
- `verified` — boolean — Si el método fue verificado
- `verified_at` — timestamp/null — Fecha de verificación

### `otp_challenges`

Retos OTP con código hasheado, control de intentos y
expiración. Relación N:1 con `users`.

- `user_id` — FK — Referencia al usuario
- `method` — enum — Tipo de OTP (actualmente solo
  `email_otp`)
- `purpose` — enum — Propósito: `login_2fa`, `enable_2fa`,
  `change_2fa`
- `code_hash` — text — Hash bcrypt del código OTP (nunca
  se almacena en texto plano)
- `destination` — string/null — Correo destino
- `attempts` — smallint — Intentos realizados
- `max_attempts` — smallint — Intentos máximos
  permitidos (default: 5)
- `used` — boolean — Si el código ya fue utilizado
- `expires_at` — timestamp — Fecha de expiración
- `used_at` — timestamp/null — Fecha de uso
- `request_ip` — IP/null — IP desde donde se solicitó
- `user_agent` — text/null — User-Agent del navegador

### `recovery_codes`

Códigos de recuperación de uso único. Almacenados como
hash bcrypt.

- `user_id` — FK — Referencia al usuario
- `code_hash` — text — Hash bcrypt del código (nunca se
  almacena en texto plano)
- `used` — boolean — Si el código ya fue utilizado
- `used_at` — timestamp/null — Fecha de uso

### `passkey_credentials`

Credenciales WebAuthn para passkeys (preparado para
fase futura).

- `user_id` — FK — Referencia al usuario
- `credential_id` — string(1024) — Identificador único
  de la credencial
- `public_key` — text — Clave pública
- `sign_count` — bigint — Contador de firmas
- `transports` — jsonb/null — Transportes soportados
- `aaguid` — string/null — Identificador del
  authenticator
- `device_name` — string/null — Nombre del dispositivo
- `last_used_at` — timestamp/null — Último uso
- `active` — boolean — Si la credencial está activa

### `personal_access_tokens`

Tabla de `Laravel Sanctum` para tokens de API. Se
mantiene en la raíz de `database/migrations/` porque es
infraestructura técnica, no una entidad del store.

## Flujo Email OTP

### 1. Solicitud de código

```http
Usuario (autenticado) → POST /api/auth/2fa/email/send
```

El controlador `TwoFactorAuthController` delega a
`EmailOtpService::send()`:

1. Se genera un código numérico de 6 dígitos usando
   `random_int()` (no `rand()`).
2. Se crea un `OtpChallenge` con el hash del código
   (`Hash::make()`).
3. El código en texto plano se envía por correo mediante
   `EmailOtpNotification`.
4. Se retorna el challenge con la fecha de expiración y
   el destino.

**El código OTP nunca se almacena en texto plano.**
Solo permanece su hash bcrypt en
`otp_challenges.code_hash`.

### 2. Verificación del código

```http
Usuario → POST /api/auth/2fa/email/verify  { "code": "123456" }
```

El controlador delega a `EmailOtpService::verify()`:

1. Se busca el último `OtpChallenge` no usado para el
   usuario y propósito `enable_2fa`.
2. Se verifica que no esté expirado y que tenga intentos
   disponibles.
3. Se incrementa el contador de intentos.
4. Se compara el código ingresado con el hash usando
   `Hash::check()`.
5. Si es correcto, se marca el reto como usado
   (`used: true`, `used_at: now()`).

### 3. Activación de 2FA

Después de una verificación exitosa, el controlador:

1. Crea o actualiza `two_factor_settings` con
   `enabled: true` y `preferred_method: 'email_otp'`.
2. Crea o actualiza `two_factor_methods` con el método
   `email_otp` verificado.

**Se invalida el reto después de una verificación
exitosa para evitar reutilización.**

## Flujo Recovery Codes

### Generación

```http
Usuario → POST /api/auth/2fa/recovery-codes/regenerate
```

El controlador delega a `RecoveryCodeService::regenerate()`:

1. Se eliminan todos los recovery codes anteriores del
   usuario.
2. Se generan nuevos códigos (default: 8) con formato
   `PREFIX-XXXX-XXXX`.
3. Cada código se guarda como hash bcrypt en
   `recovery_codes.code_hash`.
4. Los códigos en texto plano se devuelven **solo una
   vez** en la respuesta.

**Los recovery codes se muestran una sola vez al usuario
y luego solo permanecen como hashes.**

### Uso

```php
RecoveryCodeService::verify($user, $submittedCode)
```

1. Se cargan todos los recovery codes no usados del
   usuario.
2. Se itera cada código y se verifica con
   `Hash::check()`.
3. Si hay coincidencia, se marca como usado y se retorna
   `true`.
4. Si ninguno coincide, se retorna `false`.

## Datos sensibles: qué se almacena con hash

- Código OTP
  - Almacenamiento: `Hash::make()` en
    `otp_challenges.code_hash`
  - Nunca en texto plano: Sí
- Recovery codes
  - Almacenamiento: `Hash::make()` en
    `recovery_codes.code_hash`
  - Nunca en texto plano: Sí
- Contraseña del usuario
  - Almacenamiento: Cast `hashed` en el modelo `User`
  - Nunca en texto plano: Sí

**Ningún código de verificación se almacena sin cifrar
en la base de datos.**

## Relación con `Laravel Sanctum`

- Todas las rutas 2FA están protegidas por el middleware
  `auth:sanctum`.
- El usuario se obtiene con `$request->user()` (token
  Bearer válido).
- La tabla `personal_access_tokens` almacena los tokens
  de API.
- El modelo `User` usa el trait `HasApiTokens` de
  `Sanctum`.
- El 2FA **no está integrado aún** dentro del proceso
  de login. Actualmente se activa y verifica una vez que
  el usuario ya tiene un token `Sanctum`.

## Configuración

El archivo `config/two_factor.php` centraliza los
valores configurables:

```php
'email_otp' => [
    'digits'            => env('TWO_FACTOR_EMAIL_OTP_DIGITS', 6),
    'expires_minutes'   => env('TWO_FACTOR_EMAIL_OTP_EXPIRES_MINUTES', 10),
    'max_attempts'      => env('TWO_FACTOR_EMAIL_OTP_MAX_ATTEMPTS', 5),
],
'recovery_codes' => [
    'count'  => env('TWO_FACTOR_RECOVERY_CODES_COUNT', 8),
    'prefix' => env('TWO_FACTOR_RECOVERY_CODES_PREFIX', 'APP'),
],
```

Variables de entorno en `.env`:

```env
TWO_FACTOR_EMAIL_OTP_DIGITS=6
TWO_FACTOR_EMAIL_OTP_EXPIRES_MINUTES=10
TWO_FACTOR_EMAIL_OTP_MAX_ATTEMPTS=5
TWO_FACTOR_RECOVERY_CODES_COUNT=8
TWO_FACTOR_RECOVERY_CODES_PREFIX=STORE
```

## Lo que falta para la fase siguiente

- Integración en login
  - Verificar 2FA después de credenciales válidas, antes
    de emitir token `Sanctum`
- TOTP con QR
  - Integrar `pragmarx/google2fa-laravel` y
    `bacon/bacon-qr-code`
- Passkeys / WebAuthn
  - Integrar `laravel/passkeys` con la tabla
    `passkey_credentials` existente
- Middleware 2FA
  - Middleware que exija 2FA habilitado para usuarios
    administradores
- Auditoría
  - Eventos de seguridad: intentos fallidos,
    activación/desactivación de 2FA, uso de recovery
    codes
