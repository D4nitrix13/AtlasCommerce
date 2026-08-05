# Seeds para production

Cada tienda tiene 1 archivo de seed:

- **`<store>.json`** — Datos del tenant para producción. Incluye:
  - Slug, dominios (storefront + admin), theme.
  - Admin user con email `admin@<slug>.test` y password referenciado
    por env (`<STORE>_ADMIN_PASSWORD`, ver `environment/.env`).

## Cómo se aplican

El `entrypoint.production.sh` corre las seeds automáticamente cuando
arranca el container (vía `tenants:bootstrap`). Para re-correrlas
manualmente:

```bash
docker compose -f docker/instances/production/<store>/docker-compose.yml \
    exec app php artisan tenants:bootstrap /tmp/seeds/<store>.json
```
