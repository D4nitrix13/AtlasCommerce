# Métodos de recuperación de contraseña

Este documento describe el sistema de recuperación de cuenta del
panel admin. Cubre los 3 métodos soportados, el flujo end-to-end
del wizard, consideraciones de seguridad y cómo testearlo localmente.

## Overview

Un usuario que olvidó su contraseña puede recuperarla usando uno de
estos métodos (los que tenga habilitados):

| Método            | Descripción                                          | Quién lo usa          |
| ----------------- | ---------------------------------------------------- | --------------------- |
| **Email OTP**     | Código de 6 dígitos enviado al correo.               | Todos los admins      |
| **Recovery codes**| 10 códigos alfanuméricos de un solo uso.             | Cuentas con 2FA       |
| **TOTP**          | Código de 6 dígitos del authenticator app.           | Cuentas con 2FA       |

El backend decide qué métodos están disponibles para cada usuario en
función de su estado. El frontend los muestra como opciones en el
wizard de recuperación.

## Arquitectura

```text
┌──────────────────────────────────────────────────────────────┐
│  Frontend (React) — ForgotPasswordWizard                    │
│  4 estados: email → method → code → password                │
└────────────────────────────┬─────────────────────────────────┘
                             │
┌────────────────────────────▼─────────────────────────────────┐
│  Backend — PasswordResetTokenController                     │
│                                                              │
│  POST /auth/forgot-password                                  │
│      → siempre 200 OK (no filtra si email existe)           │
│      → setea código OTP en password_reset_tokens            │
│      → envía email                                          │
│                                                              │
│  POST /auth/recover-via-recovery-code  (nuevo, 2-step)      │
│      → valida code contra recovery_codes                    │
│      → emite OtpChallenge (TYPE_RECOVERY_CODE)             │
│      → devuelve challenge_token                             │
│                                                              │
│  POST /auth/recover-via-totp  (nuevo, 2-step)               │
│      → valida code contra TwoFactorSetting                  │
│      → emite OtpChallenge (TYPE_TOTP)                      │
│      → devuelve challenge_token                             │
│                                                              │
│  POST /auth/reset-password                                   │
│      → modo A: code OTP de 6 dígitos (legacy, email)        │
│      → modo B: method=recovery_codes + 10-char code         │
│      → modo C: challenge_token (2-step, NUEVO)              │
│      → cambia password, marca token/challenge consumido    │
│      → emite Sanctum token (auto-login)                     │
└──────────────────────────────────────────────────────────────┘
```text

## Flujo por método

### Email OTP (default)

```text
1. POST /auth/forgot-password { email }
   → 200 OK + (dev) code en JSON
   → email con código de 6 dígitos

2. POST /auth/reset-password { email, code, password, ... }
   → 200 OK { token, user }
```text

Sigue funcionando como hasta ahora. Compatible hacia atrás.

### Recovery codes (2-step)

```text
1. POST /auth/forgot-password { email }
   → 200 OK con mensaje genérico

2. POST /auth/recover-via-recovery-code { email, code }
   → 200 OK { challenge_token, expires_at, expires_minutes }
   → marca el recovery code como usado (RecoveryCodeService::verifyAndConsume)

3. POST /auth/reset-password { email, challenge_token, password, ... }
   → 200 OK { token, user }
   → marca el OtpChallenge como consumido
```text

### TOTP (2-step)

```text
1. POST /auth/forgot-password { email }
   → 200 OK con mensaje genérico

2. POST /auth/recover-via-totp { email, code }
   → 200 OK { challenge_token, expires_at, expires_minutes }

3. POST /auth/reset-password { email, challenge_token, password, ... }
   → 200 OK { token, user }
```text

## Cómo generar recovery codes

Los recovery codes se generan en dos lugares:

1. **Automáticamente al activar 2FA**: `POST /auth/2fa/totp/setup`
   + `POST /auth/2fa/totp/verify` (el `verify` exitoso genera 10
   codes y los devuelve UNA sola vez en la respuesta).
2. **Manualmente desde el perfil**: `POST /auth/recovery-codes/regenerate`
   (bearer token requerido). Devuelve 10 codes nuevos. Los anteriores
   quedan marcados como usados (no se borran para audit trail).

El usuario DEBE guardar los códigos en un password manager en el
momento de la generación — no se vuelven a mostrar.

## Cómo testear localmente

### 1. Sembrar los personajes de prueba

```bash
# Crea 4 personajes en 4 tiendas con combinaciones distintas:
#   admin@panda-estampados.test  → 2FA + recovery codes
#   ops@mm-boutique.test         → solo email
#   warehouse@morales-store.test → 2FA sin recovery codes
#   admin@kitsune.test           → 2FA + recovery codes
php artisan db:seed --class=RecoveryCodesTestSeeder
```text

El seeder imprime los recovery codes en una caja decorada a stdout.
**Solo se muestran una vez** — copialos antes de cerrar la terminal.

### 2. Probar el flujo email (rápido)

```bash
# 1. Crear un password_reset_token via tinker o via el flow wizard
php artisan tinker --execute '...'

# 2. Resetear
curl -X POST http://admin.panda-estampados.com/api/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@panda-estampados.test","code":"123456","password":"NewPwd12345abc","password_confirmation":"NewPwd12345abc"}'
```text

### 3. Probar el flujo recovery code (2-step)

```bash
# 1. Recover (consume 1 de los 10 codes del seeder)
curl -X POST http://admin.panda-estampados.com/api/auth/recover-via-recovery-code \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@panda-estampados.test","code":"ABCDEFGHJK"}'
# → { "challenge_token": 42, "expires_at": "...", "expires_minutes": 10 }

# 2. Reset con el challenge_token
curl -X POST http://admin.panda-estampados.com/api/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@panda-estampados.test","challenge_token":42,"password":"NewPwd12345abc","password_confirmation":"NewPwd12345abc"}'
# → { "token": "...", "user": {...} }
```text

### 4. Probar el flujo TOTP (2-step)

Para generar un código TOTP desde el secret del seeder:

```bash
# El secret vive en la DB (tabla two_factor_settings, columna secret
# encriptada). Para los tests E2E, exportalo como env var:
export E2E_TOTP_SECRET=$(php artisan tinker --execute 'echo Crypt::decryptString(TwoFactorSetting::first()->secret);')

# O desde Node:
node -e "const otplib = require('otplib'); console.log(otplib.authenticator.generate('$E2E_TOTP_SECRET'))"

# Después el flow es idéntico al de recovery code pero usando
# /auth/recover-via-totp.
```text

### 5. Tests automatizados

```bash
# Solo los tests de recovery flow (challenge_token + 2-step)
php artisan test --filter=PasswordResetRecoveryTest

# Todos los tests de auth
php artisan test --group=auth

# Suite completa
php artisan test --compact
```text

## Consideraciones de seguridad

### Rate limiting

| Endpoint                          | Throttle            | Razón                          |
| --------------------------------- | ------------------- | ------------------------------ |
| `POST /auth/forgot-password`      | 3/5min por email    | Anti-spam de OTPs por email    |
| `POST /auth/recover-via-recovery-code` | 5/15min por IP  | Anti brute-force (~30^10 codes) |
| `POST /auth/recover-via-totp`     | 5/15min por IP      | Anti brute-force de TOTP       |
| `POST /auth/reset-password`       | 5/15min por IP      | Anti brute-force del code/challenge |

Los límites viven en `PasswordResetTokenController::RECOVER_MAX_ATTEMPTS`
y `RECOVER_DECAY_SECONDS`. Subirlos baja la seguridad; bajarlos
aumenta los falsos positivos en redes NAT (varios usuarios detrás
de la misma IP comparten el throttle).

### Audit log

Cada challenge emitido por `recover-via-*` se persiste en
`otp_challenges` con `context.action` y `context.ip`. Cada challenge
consumido por `reset-password` emite un `Log::info` con
`user_id + challenge_id + challenge_type`. Esto permite detectar:

+ Patrones de brute force (varios recover-* desde la misma IP sin
  reset exitoso).
+ Uso sospechoso de recovery codes (user consume 5 codes en 1 hora).
+ Challenges emitidos pero nunca consumidos (posible robo de token).

### No se envía email de notification

Cuando el usuario usa un recovery code o TOTP para recuperar, NO se
envía email de notificación. Esto es por seguridad: si el atacante
tiene acceso a la API pero no al email, no debería enterarse de que
la cuenta está siendo recuperada. (Trade-off: el usuario legítimo
tampoco se entera — pero el atacante que tenga acceso al email
puede leer los logs de cualquier manera.)

### `code_hash` en challenges

Los `OtpChallenge::code_hash` son bcrypt de strings random. NO se
guarda el código en texto plano. Esto significa que aunque la DB
sea comprometida, los challenges activos no son recuperables — el
atacante solo vería hashes inútiles (los challenges ya vienen
expuestos como IDs en `challenge_token`, pero el ID es solo el
puntero, no el code).

### `consumed_at` para anti-replay

Una vez consumido un challenge (vía `reset-password`), `consumed_at`
se setea. El siguiente intento con el mismo `challenge_token` falla
incluso si el challenge no ha expirado. Defense-in-depth: aunque
un atacante robe el `challenge_token` antes que el usuario legítimo
complete el reset, el usuario legítimo ganará (el primer request
que llegue al server consume el challenge y rechaza todos los
posteriores).

## Referencia de la API

### `POST /auth/recover-via-recovery-code`

**Body**:

```json
{
  "email": "admin@panda-estampados.test",
  "code": "ABCDEFGHJK"
}
```text

**Response 200**:

```json
{
  "challenge_token": 42,
  "expires_at": "2026-08-23T15:30:00+00:00",
  "expires_minutes": 10
}
```text

**Errores 422**:
+ `{"errors": {"email": [...]}}` — email faltante o formato inválido.
+ `{"errors": {"code": ["El recovery code es inválido o ya fue usado."]}}`
+ `{"errors": {"code": ["Tu cuenta no tiene recovery codes disponibles."]}}`
+ `{"errors": {"code": ["Demasiados intentos. Probá de nuevo en N segundos."]}}`

### `POST /auth/recover-via-totp`

**Body**:

```json
{
  "email": "admin@panda-estampados.test",
  "code": "123456"
}
```text

**Response 200**:

```json
{
  "challenge_token": 43,
  "expires_at": "2026-08-23T15:30:00+00:00",
  "expires_minutes": 10
}
```text

**Errores 422**:
+ `{"errors": {"code": ["El código TOTP es inválido."]}}`
+ `{"errors": {"code": ["Tu cuenta no tiene 2FA habilitada."]}}`

### `POST /auth/reset-password` (modo challenge_token)

**Body**:

```json
{
  "email": "admin@panda-estampados.test",
  "challenge_token": 42,
  "password": "NewPwd12345abc",
  "password_confirmation": "NewPwd12345abc",
  "device_name": "web-admin"
}
```text

**Response 200**:

```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "email": "admin@panda-estampados.test",
    "full_name": "Admin Panda",
    "role": { "id": 1, "slug": "admin", "name": "Admin" },
    "store_ids": [1],
    "is_active": true,
    "permissions": []
  }
}
```text

**Errores 422**:
+ `{"errors": {"challenge_token": ["El challenge es inválido, expiró o ya fue consumido."]}}`
+ `{"errors": {"password": [...]}}` — validación de complejidad.
