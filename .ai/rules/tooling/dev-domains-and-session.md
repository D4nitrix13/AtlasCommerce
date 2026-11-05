# Dev domains & sesión — cómo se accede y por qué la auth se rompe

> Regla load-bearing para tocar `docker/**`, `frontend/astro.config.mjs`
> o `frontend/src/lib/{api,csrf}.ts`. Verificada el 2026-11-05 tras un
> smoke que costó reconstruir toda la cadena de sesión.

## Acceso a las vistas en dev

El entrypoint limpio (`http://panda-estampados.com`, puerto 80) lo
sirve **Caddy del host** (systemd, NO container):

```bash
./scripts/docker-stack.sh caddy | sudo tee /etc/caddy/Caddyfile
sudo systemctl restart caddy      # o start si está parado
```

Si `curl panda-estampados.com` da connection refused: `systemctl
is-active caddy` — lo normal es que esté `inactive` (el Caddyfile ya
suele estar instalado). Sin Caddy, el acceso es por el puerto dev del
container frontend (`http://<dominio>:5321`), resuelto por `/etc/hosts`.

## Tres invariantes de la sesión (romper cualquiera = 401/419)

1. **`SANCTUM_STATEFUL_DOMAINS` matchea `Origin` CON puerto.** Sanctum
   compara `host:puerto` del `Origin`/`Referer` contra la lista (por
   eso ya existen entradas como `localhost:3000`). Si se entra por
   `<dominio>:5321`, ese `host:5321` tiene que estar en la lista del
   compose **y hay que recrear el backend** (`docker-stack.sh up`):
   un `restart` no recarga envs. Sin stateful, `hasSession()` es false,
   el login emite solo un Bearer (que el frontend ignora) y no hay
   `Set-Cookie`. Diagnóstico: `curl -i -H "Origin: …" …/api/v1/auth/me`
   y buscar `Set-Cookie: ecommerce-dev-session`.

2. **El proxy dev de Astro debe cubrir `/api` Y `/sanctum`.**
   `bootstrapCsrf` llama `fetch('/sanctum/csrf-cookie')`, que vive
   FUERA de `/api`; sin proxy ese fetch da 404 → no hay cookie
   `XSRF-TOKEN` → todo POST responde 419 CSRF mismatch. Ambos proxies
   con `changeOrigin: false` (Vite default `true` manda `Host: nginx:8000`
   y el middleware `store.domain` responde "Store not found").

3. **SSR de rutas autenticadas: reenviar `Cookie` + `Origin`.** Sanctum
   solo arranca sesión para requests con `Origin`/`Referer` stateful;
   la cookie sin Origin = 401 "Unauthenticated". En el frontmatter de
   Astro: `headers: { Cookie: Astro.request.headers.get('cookie') ?? '',
   Origin: Astro.url.origin }` en `apiFetch` (soporta headers custom).
   Receta canónica: `admin/catalog/products/[id].astro`.

## Token de diagnóstico rápido

```bash
# ¿Hay sesión? (esperar Set-Cookie en la respuesta)
curl -s -i -H "Host: admin.panda-estampados.com" \
  -H "Origin: http://admin.panda-estampados.com:5321" \
  -H "Accept: application/json" \
  http://127.0.0.1:9000/api/v1/auth/me | grep -i set-cookie
```
