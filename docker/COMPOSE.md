# docker-compose.yml — Convenciones

Todos los archivos `docker-compose.yml` viven en
`docker/instances/{production,development}/<store>/docker-compose.yml`.

Este README documenta las convenciones obligatorias para escribirlos.

## 1. Rutas absolutas en `command:` y `entrypoint:`

**REGLA CRÍTICA**: cualquier binario en `command:` debe ir con ruta
absoluta. Dentro del container, el `PATH` puede incluir directorios
donde un atacante con acceso de escritura haya plantado binarios
maliciosos. PATH hijacking se mitiga con rutas absolutas.

```yaml
# ✅ Correcto
command:
  - /usr/lib/postgresql/15/bin/postgres
  - -c
  - config_file=/etc/postgresql/postgresql.conf
entrypoint: ["/usr/local/bin/entrypoint"]

# ❌ Incorrecto
command:
  - postgres
  - -c
  - config_file=/etc/postgresql/postgresql.conf
```

Mapeo de binarios comunes en este repo:

| Binario              | Path                                      |
|----------------------|-------------------------------------------|
| `corepack`           | `/usr/local/bin/corepack`                 |
| `postgres`           | `/usr/lib/postgresql/<ver>/bin/postgres`  |
| `redis-server`       | `/usr/local/bin/redis-server`             |
| `vsftpd`             | `/usr/sbin/vsftpd`                        |
| `pnpm`               | `/usr/local/bin/pnpm`                     |
| `node`               | `/usr/local/bin/node`                     |
| `php`                | `/usr/local/bin/php`                      |
| `php-fpm`            | `/usr/local/sbin/php-fpm`                 |
| `php artisan`        | `/usr/local/bin/php artisan` (artisan es subcommand) |
| `composer`           | `/usr/bin/composer`                       |
| `docker-php-ext-install` | `/usr/local/bin/docker-php-ext-install` |
| `docker-php-ext-enable`  | `/usr/local/bin/docker-php-ext-enable` |
| `pecl`               | `/usr/local/bin/pecl`                     |
| `apk`                | `/sbin/apk`                               |
| `pg_isready`         | `/usr/bin/pg_isready`                     |
| `id`                 | `/usr/bin/id`                             |
| `grep`               | `/usr/bin/grep`                           |
| `seq`                | `/usr/bin/seq`                            |
| `find`               | `/usr/bin/find`                           |
| `pkill`              | `/usr/bin/pkill`                          |
| `sleep`              | `/bin/sleep`                              |
| `wget`               | `/usr/bin/wget`                           |
| `chown`              | `/bin/chown`                              |
| `chmod`              | `/bin/chmod`                              |
| `mkdir`              | `/bin/mkdir`                              |
| `cp`                 | `/usr/bin/cp`                             |
| `rm`                 | `/bin/rm`                                 |
| `pwd`                | `/usr/bin/pwd`                            |

Si necesitás un binario que no está en la tabla, validalo dentro del
container con `docker compose exec <svc> /usr/bin/which <nombre>`. Nunca
asumas que `nombre` solo va a funcionar — la convención es absoluta.

Para verificar el path exacto dentro del container:
```bash
docker compose exec <svc> /usr/bin/which <binario>
```

## 2. `init: true` para tini (PID 1)

Activar en cada servicio que escribimos nosotros (backend, frontend).
No es necesario en servicios de imágenes oficiales (postgres, redis,
vsftpd) porque esas imágenes ya manejan su propio PID 1.

```yaml
services:
  backend:
    init: true
```

## 3. `user:` no-root (obligatorio)

Aplicar `user:` en backend y frontend, tanto dev como prod:

```yaml
# dev: matchear UID del host para que bind-mounts funcionen
user: "${HOST_UID:-1000}:${HOST_GID:-1000}"

# prod: UID fijo (sin bind-mounts)
user: "33:33"      # backend: www-data
user: "1000:1000"  # frontend: node
```

`HOST_UID` y `HOST_GID` se exportan automáticamente desde
`scripts/docker-stack.sh` antes de invocar `docker compose`. No hace
falta setearlos manualmente.

## 4. Volúmenes separados por concern

Separar bind mounts, named volumes y volumes anónimos por servicio.
Mezclarlos complica el lifecycle (¿qué pasa si bajás un container?
¿se borra el named volume? ¿el bind mount queda stale?).

```yaml
volumes:
  # Bind mounts: solo dev. Source explícito, no se persiste.
  - ../../../../backend:/var/www/html
  - ../../../../frontend:/app

  # Named volumes: estado que persiste entre `up`/`down`.
  - atlas_commerce_<inst>_dev_postgres_data:/var/lib/postgresql
  - atlas_commerce_<inst>_dev_storage:/var/www/html/storage

  # Anonymous volumes: enmascaran caches efímeros sobre el bind-mount.
  # Recreados vacíos en cada `up`. Ideales para .vite/, .astro/,
  # .next/, etc. que arrastran lock files stale del host.
  - /app/.astro
  - /app/node_modules/.vite
```

Regla: si el directorio tiene lock files que pueden stale (vite, astro,
next), usá anonymous volume. Si tiene datos valiosos (DB, storage),
usá named volume. Si es código fuente, usá bind mount.

## 5. Redes dedicadas por instancia

Cada instancia tiene su propia network bridge. NO compartir redes
entre instancias — el aislamiento es lo que hace que prod y dev
puedan coexistir sin pisarse IPs.

```yaml
networks:
  panda-estampados-dev:
    name: atlas-commerce-panda-estampados-dev

# Y para prod con IPs fijas:
networks:
  panda-estampados:
    driver: bridge
    ipam:
      config:
        - subnet: 172.21.0.0/24
          gateway: 172.21.0.1
```

## 6. Healthchecks

Cada servicio que expone un endpoint HTTP debe tener healthcheck:

```yaml
healthcheck:
  test: ["CMD-SHELL", "/usr/bin/pg_isready -U $${POSTGRES_USER} -d $${POSTGRES_DB}"]
  interval: 10s
  timeout: 5s
  retries: 5
```

`$$` escapa el `$` para que Compose NO lo interpole al leer el
compose file, sino que lo pase literal al shell dentro del container.

## 7. `depends_on` con condiciones

Si un servicio depende de otro, usar la forma `condition:` para no
arrancar antes de que el dependiente esté saludable:

```yaml
depends_on:
  postgres:
    condition: service_healthy
  redis:
    condition: service_healthy
```

Sin `condition:`, `depends_on` solo espera al `started` (proceso
arrancado) pero no a que esté listo. La app se conecta a Postgres
y falla.

## 8. `restart` policy

```yaml
restart: unless-stopped
```

Reinicia si crashea, pero NO si vos explícitamente lo bajás. Para
dev es lo más práctico.

## 9. `.env` por instancia

Cada instancia tiene su propio `.env` en
`docker/instances/<stage>/<store>/environment/.env`. Ejemplo de
template: `docker/instances/development/panda-estampados/environment/.env.example`.

```yaml
env_file:
  - environment/.env
```

Compose lee automáticamente las variables. Si la variable no existe,
Compose usa su default (definido en el compose con `${VAR:-default}`)
o falla con un error explícito.

## 10. Nombres consistentes

| Recurso                  | Convención                                 |
|--------------------------|--------------------------------------------|
| `container_name`         | `<instancia>-<stage>-<servicio>` (dev)     |
|                          | `<instancia-corto>-<servicio>` (prod)      |
| Named volume             | `atlas_commerce_<inst>_<stage>_<concern>`       |
| Network                  | `atlas-commerce-<inst>-<stage>`                 |

Ejemplos (dev):
- `panda-estampados-dev-frontend`
- `atlas_commerce_panda_estampados_and_kitsune_dev_postgres_data`
- `atlas-commerce-panda-estampados-dev`

Ejemplos (prod):
- `panda-estampados-frontend`
- `panda-estampados-db-data`

Naming consistente permite filtrar por label con un solo comando:
```bash
docker ps --filter "label=com.docker.compose.project=panda-estampados-dev"
```

## 11. Recursos (CPU, memoria)

Para prod, declarar límites:

```yaml
deploy:
  resources:
    limits:
      cpus: "1.0"
      memory: 512M
```

En dev no hace falta (Docker Desktop tiene defaults razonables).

## 12. Secrets

NO hardcodear secrets en el compose. Usar `environment/.env` (que
está en `.gitignore`) o docker secrets en prod.

```yaml
# ❌ Mal
environment:
  - DB_PASSWORD=supersecret123

# ✅ Bien
env_file:
  - environment/.env   # tiene DB_PASSWORD=...
```

## Verificar antes de commitear

```bash
# Validar el compose (sin ejecutar)
docker compose -f docker/instances/development/panda-estampados/docker-compose.yml \
    config --quiet

# Verificar que arranca sin secretos en claro
docker compose -f ... config | /usr/bin/grep -i password
# (no debería haber matches en líneas que no sean env_file)

# Levantar y verificar UID del container
docker compose -f ... up -d backend
docker compose -f ... exec backend /usr/bin/id
# Esperado: uid=1000 o uid=33, NO uid=0

# Verificar tini como PID 1
docker compose -f ... exec backend /usr/bin/cat /proc/1/comm
# Esperado: tini
```