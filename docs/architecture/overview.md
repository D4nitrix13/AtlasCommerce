# Arquitectura General del Sistema

## Visión general

AtlasCommerce es una plataforma ecommerce **multi-instancia**
desplegada como **3 stacks Docker independientes** que
atienden **4 marcas** totales. Cada instancia es una unidad
aislada con su propia base de datos `PostgreSQL`, su
`Redis`, su backend `Laravel`, su frontend `Astro` y su
reverse proxy `Caddy`. Multi-store opera dentro de una misma
instancia cuando el dueño comparte operación entre marcas
(caso `panda-estampados-and-kitsune`). Panda y Kitsune son tiendas
diferentes para el cliente, pero forman parte de una misma operación
administrativa.

```txt
                                                  Internet (HTTPS)
                                                       │
                                                       ▼
              ┌──────────────────────────────────────────────────────┐
              │           Cloudflare (DNS · WAF · CDN)              │
              └──────────────────────┬───────────────────────────────┘
                                     │
                                     ▼
              ┌──────────────────────────────────────────────────────┐
              │   Caddy (TLS termination + VirtualHost routing)     │
              │   Único puerto 80/443 expuesto por instancia        │
              └───┬──────────────┬──────────────┬───────────────────┘
                  │              │              │
       ┌──────────▼───┐  ┌───────▼──────┐  ┌────▼──────────┐
       │  Instancia 1 │  │  Instancia 2 │  │  Instancia 3  │
       │  panda-      │  │  mm-         │  │  morales-     │
       │  estampados- │  │  boutique    │  │  store        │
       │  and-kitsune │  │              │  │               │
       │  (2 marcas)  │  │  (1 marca)   │  │  (1 marca)    │
       └──────┬───────┘  └──────┬───────┘  └──────┬────────┘
              │                 │                 │
              ▼                 ▼                 ▼
       ┌────────────┐    ┌────────────┐    ┌────────────┐
       │ Frontend   │    │ Frontend   │    │ Frontend   │
       │ Astro 7    │    │ Astro 7    │    │ Astro 7    │
       ├────────────┤    ├────────────┤    ├────────────┤
       │ Backend    │    │ Backend    │    │ Backend    │
       │ Laravel 13 │    │ Laravel 13 │    │ Laravel 13 │
       ├────────────┤    ├────────────┤    ├────────────┤
       │ PostgreSQL │    │ PostgreSQL │    │ PostgreSQL │
       │ + Redis    │    │ + Redis    │    │ + Redis    │
       │ + FTP      │    │ + FTP      │    │ + FTP      │
       └────────────┘    └────────────┘    └────────────┘
        Aislados entre sí — sin red compartida.
```

### Marcas por instancia

- **Instancia 1 — `panda-estampados-and-kitsune`**
  - `panda-estampados.com` · `www.` · `admin.` → Panda
    Estampados.
  - `kitsune.com` · `www.` · `admin.` → Kitsune Store.
- **Instancia 2 — `mm-boutique`**
  - `mm-boutique.com` · `www.` · `admin.` → MM Boutique.
- **Instancia 3 — `morales-store`**
  - `morales-store.com` · `www.` · `admin.` → Morales Store.

Ver [multi-store.md](multi-store.md "multi-store.md") para el
detalle de cómo multi-store convive dentro de la instancia 1.

---

## Componentes por instancia

Las tres instancias comparten el mismo stack técnico. Los
nombres exactos difieren levemente entre dev y producción
(producción usa `app`, dev usa `backend` + sidecar `nginx`).

### Frontend (`Astro` 7 + `React` 19)

Un solo build de `Astro` sirve tanto la tienda pública como
el panel administrativo de su instancia. El mismo código
fuente se despliega bajo diferentes dominios:

- Instancia 1: `panda-estampados.com` y `kitsune.com`.
- Instancia 2: `mm-boutique.com`.
- Instancia 3: `morales-store.com`.

El frontend resuelve qué marca renderizar según el dominio
de origen. Los componentes `React` manejan la interactividad
(formularios, carrito, modales) mientras que los componentes
de `Astro` manejan la estructura estática.

### Backend `API` (`Laravel` 13)

Una `API` centralizada por instancia expone los recursos
del sistema. Autentica peticiones con Bearer tokens de
`Laravel Sanctum`. Los módulos están organizados por dominio
de negocio:

- **Auth** — Usuarios, roles, permisos, 2FA.
- **Catalog** — Productos, categorías, marcas, variantes.
- **Customers** — Clientes, direcciones.
- **Inventory** — Stock, movimientos, almacenes.
- **Purchases** — Proveedores, compras.
- **Carts** — Carritos, wishlists.
- **Sales** — Órdenes, facturas.
- **Payments** — Métodos de pago, transacciones.
- **Shipping** — Empresas, zonas, envíos.
- **Marketing** — Cupones, promociones.
- **Taxes** — Impuestos.
- **Returns** — Devoluciones, reembolsos.
- **Support** — Tickets, reseñas, preguntas.
- **System** — Auditorías, notificaciones.
- **Settings** — Configuración, monedas, tipos de cambio.
- **Stores** — Configuración por marca (multi-store).
- **Storefront** — Endpoints públicos del storefront.

### Base de datos (`PostgreSQL`)

Una instancia de `PostgreSQL` por stack (no compartida entre
instancias). Dentro de la instancia, las tablas
transaccionales incluyen `store_id` para filtrar datos por
marca cuando la instancia atiende varias (caso instancia 1).
Las tablas globales (roles, permisos, monedas) no tienen
`store_id`.

### Infraestructura (`Docker` + `Caddy`)

`Docker` gestiona los servicios internos (`PostgreSQL`,
`Redis`, FTP) por instancia. `Caddy` actúa como reverse
proxy con TLS automático vía Let's Encrypt. Solo los
puertos 80 y 443 están expuestos públicamente al host.

---

## Patrón de arquitectura

Dentro de cada instancia, el sistema sigue un patrón
**multi-store por shared database** cuando la instancia
atiende varias marcas. Las marcas que comparten instancia
comparten la misma BD y la misma `API`; la diferenciación
se realiza mediante el campo `store_id` en las tablas
correspondientes.

```txt
Multi-store por shared database (intra-instancia):

┌────────────────────────────────────────────┐
│                PostgreSQL                  │
│                                            │
│  ┌─────────────────────┐  ┌─────────────┐  │
│  │  Panda Estampados   │  │   Kitsune   │  │
│  │  store_id=1         │  │  store_id=2 │  │
│  └─────────────────────┘  └─────────────┘  │
│                                            │
│  Tablas sin store_id:                      │
│  users, roles, permissions, currencies     │
└────────────────────────────────────────────┘
```

Las instancias `mm-boutique` y `morales-store` solo
contienen un `store_id` cada una. El middleware
`ResolveStoreFromDomain` resuelve siempre al mismo
`store_id` para todos los requests de esa instancia.

---

## Principios arquitectónicos

1. **Aislamiento por instancia** — Cada instancia tiene su
   propia BD, Redis, backend y frontend. No se comparten
   datos entre instancias de tenants distintos.
2. **Aislamiento por marca dentro de instancia** — Las
   marcas que comparten instancia (Panda + Kitsune) se
   filtran por `store_id` vía middleware y global scope.
3. **Administración centralizada por instancia** — Un solo
   panel admin gestiona las marcas de la instancia.
4. **Exposición pública separada** — Cada marca tiene su
   propio dominio y experiencia de usuario.
5. **Inventario compartido dentro de instancia** — El
   stock es global dentro de la instancia (mismo almacén
   para Panda y Kitsune). Cada instancia mantiene su
   propio almacén.
6. **Catálogo filtrado** — Los productos se publican
   selectivamente en cada marca vía `product_stores`.
7. **Datos aislados por marca** — Pedidos, facturas,
   clientes, cupones y reportes se filtran por `store_id`.
8. **Seguridad uniforme** — Un solo sistema de
   autenticación y autorización por instancia.
