# Frontend Structure - Ecommerce Astro

Este documento explica la estructura del frontend del
ecommerce construido con `Astro`, `Tailwind`, `React` y
`TypeScript`. El objetivo es mantener una organización clara,
modular y alineada con los módulos del backend Laravel API.

## Propósito del documento

Este README sirve para entender:

```txt
- Qué carpetas existen en el frontend.
- Para qué sirve cada sección.
- Cuántos README conviene crear.
- Dónde se deben ubicar nuevas páginas.
- Dónde se deben ubicar nuevos componentes.
- Cómo se relaciona la estructura visual de Astro con los
  endpoints de Laravel.
```

El frontend no debe copiar exactamente la estructura de
tablas del backend. El frontend representa pantallas, vistas,
componentes y flujos de usuario. El backend representa
entidades, controladores, modelos, migraciones y endpoints.

## Tecnologías principales

El proyecto usa:

```txt
Astro
React
Tailwind CSS
TypeScript
Zod
React Hook Form
Chart.js
Laravel API como backend
```

`Astro` maneja las páginas y layouts. `React` se puede usar
para componentes interactivos. `Tailwind` se usa para
estilos. `TypeScript` define contratos de datos y mejora la
seguridad del código.

## Estructura general recomendada

La estructura principal del frontend debe mantenerse así:

```txt
.
├── public/
├── src/
│   ├── assets/
│   ├── components/
│   ├── config/
│   ├── data/
│   ├── layouts/
│   ├── lib/
│   ├── pages/
│   ├── stores/
│   ├── styles/
│   └── types/
├── astro.config.mjs
├── package.json
├── pnpm-lock.yaml
├── tsconfig.json
└── README.md
```

Esta estructura está bien planteada para un ecommerce
modular.

## Cantidad recomendada de README

No conviene crear un README en cada carpeta pequeña porque
se vuelve difícil de mantener.

La cantidad recomendada es entre 5 y 7 archivos de
documentación.

Documentación mínima recomendada:

```txt
1. README.md
2. DOCUMENTACION_ESTRUCTURA_FRONTEND.md
3. src/components/README.md
4. src/pages/README.md
5. src/lib/README.md
6. src/types/README.md
7. src/stores/README.md
```

Documentación opcional:

```txt
8. src/config/README.md
9. src/layouts/README.md
10. src/data/README.md
```

No es necesario crear README dentro de carpetas pequeñas
como:

```txt
src/components/admin/sidebar
src/components/admin/header
src/pages/admin/catalog/products
src/pages/admin/sales/orders
```

Es mejor documentar esas secciones desde un README superior.

## Sección raíz del proyecto

Ubicación:

```txt
.
```

Archivos importantes:

```txt
README.md
DOCUMENTACION.md
DOCUMENTACION_ESTRUCTURA_FRONTEND.md
package.json
astro.config.mjs
tsconfig.json
pnpm-lock.yaml
```

Responsabilidad:

```txt
- Explicar cómo instalar el proyecto.
- Explicar cómo correr el servidor.
- Explicar cómo compilar el frontend.
- Documentar variables de entorno.
- Explicar la arquitectura general.
```

Comandos típicos:

```bash
pnpm install
pnpm dev
pnpm build
pnpm preview
```

El README raíz debe ser más general. Este documento debe
enfocarse en la estructura interna del frontend.

## Sección public

Ubicación:

```txt
public/
```

Contenido actual:

```txt
favicon.ico
favicon.svg
```

Responsabilidad:

```txt
- Archivos públicos servidos directamente.
- Favicons.
- Imágenes estáticas globales.
- Archivos que no necesitan procesamiento de Astro.
```

No colocar aquí componentes, páginas ni lógica de
`TypeScript`.

Ejemplos válidos:

```txt
public/favicon.ico
public/favicon.svg
public/images/logo.svg
public/images/banners/home-banner.webp
```

## Sección src

Ubicación:

```txt
src/
```

Responsabilidad:

```txt
- Código fuente principal del frontend.
- Páginas Astro.
- Layouts.
- Componentes.
- Tipos TypeScript.
- Funciones para consumir la API.
- Stores.
- Configuración del menú.
- Estilos globales.
```

Casi todo el trabajo del frontend debe ocurrir dentro de
`src`.

## Sección assets

Ubicación:

```txt
src/assets/
```

Contenido actual:

```txt
astro.svg
background.svg
```

Responsabilidad:

```txt
- Recursos importados desde componentes o páginas.
- Imágenes procesadas por Astro o Vite.
- SVG usados como assets internos.
```

Diferencia con `public`:

```txt
public/     = archivos servidos directamente por URL.
src/assets/ = archivos importados desde el código.
```

Ejemplo:

```astro
---
import background from "../assets/background.svg";
---
```

## Sección components

Ubicación:

```txt
src/components/
```

Responsabilidad:

```txt
- Componentes reutilizables.
- Componentes visuales.
- Componentes de módulos.
- Componentes de UI base.
- Componentes del panel administrativo.
- Componentes del ecommerce público.
```

Estructura actual:

```txt
src/components/
├── admin/
├── charts/
├── ecommerce/
├── forms/
├── public/
├── tables/
├── ui/
└── Welcome.astro
```

La estructura es válida, pero se recomienda renombrar
`public` a `storefront` para evitar confusión con la
carpeta raíz `public`.

Estructura recomendada:

```txt
src/components/
├── admin/
├── charts/
├── ecommerce/
├── forms/
├── storefront/
├── tables/
└── ui/
```

## Sección components admin

Ubicación:

```txt
src/components/admin/
```

Responsabilidad:

```txt
- Componentes usados por el panel administrativo.
- Tarjetas del dashboard.
- Sidebar administrativo.
- Header administrativo.
- Componentes de reportes.
- Modales administrativos.
```

Estructura actual:

```txt
src/components/admin/
├── cards/
├── dashboard/
├── header/
├── modals/
├── reports/
└── sidebar/
```

Esta estructura está bien.

Ejemplos:

```txt
AdminHeader.astro
AdminSidebar.astro
AdminSidebarItem.astro
MetricCard.astro
RecentOrdersTable.astro
RevenueChart.astro
TopProductsCard.astro
```

Regla recomendada:

```txt
Si un componente solo se usa dentro de /admin, debe vivir
en src/components/admin.
```

## Sección components ecommerce

Ubicación:

```txt
src/components/ecommerce/
```

Responsabilidad:

```txt
- Componentes relacionados con compra pública.
- Carrito.
- Checkout.
- Resumen de pedido.
- Elementos de flujo comercial del cliente.
```

Contenido actual:

```txt
src/components/ecommerce/
├── cart/
└── checkout/
```

Ejemplos:

```txt
CartItem.astro
CartSummary.astro
CheckoutForm.astro
```

Regla recomendada:

```txt
Si el componente pertenece al flujo de compra del cliente,
debe vivir en ecommerce.
```

## Sección components storefront

Ubicación recomendada:

```txt
src/components/storefront/
```

Actualmente existe:

```txt
src/components/public/
```

Se recomienda renombrar a:

```txt
src/components/storefront/
```

Responsabilidad:

```txt
- Header público.
- Footer público.
- Componentes de home.
- Grilla pública de productos.
- Tarjetas de producto públicas.
```

Estructura recomendada:

```txt
src/components/storefront/
├── footer/
├── header/
├── home/
└── products/
```

Ejemplos:

```txt
PublicHeader.astro
PublicFooter.astro
ProductCard.astro
ProductGrid.astro
```

Motivo del cambio:

```txt
public/ ya existe en la raíz del proyecto.
src/components/public puede confundirse con public/.
storefront describe mejor la tienda pública.
```

## Sección components ui

Ubicación:

```txt
src/components/ui/
```

Responsabilidad:

```txt
- Componentes base reutilizables.
- Elementos visuales genéricos.
- Bloques usados por admin y tienda pública.
```

Contenido actual:

```txt
Alert.astro
Badge.astro
Breadcrumb.astro
Button.astro
Card.astro
Dropdown.astro
Icon.astro
Input.astro
Modal.astro
Pagination.astro
Select.astro
Table.astro
Textarea.astro
```

Regla recomendada:

```txt
Un componente en ui no debe conocer reglas específicas del
negocio.
```

Ejemplo correcto:

```txt
Button.astro
Input.astro
Modal.astro
```

Ejemplo incorrecto:

```txt
ProductPaymentStatusCard.astro
InvoiceTotalSummary.astro
CustomerDebtBadge.astro
```

Esos componentes deberían vivir en su módulo correspondiente.

## Sección config

Ubicación:

```txt
src/config/
```

Contenido actual:

```txt
menu.ts
site.ts
```

Responsabilidad:

```txt
- Configuración del sitio.
- Configuración del menú lateral.
- Configuración de navegación.
- Constantes globales visuales.
```

Ejemplos:

```txt
site.ts = nombre del sitio, descripción, metadata.
menu.ts = elementos del sidebar administrativo.
```

Regla recomendada:

```txt
Si es configuración global visual, va en config.
Si es endpoint de API, va en lib/endpoints.ts.
```

## Sección data

Ubicación:

```txt
src/data/
```

Contenido actual:

```txt
admin-dashboard.ts
```

Responsabilidad:

```txt
- Datos temporales.
- Datos mock.
- Datos estáticos para desarrollo.
- Información de prueba antes de conectar con Laravel.
```

Cuando el backend esté conectado, gran parte de esta
información debería venir desde la API.

Regla recomendada:

```txt
No dejar datos mock como fuente final de producción.
```

## Sección layouts

Ubicación:

```txt
src/layouts/
```

Contenido actual:

```txt
AdminLayout.astro
AuthLayout.astro
Layout.astro
PublicLayout.astro
```

Responsabilidad:

```txt
- Estructuras base de páginas.
- Envolver contenido común.
- Definir layout administrativo.
- Definir layout público.
- Definir layout de autenticación.
```

Uso recomendado:

```txt
AdminLayout.astro  = páginas dentro de /admin
PublicLayout.astro = tienda pública
AuthLayout.astro   = login, register, recuperación
Layout.astro       = layout base general
```

Ejemplo:

```astro
---
import AdminLayout from "../../layouts/AdminLayout.astro";
---

<AdminLayout title="Dashboard">
  <h1>Dashboard</h1>
</AdminLayout>
```

## Sección lib

Ubicación:

```txt
src/lib/
```

Contenido actual:

```txt
api.ts
auth.ts
endpoints.ts
formatters.ts
storage.ts
```

Responsabilidad:

```txt
- Cliente HTTP para Laravel API.
- Endpoints centralizados.
- Funciones de autenticación.
- Utilidades de formato.
- Utilidades para localStorage o sessionStorage.
```

Este módulo es clave para conectar `Astro` con Laravel.

## Archivo lib api

Ubicación:

```txt
src/lib/api.ts
```

Responsabilidad:

```txt
- Definir la función base para llamar la API.
- Agregar headers comunes.
- Adjuntar token Bearer.
- Manejar errores HTTP.
- Procesar respuestas JSON.
```

Recomendación:

```txt
PUBLIC_API_URL debe incluir el prefijo /api.
```

Ejemplo:

```env
PUBLIC_API_URL=http://localhost:9000/api
```

Los endpoints internos no deben incluir `/api`.

Correcto:

```txt
/products
/auth/login
/orders
```

Incorrecto:

```txt
/api/products
/api/auth/login
/api/orders
```

## Archivo lib endpoints

Ubicación:

```txt
src/lib/endpoints.ts
```

Responsabilidad:

```txt
- Centralizar rutas de la API.
- Evitar strings repetidos.
- Mantener consistencia con Laravel.
- Facilitar refactor futuro de endpoints.
```

Los endpoints deben seguir la convención REST de Laravel:

```txt
/products
/categories
/orders
/invoices
/payment-methods
/support-tickets
```

Para módulos complejos, se recomienda agrupar por dominio:

```txt
auth
catalog
customers
inventory
purchases
carts
sales
payments
shipping
marketing
taxes
returns
support
system
settings
```

## Archivo lib auth

Ubicación:

```txt
src/lib/auth.ts
```

Responsabilidad:

```txt
- Funciones relacionadas con login.
- Logout.
- Registro.
- Obtener usuario autenticado.
- Validar sesión.
- Manejo auxiliar del token.
```

No debe contener lógica visual ni manipular directamente
componentes.

## Archivo lib formatters

Ubicación:

```txt
src/lib/formatters.ts
```

Responsabilidad:

```txt
- Formatear moneda.
- Formatear fechas.
- Formatear porcentajes.
- Formatear números.
```

Ejemplos recomendados:

```txt
formatCurrency()
formatDate()
formatPercent()
formatNumber()
```

## Archivo lib storage

Ubicación:

```txt
src/lib/storage.ts
```

Responsabilidad:

```txt
- Leer token del navegador.
- Guardar token.
- Eliminar token.
- Manejar preferencias simples del usuario.
```

No debe guardar datos sensibles innecesarios.

## Sección pages

Ubicación:

```txt
src/pages/
```

Responsabilidad:

```txt
- Definir rutas reales de Astro.
- Crear páginas públicas.
- Crear páginas de autenticación.
- Crear páginas de cuenta.
- Crear páginas del panel administrativo.
```

En `Astro`, cada archivo dentro de `src/pages` representa
una ruta.

Ejemplo:

```txt
src/pages/index.astro                = /
src/pages/products/index.astro       = /products
src/pages/products/[slug].astro      = /products/:slug
src/pages/admin/dashboard/index.astro = /admin/dashboard
```

## Sección pages public

Ubicaciones actuales:

```txt
src/pages/index.astro
src/pages/products/
src/pages/categories/
src/pages/cart.astro
src/pages/checkout.astro
src/pages/orders/
```

Responsabilidad:

```txt
- Home pública.
- Catálogo público.
- Detalle de producto.
- Categorías.
- Carrito.
- Checkout.
- Pedidos del cliente.
```

Rutas generadas:

```txt
/
/products
/products/:slug
/categories
/cart
/checkout
/orders
```

## Sección pages auth

Ubicación:

```txt
src/pages/auth/
```

Contenido actual:

```txt
login.astro
register.astro
```

Responsabilidad:

```txt
- Login público.
- Registro de usuario.
- Recuperación de contraseña si se agrega después.
```

Rutas generadas:

```txt
/auth/login
/auth/register
```

Estas rutas son visuales del frontend. No son iguales a
los endpoints API, aunque tengan nombres parecidos.

## Sección pages account

Ubicación:

```txt
src/pages/account/
```

Contenido actual:

```txt
index.astro
```

Responsabilidad:

```txt
- Perfil del usuario autenticado.
- Datos personales.
- Seguridad de la cuenta.
- Métodos 2FA.
- Códigos de recuperación.
```

Se recomienda agregar:

```txt
src/pages/account/security/index.astro
src/pages/account/security/two-factor.astro
src/pages/account/security/recovery-codes.astro
```

Rutas generadas:

```txt
/account
/account/security
/account/security/two-factor
/account/security/recovery-codes
```

La configuración 2FA pertenece al usuario autenticado, por
eso se recomienda ubicarla en `account/security`.

## Sección pages admin

Ubicación:

```txt
src/pages/admin/
```

Responsabilidad:

```txt
- Panel administrativo.
- Dashboard.
- Gestión de catálogo.
- Gestión de clientes.
- Gestión de inventario.
- Gestión de compras.
- Gestión de ventas.
- Gestión de pagos.
- Gestión de envíos.
- Gestión de soporte.
- Gestión del sistema.
- Configuración de tienda.
```

Rutas principales:

```txt
/admin
/admin/dashboard
/admin/catalog
/admin/customers
/admin/inventory
/admin/purchases
/admin/sales
/admin/payments
/admin/shipping
/admin/marketing
/admin/returns
/admin/support
/admin/system
/admin/settings
/admin/users
/admin/roles
/admin/reports
```

## Sección pages admin catalog

Ubicación:

```txt
src/pages/admin/catalog/
```

Contenido actual:

```txt
brands/
categories/
products/
variants/
index.astro
```

Responsabilidad:

```txt
- Administración de productos.
- Administración de categorías.
- Administración de marcas.
- Administración de variantes.
```

Rutas generadas:

```txt
/admin/catalog
/admin/catalog/brands
/admin/catalog/categories
/admin/catalog/products
/admin/catalog/products/create
/admin/catalog/products/:id
/admin/catalog/variants
```

Esta estructura está bien alineada con el módulo `Catalog`
del backend.

## Sección pages admin inventory

Ubicación:

```txt
src/pages/admin/inventory/
```

Contenido actual:

```txt
index.astro
movements/
stock/
warehouses/
```

Responsabilidad:

```txt
- Inventario general.
- Stock.
- Movimientos.
- Almacenes.
```

Rutas generadas:

```txt
/admin/inventory
/admin/inventory/movements
/admin/inventory/stock
/admin/inventory/warehouses
```

Nota de endpoint:

```txt
La página /admin/inventory/stock puede consumir el endpoint
/inventories.
```

No es obligatorio que el nombre de la página sea igual al
endpoint.

## Sección pages admin purchases

Ubicación:

```txt
src/pages/admin/purchases/
```

Contenido actual:

```txt
index.astro
orders/
suppliers/
```

Responsabilidad:

```txt
- Compras a proveedores.
- Proveedores.
- Órdenes o registros de compra.
```

Recomendación:

```txt
Renombrar admin/purchases/orders a
admin/purchases/purchase-orders si se quiere evitar
confusión con sales/orders.
```

Rutas actuales:

```txt
/admin/purchases
/admin/purchases/orders
/admin/purchases/suppliers
```

Rutas recomendadas:

```txt
/admin/purchases
/admin/purchases/purchase-orders
/admin/purchases/suppliers
```

Motivo:

```txt
/admin/sales/orders representa pedidos de clientes.
/admin/purchases/orders puede confundirse visualmente
con pedidos de venta.
```

## Sección pages admin sales

Ubicación:

```txt
src/pages/admin/sales/
```

Contenido actual:

```txt
index.astro
invoices/
orders/
```

Responsabilidad:

```txt
- Pedidos de clientes.
- Facturas.
- Historial comercial.
```

Rutas generadas:

```txt
/admin/sales
/admin/sales/orders
/admin/sales/orders/:id
/admin/sales/invoices
/admin/sales/invoices/:id
```

Esta estructura está bien alineada con el módulo `Sales`.

## Sección pages admin payments

Ubicación:

```txt
src/pages/admin/payments/
```

Contenido actual:

```txt
index.astro
installments/
methods/
```

Responsabilidad:

```txt
- Pagos.
- Métodos de pago.
- Cuotas.
- Transacciones si se agregan páginas después.
```

Rutas generadas:

```txt
/admin/payments
/admin/payments/installments
/admin/payments/methods
```

Se puede agregar después:

```txt
src/pages/admin/payments/transactions/index.astro
```

## Sección pages admin shipping

Ubicación:

```txt
src/pages/admin/shipping/
```

Contenido actual:

```txt
companies/
index.astro
zones/
```

Responsabilidad:

```txt
- Empresas de envío.
- Zonas de envío.
- Envíos.
- Seguimiento.
```

Rutas generadas:

```txt
/admin/shipping
/admin/shipping/companies
/admin/shipping/zones
```

Se puede agregar después:

```txt
src/pages/admin/shipping/shipments/index.astro
src/pages/admin/shipping/trackings/index.astro
```

## Sección pages admin marketing

Ubicación:

```txt
src/pages/admin/marketing/
```

Contenido actual:

```txt
coupons/
index.astro
promotions/
```

Responsabilidad:

```txt
- Cupones.
- Promociones.
- Reglas comerciales de descuento.
```

Rutas generadas:

```txt
/admin/marketing
/admin/marketing/coupons
/admin/marketing/promotions
```

## Sección pages admin support

Ubicación:

```txt
src/pages/admin/support/
```

Contenido actual:

```txt
index.astro
questions/
reviews/
tickets/
```

Responsabilidad:

```txt
- Tickets.
- Reseñas.
- Preguntas de productos.
- Atención al cliente.
```

Rutas generadas:

```txt
/admin/support
/admin/support/questions
/admin/support/reviews
/admin/support/tickets
```

## Sección pages admin settings

Ubicación:

```txt
src/pages/admin/settings/
```

Contenido actual:

```txt
currencies/
index.astro
store/
taxes/
```

Responsabilidad:

```txt
- Configuración general.
- Monedas.
- Tipos de cambio.
- Impuestos si se administran desde settings.
```

Rutas generadas:

```txt
/admin/settings
/admin/settings/currencies
/admin/settings/store
/admin/settings/taxes
```

Nota:

```txt
Aunque taxes tenga módulo propio en backend, visualmente
puede estar dentro de settings porque para el
administrador es configuración.
```

## Sección stores

Ubicación:

```txt
src/stores/
```

Contenido actual:

```txt
auth.ts
cart.ts
ui.ts
```

Responsabilidad:

```txt
- Estado global o compartido del frontend.
- Estado de autenticación.
- Estado del carrito.
- Estado de interfaz.
```

Ejemplos de uso:

```txt
auth.ts = usuario, token, sesión.
cart.ts = carrito actual.
ui.ts = sidebar abierto/cerrado, tema, modales.
```

Regla recomendada:

```txt
No guardar en stores datos que solo usa una página.
```

## Sección styles

Ubicación:

```txt
src/styles/
```

Contenido actual:

```txt
global.css
```

Responsabilidad:

```txt
- Estilos globales.
- Variables CSS.
- Configuración base de Tailwind.
- Estilos compartidos.
```

Regla recomendada:

```txt
Evitar colocar estilos específicos de una página si pueden
resolverse con clases Tailwind.
```

## Sección types

Ubicación:

```txt
src/types/
```

Contenido actual:

```txt
api.ts
auth.ts
carts.ts
catalog.ts
customers.ts
inventory.ts
marketing.ts
payments.ts
purchases.ts
returns.ts
sales.ts
settings.ts
shipping.ts
support.ts
system.ts
```

Responsabilidad:

```txt
- Tipos TypeScript del frontend.
- Contratos de datos recibidos desde Laravel.
- Tipos de formularios.
- Tipos de respuestas API.
```

Esta estructura está bien alineada con los módulos del
backend.

Regla recomendada:

```txt
Cada módulo debe tener su propio archivo de tipos.
```

Ejemplo:

```txt
catalog.ts   = Product, Category, Brand, ProductVariant
sales.ts     = Order, Invoice, OrderItem, InvoiceItem
payments.ts  = Payment, PaymentMethod, PaymentTransaction
auth.ts      = User, Role, Permission, AuthResponse
```

## Relación entre páginas y endpoints

Las páginas de `Astro` no tienen que llamarse igual que los
endpoints de Laravel.

Ejemplo:

```txt
Página Astro:
    /admin/inventory/stock

Endpoint Laravel:
    /api/inventories
```

Ejemplo:

```txt
Página Astro:
    /admin/sales/orders

Endpoint Laravel:
    /api/orders
```

Ejemplo:

```txt
Página Astro:
    /admin/settings/taxes

Endpoint Laravel:
    /api/taxes
```

Lo importante es que el mapeo esté centralizado en:

```txt
src/lib/endpoints.ts
```

## Convención recomendada de endpoints

El archivo `src/lib/endpoints.ts` debe contener endpoints
sin el prefijo `/api`.

Correcto:

```txt
/products
/orders
/auth/login
```

Incorrecto:

```txt
/api/products
/api/orders
/api/auth/login
```

El prefijo `/api` debe venir desde:

```txt
PUBLIC_API_URL=http://localhost:9000/api
```

o:

```txt
PUBLIC_API_URL=http://localhost:8080/api
```

según el puerto donde corra Laravel.

## Relación con Laravel API

El backend Laravel debe exponer endpoints REST parecidos a:

```txt
/api/auth/login
/api/auth/register
/api/auth/logout
/api/auth/me

/api/users
/api/roles
/api/permissions

/api/products
/api/categories
/api/brands
/api/product-variants

/api/customers
/api/customer-addresses

/api/inventories
/api/inventory-movements
/api/warehouses

/api/orders
/api/invoices
/api/payments
```

El frontend debe consumirlos desde:

```txt
src/lib/api.ts
src/lib/endpoints.ts
```

## Recomendaciones de documentación interna

Crear estos README:

```txt
README.md
DOCUMENTACION_ESTRUCTURA_FRONTEND.md
src/components/README.md
src/pages/README.md
src/lib/README.md
src/types/README.md
src/stores/README.md
```

No crear README en cada carpeta pequeña.

Ejemplo de carpetas que no necesitan README propio:

```txt
src/components/admin/sidebar
src/components/admin/header
src/pages/admin/catalog/products
src/pages/admin/sales/orders
src/pages/admin/settings/store
```

Esas carpetas ya deben explicarse desde los README
superiores.

## Recomendaciones finales

1. Mantener la estructura modular actual.
2. Renombrar `src/components/public` a
   `src/components/storefront`.
3. Evitar confusión entre `admin/purchases/orders` y
   `admin/sales/orders`.
4. Agregar páginas de seguridad en
   `src/pages/account/security`.
5. Mantener endpoints centralizados en
   `src/lib/endpoints.ts`.
6. Mantener tipos separados por módulo en `src/types`.
7. Usar layouts para separar admin, auth y tienda pública.
8. Evitar lógica de negocio compleja dentro de páginas
   `Astro`.
9. Usar componentes UI genéricos en
   `src/components/ui`.
10. Usar componentes específicos dentro de su módulo.
11. No versionar `dist` si el proyecto usa Git.
12. No versionar `node_modules`.
13. Mantener `PUBLIC_API_URL` con el prefijo `/api`.
14. Documentar cada cambio estructural importante.
15. Mantener nombres en inglés para carpetas, archivos
    y tipos.

## Estado actual recomendado

La estructura actual está bien planteada y sigue una
organización profesional para un ecommerce multi-tienda
con Laravel API.

Los cambios mínimos recomendados son:

```txt
src/components/public     -> src/components/storefront
src/pages/account/security -> agregar para 2FA
src/lib/endpoints.ts       -> mantener alineado con Laravel
src/config/stores.ts       -> crear para resolución de tienda
src/lib/store-context.ts   -> crear para helper de contexto
src/types/stores.ts        -> crear para tipos Store
```

Opcionalmente:

```txt
src/pages/admin/purchases/orders
  -> src/pages/admin/purchases/purchase-orders
```

Con estos ajustes, el frontend queda correctamente alineado
con los módulos y endpoints del backend Laravel, y preparado
para soportar múltiples tiendas (Panda Estampados y Kitsune)
desde un mismo build.

Ver también:

- [frontend/README.md](README.md "Visión general del frontend")
- [frontend/storefront.md](storefront.md "Tienda pública multi-tienda")
- [frontend/admin-panel.md](admin-panel.md "Panel administrativo")
- [frontend/api-client.md](api-client.md "Cliente API")
