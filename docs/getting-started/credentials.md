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

No existe auth de Customer en MVP. Ver `odd/tasks/customer-auth.md`
(F0.x follow-up) para el spec del sprint dedicado. Mientras tanto,
los Customers se administran vía `CustomerController` (admin-side) y
no pueden hacer login propio.

## Service accounts (Mail, OAuth, etc.)

Ver `operations/environments.md` — listadas por env. Los secrets
reales NUNCA van en este repo (`.env` está en `.gitignore`); acá
sólo se documenta la ESTRUCTURA de las credenciales esperadas.
