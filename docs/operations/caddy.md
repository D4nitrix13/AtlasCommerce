# Caddy — Reverse Proxy

Caddy enruta las peticiones a backend/frontend según el host. Este
doc consolida el antiguo `getting-started/caddy.md` (setup dev
detallado) con `operations/caddy.md` (visión conceptual, headers,
CORS).

## Visión general

`Caddy` actúa como reverse proxy con TLS automático. Enruta las
peticiones según el dominio de origen hacia el servicio
correspondiente.

### Por qué Caddy

- **TLS automático** — Certificados Let's Encrypt sin configuración
  manual
- **Configuración simple** — Sintaxis clara y legible
- **Renovación automática** — Certificados se renuevan solos
- **Reverse proxy** — Enrutamiento por dominio

### Dominios

- `panda-estampados.com` → Frontend `Astro` (Tienda Panda)
- `kitsune.com` → Frontend `Astro` (Tienda Kitsune)
- `admin.panda-estampados.com` → Frontend `Astro` (Panel
  administrativo)
- `api.panda-estampados.com` → Backend `Laravel` (API REST)

### Puertos

- **Puerto 80** — HTTP, `Caddy` redirige a 443
- **Puerto 443** — HTTPS, `Caddy` reverse proxy

Para topología de infraestructura completa y cómo se inserta Caddy
frente a los servicios Docker, ver
[`operations/deployment.md`](deployment.md "deployment.md").

## Configuración recomendada

### Caddyfile de dev (multi-tienda local)

Ubicación: `Caddyfile` en la raíz del proyecto.

```caddyfile
# ============================================================
# Caddyfile — AtlasCommerce local (multi-tienda)
# ============================================================
{
    auto_https off
    admin off
}

http:// {
    @hostnames host panda-estampados.com www.panda-estampados.com \
                       admin.panda-estampados.com \
                       kitsune.com www.kitsune.com admin.kitsune.com

    handle @hostnames {
        @api path /api/*
        handle @api {
            reverse_proxy 127.0.0.1:8000
        }
        handle {
            reverse_proxy 127.0.0.1:4321
        }
    }

    handle {
        respond "Not found" 404
    }
}
```

> **Multi-tenant routing detallado**: ver
> [`docs/agent-skills/caddyfile-multi-tenant-routing/SKILL.md`](../agent-skills/caddyfile-multi-tenant-routing/SKILL.md "SKILL.md").

### Caddyfile de producción

Conceptualmente lo mismo pero con `auto_https on` (default) y los
dominios reales. Para el archivo completo y deploy en VPS, ver
[`operations/deployment.md`](deployment.md "deployment.md") §
"Caddyfile conceptual" y "Configurar Caddy".

```caddyfile
{
    # email para Let's Encrypt
    email ops@example.com
}

panda-estampados.com, www.panda-estampados.com,
admin.panda-estampados.com {
    reverse_proxy frontend:4173
}

kitsune.com, www.kitsune.com, admin.kitsune.com {
    reverse_proxy frontend:4173
}

api.panda-estampados.com {
    reverse_proxy backend:8000
}
```

Caddy lee los certificados vía DNS-01 challenge (requiere provider
configurado: Cloudflare, Route53, etc.).

## TLS / HTTPS

`Caddy` gestiona TLS automáticamente:

1. Al iniciar, `Caddy` solicita certificados a Let's Encrypt.
2. Los certificados se almacenan en `caddy_data`.
3. Se renuevan automáticamente antes de expirar.
4. Las peticiones HTTP (puerto 80) se redirigen a HTTPS.

```text
http://panda-estampados.com → https://panda-estampados.com
```

## Headers de seguridad (producción)

```caddyfile
# Headers de seguridad (recomendado)
header {
    X-Content-Type-Options nosniff
    X-Frame-Options DENY
    Referrer-Policy strict-origin-when-cross-origin
    Strict-Transport-Security max-age=31536000
}
```

## CORS para la API

```caddyfile
api.panda-estampados.com {
    header {
        Access-Control-Allow-Origin *
        Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
        Access-Control-Allow-Headers "Authorization, Content-Type, X-Store-Id"
    }

    reverse_proxy backend:8000
}
```

## Compresión

```caddyfile
encode gzip zstd
```

## Rate limiting (futuro)

```caddyfile
rate_limit {
    zone api_limit {
        key {remote_host}
        events 100
        window 1m
    }
}
```

## Workflow dev — validar, formatear, instalar

### Validar el Caddyfile

```bash
caddy adapt --config ./Caddyfile
```

Si hay errores de sintaxis, los reporta aquí.

### Formatear el Caddyfile

```bash
caddy fmt --overwrite ./Caddyfile
```

Auto-formatea el archivo con la convención de Caddy.

### Instalar como service del sistema

El Caddyfile por defecto del sistema está en `/etc/caddy/Caddyfile`.
Para usar el nuestro:

```bash
sudo cp ./Caddyfile /etc/caddy/Caddyfile
sudo caddy fmt --overwrite /etc/caddy/Caddyfile
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl restart caddy
```

### Alternativa: Caddy en foreground

Si no querés usar el service del sistema:

```bash
sudo caddy run --config ./Caddyfile
```

Útil para debugging (logs en vivo, no hace falta journalctl).

### Verificar que está corriendo

```bash
systemctl status caddy
# Active: active (running) since ...

# O en foreground
ps aux | grep -v grep | grep "caddy run"
```

### Probar el routing

```bash
# Storefront Panda → frontend
curl -sI http://panda-estampados.com/

# Admin Panda → frontend (rewrite a /es/admin/auth/login)
curl -sI http://admin.panda-estampados.com/login

# API → backend
curl -sI http://panda-estampados.com/api/v1/auth/login

# Host desconocido → 404
curl -sI http://example.com/
```

### Logs

Service del sistema:

```bash
sudo journalctl -u caddy -f
```

Foreground:

```bash
# Los logs van a stdout
```

## Problemas comunes

### `bind: address already in use`

Otro proceso está usando el puerto 80. Identificar:

```bash
ss -tlnp | grep :80
```

Si es el `caddy.service` viejo:

```bash
sudo systemctl stop caddy
sudo caddy run --config ./Caddyfile
```

### `Caddyfile: line N: unrecognized directive`

Error de sintaxis. Validar:

```bash
caddy validate --config ./Caddyfile
```

### `localhost:8080` devuelve 404

El `Caddyfile` solo lista los hosts específicos. `localhost` no
está incluido. Agregar a la lista `@hostnames` si querés usar
`localhost` directo (o usar uno de los hosts
`panda-estampados.com`, etc.).

### Caddy no levanta después de cambios

```bash
sudo systemctl restart caddy
sudo journalctl -u caddy -n 50
```

## Estado actual

- **Caddyfile (dev)** — Funcional con multi-tenant routing local
- **TLS producción** — Funcional con Caddy + Let's Encrypt
- **Headers de seguridad** — Pendiente
- **CORS** — Pendiente
- **Compresión** — Pendiente
