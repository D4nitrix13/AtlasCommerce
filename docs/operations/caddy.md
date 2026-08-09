# Caddy — Reverse Proxy

## Visión general

`Caddy` actúa como reverse proxy con TLS automático.
Enruta las peticiones según el dominio de origen
hacia el servicio correspondiente.

## Por qué Caddy

- **TLS automático** — Certificados Let's Encrypt sin
  configuración manual
- **Configuración simple** — Sintaxis clara y legible
- **Renovación automática** — Certificados se renuevan
  solos
- **Reverse proxy** — Enrutamiento por dominio

## Caddyfile conceptual

```caddyfile
# Tienda Panda Estampados
panda-estampados.com {
    reverse_proxy frontend:4173
}

# Tienda Kitsune
kitsune.com {
    reverse_proxy frontend:4173
}

# Panel administrativo
admin.panda-estampados.com {
    reverse_proxy frontend:4173
}

# API Backend
api.panda-estampados.com {
    reverse_proxy backend:8000
}
```

## Dominios

- `panda-estampados.com` → Frontend `Astro`
  (Tienda Panda)
- `kitsune.com` → Frontend `Astro` (Tienda Kitsune)
- `admin.panda-estampados.com` → Frontend `Astro`
  (Panel administrativo)
- `api.panda-estampados.com` → Backend `Laravel`
  (API REST)

## TLS / HTTPS

`Caddy` gestiona TLS automáticamente:

1. Al iniciar, `Caddy` solicita certificados a
   Let's Encrypt.
2. Los certificados se almacenan en `caddy_data`.
3. Se renuevan automáticamente antes de expirar.
4. Las peticiones HTTP (puerto 80) se redirigen a HTTPS.

```txt
http://panda-estampados.com → https://panda-estampados.com
```

## Puertos

- **Puerto 80** — HTTP, `Caddy` redirige a 443
- **Puerto 443** — HTTPS, `Caddy` reverse proxy

## Headers de seguridad

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
# Habilitar compresión gzip/brotli
encode gzip zstd
```

## Rate limiting (futuro)

```caddyfile
# Limitar peticiones por IP (futuro)
rate_limit {
    zone api_limit {
        key {remote_host}
        events 100
        window 1m
    }
}
```

## Estado actual

- **Caddyfile** — **Pendiente** de crear
- **TLS** — Funcional con `Caddy`
- **Headers de seguridad** — Pendiente
- **CORS** — Pendiente
- **Compresión** — Pendiente
