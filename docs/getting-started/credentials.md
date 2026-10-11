# Credenciales por defecto

> **Single source of truth.** Si tocás seeders, seed JSONs por
> instancia, `DefaultAdminSeeder`, o `BootstrapTenantCommand`,
> actualizá esta página. Es la referencia que consultan los devs
> nuevos y la que usa el script de bootstrap (`./scripts/docker-stack.sh
> setup dev <store>`) para crear cuentas.

## Admin (todas las instancias dev)

| Slug tienda | Email | Password |
| --- | --- | --- |
| `panda-estampados` | `admin@panda-estampados.com` | `password` |
| `kitsune` | `admin@kitsune.com` | `password` |
| `panda-estampados-and-kitsune` (test) | `admin@panda-estampados-and-kitsune.test` | `password` |
| `mm-boutique` | `admin@mm-boutique.com` | `password` |
| `morales-store` | `admin@morales-store.com` | `password` |

Convención: `admin@<slug>.<tld>` / `password`. Aplica a todos los
seeds dev (`DefaultAdminSeeder`, `BootstrapTenantCommand`, seeds
JSON por instancia bajo
`docker/instances/development/<instancia>/seeds/dev-<instancia>.json`).

## Customer

| Slug tienda | Email | Password | Notes |
| --- | --- | --- | --- |
| `panda-estampados` | `cliente@panda-estampados.test` | `password` | 3 orders B2B (NIO), customer_type=wholesale. Seeder: `CustomerTestSeeder`. |
| `kitsune` | `cliente@kitsune.test` | `password` | 2 orders B2C (USD), customer_type=retail. Mismo seeder. |

Convención: `cliente@<slug>.test` / `password`. Los customers se
crean via `php artisan db:seed --class=CustomerTestSeeder` (o el
`db:seed` default que ya lo incluye via `DatabaseSeeder`).

Los customers también pueden registrarse via el form público en
`/es/account/signup` (ver `odd/tasks/customer-auth.md` — sprint
CA.1-CA.5 ya mergeado). El register flow crea User(role=customer) +
Customer profile linked via `customer.user_id` + Sanctum stateful
cookie.

## Service accounts (Mail, OAuth, etc.)

Ver `operations/environments.md` — listadas por env. Los secrets
reales NUNCA van en este repo (`.env` está en `.gitignore`); acá
sólo se documenta la ESTRUCTURA de las credenciales esperadas.
