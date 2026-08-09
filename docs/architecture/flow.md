# Flujo Multi-Tienda — Arquitectura Final

> Última actualización: 2027-08-01
> Stack: Docker / Kubernetes / Caddy / Laravel 13 / Astro 7 /
> PostgreSQL 18 / Redis

---

## 1. Topología de despliegue

Una sola instancia de cada servicio atienda ambas tiendas. El routing por
dominio se resuelve en el edge (Caddy) y se enriquece en el backend vía
middleware.

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

    subgraph K8s["Kubernetes Cluster"]
        subgraph FE["Frontend — Astro SSR"]
            Astro["astro:4321<br/>1 pod · N réplicas<br/>lee Host → config tienda"]:::fe
        end
        subgraph BE["Backend — Laravel 13 + PHP 8.3"]
            PHP["php-fpm:9000<br/>1 sola app<br/>atiende Panda + Kitsune<br/>Resuelve tienda por middleware"]:::be
            MW["ResolveStoreFromDomain<br/>middleware"]:::be
            SC["StoreContextService<br/>singleton por request"]:::be
        end
    end

    subgraph DataLayer["Data Layer"]
        PG[("PostgreSQL 18 + PostGIS<br/>schema multi-tenant<br/>store_id en cada tabla<br/>BelongsToStore global scope")]:::data
        RD[("Redis 7<br/>cache + sessions + queues<br/>namespace panda: · kitsune:")]:::data
        FTP[("FTP / S3<br/>assets · uploads<br/>namespace panda/ · kitsune/")]:::data
    end

    User -->|HTTPS| CF
    CF -->|HTTPS<br/>cached o proxied| Caddy

    Caddy -->|"panda-estampados.com<br/>www.panda-estampados.com<br/>kitsune.com<br/>www.kitsune.com"| Astro
    Caddy -->|"admin.panda-estampados.com<br/>admin.kitsune.com"| PHP
    Caddy -->|"api.panda-estampados.com<br/>api.kitsune.com"| PHP

    Astro -->|"fetch /api/*<br/>cookie + X-Forwarded-Host"| PHP

    PHP --> MW
    MW --> SC
    SC --> PG
    SC --> RD
    PHP -->|"upload/download assets"| FTP

    Astro -.->|"static assets"| FTP

    subgraph Stores
        S1["Panda Estampados<br/>store_id=1"]:::store
        S2["Kitsune Store<br/>store_id=2"]:::store
    end

    SC -.->|"setStore(id)"| Stores
```

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

**Punto clave:** la línea `WHERE store_id=1` **no la escribió ningún
developer**. La inyecta el global scope del trait `BelongsToStore`. Si
olvidás el trait, Laravel no filtra y rompes el aislamiento. Por eso
el `MultiTenantHttpIsolationTest` es crítico.

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
    Caddy->>BE: reverse_proxy a backend:8000

    BE->>MW: handle(request)
    MW->>DB: SELECT store FROM store_domains<br/>WHERE domain='admin.panda-estampados.com'
    DB-->>MW: store_id=1 (Panda)
    MW->>BE: store resuelto en contexto

    Admin->>BE: POST /api/auth/login<br/>{ email, password }
    BE->>Auth: AuthController@login
    Auth->>DB: SELECT user WHERE email=?
    DB-->>Auth: user con store_id=1
    Auth->>Auth: valida password + 2FA
    Auth->>DB: INSERT personal_access_tokens<br/>(Sanctum, scoped a store_id)
    Auth-->>Admin: 200 + cookie Secure + Bearer token

    Admin->>BE: GET /api/admin/dashboard<br/>Cookie: panda_session
    BE->>MW: handle — store_id=1 confirmado
    BE->>DB: queries filtradas por store_id=1
    DB-->>BE: data de Panda
    BE-->>Admin: dashboard
```

**Diferencia con storefront:** el admin fija el `store_id` **dos
veces**: una por el dominio (`ResolveStoreFromDomain`) y otra por el
usuario logueado (campo `users.store_id`). Si no coinciden, **se
rechaza el request**. Esto evita que un operador de Kitsune entre a
`admin.panda-estampados.com`.

---

## 4. Conexiones con servicios

| Servicio       | Quién lo usa       | Protocolo    | Datos que pasan                              |
| -------------- | ------------------ | ------------ | -------------------------------------------- |
| **Cloudflare** | Caddy              | HTTPS        | Request/response. Headers, cookies, body.    |
| **Caddy**      | Frontend / Backend | HTTP interno | Host header, X-Forwarded-Proto, X-Real-IP.   |
| **Frontend**   | Backend            | HTTP/HTTPS   | Fetch API. Cookies Sanctum. Header `Host`.   |
| **Backend**    | PostgreSQL         | TCP 5432     | Credenciales `.env`. Queries parametrizadas. |
| **Backend**    | Redis              | TCP 6379     | `REDIS_PASSWORD`. Sesiones, cache, queues.   |
| **Backend**    | FTP / S3           | FTP/S3       | Credenciales en `.env`. Assets binarios.     |
| **Frontend**   | FTP / S3           | HTTPS        | Solo assets públicos vía CDN.                |

### Conexiones K8s internas

```mermaid
flowchart LR
    subgraph Pods["Kubernetes Pods"]
        Astro["astro pod<br/>:4321"]
        PHP["laravel pod<br/>:8000"]
        Worker["worker pod<br/>php artisan queue:work"]
    end
    subgraph Stateful["StatefulSet"]
        PG[("postgres<br/>:5432")]
        RD[("redis<br/>:6379")]
        FTP_S[("ftp / s3<br/>:21 / :443")]
    end

    Astro <-->|"HTTP /api/*"| PHP
    PHP -->|"5432 TCP"| PG
    PHP -->|"6379 TCP"| RD
    Worker -->|"5432 TCP"| PG
    Worker -->|"6379 TCP"| RD
    Worker -->|"21 / 443"| FTP_S
    PHP -->|"21 / 443"| FTP_S
```

> Los **Secrets** (DB password, Redis password, FTP creds) viven en
> K8s Secrets montados como variables de entorno en cada pod. Nunca en
> código ni en ConfigMaps.

---

## 5. Decisiones clave del diseño

| Decisión                                   | Por qué                                                                                                                 |
| ------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------- |
| **1 backend, 1 frontend, N tiendas**       | El 95% del código es idéntico. Multi-instance es duplicar operaciones, no valor.                                                |
| **Subdominio admin por tienda**            | Cookies aisladas, deploy independiente, branding distinto, seguridad reforzada.                                         |
| **PostgreSQL compartido con `store_id`**   | Una sola fuente de verdad. El global scope del trait aísla los datos.                                                   |
| **Redis compartido con namespaces**        | `panda:cart:123` y `kitsune:cart:123` conviven sin colisión. Invalidación cross-tenant no es problema porque no existe. |
| **FTP único con prefijos**                 | Los assets son archivos. El servidor no sabe de tiendas. Los nombres de archivo lleva la tienda.                          |
| **Middleware resuelve tienda por host**    | Single source of truth para el store activo en cada request.                                                            |
| **Wildcard TLS `*.panda-estampados.com`** | Un cert cubre storefront + admin. Renovación automática con Caddy + Cloudflare.                                         |

---

## 6. Lo que falta para llegar a producción

1. **Caddyfile** con los virtualhosts para cada dominio real.
2. **Seeder `store_domains`** cargando `panda-estampados.com`,
   `admin.panda-estampados.com`, `kitsune.com`,
   `admin.kitsune.com`, `www.*`, etc.
3. **Campo `users.store_id`** con FK a `stores.id`.
4. **Middleware/Policy** que valida que
   `users.store_id === store_context.store_id` antes de cualquier
   acción admin.
5. **Astro middleware** que lee el `Host` del request y expone el tenant al SSR.
6. **HPA en K8s** para escalar `astro` y `laravel` independientemente.
7. **Backups automatizados** de Postgres y Redis (cron + S3).
8. **Monitoreo** con Prometheus + Grafana (métricas de latencia por tienda).
