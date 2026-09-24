# Infraestructura — Docker, Caddy, Kubernetes

## Visión general

Capas externas al código de la aplicación: `Docker` para entornos de
desarrollo, `Caddy` como reverse proxy con TLS automático, y
`Kubernetes` para orquestación en producción.

## Docker

`Docker` se usa para estandarizar el entorno de desarrollo. Los
servicios persistentes (`PostgreSQL`, `Redis`) corren en containers
mientras que el código backend/frontend se monta desde el filesystem.

### Instalación

```bash
# Ubuntu / Debian
sudo apt install docker.io docker-compose-v2
sudo usermod -aG docker $USER

# macOS
brew install docker docker-compose
```

### Estructura

```txt
docker/
├── dev/
│   ├── docker-compose.dev.yml    # Postgres + Redis + tools
│   └── scripts/                   # Scripts de admin
└── tools/
    └── vikunja/                   # Tool alternativo de tasks
```

### Comandos

```bash
cd docker/dev

# Levantar servicios
docker compose up -d

# Ver logs
docker compose logs -f postgres

# Detener
docker compose down

# Reset completo (¡borra datos!)
docker compose down -v
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

`Caddyfile` global: `/etc/caddy/Caddyfile`.

Para desarrollo local ver
[getting-started/caddy.md](../getting-started/caddy.md "getting-started/caddy.md").

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

## Kubernetes

Orquestación de producción. Los manifests viven en `infra/k8s/` (a
desarrollar). El sistema está pensado para desplegarse en:

- **VPS** con `k3s` (Kubernetes ligero).
- **Cluster gestionado** (EKS, GKE, AKS).

### Componentes planeados

```txt
ingress (nginx/traefik)
  ├── frontend (Deployment + Service)
  └── backend (Deployment + Service)

stateful
  ├── postgres (StatefulSet + PVC)
  └── redis (Deployment + PVC)

jobs
  └── backend-worker (Deployment + Command)

secrets (Secret)
  ├── db-credentials
  ├── redis-password
  └── api-keys
```

### Comandos básicos

```bash
# Aplicar manifests
kubectl apply -f infra/k8s/

# Ver pods
kubectl get pods -n ecommerce

# Logs
kubectl logs -f deployment/backend -n ecommerce

# Port-forward para debug
kubectl port-forward svc/backend 8000:8000 -n ecommerce
```

## Cloudflare

`Cloudflare` se usa como:

- DNS autoritativo para los dominios.
- WAF (Web Application Firewall).
- CDN y proxy inverso externo.
- Rate limiting perimetral.

### Configuración DNS

```dns
Tipo    Nombre                              Valor
A       panda-estampados.com               <VPS_IP>
A       www.panda-estampados.com           <VPS_IP>
A       admin.panda-estampados.com         <VPS_IP>
A       kitsune.com                         <VPS_IP>
A       www.kitsune.com                     <VPS_IP>
A       admin.kitsune.com                   <VPS_IP>
```

Proxy: a través de `Cloudflare` (nube naranja activada).

### Configuración recomendada

- SSL/TLS: `Full` (Cloudflare cifra hasta Caddy).
- Always HTTPS: activado.
- Auto-minify: HTML, CSS, JS.
- Brotli: activado.
- HTTP/3: activado.
- Bot Fight Mode: activado.

## Almacenamiento de objetos

Producción debería usar `S3` o compatible. Ver
[stack/database.md](database.md "stack/database.md") sección Storage.

## Monitoreo y logs

A definir. Stack candidato:

- **Prometheus** + **Grafana** para métricas.
- **Loki** + **Grafana** para logs centralizados.
- **Sentry** para tracking de errores.

## Respaldo

- Volúmenes `PersistentVolumeClaim` con `VolumeSnapshot`.
- Snapshots programados semanalmente.
- Retención 30 días mínimo.

## Documentación relacionada

- [operations/deployment.md](../operations/deployment.md "operations/deployment.md")
- [operations/docker.md](../operations/docker.md "operations/docker.md")
- [operations/caddy.md](../operations/caddy.md "operations/caddy.md")
- [operations/deployment.md](../operations/deployment.md "operations/deployment.md")
- [getting-started/caddy.md](../getting-started/caddy.md "getting-started/caddy.md")
