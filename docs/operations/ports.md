# Puertos por instancia

Mapa completo de qué puerto escucha cada servicio, en qué instancia, y cómo
cambiarlo.

> **Convención del repo:** el **puerto del host** (lado izquierdo del mapeo
> `HOST:CONTAINER`) es **variable configurable** vía `*_PORT` en el `.env`
> de cada instancia. El **puerto dentro del container** (lado derecho) es
> **fijo** y matchea el default del servicio (Postgres 5432, Redis 6379, etc.).
> Esto permite shiftar puertos del host sin tocar la red interna de docker.

---

## TL;DR — Qué está publicado al host

| Instancia | Postgres | Redis | Backend | Frontend | FTP |
| --- | --- | --- | --- | --- | --- |
| `dev` | 6432 | 7379 | 9000 | 5321 | 2121 |
| `panda-estampados` | — | — | — | — | 21 |
| `mm-boutique` | — | — | — | — | — |
| `morales-store` | — | — | — | — | — |

`—` significa que **no se publica al host**. Esa decisión es deliberada:
las instancias de prod resuelven HTTP/HTTPS por Caddy vía dominio
(`panda-estampados.com`, etc.) y los servicios internos se hablan por la
red de docker. Publicarlos al host sumaría superficie de ataque sin
beneficio.

`dev` es la única que publica los 5 puertos, porque es donde vos accedés
desde el browser y clientes de DB.

---

## `dev` — entorno local

Compose: [`docker/dev/docker-compose.dev.yml`](../../docker/dev/docker-compose.dev.yml)
Env:      [`docker/dev/.env`](../../docker/dev/.env) (copia de
[`.env.example`](../../docker/dev/.env.example))

| Servicio | Puerto host | Puerto container | Variable | Default |
| --- | --- | --- | --- | --- |
| Postgres | 6432 | 5432 | `POSTGRES_PORT` | `6432` |
| Redis | 7379 | 6379 | `REDIS_PORT` | `7379` |
| Backend (Laravel) | 9000 | 8000 | `BACKEND_PORT` | `9000` |
| Frontend (Astro) | 5321 | 4321 | `FRONTEND_PORT` | `5321` |
| FTP (control) | 2121 | 21 | `FTP_PORT` | `2121` |
| FTP (PASV) | 40000–40100 | 40000–40100 | _(fijo)_ | — |

**Por qué los defaults están shifted:** para no chocar con un Postgres
local en `:5432`, un Redis local en `:6379`, un `php artisan serve` en
`:8000`, el dev server de Astro en `:4321`, o un vsftpd local en `:21`.
Shift = +1000 (excepto FTP, que va a +2100 para no solaparse con el
rango PASV).

**URLs para abrir en el browser / cliente:**

| Qué | URL |
| --- | --- |
| Frontend | `http://localhost:5321` |
| API | `http://localhost:9000/api` |
| DB GUI (DBeaver, TablePlus) | `localhost:6432` (user/pass del `.env`) |
| Redis GUI | `localhost:7379` |
| Cliente FTP | `localhost:2121` |

---

## `panda-estampados` — producción multi-tenant

Compose: [`docker/instances/panda-estampados/docker-compose.yml`](../../docker/instances/panda-estampados/docker-compose.yml)
Env:      [`docker/instances/panda-estampados/.env`](../../docker/instances/panda-estampados/.env)

| Servicio | Puerto host | Puerto container | Variable | Default |
| --- | --- | --- | --- | --- |
| FTP (control) | **21** | 21 | `FTP_PORT` | `21` |
| FTP (PASV) | 40000–40100 | 40000–40100 | _(fijo)_ | — |

Es la **única** instancia de prod que publica FTP al host, y lo hace en
`:21` (default FTP). Si en el host de deploy hay otro servicio en `:21`,
se cambia con `FTP_PORT=2121` (o el que esté libre) en el `.env` de la
instancia.

El resto de los servicios (postgres, redis, backend, frontend) **no se
publican** — Caddy los rutea por dominio.

---

## `mm-boutique` — producción single-tenant

Compose: [`docker/instances/mm-boutique/docker-compose.yml`](../../docker/instances/mm-boutique/docker-compose.yml)
Env:      [`docker/instances/mm-boutique/.env`](../../docker/instances/mm-boutique/.env)

| Servicio | Puerto host | Notas |
| --- | --- | --- |
| (ninguno) | — | Nada se publica al host. |

El FTP está **deliberadamente no publicado** para no colisionar con el
`21` de panda-estampados en el mismo host. Si el deploy lo requiere expuesto,
agregar `ports:` al servicio `ftp` en el compose y setear `FTP_PORT=2121`
(o el que esté libre) en el `.env`.

---

## `morales-store` — producción single-tenant

Compose: [`docker/instances/morales-store/docker-compose.yml`](../../docker/instances/morales-store/docker-compose.yml)
Env:      [`docker/instances/morales-store/.env`](../../docker/instances/morales-store/.env)

| Servicio | Puerto host | Notas |
| --- | --- | --- |
| (ninguno) | — | Nada se publica al host. |

Idem `mm-boutique`: FTP interno, no publicado por defecto.

---

## ¿Por qué la asimetría entre dev y prod?

| Cosa | dev | prod |
| --- | --- | --- |
| Quién accede desde el host | vos (browser, DB GUI, FTP client) | Caddy (HTTP/HTTPS) |
| Por qué se publican puertos | para que vos los uses | no hace falta, Caddy rutea por dominio |
| Convención de puertos | shifted +1000/+2100 | defaults del servicio (FTP 21) |

En prod real, **cada instancia corre en su propio host** (o contenedor con
IP pública). Podés usar el `:21` default sin chocar con nada. El shift de
dev es solo para que vos puedas correr dev mientras el resto de la
máquina sigue funcionando.

---

## Cómo verificar puertos en uso

```bash
# Ver qué containers están публицando puertos al host:
docker ps --format "table {{.Names}}\t{{.Ports}}"

# Ver qué proceso del host tiene un puerto ocupado (ej. :21):
sudo ss -tlnp | grep ':21 '

# Ver solo los containers de una instancia:
./scripts/docker-stack.sh ps
./scripts/docker-stack.sh status dev
```

## Cómo cambiar un puerto

1. **Editar el `.env`** de la instancia. Ejemplo para dev, cambiar el
   backend a `:9001`:
   ```ini
   BACKEND_PORT=9001
   ```
2. **Reconstruir / reiniciar** la instancia:
   ```bash
   ./scripts/docker-stack.sh down dev
   ./scripts/docker-stack.sh build dev
   ./scripts/docker-stack.sh start dev
   ```
   No siempre hace falta `build` (solo si cambió un Dockerfile). Para
   cambios de puerto alcanza con `down` + `start`.
3. **Verificar** que el nuevo puerto está publicado:
   ```bash
   ./scripts/docker-stack.sh ps
   ```

## Cómo aplicar un cambio de `.env` sin rebuild

```bash
./scripts/docker-stack.sh down dev       # baja containers (preserva volúmenes)
./scripts/docker-stack.sh start dev      # levanta con el nuevo .env
```

`build` solo es necesario si cambia un `Dockerfile.*` o el contexto de
build. Cambios de variables de entorno no requieren rebuild.

## Si dos instancias quieren el mismo puerto del host

Solo `panda-estampados` публицa algo al host hoy. Si en el futuro dos
instancias necesitan exponer el mismo puerto (ej. FTP 21), hay 3
opciones:

1. **Puerto distinto por instancia** (lo más simple): `FTP_PORT=21` para
   Panda, `FTP_PORT=2121` para MM, `FTP_PORT=3121` para Morales.
2. **IPs distintas del host** (multihoming): cada instancia en una IP
   pública diferente.
3. **Proxy TCP** (HAProxy, Caddy TCP, etc.) adelante que rutea por SNI
   o por puerto externo.

Para dev local, la opción 1 es la indicada.
