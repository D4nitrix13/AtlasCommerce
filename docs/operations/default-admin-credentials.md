# Credenciales admin por defecto — multi-instance

> Single source of truth de las credenciales de admin que el repo
> crea en cada tienda. **Esta página es referencia obligatoria antes
> de mergear cualquier cambio que toque seeds, seeders, o el bootstrap
> de tenants.**

## Tabla de credenciales (desarrollo)

| Tienda (slug) | Email | Password |
| --- | --- | --- |
| `panda-estampados` | `admin@panda-estampados.com` | `password` |
| `kitsune` | `admin@kitsune.com` | `password` |
| `mm-boutique` | `admin@mm-boutique.com` | `password` |
| `morales-store` | `admin@morales-store.com` | `password` |

**Convención**: `admin@<slug>.com` / `password`. Aplicada de forma
consistente en todos los seeds dev, en `DefaultAdminSeeder` y en
`BootstrapTenantCommand`.

## Dónde está definido

### 1. Seed JSONs por instancia (dev)

Cada tienda tiene su seed declarativo bajo
`docker/instances/development/<instancia>/seeds/dev-<instancia>.json`:

```json
{
  "admin_user": {
    "email": "admin@panda-estampados.com",
    "password": "password"
  },
  ...
}
```

Aplicado por `tenants:bootstrap` cuando se levanta la instancia via
`scripts/docker-stack.sh setup dev <store>`.

### 2. `backend/database/seeders/DefaultAdminSeeder.php`

`DEFAULT_PASSWORD = 'password'` (línea 47) y `DEFAULT_TENANTS = [...]`
(líneas 56-61). Seeder idempotente que crea tienda + dominios + admin
para los 4 slugs canónicos. Idem que el seed declarativo, pero para
entornos donde se corre `php artisan db:seed` directo.

### 3. `backend/app/Console/Commands/BootstrapTenantCommand.php::resolveAdminPassword()`

Resuelve el password en este orden:

1. Si el JSON tiene `password_env` Y el env var existe y no está vacío:
   usa el valor del env var.
2. Si no, si el JSON tiene `password` (literal): usa ese valor.
3. Si no, fallback a `"password"`.

## Producción: NUNCA usar el literal

> **⚠️ REGLA CRÍTICA:** Antes de cualquier deploy a producción,
> configurar el password via env variable. El repo está protegido
> con tres capas defensivas:

1. **JSON seeds de producción** (`docker/instances/production/*/seeds/*.json`)
   declaran `password_env: "PANDA_ADMIN_PASSWORD"` (etc.) — NO usan
   `password` literal. El password viene del env var en runtime.

2. **`BootstrapTenantCommand::resolveAdminPassword()`** lanza
   `RuntimeException` en `APP_ENV=production` si no se configuró ni
   `password_env` ni `password` (defensa contra deploys sin env var).

3. **`DefaultAdminSeeder`** está pensado solo para dev/testing/seeders
   locales — NUNCA se ejecuta en producción porque
   `tenants:bootstrap` lo reemplaza con el comando más rico
   (`BackendServiceProvider` solo lo corre si la instancia no tiene
   seed JSON).

### Configurar env vars en producción

Las env vars siguen el patrón `<TENANT>_ADMIN_PASSWORD`:

```bash
PANDA_ADMIN_PASSWORD='...'
MM_ADMIN_PASSWORD='...'
MORALES_ADMIN_PASSWORD='...'
```

Para `kitsune` (que comparte instancia con panda-estampados):
`KITSUENE_ADMIN_PASSWORD='...'` o usar la misma `PANDA_ADMIN_PASSWORD`
si los admins son compartidos.

## Política de rotación

- **Desarrollo**: `password` literal está OK (entorno no expuesto).
- **Producción**: rotar cada 90 días mínimo; rotar inmediatamente
  si el secret se filtra.
- **Post-deploy**: forzar cambio en el primer login (feature
  pendiente, RF-Login-Forced-Password-Change del roadmap).

## Cómo verificar que producción NO tiene el password literal

```bash
# Verificar que las env vars están en el .env de la instancia de prod
ssh <server> "cd /opt/AtlasCommerce && grep -E 'PANDA_ADMIN|MM_ADMIN|MORALES_ADMIN' .env"
# Debe listar las 3 vars con valores no vacíos.
```

Si alguna línea está vacía o falta, el `BootstrapTenantCommand` con
`APP_ENV=production` rehusará crear el admin user hasta que se
configure.

## Referencias

- `backend/database/seeders/DefaultAdminSeeder.php` (líneas 14-21 + 56-61)
- `backend/app/Console/Commands/BootstrapTenantCommand.php::resolveAdminPassword()` (líneas 330-345)
- `docker/instances/development/{tenant}/seeds/dev-<tenant>.json`
- `docker/instances/production/{tenant}/seeds/<tenant>.json`
- `docs/development/operations.md` — runbook de deploy con setup de secrets