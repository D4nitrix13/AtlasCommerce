# Autenticación de Dos Factores (2FA)

## Problema que resuelve

La autenticación de dos factores agrega una capa de
seguridad adicional más allá de la contraseña. Incluso
si las credenciales de un usuario son comprometidas, un
atacante no podría acceder sin el segundo factor de
verificación.

Este sistema **sí está integrado dentro del flujo de
login**: cuando un usuario con 2FA activo envía
credenciales a `POST /api/v1/auth/login`, el backend
responde con `2fa_required` + `challenge_token` y NO
emite el token `Sanctum` todavía. El cliente envía el
código TOTP a `POST /api/v1/auth/2fa/verify-totp-login`
para completar el login y obtener el token.

## Métodos implementados

- **TOTP (Time-based One-Time Password)**
  - Estado: Implementado
  - Descripción: Activación vía
    `POST /api/v1/auth/2fa/totp/setup` + verificación
    con `POST /api/v1/auth/2fa/totp/verify`. La
    verificación durante el login ocurre en
    `POST /api/v1/auth/2fa/verify-totp-login` (público,
    acepta `challenge_token` + `code`). Implementado con
    `pragmarx/google2fa-laravel` + `bacon/bacon-qr-code`.
- **Recovery Codes**
  - Estado: Implementado
  - Descripción: Códigos de un solo uso para
    emergencias (`PREFIX-XXXX-XXXX`). Endpoints:
    `GET /api/v1/auth/recovery-codes` y
    `POST /api/v1/auth/recovery-codes/regenerate`.
    También usados en el wizard de password recovery
    vía `auth/recover-via-recovery-code`.
- **Email OTP (passwordless login, RF-AUTH-007)**
  - Estado: Implementado
  - Descripción: Login sin contraseña. El usuario
    solicita un código de 6 dígitos con
    `POST /api/v1/auth/login/email-otp/request` y lo
    intercambia por token `Sanctum` en
    `POST /api/v1/auth/login/email-otp/verify`. No es
    un segundo factor, sino un mecanismo de login
    alternativo.
- **Passkeys / WebAuthn**
  - Estado: Implementado (setup base, commit
    `d4621fb`)
  - Descripción: Paquete `laravel/passkeys` integrado.
    Las rutas se registran con middleware `web` (no
    bajo `/api/v1/`): por ejemplo
    `GET /passkeys/login/options`,
    `POST /passkeys/login`, `POST /user/passkeys`. La
    tabla `passkey_credentials` referenciada en este
    doc es histórica — el paquete usa `passkeys`.

## Arquitectura general

```txt
┌─────────────────────────────────────────────────────────┐
│      Rutas API (backend/routes/api.php, prefix /api/v1)│
│  Grupo `store.domain` (públicas) + `auth:sanctum`       │
└────────────────────────┬────────────────────────────────┘
                         │
        ┌────────────────┼────────────────────┐
        ▼                ▼                    ▼
┌───────────────┐ ┌────────────────┐ ┌─────────────────────┐
│ AuthController │ │ TotpController │ │ RecoveryCodeCtrl    │
│ - login       │ │ - status       │ │ - index             │
│ - logout      │ │ - setup        │ │ - regenerate        │
│ - me          │ │ - verify       │ └────────┬────────────┘
│ - verifyTotp  │ │ - destroy      │          │
│   Login       │ └────────┬───────┘          │
└───────────────┘          │                  │
                           │                  │
                           ▼                  ▼
                  ┌─────────────────┐ ┌─────────────────────┐
                  │  TotpService    │ │ RecoveryCodeService │
                  │  - generate()   │ │ - regenerate()      │
                  │  - verify()     │ │ - verify()          │
                  └─────────────────┘ └────────┬────────────┘
                                               │
                                               ▼
                                    ┌─────────────────────┐
                                    │  RecoveryCode       │
                                    │  code_hash (bcrypt) │
                                    └─────────────────────┘

Login passwordless (EmailOtpController, ruta pública):
┌─────────────────────────┐         ┌─────────────────────┐
│ EmailOtpController      │ ──────► │  OtpChallenge       │
│ - request               │         │  code_hash (bcrypt) │
│ - verify                │         └─────────────────────┘
└─────────────────────────┘
```

Passkeys (`laravel/passkeys`) — rutas con middleware
`web`, sin prefijo `/api/v1/`:

```txt
┌──────────────────────────────────────┐
│ /passkeys/login (web)                │
│ /passkeys/login/options (web)        │
│ /passkeys/confirm (web, auth:sanctum)│
│ /user/passkeys (web, auth:sanctum)   │
└──────────────────────────────────────┘
```

## Endpoints

Todos los endpoints bajo `/api/v1/` (prefijo aplicado
en `backend/bootstrap/app.php`, RNF-MAIN-054). Las
rutas autenticadas requieren `store.domain` +
`auth:sanctum`; la verificación TOTP durante login es
pública pero exige `challenge_token` válido.

### TOTP (autenticado)

- `GET` `/api/v1/auth/2fa/totp`
  - Controlador: `TotpController@status`
  - Descripción: Estado del TOTP del usuario
    autenticado (si está configurado, si está activo).
- `POST` `/api/v1/auth/2fa/totp/setup`
  - Controlador: `TotpController@setup`
  - Descripción: Genera secret TOTP + URL del QR.
    **No activa 2FA** todavía — el usuario debe
    verificar después.
- `POST` `/api/v1/auth/2fa/totp/verify`
  - Controlador: `TotpController@verify`
  - Descripción: Verifica el primer código TOTP y, si
    es correcto, activa 2FA (`enabled_at = now()`).
- `DELETE` `/api/v1/auth/2fa/totp`
  - Controlador: `TotpController@destroy`
  - Descripción: Desactiva 2FA y limpia recovery codes
    y challenges activos. Requiere re-auth:
    `current_totp_code` si 2FA está activo, `password`
    si no.

### TOTP durante login (público)

- `POST` `/api/v1/auth/2fa/verify-totp-login`
  - Controlador: `AuthController@verifyTotpLogin`
  - Descripción: Recibe `challenge_token` (devuelto por
    `/api/v1/auth/login` cuando el usuario tiene 2FA)
    y código TOTP de 6 dígitos. Emite el token
    `Sanctum` y la cookie de sesión.

### Recovery codes (autenticado)

- `GET` `/api/v1/auth/recovery-codes`
  - Controlador: `RecoveryCodeController@index`
  - Descripción: Estadísticas de los recovery codes
    del usuario: total, usados y restantes. **No**
    expone los códigos en texto plano.
- `POST` `/api/v1/auth/recovery-codes/regenerate`
  - Controlador: `RecoveryCodeController@regenerate`
  - Descripción: Elimina los códigos anteriores,
    genera nuevos y los devuelve **una sola vez** en
    la respuesta.

### Login passwordless (público)

- `POST` `/api/v1/auth/login/email-otp/request`
  - Controlador: `EmailOtpController@request`
  - Descripción: Genera código de 6 dígitos, hash
    bcrypt, guarda `OtpChallenge` y envía email.
    Rate-limited.
- `POST` `/api/v1/auth/login/email-otp/verify`
  - Controlador: `EmailOtpController@verify`
  - Descripción: Valida `code` + `email`, marca el
    challenge como consumido y emite token `Sanctum`.

### Passkeys / WebAuthn (`laravel/passkeys`, web routes)

Las rutas del paquete **no** están bajo `/api/v1/`; se
registran con middleware `web` desde
`backend/vendor/laravel/passkeys/routes/routes.php`:

- `GET` `/passkeys/login/options`
- `POST` `/passkeys/login`
- `GET` `/passkeys/confirm/options`
- `POST` `/passkeys/confirm`
- `GET` `/user/passkeys/options`
- `POST` `/user/passkeys`
- `DELETE` `/user/passkeys/{passkey}`

Ver `backend/config/passkeys.php` (`guard = sanctum`,
`throttle: 6,1`).

### Autenticación general

- `POST` `/api/v1/auth/login` — devuelve
  `{ token }` o `{ status: "2fa_required",
  challenge_token }`.
- `POST` `/api/v1/auth/logout` — revoca el token
  actual.
- `GET` `/api/v1/auth/me` — datos del usuario del
  token.

Todas las rutas autenticadas requieren `Authorization:
Bearer {token}`.

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

### `passkey_credentials` (referencia histórica)

> **Nota:** Esta tabla corresponde a un esquema interno
> anterior. El paquete `laravel/passkeys` (commit
> `d4621fb`) usa la tabla `passkeys` definida en
> `backend/database/migrations/*_create_passkeys_table.php`.
> La estructura real la gestiona el paquete y difiere
> del esquema listado abajo (no mantenemos FK ni
> campos custom — ver
> `backend/vendor/laravel/passkeys/database/migrations`
> para la fuente de verdad).

Esquema histórico (no vigente):

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

## Flujo Email OTP (passwordless login)

> **Nota:** Este flujo es **login sin contraseña**
> (RF-AUTH-007), NO la activación de 2FA. En el sistema
> actual el 2FA se activa exclusivamente vía TOTP
> (`TotpController`).

### 1. Solicitud del código

```http
Usuario → POST /api/v1/auth/login/email-otp/request  { email }
```

El controlador `EmailOtpController@request`:

1. Valida el email (rate-limited).
2. Genera un código numérico de 6 dígitos con
   `random_int()` (no `rand()`).
3. Crea un `OtpChallenge` con `purpose = login_otp`,
   guarda el hash bcrypt del código en
   `otp_challenges.code_hash`.
4. Envía el código en texto plano por correo mediante
   `EmailOtpLoginNotification`.
5. Retorna la fecha de expiración y el destino (sin
   exponer el código).

**El código OTP nunca se almacena en texto plano.** Solo
permanece su hash bcrypt en
`otp_challenges.code_hash`.

### 2. Verificación del código

```http
Usuario → POST /api/v1/auth/login/email-otp/verify  { email, code }
```

El controlador `EmailOtpController@verify`:

1. Busca el último `OtpChallenge` no usado con
   `purpose = login_otp`.
2. Verifica que no esté expirado y que tenga intentos
   disponibles (default `max_attempts = 5`).
3. Incrementa el contador de intentos.
4. Compara el código ingresado con `Hash::check()`.
5. Si coincide: marca el reto como usado (`used: true`,
   `used_at: now()`) y emite el token `Sanctum`.

**Se invalida el reto después de una verificación
exitosa para evitar reutilización.**

## Flujo Recovery Codes

### Generación

```http
Usuario → POST /api/v1/auth/recovery-codes/regenerate
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

- Todas las rutas 2FA (excepto
  `/api/v1/auth/2fa/verify-totp-login`) están protegidas
  por el middleware `auth:sanctum`.
- El usuario se obtiene con `$request->user()` (token
  Bearer válido).
- La tabla `personal_access_tokens` almacena los tokens
  de API.
- El modelo `User` usa el trait `HasApiTokens` de
  `Sanctum`.
- El 2FA **sí está integrado** dentro del proceso de
  login: si el usuario tiene 2FA activo,
  `AuthController@login` retorna
  `{ status: "2fa_required", challenge_token }` en vez
  del token; el cliente envía el código TOTP a
  `/api/v1/auth/2fa/verify-totp-login` para completar
  el login.
- Passkeys (`laravel/passkeys`) usan el guard
  `sanctum` configurado en `backend/config/passkeys.php`.

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

Variables de entorno por instancia en
`docker/instances/<stage>/<store>/environment/.env`
(ejemplo:
`docker/instances/development/panda-estampados-and-kitsune/environment/.env`):

```env
TWO_FACTOR_EMAIL_OTP_DIGITS=6
TWO_FACTOR_EMAIL_OTP_EXPIRES_MINUTES=10
TWO_FACTOR_EMAIL_OTP_MAX_ATTEMPTS=5
TWO_FACTOR_RECOVERY_CODES_COUNT=8
TWO_FACTOR_RECOVERY_CODES_PREFIX=APP
```

> **Discrepancia resuelta:** `config/two_factor.php`
> define el default de
> `TWO_FACTOR_RECOVERY_CODES_PREFIX` como `'APP'`. Este
> doc mantenía dos valores distintos (`'APP'` en la
> sección `Configuración` arriba, `'STORE'` en el
> bloque `.env`) — se unifica a `'APP'` que es la
> fuente de verdad del código.

## Lo que falta para la fase siguiente

- ✅ **Integración en login** — implementado en
  `AuthController::login` + `verifyTotpLogin`. Ver
  sección "Relación con `Laravel Sanctum`".
- ✅ **TOTP con QR** — implementado vía
  `pragmarx/google2fa-laravel` + `bacon/bacon-qr-code`
  (`TotpController@setup` retorna secret + URL del QR).
- ✅ **Passkeys / WebAuthn** — setup base con
  `laravel/passkeys` (commit `d4621fb`). La tabla
  vigente es `passkeys` del paquete, no
  `passkey_credentials` (que queda como referencia
  histórica arriba).
- 🔲 **Middleware 2FA** — middleware que exija 2FA
  habilitado para usuarios administradores (no
  implementado todavía).
- 🔲 **Auditoría** — eventos de seguridad: intentos
  fallidos, activación/desactivación de 2FA, uso de
  recovery codes. No implementado todavía.

## Ver también

- [recovery-methods.md](recovery-methods.md "recovery-methods.md")
  — Wizard de recuperación de contraseña (Email OTP, Recovery
  Codes, TOTP). Mismos mecanismos de auth que este doc pero usados
  en el flujo de **password reset** (pre-login) en vez de 2FA
  (post-login).
