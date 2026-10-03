# 8.1.1 — Plantillas de Coleman

18 plantillas, una por cada caso de uso / historia de usuario (HU-01 a HU-18).

Cada archivo es un `.md` independiente con la estructura formal de la plantilla de Coleman
(actores, precondiciones, postcondiciones, flujo principal, flujos alternativos, reglas de
negocio, RF relacionados, prioridad).

## Índice

| Archivo | Caso de uso | HU | Actor primario | Prioridad |
|---------|-------------|----|----|---|
| [CU-01-navegar-catalogo-publico.md](./CU-01-navegar-catalogo-publico.md) | Navegar catálogo público | HU-01 | Cliente externo | Alta |
| [CU-02-registrarse-sistema.md](./CU-02-registrarse-sistema.md) | Registrarse en el sistema | HU-02 | Cliente externo | Alta |
| [CU-03-gestionar-carrito.md](./CU-03-gestionar-carrito.md) | Gestionar carrito de compras | HU-03 | Cliente externo | Alta |
| [CU-04-realizar-checkout.md](./CU-04-realizar-checkout.md) | Realizar checkout paso a paso | HU-04 | Cliente externo | Alta |
| [CU-05-pagar-pedido.md](./CU-05-pagar-pedido.md) | Pagar pedido en línea | HU-05 | Cliente externo | Alta |
| [CU-06-consultar-estado-pedido.md](./CU-06-consultar-estado-pedido.md) | Consultar estado de pedido | HU-06 | Cliente externo | Media |
| [CU-07-gestionar-lista-deseos.md](./CU-07-gestionar-lista-deseos.md) | Gestionar lista de deseos | HU-07 | Cliente externo | Baja |
| [CU-08-administrar-productos.md](./CU-08-administrar-productos.md) | Administrar productos (CRUD) | HU-08 | Operador de tienda | Alta |
| [CU-09-gestionar-stock-inventario.md](./CU-09-gestionar-stock-inventario.md) | Gestionar stock e inventario | HU-09 | Operador de tienda | Alta |
| [CU-10-gestionar-pedido.md](./CU-10-gestionar-pedido.md) | Gestionar pedido | HU-10 | Operador de tienda | Alta |
| [CU-11-atender-tickets-soporte.md](./CU-11-atender-tickets-soporte.md) | Atender tickets de soporte | HU-11 | Operador de tienda | Media |
| [CU-12-ver-dashboard-reportes.md](./CU-12-ver-dashboard-reportes.md) | Ver dashboard y reportes | HU-12 | Supervisor | Media |
| [CU-13-validar-cierre-caja.md](./CU-13-validar-cierre-caja.md) | Validar cierre de caja diario | HU-13 | Supervisor | Media |
| [CU-14-gestionar-usuarios.md](./CU-14-gestionar-usuarios.md) | Gestionar usuarios | HU-14 | Administrador | Alta |
| [CU-15-configurar-tienda.md](./CU-15-configurar-tienda.md) | Configurar tienda | HU-15 | Administrador | Alta |
| [CU-16-configurar-pasarela-pago.md](./CU-16-configurar-pasarela-pago.md) | Configurar pasarela de pago | HU-16 | Administrador | Alta |
| [CU-17-gestionar-respaldos.md](./CU-17-gestionar-respaldos.md) | Gestionar respaldos | HU-17 | Administrador | Media |
| [CU-18-revisar-bitacora.md](./CU-18-revisar-bitacora.md) | Revisar bitácora de auditoría | HU-18 | Administrador | Media |

## Distribución por actor

| Actor | Casos de uso |
|-------|--------------|
| Cliente externo | CU-01 a CU-07 (7 casos) |
| Operador de tienda | CU-08 a CU-11 (4 casos) |
| Supervisor | CU-12 a CU-13 (2 casos) |
| Administrador | CU-14 a CU-18 (5 casos) |
| **Total** | **18 casos** |

## Distribución por prioridad

| Prioridad | Casos de uso | Porcentaje |
|-----------|--------------|------------|
| Alta | CU-01, CU-02, CU-03, CU-04, CU-05, CU-08, CU-09, CU-10, CU-14, CU-15, CU-16 | 11 (61%) |
| Media | CU-06, CU-11, CU-12, CU-13, CU-17, CU-18 | 6 (33%) |
| Baja | CU-07 | 1 (6%) |

## Trazabilidad con la implementación

Cada plantilla está vinculada a su historia de usuario (HU-XX) y a los requerimientos
funcionales (RF-XXX) correspondientes. La trazabilidad 1:1 con los modelos Laravel
está documentada en [../README.md](../README.md#nota-de-trazabilidad-con-la-implementación-recomendado-para-el-documento).
