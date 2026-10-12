# Puertos por instancia

Mapa completo de qué puerto escucha cada servicio, en qué instancia, y cómo
cambiarlo.

> **Convención del repo:** el **puerto del host** (lado izquierdo del mapeo
> `HOST:CONTAINER`) es **variable configurable** vía `*_PORT` en el
> `environment/.env` de cada instancia. El **puerto dentro del container**
> (lado derecho) es **fijo** y matchea el default del servicio
> (Postgres `5432`, Redis `6379`, etc.). Esto permite shiftar puertos
> del host sin tocar la red interna de docker.

---

## TL;DR — Qué está publicado al host

| Stage · Store                          | Postgres | Redis | Backend | Frontend | FTP   |
| -------------------------------------- | -------- | ----- | ------- | -------- | ----- |
| `dev` (los 3 stores usan mismos ports) | `6432`   | `7379`| `9000`  | `5321`   | `2121`|
| `prod panda-estampados-and-kitsune`    | —        | —     | —       | —        | `21`  |
| `prod mm-boutique`                     | —        | —     | —       | —        | —     |
| `prod morales-store`                   | —        | —     | —       | —        | —     |

> Los defaults de dev (`6432`, `7379`, `9000`, `5321`, `2121`) viven en
> `docker/instances/development/<store>/environment/.env.example` de cada
> tienda. Cada instancia tiene su propio `.env` (gitignored) que pisa
> esos valores; shiftar puertos por instancia es la forma soportada de
> correr 2 dev en paralelo.

`—` significa que **no se publica al host**. Esa decisión es deliberada:
las instancias de prod resuelven HTTP/HTTPS por Caddy vía dominio
(`panda-estampados.com`, `mm-boutique.com`, `morales-store.com`, etc.)
y los servicios internos se hablan por la red de docker. Publicarlos
al host sumaría superficie de ataque sin beneficio.

`dev` publica los 5 puertos base en todas las instancias. Si necesitás
correr 2 instancias de dev simultáneas (por ej. `panda-estampados-and-kitsune`
y `mm-boutique`), shiftá `BACKEND_PORT`, `FRONTEND_PORT`, `POSTGRES_PORT`,
`REDIS_PORT` y/o `FTP_PORT` en uno de los `.env` para que no colisionen.

---

## Dev — entorno local

Cada store tiene su propio compose bajo
`docker/instances/development/<store>/`. Las tres comparten los mismos
defaults de puerto host (shifted `+1000`/`+2100`).

Compose dev (ejemplo Panda):
[`docker/instances/development/panda-estampados-and-kitsune/docker-compose.yml`](../../docker/instances/development/panda-estampados-and-kitsune/docker-compose.yml)
Env (template):
[`.env.example`](../../docker/instances/development/panda-estampados-and-kitsune/environment/.env.example)
(la copia operativa `.env` es local, gitignored).

### Puertos base (compartidos por las 3 instancias dev)

| Servicio          | Puerto host | Puerto container | Variable       | Default |
| ----------------- | ----------- | ---------------- | -------------- | ------- |
| Postgres          | `6432`      | `5432`           | `POSTGRES_PORT`| `6432`  |
| Redis             | `7379`      | `6379`           | `REDIS_PORT`   | `7379`  |
| Backend (Laravel) | `9000`      | `8000` (nginx)   | `BACKEND_PORT` | `9000`  |
| Frontend (Astro)  | `5321`      | `4321`           | `FRONTEND_PORT`| `5321`  |
| FTP (control)     | `2121`      | `21`             | `FTP_PORT`     | `2121`  |
| FTP (PASV)        | `40000-40100` | `40000-40100`  | _(fijo)_       | —       |

> **Por qué el backend sale en `:9000` pero el container expone `:8000`:**
> hay un **sidecar `nginx`** dentro del compose dev que escucha en
> `:8000` y hace reverse proxy a FPM en `:9000`. El port mapping
> `${BACKEND_PORT:-9000}:8000` apunta al sidecar, no a FPM directo.
> Caddy en el host habla HTTP con el sidecar vía `:9000`.

### Servicios auxiliares dev (perfiles / dev-extra)

Levantan bajo profiles/compose-include definidos en cada compose dev:

| Servicio        | Puerto host | Puerto container | Variable              |
| --------------- | ----------- | ---------------- | --------------------- |
| Mailpit (UI)    | `8025`      | `8025`           | `MAILPIT_UI_PORT`     |
| Mailpit (SMTP)  | `1025`      | `1025`           | `MAILPIT_SMTP_PORT`   |
| pgAdmin         | `6050`      | `80`             | `PGADMIN_PORT`        |
| Redis Insight   | `6540`      | `5540`           | `REDIS_INSIGHT_PORT`  |
| Filebrowser     | `9080`      | `80`             | `FILEBROWSER_PORT`    |

### URLs para abrir en el browser / cliente

| Qué                          | URL                              |
| ---------------------------- | -------------------------------- |
| Frontend (Astro)             | `http://localhost:5321`          |
| Backend API                  | `http://localhost:9000/api`      |
| DB GUI (DBeaver, TablePlus)  | `localhost:6432`                 |
| Redis GUI (Insight)          | `http://localhost:6540`          |
| pgAdmin                      | `http://localhost:6050`          |
| Mailpit (inbox dev)          | `http://localhost:8025`          |
| Filebrowser (uploads)        | `http://localhost:9080`          |
| Cliente FTP                  | `localhost:2121`                 |

**Por qué los defaults están shifted:** para no chocar con un Postgres
local en `:5432`, un Redis local en `:6379`, un `php artisan serve` en
`:8000`, el dev server de Astro en `:4321`, o un vsftpd local en `:21`.
Shift = `+1000` (excepto FTP, que va a `+2100` para no solaparse con el
rango PASV).

---

## Prod — multi-instancia

Cada store corre en su **propio VPS / host**, aislado. El único puerto
que Caddy publica al exterior es `80` y `443`; los servicios internos
se hablan por la red Docker privada del compose.

### `panda-estampados-and-kitsune` — producción multi-tenant

Compose:
[`docker/instances/production/panda-estampados-and-kitsune/docker-compose.yml`](../../docker/instances/production/panda-estampados-and-kitsune/docker-compose.yml)
Env (template):
[`docker/instances/production/panda-estampados-and-kitsune/environment/.env.example`](../../docker/instances/production/panda-estampados-and-kitsune/environment/.env.example)
Caddyfile:
[`docker/instances/production/panda-estampados-and-kitsune/Caddyfile`](../../docker/instances/production/panda-estampados-and-kitsune/Caddyfile)

| Servicio      | Puerto host | Puerto container | Variable   | Default |
| ------------- | ----------- | ---------------- | ---------- | ------- |
| FTP (control) | `21`        | `21`             | `FTP_PORT` | `21`    |
| FTP (PASV)    | `40000-40100` | `40000-40100`  | _(fijo)_   | —       |

Es la **única** instancia de prod que publica FTP al host, en `:21`
(default FTP). Si en el host de deploy hay otro servicio en `:21`,
se cambia con `FTP_PORT=2121` (o el que esté libre) en el `.env`.

El resto de los servicios (postgres, redis, backend, frontend) **no se
publican** — Caddy los rutea por dominio dentro de la misma red
Docker.

### `mm-boutique` — producción single-tenant

Compose:
[`docker/instances/production/mm-boutique/docker-compose.yml`](../../docker/instances/production/mm-boutique/docker-compose.yml)
Env (template):
[`.env.example`](../../docker/instances/production/mm-boutique/environment/.env.example)
Caddyfile:
[`docker/instances/production/mm-boutique/Caddyfile`](../../docker/instances/production/mm-boutique/Caddyfile)

| Servicio | Puerto host | Notas                          |
| -------- | ----------- | ------------------------------ |
| (ninguno)| —           | Nada se publica al host.       |

El FTP existe en el compose (interno, no publicado) para no exponerlo
a internet; los assets se sirven vía backend Laravel. Si el deploy
requiere FTP expuesto, agregar `ports:` al servicio `ftp` y setear
`FTP_PORT` en `.env`.

### `morales-store` — producción single-tenant

Compose:
[`docker/instances/production/morales-store/docker-compose.yml`](../../docker/instances/production/morales-store/docker-compose.yml)
Env (template):
[`.env.example`](../../docker/instances/production/morales-store/environment/.env.example)
Caddyfile:
[`docker/instances/production/morales-store/Caddyfile`](../../docker/instances/production/morales-store/Caddyfile)

| Servicio | Puerto host | Notas                          |
| -------- | ----------- | ------------------------------ |
| (ninguno)| —           | Nada se publica al host.       |

Idem `mm-boutique`: FTP interno, no publicado por defecto.

---

## ¿Por qué la asimetría entre dev y prod?

| Cosa                                    | dev                       | prod                  |
| --------------------------------------- | ------------------------- | --------------------- |
| Quién accede desde el host              | vos (browser, DB GUI)     | Caddy (HTTP/HTTPS)    |
| Por qué se publican puertos             | para que vos los uses     | no hace falta         |
| Convención de puertos                   | shifted `+1000`/`+2100`   | defaults del servicio |
| Quién publica `80`/`443`                | Caddy local (en `:80`)    | Caddy en el host      |

En prod real, **cada instancia corre en su propio host** (o contenedor
con IP pública). Podés usar el `:21` default sin chocar con nada. El
shift de dev es solo para que vos puedas correr dev mientras el resto
de la máquina sigue funcionando.

---

## Cómo verificar puertos en uso

```bash
# Ver qué containers están publicando puertos al host:
docker ps --format "table {{.Names}}\t{{.Ports}}"

# Ver qué proceso del host tiene un puerto ocupado (ej. :21):
sudo ss -tlnp | grep ':21 '

# Ver solo los containers de una instancia (canónico):
./scripts/docker-stack.sh ps dev panda-estampados-and-kitsune
./scripts/docker-stack.sh status dev panda-estampados-and-kitsune
```

`./scripts/docker-stack.sh` recibe siempre `<cmd> <stage> <store>
[<servicio>]`. Ver `scripts/docker-stack.sh help` para el catálogo
completo de subcomandos.

## Cómo cambiar un puerto

1. **Editar el `environment/.env`** de la instancia. Ejemplo para dev,
   cambiar el backend a `:9001`:

   ```ini
   BACKEND_PORT=9001
   ```

2. **Reconstruir / reiniciar** la instancia:

   ```bash
   ./scripts/docker-stack.sh down dev panda-estampados-and-kitsune
   ./scripts/docker-stack.sh start dev panda-estampados-and-kitsune
   ```

   No siempre hace falta `build` (solo si cambió un `Dockerfile.*`).
   Para cambios de puerto alcanza con `down` + `start`.

3. **Verificar** que el nuevo puerto está publicado:

   ```bash
   ./scripts/docker-stack.sh ps dev panda-estampados-and-kitsune
   ```

## Cómo aplicar un cambio de `.env` sin rebuild

```bash
./scripts/docker-stack.sh down dev panda-estampados-and-kitsune
./scripts/docker-stack.sh start dev panda-estampados-and-kitsune
```

`build` solo es necesario si cambia un `Dockerfile.*` o el contexto
de build. Cambios de variables de entorno no requieren rebuild.

## Si dos instancias quieren el mismo puerto del host

En dev, los 3 stores usan los mismos defaults shifted. Para correr 2
stores de dev simultáneos hay 3 opciones:

1. **Puerto distinto por instancia** (lo más simple): shiftá los
   `*_PORT` en uno de los `.env` (`BACKEND_PORT=9100`, etc.). El
   compose lee de `environment/.env` y aplica el override al port
   mapping.
2. **Levantar de a una instancia** (la mayoría de los casos): bajá
   la primera con `./scripts/docker-stack.sh down dev <store>` antes
   de levantar la segunda.
3. **Hosts distintos** (Docker `extra_hosts` o `network_mode host`):
   raro en dev; tiene sentido solo si querés simular tráfico entre
   tiendas.
