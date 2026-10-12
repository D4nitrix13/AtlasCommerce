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
- `MAIL_HOST` — Host SMTP. En dev con Docker: `mailpit` (hostname del
  container Mailpit levantado por docker-compose). En otros entornos:
  `localhost` o el host del SMTP real.
- `MAIL_PORT` — Puerto SMTP. En dev el container Mailpit escucha en
  `1025`. El puerto expuesto al host depende de la instancia:
  `1025` (panda-estampados-and-kitsune), `11025` (mm-boutique),
  `12025` (morales-store).
- `MAIL_USERNAME` — Usuario SMTP
- `MAIL_PASSWORD` — Contraseña SMTP
- `MAIL_FROM_ADDRESS` — Correo remitente
  (ej. `noreply@panda.com`)

#### Mailpit en dev

Cada `docker-compose.yml` de dev levanta un container Mailpit
(`axllent/mailpit:latest`) en la misma red Docker que el backend.
Mailpit captura todos los emails que Laravel envía via SMTP y los
muestra en una UI web.

| Instancia dev                 | SMTP (host) | UI web                   |
| ----------------------------- | ----------- | ------------------------ |
| `panda-estampados-and-kitsune` | `:1025`     | <http://localhost:8025>    |
| `mm-boutique`                  | `:11025`    | <http://localhost:18025>   |
| `morales-store`                | `:12025`    | <http://localhost:28025>   |

Uso:

```bash
./scripts/docker/mailpit-up.sh development:panda-estampados-and-kitsune
./scripts/docker/mailpit-up.sh all   # las 3 instancias
```

### Redis

- `REDIS_HOST` — Host de `Redis`
  (`localhost` en local, `redis` en `Docker`)
- `REDIS_PORT` — Puerto de `Redis` (ej. `6379`)
- `REDIS_PASSWORD` — Contraseña de `Redis`

## Frontend — Astro

- `PUBLIC_API_URL` — URL base de la API `Laravel`
  (relativa `/api`, ej. `/api` en dev). En local con un
  stack corriendo fuera de Caddy puede forzarse absoluto
  (ej. `http://localhost:9000/api`).

### Notas

- `PUBLIC_API_URL` **debe** incluir el sufijo `/api`.
- Los endpoints en `src/lib/endpoints.ts` **no**
  incluyen `/api`.
- En producción se usa la forma relativa `/api` para que
  Caddy rutee por `Host` header a la instancia correcta.
  Ver [multi-store.md](../architecture/multi-store.md
  "docs/architecture/multi-store.md").

## Docker

- `POSTGRES_PORT` — Puerto local de `PostgreSQL`
  (archivo: `docker/instances/development/<tenant>/environment/.env`)
- `POSTGRES_USER` — Usuario de `PostgreSQL`
  (archivo: `docker/instances/development/<tenant>/environment/.env`)
- `POSTGRES_PASSWORD` — Contraseña de `PostgreSQL`
  (archivo: `docker/instances/development/<tenant>/environment/.env`)
- `POSTGRES_DB` — Nombre de la base de datos
  (archivo: `docker/instances/development/<tenant>/environment/.env`)

`<tenant>` es uno de `panda-estampados-and-kitsune`,
`mm-boutique` o `morales-store`. Cada instancia tiene su
propio `.env` aislado.

## Dominios locales (desarrollo)

Para desarrollo local con múltiples dominios, agregar en
`/etc/hosts`. Sigue la convención de
[getting-started/local-domains.md](../getting-started/local-domains.md
"docs/getting-started/local-domains.md") usando `.com` como
TLD local. Las instancias pueden correr en paralelo porque
cada una expone puertos distintos en el host.

### Instancia `panda-estampados-and-kitsune` (multi-brand)

Panda y Kitsune son tiendas diferentes para el cliente, pero forman parte
de una misma operación administrativa.

```txt
127.0.0.1   panda-estampados.com
127.0.0.1   www.panda-estampados.com
127.0.0.1   admin.panda-estampados.com
127.0.0.1   kitsune.com
127.0.0.1   www.kitsune.com
127.0.0.1   admin.kitsune.com
```

### Instancia `mm-boutique`

```txt
127.0.0.1   mm-boutique.com
127.0.0.1   www.mm-boutique.com
127.0.0.1   admin.mm-boutique.com
```

### Instancia `morales-store`

```txt
127.0.0.1   morales-store.com
127.0.0.1   www.morales-store.com
127.0.0.1   admin.morales-store.com
```

> Solo una instancia puede correr a la vez (puertos del
> host compartidos: `5432`, `6379`, `9000`, `4321` mapeados
> con shift +1000). Para correr varias en paralelo, override
> `*_PORT` en el `.env` correspondiente.

## Producción

- `APP_ENV` — `production`
- `APP_DEBUG` — `false`
- `DB_HOST` — `postgres` (`Docker`)
- `REDIS_HOST` — `redis` (`Docker`)
- `PUBLIC_API_URL` — Relativo (`/api`), no absoluto. Caddy
  expone API bajo cada `admin.<dominio>/*` según el
  `Caddyfile` por instancia.
- `SANCTUM_STATEFUL_DOMAINS` (ejemplo instancia
  `panda-estampados-and-kitsune`):
  `panda-estampados.com,kitsune.com,admin.panda-estampados.com,
  www.panda-estampados.com,www.kitsune.com`. Las otras
  instancias usan sus propios dominios equivalentes.
