# Dockerfiles — Convenciones

Todos los Dockerfiles de este repo viven en
`docker/instances/{production,development}/<store>/{frontend,backend}/Dockerfile.{production,development}`.

Este README documenta las convenciones obligatorias para escribir
Dockerfiles consistentes y seguros.

## 1. Rutas absolutas para binarios (PATH hijacking)

**REGLA CRÍTICA**: usar SIEMPRE la ruta absoluta de cualquier binario
externo en `RUN`, `CMD`, `ENTRYPOINT` y `command:` de docker-compose.

```dockerfile
# ✅ Correcto
RUN /usr/local/bin/pnpm fetch --frozen-lockfile
RUN /usr/local/bin/composer install --no-dev --no-scripts
CMD ["/usr/local/bin/node", "./dist/server/entry.mjs"]

# ❌ Incorrecto
RUN pnpm fetch --frozen-lockfile
RUN composer install --no-dev
CMD ["node", "./dist/server/entry.mjs"]
```

**Por qué importa**: dentro de un container, el `PATH` puede incluir
directorios donde un atacante con acceso de escritura al FS (por
ejemplo, vía una vulnerabilidad de la app) podría plantar binarios
maliciosos. `php`, `node`, `composer`, `pnpm`, `chown`, `apk`,
`docker-php-ext-install` — todos deben ir con ruta absoluta.

Excepciones legítimas (binarios que no existen en `/usr/bin` o
`/usr/local/bin`): ninguno. Si está en el container, está en un path
absoluto fijo. Usá `which <bin>` dentro del container en build
time si no sabés dónde está.

## 2. Multi-stage builds para cache de layers

Separar build time de runtime. El stage builder hace pnpm install +
build; el stage runtime solo copia el bundle final. Beneficios:

- Imagen final 5-10x más chica (sin devDeps, sin source).
- Cache más granular: un cambio en el código NO invalida el layer
  de instalación de deps.
- Surface area más chica en producción.

Patrón:

```dockerfile
# Stage 1: builder
FROM node:22-alpine AS builder
WORKDIR /app
COPY package.json pnpm-lock.yaml ./
RUN /usr/local/bin/pnpm fetch --frozen-lockfile
RUN /usr/local/bin/pnpm install --frozen-lockfile --offline
COPY . .
RUN /usr/local/bin/pnpm run build

# Stage 2: runtime (mínimo)
FROM node:22-alpine
COPY --from=builder /app/dist ./dist
COPY --from=builder /app/node_modules ./node_modules
USER node
CMD ["/usr/local/bin/node", "./dist/server/entry.mjs"]
```

## 3. Layer caching granular

Cada `RUN` debe representar un "salto" conceptual. Si cambia solo
un comando, los demás no se invalidan.

```dockerfile
# ✅ Correcto: 3 RUNs separados, cada uno cachea independiente
RUN /sbin/apk add --no-cache postgresql-dev libzip-dev icu-dev
RUN /usr/local/bin/docker-php-ext-install pdo_pgsql zip intl bcmath
RUN /usr/local/bin/pecl install redis xdebug \
    && /usr/local/bin/docker-php-ext-enable redis xdebug

# ❌ Incorrecto: un solo RUN gigante que se invalida entero
# ante cualquier cambio mínimo
RUN /sbin/apk add … && /usr/local/bin/docker-php-ext-install … \
    && /usr/local/bin/pecl install …
```

## 4. USER no-root (obligatorio)

El container NUNCA debe correr como root. Aplicar en TODOS los
Dockerfiles (no solo en compose):

```dockerfile
# Drop privilegios antes del CMD/ENTRYPOINT.
# Build time sigue siendo root (necesario para apk, composer
# install, etc.); runtime es el usuario no-root.
USER node       # frontend: usuario pre-existente en node:alpine
USER www-data   # backend PHP: usuario pre-existente en php:alpine
```

Casos especiales:

- Si el binario necesita escribir fuera de `$HOME`, el `chown`
  previo va en build time, no en entrypoint (entrypoint corre como
  no-root).
- En dev con bind-mounts, el runtime UID tiene que matchear el
  host UID. Usar `user: "${HOST_UID:-1000}:${HOST_GID:-1000}"`
  en el compose y exportar `HOST_UID=$(id -u)` desde
  `scripts/docker-stack.sh` (ya automatizado).

## 5. tini como PID 1

Activar `init: true` en docker-compose para que Docker inyecte
`tini` como PID 1. Sin esto, php-fpm / astro dev / node SSR son PID
1 directamente y no manejan zombies ni señales correctamente.

```yaml
services:
  backend:
    init: true
```

No es necesario tocar el Dockerfile para esto; Docker lo inyecta
automáticamente.

## 6. HEALTHCHECK

```dockerfile
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD /usr/bin/wget -q --spider http://localhost:4321/ || exit 1
```

`start-period` debe ser mayor al tiempo de boot del servicio (cold
start de Laravel puede tomar 15-20s).

## 7. Variables de entorno agrupadas

```dockerfile
# ✅ Correcto: un solo ENV por layer
ENV NODE_ENV=production \
    HOST=0.0.0.0 \
    PORT=4321

# ❌ Incorrecto: 3 layers por 3 ENVs
ENV NODE_ENV=production
ENV HOST=0.0.0.0
ENV PORT=4321
```

## 8. No secrets en el Dockerfile

Nada de passwords, API keys, tokens. Esos van en `.env` (que está
en `.gitignore`) o en docker secrets. Si necesitás pasar un secret
durante el build, usá `--secret` con BuildKit o un ARG + multi-stage
para que no quede en la imagen final.

## 9. `.dockerignore` por cada Dockerfile

Si el contexto de build es grande (ej. todo `frontend/`), creá un
`.dockerignore` al lado del Dockerfile para excluir:

```text
node_modules
.git
.gitignore
*.md
.dockerignore
Dockerfile*
.env*
tests/
.vite/
.astro/
```

Acelera el build (menos contexto para subir a Docker daemon) y
reduce el riesgo de copiar archivos sensibles.

## 10. Comandos en Dockerfile — checklist

Para cada `RUN` / `CMD` / `ENTRYPOINT`, verificar:

- [ ] Usa ruta absoluta (`/usr/bin/foo`, no `foo`)
- [ ] Si es `apk add`, instala solo lo necesario (una imagen
      chica es más rápida de levantar)
- [ ] Si es `RUN` con `&&`, las dependencias se mantienen en el
      mismo layer (no separar con `;` salvo que el cleanup sea
      crítico)
- [ ] El comando es idempotente o se ejecuta una sola vez

## 11. Naming y tags

- Nombre de imagen: `<instancia>-<servicio>` (ej.
  `panda-estampados-backend`, `mm-boutique-frontend`).
- Tag: por ahora `:latest` en dev (regenerado en cada `up` con
  `--build`). En prod usar tag semver (`v0.1.0`, `v0.1.1`, etc.).
- No commitear `:latest` a registry de producción.

## 12. Entry points

Si el container necesita lógica de startup (esperar DB, correr
migraciones, generar APP_KEY), usar un entrypoint script:

```yaml
# compose
entrypoint: ["/usr/local/bin/entrypoint"]
```

```bash
# entrypoint.sh
#!/bin/sh
set -eu
cd /var/www/html
# Esperar DB, generar APP_KEY, etc.
# SIEMPRE rutas absolutas en el script.
exec /usr/local/sbin/php-fpm
```

`exec` reemplaza el shell con el proceso final (importante para que
las señales lleguen al proceso principal).

## Verificar antes de commitear

```bash
# Rebuild y check de tamaño
docker compose -f docker/instances/development/panda-estampados/docker-compose.yml \
    build --no-cache backend
docker images | grep backend

# Verificar usuario runtime
docker compose -f ... up -d backend
docker exec -it <container> /usr/bin/id
# Esperado: uid=33(www-data) o uid=1000(node), NO uid=0(root)

# Verificar tini como PID 1
docker exec -it <container> /usr/bin/cat /proc/1/comm
# Esperado: tini
```
