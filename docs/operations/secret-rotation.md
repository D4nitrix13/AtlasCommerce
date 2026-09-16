# Secret Rotation Runbook (RF-SEC-020)

> **RNF-SEC-020**: Los secrets críticos (APP_KEY, claves de pasarela de
> pago, etc.) se rotarán según un calendario documentado.

## Inventario de secrets

| Secret | Dónde vive | Impacto de compromiso |
| --- | --- | --- |
| `APP_KEY` | `backend/.env` (no commiteado) | Sesiones + tokens Sanctum descifrados |
| `DB_PASSWORD` (Postgres) | `backend/.env` + `docker-compose.yml` | Acceso total a BD |
| `REDIS_PASSWORD` | `backend/.env` | Cache + queues accesibles |
| `SANCTUM_STATEFUL_DOMAINS` | `backend/.env` | No es secret, es config pública |
| `MAIL_*` (mailer credentials) | `backend/.env` | Envío de emails suplantados |
| `FTP_PASSWORD` (vsftpd tenant assets) | `docker-compose.yml` | Lectura/escritura de assets |
| `PGADMIN_PASSWORD` (dev only) | `docker-compose.yml` (dev) | DB admin access en dev |
| Stripe API key (cuando se integre) | `backend/.env` | Pagos fraudulentos |
| Pasarela de pago local (cuando se integre) | `backend/.env` | Pagos fraudulentos |

## Calendario de rotación

| Secret | Frecuencia | Rotación manual/automatizada |
| --- | --- | --- |
| `APP_KEY` | Trimestral (cada 3 meses) | Manual via `php artisan key:generate` |
| `DB_PASSWORD` (Postgres) | Semestral | Manual via `ALTER USER` + actualizar `.env` |
| `REDIS_PASSWORD` | Anual | Manual, raramente necesario |
| `MAIL_PASSWORD` | Anual | Manual |
| `FTP_PASSWORD` | Anual | Manual |
| API keys de pasarela de pago | Según proveedor (típicamente 90 días) | Manual via dashboard del proveedor |

**Próximas rotaciones** (cronograma propuesto, ajustar según uso real):

| Fecha | Secret |
| --- | --- |
| 2026-12-01 | APP_KEY (Q4) |
| 2027-03-01 | APP_KEY (Q1) + DB_PASSWORD |
| 2027-06-01 | APP_KEY (Q2) |
| 2027-09-01 | APP_KEY (Q3) |
| 2027-12-01 | APP_KEY (Q4) + DB_PASSWORD |

## Procedimiento: Rotar APP_KEY (Laravel)

### Pre-requisitos

- Acceso SSH al servidor backend.
- Acceso a la base de datos (para reset de tokens Sanctum + sessions).
- Ventana de mantenimiento programada (5-10 min de downtime aceptable).

### Paso a paso

```bash
# 1. Generar nueva key (en staging primero, no en prod).
cd backend
php artisan key:generate --show
# → base64:XYZ123... (copiar este valor)

# 2. Backup del .env actual (rollback plan).
sudo cp /var/www/.env /var/www/.env.backup-$(date +%Y%m%d-%H%M%S)

# 3. Actualizar APP_KEY.
sudo sed -i 's|^APP_KEY=.*|APP_KEY=base64:NEW_KEY_HERE|' /var/www/.env

# 4. IMPORTANTE: re-cifrar todos los valores cifrados con la key anterior.
# Sanctum tokens + cualquier encrypted cast se invalidan.
# Usuarios deben re-loguearse.
php artisan config:clear
php artisan cache:clear

# 5. Reiniciar PHP-FPM para que tome el nuevo .env.
sudo systemctl restart php8.4-fpm

# 6. Smoke test:
curl -I http://localhost/up
# → 200 OK

# 7. Verificar que login funciona (manual o via test).
curl -X POST http://localhost/api/v1/auth/login -d '...'

# 8. Monitorear logs por errores de descifrado durante 1h.
sudo tail -f /var/log/caddy/access.log | grep "500"
```

### Rollback

Si algo falla en los primeros minutos:

```bash
sudo cp /var/www/.env.backup-<timestamp> /var/www/.env
sudo systemctl restart php8.4-fpm
```

Todos los tokens Sanctum previos vuelven a ser válidos (descifrados con
la key original).

## Procedimiento: Rotar DB_PASSWORD (Postgres)

```bash
# 1. Login como postgres superuser.
docker exec -it postgres_container psql -U postgres

# 2. Cambiar password.
ALTER USER panda_user WITH PASSWORD 'NEW_PASSWORD';

# 3. Actualizar .env en backend.
sudo sed -i 's|^DB_PASSWORD=.*|DB_PASSWORD=NEW_PASSWORD|' /var/www/.env

# 4. Reiniciar backend para nueva conexión.
sudo systemctl restart php8.4-fpm

# 5. Verificar.
docker exec backend php artisan db:show
```

**Importante**: actualizar TODOS los `.env` en cada instancia (prod,
dev) si la BD es compartida. Si son BDs separadas, rotar cada una
independientemente.

## Procedimiento: Rotar API keys de pasarela de pago

(típicamente se hace via dashboard del proveedor, no en el repo)

```bash
# 1. Login al dashboard del proveedor (Stripe, etc).
# 2. Rotar API key en producción (NO test mode).
# 3. Actualizar .env en backend.
sudo sed -i 's|^STRIPE_SECRET=.*|STRIPE_SECRET=NEW_KEY|' /var/www/.env
sudo systemctl restart php8.4-fpm

# 4. Verificar con un payment intent de prueba (NO cobrar).
# 5. Borrar la API key vieja en el dashboard del proveedor.
```

## Auditoría

Cada rotación se registra en `docs/operations/secret-rotation-log.md`:

```markdown
| Fecha | Secret | Quién | Notas |
| --- | --- | --- | --- |
| 2026-09-15 | APP_KEY | Daniel | Rotación inicial, sin incidentes |
| 2026-12-01 | APP_KEY | TBD | Programada |
```

## Detección de compromiso

Si sospechás compromiso de un secret:

1. **Inmediato**: rotar el secret afectado AHORA (no esperar al
   calendario).
2. **Investigar**: revisar logs de acceso (Caddy + Laravel), buscar
   requests anómalos en el período de exposición.
3. **Notificar**: si el secret comprometido afecta datos de usuarios,
   evaluar necesidad de disclosure (GDPR, ley local).
4. **Rotación forzada**: invalidar todos los tokens/sesiones derivadas
   del secret comprometido.

## Automatización futura

Pendiente (no prioritario para MVP):

- **HashiCorp Vault**: secret manager con auto-rotación.
- **AWS Secrets Manager** o **GCP Secret Manager**: integración nativa
  con el cloud provider.
- **Renovate Bot** para secrets en package.json (RNF-OWASP A06).
  Actualmente cubierto por `dependencies-audit` job.

## Referencias

- [OWASP Secrets Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html)
- [PCI-DSS Requirement 3.6 — Cryptographic Key Management](https://www.pcisecuritystandards.org/)
- [Laravel Docs — Encryption](https://laravel.com/docs/11.x/encryption)
- PR #103 — este runbook + integración con auditoría.
