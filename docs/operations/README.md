# Operaciones

Documentación de infraestructura,
despliegue y mantenimiento del sistema.

## Documentación disponible

- [docker.md](docker.md "Servicios Docker")
  — Servicios `Docker`, redes, volúmenes
- [caddy.md](caddy.md "Reverse proxy con Caddy")
  — Reverse proxy, TLS, enrutamiento por dominio
- [deployment.md](deployment.md "Despliegue en VPS")
  — Pasos de despliegue en VPS
- [environments.md](environments.md "Variables de entorno")
  — Variables de entorno por ambiente
- [backups.md](backups.md "Estrategia de respaldos")
  — Estrategia de respaldos

## Arquitectura de operaciones

```txt
Cliente → DNS → Caddy (443) → Frontend / Backend API
                                    ↓
                              PostgreSQL / Redis / Storage
                              (internos, no expuestos)
```

## Servicios

- **Caddy** — expuesto en puertos 80 y 443
- **Frontend Astro** — no expuesto, sirve vía `Caddy` en puerto 4173
- **Backend `Laravel`** — no expuesto, sirve vía `Caddy` en puerto 8000
- **PostgreSQL** — no expuesto en puerto 5432
- **Redis** — no expuesto en puerto 6379

## Comandos útiles

```bash
# Ver logs de todos los servicios
docker compose logs -f

# Ver logs de un servicio específico
docker compose logs -f backend

# Reiniciar un servicio
docker compose restart backend

# Acceder a PostgreSQL
docker compose exec postgres psql -U user -d database

# Ejecutar migraciones
docker compose exec backend php artisan migrate

# Build del frontend
docker compose exec frontend pnpm build
```
