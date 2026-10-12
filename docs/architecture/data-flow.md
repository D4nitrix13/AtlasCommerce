# Flujo Multi-Store — Arquitectura por Instancia

> Última actualización: 2026-09-26
> Stack: Docker / Caddy / Laravel 13 / Astro 7 /
> PostgreSQL 18 / Redis

Este documento describe el flujo de requests en AtlasCommerce
considerando la arquitectura multi-instancia desplegada. Cada
instancia `docker/instances/<stage>/<store>/` es una unidad
aislada con su propia BD, Redis, backend y frontend. Multi-store
opera dentro de una misma instancia cuando el dueño comparte
operación entre marcas (caso Panda Estampados + Kitsune). Multi-
instancia aísla tiendas de dueños distintos en servidores
separados.

---

## 1. Topología de despliegue

Una sola instancia Docker atiende los dominios de su tenant.
El routing por dominio se resuelve en el edge (Caddy) y se
enriquece en el backend vía middleware. Las instancias `mm-
boutique` y `morales-store` exponen dominios únicos; la
instancia `panda-estampados-and-kitsune` sirve dos marcas
(Panda Estampados y Kitsune Store) bajo una sola API.

```mermaid
flowchart TB
    classDef edge fill:#fef3c7,stroke:#92400e,color:#000
    classDef proxy fill:#d1fae5,stroke:#065f46,color:#000
    classDef fe fill:#dbeafe,stroke:#1e40af,color:#000
    classDef be fill:#fce7f3,stroke:#9d174d,color:#000
    classDef store fill:#f3e8ff,stroke:#6b21a8,color:#000
    classDef data fill:#fed7aa,stroke:#9a3412,color:#000

    User([Cliente / Browser])

    subgraph Edge["Edge — Internet"]
        CF[Cloudflare<br/>DNS · WAF · CDN]:::edge
    end

    subgraph CaddyLayer["Reverse Proxy — DMZ"]
        Caddy["Caddy<br/>TLS termination<br/>VirtualHost routing"]:::proxy
    end

    subgraph DockerStack["Docker Compose Stack — una sola instancia"]
        subgraph FE["Frontend — Astro SSR"]
            Astro["astro:4321<br/>lee Host → config tienda"]:::fe
        end
        subgraph BE["Backend — Laravel 13 + PHP 8.3"]
            App["app:8000<br/>backend único<br/>atiende las marcas del tenant<br/>Resuelve tienda por middleware"]:::be
            MW["ResolveStoreFromDomain<br/>middleware"]:::be
            SC["StoreContextService<br/>singleton por request"]:::be
        end
    end

    subgraph DataLayer["Data Layer"]
        PG[("PostgreSQL 18 + PostGIS<br/>schema multi-store<br/>store_id en cada tabla<br/>BelongsToStore global scope")]:::data
        RD[("Redis 7<br/>cache + sessions + queues<br/>namespace panda: · kitsune:")]:::data
        FTP[("FTP / S3<br/>assets · uploads<br/>namespace panda/ · kitsune/")]:::data
    end

    User -->|HTTPS| CF
    CF -->|HTTPS<br/>cached o proxied| Caddy

    Caddy -->|"panda-estampados.com<br/>www.panda-estampados.com<br/>kitsune.com<br/>www.kitsune.com"| Astro
    Caddy -->|"admin.panda-estampados.com<br/>admin.kitsune.com"| App
    Caddy -->|"api.panda-estampados.com<br/>api.kitsune.com"| App

    Astro -->|"fetch /api/*<br/>cookie + X-Forwarded-Host"| App

    App --> MW
    MW --> SC
    SC --> PG
    SC --> RD
    App -->|"upload/download assets"| FTP

    Astro -.->|"static assets"| FTP

    subgraph Stores
        S1["Panda Estampados<br/>store_id=1"]:::store
        S2["Kitsune Store<br/>store_id=2"]:::store
    end

    SC -.->|"setStore(id)"| Stores
```

Las instancias `mm-boutique` y `morales-store` siguen el mismo
patrón pero con dominios únicos:

- `mm-boutique.com`, `www.mm-boutique.com`, `admin.mm-boutique.com`.
- `morales-store.com`, `www.morales-store.com`,
  `admin.morales-store.com`.

Cada instancia es una unidad aislada: BD propia, Redis propio,
FTP propio. Ver [operations/deployment.md](../operations/deployment.md
"operations/deployment.md") para el detalle de despliegue.

---

## 2. Flujo de request — Storefront

Cómo se resuelve una visita a `panda-estampados.com/productos`:

```mermaid
sequenceDiagram
    autonumber
    actor U as Cliente
    participant CF as Cloudflare
    participant Caddy as Caddy
    participant FE as Frontend Astro
    participant BE as Backend Laravel
    participant MW as ResolveStoreFromDomain
    participant SC as StoreContextService
    participant DB as PostgreSQL
    participant Cache as Redis

    U->>CF: GET https://panda-estampados.com/productos
    CF->>Caddy: HTTPS request (Host: panda-estampados.com)
    Caddy->>FE: reverse_proxy a frontend:4321

    FE->>FE: Astro lee request.headers.host<br/>Resuelve tenant='panda'
    FE->>FE: Aplica theme + i18n + branding de Panda

    FE->>BE: GET /api/storefront/products<br/>Header: X-Forwarded-Host: panda-estampados.com<br/>Cookie: session (si existe)

    BE->>MW: handle(request)
    MW->>DB: SELECT s.* FROM stores s<br/>JOIN store_domains d<br/>WHERE d.domain='panda-estampados.com'<br/>AND s.is_active=true
    DB-->>MW: store_id=1, is_active=true
    MW->>SC: setStore($store) (singleton por request)
    MW-->>BE: next($request) con store en contexto

    BE->>DB: SELECT * FROM products<br/>WHERE store_id=1<br/>(BelongsToStore global scope)
    DB-->>BE: 12 productos de Panda
    BE->>Cache: GET panda:storefront:products:hash
    Cache-->>BE: miss
    BE->>Cache: SET panda:storefront:products:hash (TTL 60s)

    BE-->>FE: 200 OK { data: [...] }
    FE-->>Caddy: HTML renderizado (SSR)
    Caddy-->>CF: response
    CF-->>U: página con productos
```

**Punto clave:** la línea `WHERE store_id=1` **no la escribió
ningún developer**. La inyecta el global scope del trait
`BelongsToStore`. Si olvidás el trait, Laravel no filtra y
rompes el aislamiento. Por eso el `MultiTenantHttpIsolationTest`
es crítico.

Para instancias single-store (MM Boutique, Morales Store) el
mismo flujo aplica pero la BD solo conoce un `store_id`. El
middleware `ResolveStoreFromDomain` valida que el dominio
entrante pertenezca al tenant activo.

---

## 3. Flujo de request — Admin

Cómo se resuelve un login en `admin.panda-estampados.com`:

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Operador
    participant CF as Cloudflare
    participant Caddy as Caddy
    participant BE as Backend Laravel
    participant MW as ResolveStoreFromDomain
    participant Auth as Sanctum
    participant DB as PostgreSQL

    Admin->>CF: GET https://admin.panda-estampados.com/login
    CF->>Caddy: HTTPS (Host: admin.panda-estampados.com)
    Caddy->>BE: reverse_proxy a app:8000

    BE->>MW: handle(request)
    MW->>DB: SELECT store FROM store_domains<br/>WHERE domain='admin.panda-estampados.com'
    DB-->>MW: store_id=1 (Panda)
    MW->>BE: store resuelto en contexto

    Admin->>BE: POST /api/v1/auth/login<br/>{ email, password }
    BE->>Auth: AuthController@login
    Auth->>DB: SELECT user WHERE email=?
    DB-->>Auth: user con store_id=1
    Auth->>Auth: valida password + 2FA
    Auth->>DB: INSERT personal_access_tokens<br/>(Sanctum, scoped a store_id)
    Auth-->>Admin: 200 + cookie Secure + Bearer token

    Admin->>BE: GET /api/v1/admin/dashboard<br/>Cookie: panda_session
    BE->>MW: handle — store_id=1 confirmado
    BE->>DB: queries filtradas por store_id=1
    DB-->>BE: data de Panda
    BE-->>Admin: dashboard
```

**Diferencia con storefront:** el admin fija el `store_id`
**dos veces**: una por el dominio (`ResolveStoreFromDomain`)
y otra por el usuario logueado (campo `users.store_id`). Si
no coinciden, **se rechaza el request**. Esto evita que un
operador de Kitsune entre a `admin.panda-estampados.com`.

---

## 4. Conexiones entre servicios (Docker Compose)

```mermaid
flowchart LR
    subgraph Stack["Docker Stack — una instancia"]
        Astro["frontend<br/>:4321"]
        App["app<br/>:8000"]
        Worker["worker<br/>php artisan queue:work"]
    end
    subgraph Volumes["Named Volumes"]
        PG[("postgres<br/>:5432")]
        RD[("redis<br/>:6379")]
        FTP_S[("ftp / s3<br/>:21 / :443")]
    end

    Astro <-->|"HTTP /api/*"| App
    App -->|"5432 TCP"| PG
    App -->|"6379 TCP"| RD
    Worker -->|"5432 TCP"| PG
    Worker -->|"6379 TCP"| RD
    Worker -->|"21 / 443"| FTP_S
    App -->|"21 / 443"| FTP_S
```

> Los **Secrets** (DB password, Redis password, FTP creds)
> viven en `docker/instances/<stage>/<store>/environment/.env`
> montados como variables de entorno en cada container. Nunca
> en código ni en archivos versionados.

En **dev** la capa HTTP está separada: existe un sidecar
`nginx` (`:8000`) entre Caddy y el FPM (`backend:9000`). En
**producción** la imagen `app` ya trae `php-fpm` + nginx
unificados en un solo puerto `:8000`. Ver
[operations/deployment.md](../operations/deployment.md
"operations/deployment.md") para la diferencia concreta.

---

## 5. Decisiones clave del diseño

| Decisión                                                  | Por qué                                                                                                                |
| --------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| **1 backend, 1 frontend, N tiendas**                      | El 95% del código es idéntico. Multi-instance es duplicar operaciones, no valor.                                       |
| **Subdominio admin por tienda**                           | Cookies aisladas, deploy independiente, branding distinto, seguridad reforzada.                                        |
| **PostgreSQL compartido con `store_id` (dentro de stack)**| Una sola fuente de verdad por instancia. El global scope del trait aísla los datos.                                    |
| **Redis compartido con namespaces**                       | `panda:cart:123` y `kitsune:cart:123` conviven sin colisión. Invalidación cross-tenant no es problema porque no existe. |
| **FTP único con prefijos**                                | Los assets son archivos. El servidor no sabe de tiendas. Los nombres de archivo lleva la tienda.                       |
| **Middleware resuelve tienda por host**                   | Single source of truth para el store activo en cada request.                                                           |
| **Wildcard TLS `*.panda-estampados.com`** + `*.kitsune.com` | Un cert cubre storefront + admin por marca. Renovación automática con Caddy + Cloudflare.                            |

---

## 6. Lo que falta para llegar a producción

1. ✅ **Caddyfile** con los virtualhosts para cada dominio real —
   `docker/caddy/Caddyfile` (uno solo compartido entre instancias).
2. ✅ **Seeder `store_domains`** cargando `panda-estampados.com`,
   `admin.panda-estampados.com`, `kitsune.com`, `admin.kitsune.com`,
   `mm-boutique.com`, `admin.mm-boutique.com`, `morales-store.com`,
   `admin.morales-store.com`, `www.*`, etc. — vía
   `tenants:bootstrap seeds/<store>.json`.
3. ✅ **Campo `users.store_id`** con FK a `stores.id`.
4. 🔄 **Middleware/Policy** que valida que
   `users.store_id === store_context.store_id` antes de cualquier
   acción admin. Verificar cobertura en `policies/` por store.
5. ✅ **Astro middleware** que lee el `Host` del request y expone
   el tenant al SSR — `src/middleware.ts` con
   `detectTenantFromHost`.
6. ⏳ **Backups automatizados** de Postgres y Redis (cron + S3).
   Ver [operations/backups.md](../operations/backups.md
   "operations/backups.md").
7. ⏳ **Monitoreo** con Prometheus + Grafana (métricas de
   latencia por tienda). Stack aún no configurado.

## Decisiones arquitectónicas vinculadas

- [architecture/overview.md](overview.md "architecture/overview.md")
  — Diagrama del sistema actual.
- [architecture/multi-store.md](multi-store.md
  "architecture/multi-store.md") — Multi-store design dentro de
  una instancia.
- [architecture/decisions.md](decisions.md "architecture/decisions.md")
  — ADRs.
