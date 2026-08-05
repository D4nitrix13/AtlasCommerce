# Variables de Entorno

## Ambientes

- **`local`** — Desarrollo en máquina local
- **`testing`** — Tests automatizados (`PHPUnit`)
- **`production`** — Servidor de producción (VPS)

## Backend — Laravel

### Variables esenciales

- `APP_NAME` — Nombre de la aplicación
  (ej. `"Panda Estampados"`)
- `APP_ENV` — Ambiente (`local`, `production`)
- `APP_KEY` — Clave de cifrado generada con
  `php artisan key:generate` (ej. `base64:...`)
- `APP_URL` — URL base del backend
  (ej. `http://localhost:9000`)
- `APP_DEBUG` — Modo debug
  (`true` en local, `false` en producción)

### Base de datos

- `DB_CONNECTION` — Motor de BD (ej. `pgsql`)
- `DB_HOST` — Host de `PostgreSQL`
  (`localhost` en local, `postgres` en `Docker`)
- `DB_PORT` — Puerto de `PostgreSQL` (ej. `5432`)
- `DB_DATABASE` — Nombre de la BD
  (ej. `ecommerce`)
- `DB_USERNAME` — Usuario de la BD (ej. `user`)
- `DB_PASSWORD` — Contraseña de la BD
  (ej. `secret`)

### Autenticación (Sanctum)

- `SANCTUM_STATEFUL_DOMAINS` — Dominios que usan
  sesiones stateful
  (ej. `localhost:5321,localhost:8080`)

### 2FA

- `TWO_FACTOR_EMAIL_OTP_DIGITS` — Dígitos del OTP
  (default: `6`)
- `TWO_FACTOR_EMAIL_OTP_EXPIRES_MINUTES` — Minutos
  de expiración (default: `10`)
- `TWO_FACTOR_EMAIL_OTP_MAX_ATTEMPTS` — Intentos
  máximos (default: `5`)
- `TWO_FACTOR_RECOVERY_CODES_COUNT` — Cantidad de
  recovery codes (default: `8`)
- `TWO_FACTOR_RECOVERY_CODES_PREFIX` — Prefijo de
  recovery codes (default: `STORE`)

### Mail

- `MAIL_MAILER` — Driver de correo
  (ej. `smtp`, `log`)
- `MAIL_HOST` — Host SMTP (ej. `localhost`)
- `MAIL_PORT` — Puerto SMTP (ej. `1025` con Mailpit)
- `MAIL_USERNAME` — Usuario SMTP
- `MAIL_PASSWORD` — Contraseña SMTP
- `MAIL_FROM_ADDRESS` — Correo remitente
  (ej. `noreply@panda.com`)

### Redis

- `REDIS_HOST` — Host de `Redis`
  (`localhost` en local, `redis` en `Docker`)
- `REDIS_PORT` — Puerto de `Redis` (ej. `6379`)
- `REDIS_PASSWORD` — Contraseña de `Redis`

## Frontend — Astro

- `PUBLIC_API_URL` — URL base de la API `Laravel`
  (con `/api`, ej. `http://localhost:9000/api`)

### Notas

- `PUBLIC_API_URL` **debe** incluir el sufijo `/api`.
- Los endpoints en `src/lib/endpoints.ts` **no**
  incluyen `/api`.
- En producción:
  `PUBLIC_API_URL=https://api.pandas-estampados.com/api`

## Docker

- `POSTGRES_PORT` — Puerto local de `PostgreSQL`
  (archivo: `docker/dev/.env`)
- `POSTGRES_USER` — Usuario de `PostgreSQL`
  (archivo: `docker/dev/.env`)
- `POSTGRES_PASSWORD` — Contraseña de `PostgreSQL`
  (archivo: `docker/dev/.env`)
- `POSTGRES_DB` — Nombre de la base de datos
  (archivo: `docker/dev/.env`)

## Dominios locales (desarrollo)

Para desarrollo local con múltiples dominios,
agregar en `/etc/hosts`:

```txt
127.0.0.1  pandas-estampados.test
127.0.0.1  kitsune.test
127.0.0.1  admin.pandas-estampados.test
127.0.0.1  api.pandas-estampados.test
```

## Producción

- `APP_ENV` — `production`
- `APP_DEBUG` — `false`
- `DB_HOST` — `postgres` (`Docker`)
- `REDIS_HOST` — `redis` (`Docker`)
- `PUBLIC_API_URL` —
  `https://api.pandas-estampados.com/api`
- `SANCTUM_STATEFUL_DOMAINS` —
  `pandas-estampados.com,kitsune.com,admin.pandas-estampados.com`
