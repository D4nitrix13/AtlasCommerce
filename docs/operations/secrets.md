# Secret Rotation Runbook (RF-SEC-020)

> **RNF-SEC-020**: Los secrets críticos (APP_KEY, claves de pasarela
> de pago, etc.) se rotarán según un calendario documentado.
>
> **Service names:** los ejemplos usan `backend` y `postgres` que
> son los nombres canónicos de dev. En prod esos servicios se llaman
> `app` y `db`; el dispatcher `scripts/docker-stack.sh` los traduce
> automáticamente — pasá el nombre canónico y el stage.

## Inventario de secrets

Los secrets viven en **3 lugares** según el ciclo de vida:

| Lugar                                              | Qué vive ahí                                              |
| -------------------------------------------------- | --------------------------------------------------------- |
| `docker/instances/<stage>/<store>/environment/.env`| Puertos, passwords DB/Redis/FTP, dominios                 |
| `backend/.env`                                     | Config de Laravel: `APP_KEY`, `DB_*`, `MAIL_*`, pasarela |
|                                                    | de pago, `<STORE>_ADMIN_PASSWORD`                          |
| `frontend/.env`                                    | Config de Astro (URLs, public vars)                       |

Inventario canónico:

| Secret                                | Dónde vive                              | Impacto de compromiso            |
| ------------------------------------- | --------------------------------------- | -------------------------------- |
| `APP_KEY`                             | `backend/.env` (no commiteado)          | Sesiones + tokens Sanctum descifrados |
| `DB_PASSWORD` (Postgres)              | `backend/.env` + `environment/.env`     | Acceso total a BD                |
| `REDIS_PASSWORD`                      | `backend/.env`                          | Cache + queues accesibles        |
| `SANCTUM_STATEFUL_DOMAINS`            | `backend/.env`                          | No es secret, es config pública  |
| `MAIL_*` (mailer credentials)         | `backend/.env`                          | Envío de emails suplantados      |
| `FTP_PASSWORD` (vsftpd tenant assets) | `environment/.env`                      | Lectura/escritura de assets       |
| `PGADMIN_PASSWORD` (dev only)         | `environment/.env`                      | DB admin access en dev           |
| `<STORE>_ADMIN_PASSWORD`              | `backend/.env` (prod) / `environment/.env` (dev vía `DEV_ADMIN_PASSWORD`) | Admin user inicial del seed |
| Stripe API key (cuando se integre)    | `backend/.env`                          | Pagos fraudulentos                |
| Pasarela de pago local                | `backend/.env`                          | Pagos fraudulentos                |

## Calendario de rotación

| Secret                                  | Frecuencia     | Rotación manual/automatizada                |
| --------------------------------------- | -------------- | ------------------------------------------- |
| `APP_KEY`                               | Trimestral     | Manual via `php artisan key:generate`       |
| `DB_PASSWORD` (Postgres)                | Semestral      | Manual via `ALTER USER` + actualizar `.env` |
| `REDIS_PASSWORD`                        | Anual          | Manual, raramente necesario                 |
| `MAIL_PASSWORD`                         | Anual          | Manual                                      |
| `FTP_PASSWORD`                          | Anual          | Manual                                      |
| API keys de pasarela de pago             | Según proveedor (típicamente 90 días) | Manual via dashboard del proveedor |

**Próximas rotaciones** (cronograma propuesto, ajustar según uso real):

| Fecha       | Secret                                    |
| ----------- | ----------------------------------------- |
| 2026-12-01  | `APP_KEY` (Q4)                            |
| 2027-03-01  | `APP_KEY` (Q1) + `DB_PASSWORD`            |
| 2027-06-01  | `APP_KEY` (Q2)                            |
| 2027-09-01  | `APP_KEY` (Q3)                            |
| 2027-12-01  | `APP_KEY` (Q4) + `DB_PASSWORD`            |

## Procedimiento: Rotar APP_KEY (Laravel 13.x)

### Pre-requisitos

- Acceso SSH al servidor backend.
- Acceso a la base de datos (para reset de tokens Sanctum + sessions).
- Ventana de mantenimiento programada (5-10 min de downtime aceptable).

### Paso a paso

```bash
# 1. Generar nueva key vía el dispatcher canónico.
#    stage=production, store=<store> (panda-estampados-and-kitsune |
#    mm-boutique | morales-store). El servicio es `backend` (dev) o
#    `app` (prod); el dispatcher lo resuelve.
./scripts/docker-stack.sh exec production <store> backend \
    php artisan key:generate --show
# → base64:XYZ123... (copiar este valor)

# 2. Backup del backend/.env actual (rollback plan).
cp backend/.env backend/.env.backup-$(date +%Y%m%d-%H%M%S)

# 3. Actualizar APP_KEY.
sed -i 's|^APP_KEY=.*|APP_KEY=base64:NEW_KEY_HERE|' backend/.env

# 4. IMPORTANTE: re-cifrar todos los valores cifrados con la key anterior.
#    Sanctum tokens + cualquier encrypted cast se invalidan.
#    Usuarios deben re-loguearse.
./scripts/docker-stack.sh exec production <store> backend \
    php artisan config:clear
./scripts/docker-stack.sh exec production <store> backend \
    php artisan cache:clear

# 5. Reiniciar el backend para que tome el nuevo .env.
./scripts/docker-stack.sh restart production <store> backend

# 6. Smoke test (desde el host):
curl -I https://api.panda-estampados.com/api/v1/up
# → 200 OK

# 7. Verificar que login funciona (manual o via test):
curl -X POST https://api.panda-estampados.com/api/v1/auth/login -d '...'

# 8. Monitorear logs por errores de descifrado durante 1h:
./scripts/docker-stack.sh logs production <store> backend --tail 200 -f
```

### Rollback

Si algo falla en los primeros minutos:

```bash
cp backend/.env.backup-<timestamp> backend/.env
./scripts/docker-stack.sh restart production <store> backend
```

Todos los tokens Sanctum previos vuelven a ser válidos (descifrados
con la key original).

## Procedimiento: Rotar DB_PASSWORD (Postgres)

El container postgres del tenant se llama `<store>-<stage_alias>-postgres`
(ej. `panda-estampados-and-kitsune-prod-postgres` para la instancia
prod de Panda). El usuario por defecto es `postgres` (NO `panda_user`
u otro nombre específico — ver `docker/instances/<stage>/<store>/
docker-compose.yml`).

```bash
# 1. Entrar al container postgres del tenant como `postgres` superuser.
./scripts/docker-stack.sh exec production <store> postgres bash
# Dentro del container:
psql -U postgres

# 2. Cambiar password.
ALTER USER postgres WITH PASSWORD 'NEW_PASSWORD';

# 3. Actualizar `backend/.env`:
sed -i 's|^DB_PASSWORD=.*|DB_PASSWORD=NEW_PASSWORD|' backend/.env

# 4. Actualizar `docker/instances/<stage>/<store>/environment/.env`
#    (la variable puede llamarse POSTGRES_PASSWORD según la instancia).
sed -i 's|^POSTGRES_PASSWORD=.*|POSTGRES_PASSWORD=NEW_PASSWORD|' \
    docker/instances/production/<store>/environment/.env

# 5. Reiniciar el backend para que tome la nueva conexión.
./scripts/docker-stack.sh restart production <store> backend

# 6. Verificar:
./scripts/docker-stack.sh exec production <store> backend \
    php artisan db:show
```

**Importante**: las instancias dev y prod son BDs **separadas**, con
usuarios distintos. Cada una rota independientemente. Para cambiar
la BD de una instancia dev, repetir con `stage=development`.

## Procedimiento: Rotar API keys de pasarela de pago

(típicamente se hace via dashboard del proveedor, no en el repo)

```bash
# 1. Login al dashboard del proveedor (Stripe, etc).
# 2. Rotar API key en producción (NO test mode).
# 3. Actualizar backend/.env.
sed -i 's|^STRIPE_SECRET=.*|STRIPE_SECRET=NEW_KEY|' backend/.env
./scripts/docker-stack.sh restart production <store> backend

# 4. Verificar con un payment intent de prueba (NO cobrar).
# 5. Borrar la API key vieja en el dashboard del proveedor.
```

## Auditoría

Cada rotación se registra en
`docs/operations/secret-rotation-log.md`:

```markdown
| Fecha       | Secret       | Quién  | Notas                                |
| ----------- | ------------ | ------ | ------------------------------------ |
| 2026-09-15  | APP_KEY      | Daniel Benjamin Pérez Morales | Rotación inicial, sin incidentes     |
| 2026-12-01  | APP_KEY      | TBD    | Programada                           |
```

## Detección de compromiso

Si sospechás compromiso de un secret:

1. **Inmediato**: rotar el secret afectado AHORA (no esperar al
   calendario).
2. **Investigar**: revisar logs de acceso (Caddy + Laravel), buscar
   requests anómalos en el período de exposición. Para acceso:

   ```bash
   ./scripts/docker-stack.sh logs production <store> backend --tail 500 -f
   ```

3. **Notificar**: si el secret comprometido afecta datos de usuarios,
   evaluar necesidad de disclosure (GDPR, ley local).
4. **Rotación forzada**: invalidar todos los tokens/sesiones
   derivados del secret comprometido.

## Automatización futura

Pendiente (no prioritario para MVP):

- **HashiCorp Vault**: secret manager con auto-rotación.
- **AWS Secrets Manager** o **GCP Secret Manager**: integración
  nativa con el cloud provider.
- **Renovate Bot** para secrets en package.json (RNF-OWASP A06).
  Actualmente cubierto por `dependencies-audit` job.

## Referencias

- [OWASP Secrets Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html)
- [PCI-DSS Requirement 3.6 — Cryptographic Key Management](https://www.pcisecuritystandards.org/)
- [Laravel Docs — Encryption](https://laravel.com/docs/13.x/encryption)
- PR #103 — este runbook + integración con auditoría.
