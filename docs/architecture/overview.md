# Arquitectura General del Sistema

## Visión general

Panda Estampados y Kitsune es una plataforma
ecommerce multi-tienda compuesta por un backend
`API` centralizado, un frontend `Astro`
multi-dominio, una base de datos `PostgreSQL`
única e infraestructura `Docker` con `Caddy`
como reverse proxy.

```txt
┌─────────────────────────────────────────────────────┐
│                  Cliente (Browser)                  │
│      panda-estampados-and-kitsune.com  ·  kitsune.com          │
│      admin.panda-estampados-and-kitsune.com                    │
└───────────────────────┬─────────────────────────────┘
                        │ HTTPS (443)
┌───────────────────────▼─────────────────────────────┐
│                     Caddy                           │
│               Reverse proxy + TLS                   │
│    Solo puertos 80 y 443 expuestos públicamente     │
└──────┬──────────────────┬──────────────────┬────────┘
       │                  │                  │
       ▼                  ▼                  ▼
┌───────────────┐ ┌────────────────┐ ┌────────────────┐
│   Frontend    │ │  Panel Admin   │ │  Backend API   │
│   Astro 7     │ │  Astro 7       │ │  Laravel 13    │
│  (storefront) │ │  + React       │ │  (API central) │
└───────────────┘ └────────────────┘ └───────┬────────┘
                                            │
                               ┌────────────┼────────────┐
                               │            │            │
                               ▼            ▼            ▼
                         ┌───────────┐ ┌──────────┐ ┌──────────┐
                         │PostgreSQL │ │  Redis   │ │ FTP      │
                         │ (PostGIS) │ │          │ │          │
                         └───────────┘ └──────────┘ └──────────┘
                          Internos — no expuestos a internet
```

## Componentes principales

### Frontend (`Astro` 7 + `React` 19)

Un solo build de `Astro` sirve tanto la tienda
pública como el panel administrativo. El mismo
código fuente se despliega bajo diferentes dominios:

- `panda-estampados-and-kitsune.com` → tienda Panda
- `kitsune.com` → tienda Kitsune
- `admin.panda-estampados-and-kitsune.com` → panel administrativo central

El frontend resuelve qué tienda renderizar según
el dominio de origen. Los componentes `React`
manejan la interactividad (formularios, carrito,
modales) mientras que los componentes de `Astro`
manejan la estructura estática.

### Backend `API` (`Laravel` 13)

Una sola `API` centralizada expone todos los
recursos del sistema. Autentica peticiones con
Bearer tokens de `Laravel Sanctum`. Los módulos
están organizados por dominio de negocio:

- **Auth** — Usuarios, roles, permisos, 2FA
- **Catalog** — Productos, categorías, marcas, variantes
- **Customers** — Clientes, direcciones
- **Inventory** — Stock, movimientos, almacenes
- **Purchases** — Proveedores, compras
- **Carts** — Carritos, wishlists
- **Sales** — Órdenes, facturas
- **Payments** — Métodos de pago, transacciones
- **Shipping** — Empresas, zonas, envíos
- **Marketing** — Cupones, promociones
- **Taxes** — Impuestos
- **Returns** — Devoluciones, reembolsos
- **Support** — Tickets, reseñas, preguntas
- **System** — Auditorías, notificaciones
- **Settings** — Configuración, monedas, tipos de cambio
- **Stores** — Configuración por tienda (multi-tienda)
- **Storefront** — Endpoints públicos del storefront

### Base de datos (`PostgreSQL`)

Una sola instancia de `PostgreSQL` con `PostGIS`
almacena todos los datos de ambas tiendas. Las
tablas transaccionales incluyen `store_id` para
filtrar datos por tienda. Las tablas globales
(roles, permisos, monedas) no tienen `store_id`.

### Infraestructura (`Docker` + `Caddy`)

`Docker` gestiona los servicios internos
(`PostgreSQL`, `Redis`, Storage). `Caddy` actúa
como reverse proxy con TLS automático. Solo los
puertos 80 y 443 están expuestos públicamente.

## Patrón de arquitectura

El sistema sigue un patrón **multi-tenant por shared
database**, donde ambas tiendas comparten la misma
instancia de base de datos y la misma `API`. La
diferenciación se realiza mediante el campo
`store_id` en las tablas correspondientes.

```txt
Multi-tenant por shared database:

┌────────────────────────────────────────────┐
│                PostgreSQL                  │
│                                            │
│  ┌─────────────────────┐  ┌─────────────┐  │
│  │   Pandas Estampados │  │   Kitsune   │  │
│  │  store_id=1         │  │  store_id=2 │  │
│  └─────────────────────┘  └─────────────┘  │
│                                            │
│  Tablas sin store_id:                      │
│  users, roles, permissions, currencies     │
└────────────────────────────────────────────┘
```

## Principios arquitectónicos

1. **Administración centralizada** — Un solo
   panel admin gestiona ambas tiendas.
2. **Exposición pública separada** — Cada tienda
   tiene su propio dominio y experiencia de usuario.
3. **Inventario compartido** — El stock es global
   (mismo almacén para ambas tiendas).
4. **Catálogo filtrado** — Los productos se
   publican selectivamente en cada tienda vía
   `product_stores`.
5. **Datos aislados por tienda** — Pedidos,
   facturas, clientes, cupones y reportes se
   filtran por `store_id`.
6. **Seguridad uniforme** — Un solo sistema de
   autenticación y autorización para todo el backend.
