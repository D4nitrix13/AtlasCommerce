# Dominios locales — Multi-tienda con /etc/hosts

Simular múltiples dominios en local mapeando los hosts a `127.0.0.1`
en `/etc/hosts`. El middleware de Astro detecta el host y aplica el
branding y routing correspondiente.

## 1. Editar /etc/hosts

```bash
sudo nano /etc/hosts
```

Agregar al final:

```txt
# Ecommerce local — multi-tienda
127.0.0.1   panda-estampados.com
127.0.0.1   www.panda-estampados.com
127.0.0.1   admin.panda-estampados.com
127.0.0.1   kitsune.com
127.0.0.1   www.kitsune.com
127.0.0.1   admin.kitsune.com
```

One-liner:

```bash
sudo bash -c 'cat >> /etc/hosts <<EOF

# Ecommerce local — multi-tienda
127.0.0.1   panda-estampados.com
127.0.0.1   www.panda-estampados.com
127.0.0.1   admin.panda-estampados.com
127.0.0.1   kitsune.com
127.0.0.1   www.kitsune.com
127.0.0.1   admin.kitsune.com
EOF'
```

## 2. Verificar resolución DNS

```bash
getent hosts admin.panda-estampados.com
# 127.0.0.1      admin.panda-estampados.com

getent hosts admin.kitsune.com
# 127.0.0.1      admin.kitsune.com
```

Si no devuelve nada, la entrada no se guardó. Verificar el archivo:

```bash
tail -10 /etc/hosts
```

## 3. Configurar Caddy

`Caddy` resuelve los hosts a la app correcta. Ver
[getting-started/caddy.md](caddy.md "getting-started/caddy.md") para
detalle completo.

Caddyfile mínimo (copiar a `/etc/caddy/Caddyfile`):

```caddyfile
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

## 4. Levantar los servicios

```bash
# Backend
cd backend
php artisan serve --host=127.0.0.1 --port=8000 &

# Frontend
cd frontend
pnpm run dev &

# Caddy (con sudo porque necesita puerto 80)
sudo caddy run --config /etc/caddy/Caddyfile &
```

## 5. Probar los URLs

```bash
# Storefronts
curl -sI http://panda-estampados.com/
curl -sI http://kitsune.com/

# Admin
curl -sI http://admin.panda-estampados.com/login
curl -sI http://admin.kitsune.com/login

# API
curl -s -X POST http://panda-estampados.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@panda-estampados.test","password":"password"}'
```

En el browser:

- `http://panda-estampados.com` → storefront Panda.
- `http://admin.panda-estampados.com/login` → login rojo (Panda).
- `http://admin.kitsune.com/login` → login violeta (Kitsune).

## 6. Resolución de problemas

### El browser no resuelve el dominio

- Cache DNS: `sudo systemd-resolve --flush-caches`.
- Probar en modo incógnito.
- Verificar `/etc/hosts` con `cat`.

### `Caddy` dice "host not found"

`Caddy` no es el problema — la resolución DNS es del sistema. Si
`getent hosts` no devuelve nada, agregar la entrada manualmente.

### Login devuelve 422 "No tienes acceso"

El usuario admin no está asociado a la tienda. Verificar:

```bash
cd backend
php artisan tinker --execute='
$user = App\Models\Auth\User::find(1);
echo "User: " . $user->email . "\n";
echo "Stores: " . $user->stores->pluck("slug")->implode(", ") . "\n";
'
```

Si no tiene stores, correr:

```bash
php artisan db:seed --class=AdminUserSeeder
```

## 7. Limpieza

Para volver al estado original:

```bash
# Quitar entradas de /etc/hosts
sudo nano /etc/hosts
# Borrar las líneas agregadas

# Parar servicios
pkill -f "php artisan serve"
pkill -f "astro dev"
sudo systemctl stop caddy
```

## Próximo paso

[caddy.md](caddy.md "caddy.md") — Configuración completa de Caddy
como reverse proxy.
