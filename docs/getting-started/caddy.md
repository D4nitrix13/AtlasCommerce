# Caddy — Reverse proxy para desarrollo

Caddy enruta el tráfico según el host. Para dev, escucha en puerto 80
y rutea `/api/*` al backend y todo lo demás al frontend.

## 1. Archivo de configuración

Crear `Caddyfile` en la raíz del proyecto (ya existe):

```caddyfile
# ============================================================
# Caddyfile — Ecommerce local (multi-tienda)
# ============================================================
{
    auto_https off
    admin off
}

http:// {
    @hostnames host pandas-estampados.com www.pandas-estampados.com \
                       admin.pandas-estampados.com \
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

## 2. Validar el Caddyfile

```bash
caddy adapt --config ./Caddyfile
```

Si hay errores de sintaxis, los reporta aquí.

## 3. Probar formato

```bash
caddy fmt --overwrite ./Caddyfile
```

Auto-formatea el archivo con la convención de Caddy.

## 4. Instalar como service del sistema

El Caddyfile por defecto del sistema está en `/etc/caddy/Caddyfile`. Para
usar el nuestro:

```bash
sudo cp ./Caddyfile /etc/caddy/Caddyfile
sudo caddy fmt --overwrite /etc/caddy/Caddyfile
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl restart caddy
```

## 5. Alternativa: Caddy en foreground

Si no querés usar el service del sistema:

```bash
sudo caddy run --config ./Caddyfile
```

Útil para debugging (logs en vivo).

## 6. Verificar que está corriendo

```bash
systemctl status caddy
# Active: active (running) since ...

# O en foreground
ps aux | grep -v grep | grep "caddy run"
```

## 7. Probar el routing

```bash
# Storefront Panda → frontend
curl -sI http://pandas-estampados.com/

# Admin Panda → frontend (rewrite a /es/admin/auth/login)
curl -sI http://admin.pandas-estampados.com/login

# API → backend
curl -sI http://pandas-estampados.com/api/auth/login

# Host desconocido → 404
curl -sI http://example.com/
```

## 8. Logs

Service del sistema:

```bash
sudo journalctl -u caddy -f
```

Foreground:

```bash
# Los logs van a stdout
```

## 9. Configuración para producción

En producción, Caddy maneja TLS automático con Let's Encrypt. Cambiar
`auto_https off` por `auto_https on` (que es el default) y agregar
los dominios reales:

```caddyfile
{
    # email para Let's Encrypt
    email ops@example.com
}

pandas-estampados.com, www.pandas-estampados.com,
admin.pandas-estampados.com {
    reverse_proxy backend:8000
}

kitsune.com, www.kitsune.com, admin.kitsune.com {
    reverse_proxy backend:8000
}
```

Caddy lee los certificados vía DNS-01 challenge (requiere provider
configurado: Cloudflare, Route53, etc.).

## 10. Problemas comunes

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

El `Caddyfile` solo lista los hosts específicos. `localhost` no está
incluido. Agregar a la lista `@hostnames` si querés usar `localhost`
directo.

### Caddy no levanta después de cambios

```bash
sudo systemctl restart caddy
sudo journalctl -u caddy -n 50
```

## Próximo paso

[seed.md](seed.md "seed.md") — Cargar datos iniciales para empezar
a trabajar.
