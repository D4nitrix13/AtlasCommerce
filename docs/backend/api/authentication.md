# Autenticación API — `Laravel Sanctum`

## Cómo funciona

El backend usa **`Laravel Sanctum`** con **Bearer tokens**
para autenticar peticiones API. El frontend envía un token
de acceso en cada petición HTTP mediante el header
`Authorization`.

```http
Authorization: Bearer abc123def456...
```

`Sanctum` valida el token contra la tabla
`personal_access_tokens` y carga el usuario
autenticado en el request.

## Flujo de autenticación

### 1. Login

```http
POST /api/v1/auth/login
Content-Type: application/json

{
    "email": "usuario@ejemplo.com",
    "password": "secret"
}
```

Respuesta:

```json
{
    "user": {
        "id": 1,
        "name": "Juan Pérez",
        "email": "usuario@ejemplo.com"
    },
    "token": "abc123..."
}
```

El frontend almacena el token y lo envía en todas las
peticiones subsecuentes.

### 2. Petición autenticada

```http
GET /api/v1/products
Authorization: Bearer abc123def456...
Accept: application/json
```

### 3. Logout

```http
POST /api/v1/auth/logout
Authorization: Bearer abc123def456...
```

Elimina el token actual de `personal_access_tokens`.

### 4. Usuario autenticado

```http
GET /api/v1/auth/me
Authorization: Bearer abc123def456...
```

Retorna los datos del usuario asociado al token.

## Rutas de autenticación

| Método | URI                       | Descripción                 | Autenticación  |
| ------ | ------------------------- | --------------------------- | -------------- |
| POST   | `/api/v1/auth/login`      | Iniciar sesión              | No requiere    |
| POST   | `/api/v1/auth/register`   | Registrar usuario           | No requiere    |
| POST   | `/api/v1/auth/logout`     | Cerrar sesión               | Requiere token |
| GET    | `/api/v1/auth/me`         | Obtener usuario autenticado | Requiere token |

**Nota:** Las rutas anteriores viven en
`backend/routes/api.php` bajo el grupo con middleware
`store.domain`. El prefijo `/api/v1/` lo aplica
`backend/bootstrap/app.php` (RNF-MAIN-054, ver
`docs/api/versioning.md`). El endpoint `auth/register`
no está implementado todavía en
`backend/routes/api.php` — la creación de clientes se
realiza vía admin (`POST /api/v1/admin/customers`).

## Rutas protegidas

Las rutas privadas y administrativas en `routes/api.php`
están dentro del grupo `auth:sanctum`:

```php
Route::middleware('auth:sanctum')->group(function (): void {
    // Rutas administrativas requieren token válido
});
```

Las rutas públicas del storefront (catálogo, productos)
no requieren autenticación. Estas rutas resuelven la
tienda por dominio.

Si un request no incluye un token válido, `Laravel`
retorna HTTP 401:

```json
{
    "message": "Unauthenticated."
}
```

## Token en el frontend

El cliente API en `src/lib/api.ts` adjunta el token
automáticamente:

```typescript
const token = localStorage.getItem("auth_token");

const response = await fetch(`${API_BASE_URL}/products`, {
    headers: {
        "Accept": "application/json",
        "Content-Type": "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
});
```

## Tabla `personal_access_tokens`

| Columna          | Tipo      | Descripción                    |
| ---------------- | --------- | ------------------------------ |
| `tokenable_type` | string    | Modelo asociado                |
| `tokenable_id`   | bigint    | ID del usuario                 |
| `name`           | string    | Nombre del token               |
| `token`          | string    | Hash del token (sha256)        |
| `abilities`      | json      | Habilidades/permisos del token |
| `last_used_at`   | timestamp | Último uso                     |
| `expires_at`     | timestamp | Expiración (opcional)          |

## Relación con 2FA

El 2FA **sí está integrado** en el flujo de login y en
los mecanismos de recuperación. El estado actual:

1. El usuario envía credenciales a
   `POST /api/v1/auth/login`.
2. Si el usuario tiene 2FA activo, la respuesta es
   `{ "status": "2fa_required", "challenge_token": "..." }`
   y NO se emite token `Sanctum` todavía.
3. El cliente envía el código TOTP a
   `POST /api/v1/auth/2fa/verify-totp-login` con el
   `challenge_token`; el backend emite el token `Sanctum`.
4. Si el usuario no tiene 2FA, se devuelve el token
   directamente.

Adicionalmente:

- **Email OTP passwordless** vía `EmailOtpController`
  (`POST /api/v1/auth/login/email-otp/request` y
  `/verify`).
- **WebAuthn / passkeys** vía `laravel/passkeys`
  (commit `d4621fb`). Las rutas del paquete se
  registran con middleware `web`, no bajo `/api/v1/`:
  por ejemplo `GET /passkeys/login/options`,
  `POST /passkeys/login`, `POST /user/passkeys`.

Ver [backend/auth/auth-2fa.md](../auth/auth-2fa.md
"Autenticación de Dos Factores") para detalles del
sistema 2FA.

## Configuración

En `config/sanctum.php`:

- `expiration` — Tiempo de vida del token en minutos
  (null = sin expiración).
- `stateful` — Dominios que usan autenticación stateful
  (SPA cookies).

En `.env` por instancia, ruta
`docker/instances/<stage>/<store>/environment/.env`
(ejemplo:
`docker/instances/development/panda-estampados-and-kitsune/environment/.env`):

```env
SANCTUM_STATEFUL_DOMAINS=localhost:5321,localhost:8080,panda-estampados.com
```

## Errores comunes

| Código | Causa                                          |
| ------ | ---------------------------------------------- |
| 401    | Token no proporcionado, inválido o expirado    |
| 403    | Token válido pero sin permisos para el recurso |
| 419    | Token CSRF expirado (solo sesiones stateful)   |

## Seguridad

- Los tokens se almacenan como hash SHA-256 en la base
  de datos.
- Nunca se retornan en texto plano después de la
  creación.
- Se puede revocar un token con
  `$user->tokens()->delete()` (todos) o
  `$token->delete()` (específico).
- Los tokens pueden tener `abilities` para limitar
  permisos.
