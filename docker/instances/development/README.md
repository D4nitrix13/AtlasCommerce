# Seeds para development

Cada tienda tiene 2 archivos de seed:

- **`<store>.json`** — Datos reales del tenant (slug, dominios, theme).
  Usado por `BootstrapTenantCommand` para crear/actualizar el tenant
  en la base de datos. NO contiene datos sensibles.

- **`dev-<store>.json`** — Datos de PRUEBA para development. Incluye:
  - Mismos tenants que prod, pero con sufijo `(DEV)` en el nombre.
  - Admin user con email `admin@dev.<slug>.test` y password
    referenciado por env (`DEV_ADMIN_PASSWORD`).
  - Features todas activadas para que puedas probar.

## Password por defecto

El admin user de dev usa la variable de entorno `DEV_ADMIN_PASSWORD`
(ver `environment/.env` de cada tienda). El entrypoint de dev
(`backend/entrypoint.development.sh`) lee esa variable al inicializar
la BD. Si querés cambiarla, editá el `.env` correspondiente.

## Cómo correr las seeds en dev

El `entrypoint.development.sh` corre las seeds automáticamente cuando
arranca el container. Si querés re-correrlas manualmente:

```bash
docker compose -f docker/instances/development/<store>/docker-compose.yml \
    exec backend php artisan tenants:bootstrap /tmp/seeds/dev-<store>.json
```
