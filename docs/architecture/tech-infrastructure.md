# Infraestructura — Docker, Caddy

## Visión general

Capas externas al código de la aplicación: `Docker` para entornos de
desarrollo y producción (multi-instance), y `Caddy` como reverse proxy
con TLS automático. NO usamos `Kubernetes`: cada store corre en su
propio host con `docker compose` administrado por
[`scripts/docker-stack.sh`](../../scripts/docker-stack.sh "scripts/docker-stack.sh").

## Docker

`Docker` se usa para estandarizar los entornos. Los servicios
persistentes (`PostgreSQL`, `Redis`) corren en containers, mientras
que el código backend/frontend se bind-mounta desde el filesystem
(en dev) o se copia al image (en prod).

### Instalación

```bash
# Ubuntu / Debian (Docker Engine + Compose plugin v2)
sudo apt install docker.io docker-compose-plugin
sudo usermod -aG docker $USER
# Necesario re-login para que surta efecto la membresía al grupo.

# macOS / Windows
brew install --cask docker
# O instalar Docker Desktop desde docker.com.
```

Verificá la instalación con `docker compose version` (debe ser
`>= 2.x`, integrada como plugin).

### Estructura del repo

```text
docker/
├── COMPOSE.md                         # Convenciones + anatomía del stack
├── DOCKERFILES.md                     # Guías de authoring de Dockerfiles
├── versions.env                       # Versiones de imágenes compartidas
└── instances/
    ├── development/                   # Stage dev
    │   ├── panda-estampados-and-kitsune/
    │   ├── mm-boutique/
    │   └── morales-store/
    └── production/                    # Stage prod
        ├── panda-estampados-and-kitsune/
        ├── mm-boutique/
        └── morales-store/
```

Cada instancia tiene su propio `docker-compose.yml` y su
`environment/.env`. Para detalle de los servicios y volúmenes
ver [`docs/operations/docker.md`](../operations/docker.md "operations/docker.md").

### Comandos (canónico: vía `scripts/docker-stack.sh`)

> **NO** usar `docker compose` directo. Toda la operatoria va por
> el dispatcher, que resuelve stage/store y valida combinaciones.
> Ver AGENTS.md raíz.

```bash
# Levantar 1 instancia (stage + store)
./scripts/docker-stack.sh up development panda-estampados-and-kitsune
./scripts/docker-stack.sh up production mm-boutique

# Bajar (preserva volúmenes)
./scripts/docker-stack.sh down development panda-estampados-and-kitsune

# Reset completo (¡BORRA DATOS! requiere -y)
./scripts/docker-stack.sh rm-volumes -y development panda-estampados-and-kitsune

# Inspeccionar
./scripts/docker-stack.sh ps development panda-estampados-and-kitsune
./scripts/docker-stack.sh status development panda-estampados-and-kitsune
./scripts/docker-stack.sh logs development panda-estampados-and-kitsune backend -f

# Rebuild sin cache (ej. después de cambiar un Dockerfile)
./scripts/docker-stack.sh build no-cache development panda-estampados-and-kitsune
```

## Caddy

`Caddy` actúa como reverse proxy. Sus responsabilidades:

- Terminación TLS (certificados Let's Encrypt automáticos).
- Routing por `Host` a frontend (Astro) o backend (Laravel).
- Servir archivos estáticos con compresión y cache headers.

### Instalación

```bash
# Ubuntu / Debian
sudo apt install -y debian-keyring debian-archive-keyring apt-transport-https
curl -1sLf "https://dl.cloudsmith.io/public/caddy/stable/gpg.key" \
  | sudo gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf "https://dl.cloudsmith.io/public/caddy/stable/config.deb.txt" \
  | sudo tee /etc/apt/sources.list.d/caddy-stable.list
sudo apt update
sudo apt install caddy

# macOS
brew install caddy
```

### Configuración

El `Caddyfile` canónico vive dentro de cada instancia, en
`docker/instances/<stage>/<store>/Caddyfile` (solo producción). En
dev el routing lo hace el Caddy local (system service) apuntado a
`127.0.0.1:9000` (backend) y `127.0.0.1:5321` (frontend).

Para detalle del setup dev ver
[`getting-started/caddy.md`](../../getting-started/caddy.md "getting-started/caddy.md").

### Comandos

```bash
# Validar sintaxis
caddy validate --config /etc/caddy/Caddyfile

# Auto-formateo
caddy fmt --overwrite /etc/caddy/Caddyfile

# Recargar config sin reiniciar (vía admin API)
caddy reload --config /etc/caddy/Caddyfile

# Ver logs
journalctl -u caddy -f
```

## Cloudflare

`Cloudflare` se usa como:

- DNS autoritativo para los dominios.
- WAF (Web Application Firewall).
- CDN y proxy inverso externo.
- Rate limiting perimetral.

### Configuración DNS

Registros `A` para apuntar cada dominio a la IP del host donde corre
la instancia (un VPS por instancia):

```dns
Tipo    Nombre                              Valor
A       panda-estampados.com                <VPS_IP_PANDA>
A       www.panda-estampados.com            <VPS_IP_PANDA>
A       admin.panda-estampados.com          <VPS_IP_PANDA>
A       kitsune.com                         <VPS_IP_PANDA>
A       www.kitsune.com                     <VPS_IP_PANDA>
A       admin.kitsune.com                   <VPS_IP_PANDA>
A       mm-boutique.com                     <VPS_IP_MM>
A       www.mm-boutique.com                 <VPS_IP_MM>
A       admin.mm-boutique.com               <VPS_IP_MM>
A       morales-store.com                   <VPS_IP_MORALES>
A       www.morales-store.com               <VPS_IP_MORALES>
A       admin.morales-store.com             <VPS_IP_MORALES>
```

Proxy: a través de `Cloudflare` (nube naranja activada) para WAF +
CDN; Caddy termina TLS del origen.

### Configuración recomendada

- SSL/TLS: `Full` (Cloudflare cifra hasta Caddy).
- Always HTTPS: activado.
- Auto-minify: HTML, CSS, JS.
- Brotli: activado.
- HTTP/3: activado.
- Bot Fight Mode: activado.

## Almacenamiento de objetos

Producción debería usar `S3` o compatible para assets grandes. Ver
[`architecture/tech-database.md`](../architecture/tech-database.md "architecture/tech-database.md")
sección Storage.

## Monitoreo y logs

Stack candidato (a evaluar):

- **Prometheus** + **Grafana** para métricas.
- **Loki** + **Grafana** para logs centralizados.
- **Sentry** para tracking de errores.

Para el runbook de detección de incidentes ver
[`operations/observability.md`](../operations/observability.md "operations/observability.md").

## Respaldo

- Volúmenes Docker con snapshots programados semanalmente.
- Retención 30 días mínimo.
- Detalle operativo en [`operations/backups.md`](../operations/backups.md "operations/backups.md").

## Documentación relacionada

- [`operations/deployment.md`](../operations/deployment.md "operations/deployment.md")
- [`operations/docker.md`](../operations/docker.md "operations/docker.md")
- [`getting-started/caddy.md`](../../getting-started/caddy.md "getting-started/caddy.md")
