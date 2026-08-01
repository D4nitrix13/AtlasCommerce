# Backend — Plan de Implementación

Inventario de tareas priorizadas para el backend de Panda Estampados / Kitsune Store.

> **Última actualización:** 2026-07-02

---

## Estado Actual del Backend

| Capa | Total | Implementado | Skeleton | Faltante |
| --- | --- | --- | --- | --- |
| Controllers | 81 | 4 (5%) | 65 (80%) | 12 vacíos (15%) |
| Models | 90 | 7 (8%) | 83 (92%) | 0 |
| Migrations | 86 | 86 (100%) | 0 | 0 |
| API Resources | 81 | 0 (0%) | 81 (100%) | 0 |
| Services | 5 | 5 (100%) | 0 | 0 |
| Seeders | 5 | 5 (100%) | 0 | 0 |
| Tests | 4 | 1 real (25%) | 2 defaults | 2 directorios vacíos |
| Routes | 331 líneas | Definidas | 0 | Customization + Stores |
| Middleware | 1 | 1 (100%) | 0 | RBAC no implementado |
| Config | 14 | 14 (100%) | 0 | 0 |
| Notifications | 1 | 1 (100%) | 0 | Templates email |

### Lo que ya funciona

- 86 migraciones con foreign keys, índices y soft deletes
- Sistema 2FA completo (Email OTP + Recovery Codes)
- Services: EmailOtpService, RecoveryCodeService, CartLineKeyService, DesignSnapshotService, StoreContextService
- Store seeding (Panda Estampados + Kitsune)
- Design assets seeding (fonts + graphics)
- Middleware ResolveStoreFromDomain
- Config layer (14 archivos, 3 custom)
- Storefront ProductController (listado público por tienda)

---

## Fase 1: Fundamentos

_Bloquea todo lo demás. Sin modelos completos no hay controllers, resources ni tests._

| # | Tarea | Descripción | Dependencias |
| --- | --- | --- | --- |
| 1.1 | Completar modelos Auth | User, OtpChallenge, TwoFactorSetting, RecoveryCode — verificar fillable, casts, relaciones | — |
| 1.2 | Completar modelos Catalog | Brand, Category, Product, ProductVariant, ProductAttribute, ProductAttributeValue, ProductImage, VariantAttributeValue | — |
| 1.3 | Completar modelos Stores | Store, StoreDomain, StoreSetting, StoreSocialLink, ProductStore, ProductStoreImage | — |
| 1.4 | Completar modelos Sales | Order, OrderItem, OrderStatusHistory, Invoice, InvoiceItem, InvoiceStatusHistory | — |
| 1.5 | Completar modelos Payments | PaymentMethod, Payment, PaymentTransaction, PaymentInstallment, InvoicePayment | — |
| 1.6 | Completar modelos Customers | Customer, CustomerAddress | — |
| 1.7 | Completar modelos Purchases | Supplier, Purchase, PurchaseItem | — |
| 1.8 | Completar modelos Inventory | Warehouse, Inventory, InventoryMovement | — |
| 1.9 | Completar modelos Carts | Cart, CartItem, Wishlist, WishlistItem | — |
| 1.10 | Completar modelos Shipping | ShippingCompany, Shipment, ShipmentTracking | — |
| 1.11 | Completar modelos Marketing | Coupon, CouponUsage, Promotion, ProductPromotion | — |
| 1.12 | Completar modelos Returns | ReturnRequest, ReturnRequestItem, Refund | — |
| 1.13 | Completar modelos Support | SupportTicket, SupportMessage, SupportCategory, SupportPriority | — |
| 1.14 | Completar modelos Taxes | Tax, TaxRate | — |
| 1.15 | Completar modelos System | Audit, Notification | — |
| 1.16 | Completar modelos Settings | Currency, ExchangeRate | — |
| 1.17 | Completar modelos Customization | CustomDesign, DesignLayer, DesignAsset, DesignFont, DesignGraphic, PrintArea, DesignTemplate, ProductCustomization, ProductCustomizationConfig | — |
| 1.18 | Crear Form Requests por módulo | Validación para cada endpoint (store, update, index filters) | Modelos |
| 1.19 | Completar API Resources | Serialización real de datos para cada modelo | Modelos |

---

## Fase 2: Auth y Control de Acceso

| # | Tarea | Descripción | Dependencias |
| --- | --- | --- | --- |
| 2.1 | CRUD User | Listar, mostrar, crear, actualizar, eliminar usuarios | Fase 1 |
| 2.2 | CRUD Role | Gestión de roles del sistema | Fase 1 |
| 2.3 | CRUD Permission | Gestión de permisos por módulo | Fase 1 |
| 2.4 | CRUD PermissionRole | Asignación de permisos a roles | Fase 1 |
| 2.5 | RBAC middleware | Middleware de roles y permisos para proteger rutas | 2.2, 2.3 |
| 2.6 | Integrar Passkeys | Usar paquete laravel/passkeys ya instalado | Fase 1 |
| 2.7 | Integrar TOTP | Usar paquetes google2fa ya instalados | Fase 1 |

---

## Fase 3: Core Business

| # | Tarea | Descripción | Dependencias |
| --- | --- | --- | --- |
| 3.1 | CRUD Brand | Marcas del catálogo | Fase 1 |
| 3.2 | CRUD Category | Categorías con jerarquía | Fase 1 |
| 3.3 | CRUD Product | Productos con atributos, variantes, imágenes | Fase 1 |
| 3.4 | CRUD ProductVariant | Variantes de producto (talle, color, etc.) | 3.3 |
| 3.5 | CRUD ProductAttribute | Atributos del catálogo | 3.3 |
| 3.6 | CRUD ProductAttributeValue | Valores de atributos por variante | 3.5 |
| 3.7 | CRUD ProductImage | Imágenes de productos | 3.3 |
| 3.8 | CRUD Inventory | Stock por almacén | 3.3 |
| 3.9 | CRUD InventoryMovement | Movimientos de entrada/salida | 3.8 |
| 3.10 | CRUD Warehouse | Almacenes | 3.8 |
| 3.11 | CRUD Customer | Clientes del sistema | Fase 1 |
| 3.12 | CRUD CustomerAddress | Direcciones de clientes | 3.11 |
| 3.13 | CRUD Order | Órdenes de venta | 3.11, 3.3 |
| 3.14 | CRUD OrderItem | Líneas de órdenes | 3.13 |
| 3.15 | CRUD OrderStatusHistory | Historial de estados de órdenes | 3.13 |
| 3.16 | CRUD Invoice | Facturas | 3.13 |
| 3.17 | CRUD InvoiceItem | Líneas de facturas | 3.16 |
| 3.18 | CRUD InvoiceStatusHistory | Historial de estados de facturas | 3.16 |
| 3.19 | CRUD Payment | Pagos | 3.16 |
| 3.20 | CRUD PaymentMethod | Métodos de pago | Fase 1 |
| 3.21 | CRUD PaymentTransaction | Transacciones | 3.19 |
| 3.22 | CRUD PaymentInstallment | Cuotas | 3.19 |
| 3.23 | CRUD InvoicePayment | Relación factura-pago | 3.16, 3.19 |

---

## Fase 4: Operaciones

| # | Tarea | Descripción | Dependencias |
| --- | --- | --- | --- |
| 4.1 | CRUD Supplier | Proveedores | Fase 1 |
| 4.2 | CRUD Purchase | Órdenes de compra | 4.1, 3.3 |
| 4.3 | CRUD PurchaseItem | Líneas de compra | 4.2 |
| 4.4 | CRUD ShippingCompany | Empresas de envío | Fase 1 |
| 4.5 | CRUD Shipment | Envíos | 4.4, 3.13 |
| 4.6 | CRUD ShipmentTracking | Seguimiento de envíos | 4.5 |
| 4.7 | CRUD ReturnRequest | Solicitudes de devolución | 3.13 |
| 4.8 | CRUD ReturnRequestItem | Ítems de devolución | 4.7 |
| 4.9 | CRUD Refund | Reembolsos | 4.7, 3.19 |
| 4.10 | CRUD Cart | Carritos de compra | 3.11, 3.3 |
| 4.11 | CRUD CartItem | Ítems del carrito | 4.10 |
| 4.12 | CRUD Wishlist | Listas de deseos | 3.11 |
| 4.13 | CRUD WishlistItem | Ítems de lista de deseos | 4.12 |

---

## Fase 5: Soporte y Configuración

| # | Tarea | Descripción | Dependencias |
| --- | --- | --- | --- |
| 5.1 | CRUD Coupon | Cupones de descuento | Fase 1 |
| 5.2 | CRUD CouponUsage | Uso de cupones | 5.1 |
| 5.3 | CRUD Promotion | Promociones | Fase 1 |
| 5.4 | CRUD ProductPromotion | Relación producto-promoción | 5.3, 3.3 |
| 5.5 | CRUD Tax | Impuestos | Fase 1 |
| 5.6 | CRUD TaxRate | Tasas de impuesto | 5.5 |
| 5.7 | CRUD SupportTicket | Tickets de soporte | 3.11 |
| 5.8 | CRUD SupportMessage | Mensajes de soporte | 5.7 |
| 5.9 | CRUD SupportCategory | Categorías de soporte | Fase 1 |
| 5.10 | CRUD SupportPriority | Prioridades de soporte | Fase 1 |
| 5.11 | CRUD Currency | Monedas | Fase 1 |
| 5.12 | CRUD ExchangeRate | Tipos de cambio | 5.11 |
| 5.13 | CRUD Audit | Auditoría (solo lectura) | Fase 1 |
| 5.14 | CRUD Notification | Notificaciones (index/show/update) | Fase 1 |

---

## Fase 6: Multi-Tienda

| # | Tarea | Descripción | Dependencias |
| --- | --- | --- | --- |
| 6.1 | CRUD Store | Gestión de tiendas | Fase 1 |
| 6.2 | CRUD StoreDomain | Dominios por tienda | 6.1 |
| 6.3 | CRUD StoreSetting | Configuración EAV por tienda | 6.1 |
| 6.4 | CRUD StoreSocialLink | Redes sociales por tienda | 6.1 |
| 6.5 | CRUD ProductStore | Producto-tienda (SEO, publicación) | 6.1, 3.3 |
| 6.6 | CRUD ProductStoreImage | Imágenes por tienda | 6.5 |
| 6.7 | Agregar rutas Stores | apiResource para los 8 controllers | 6.1–6.6 |
| 6.8 | CRUD Customization | Personalización de productos | 3.3 |
| 6.9 | CRUD DesignTemplate | Plantillas de diseño | 6.8 |
| 6.10 | CRUD PrintArea | Áreas de impresión | 6.8 |
| 6.11 | CRUD DesignFont | Fuentes de diseño | Fase 1 |
| 6.12 | CRUD DesignGraphic | Gráficos de diseño | Fase 1 |
| 6.13 | CRUD ProductCustomization | Config de personalización por producto | 6.8 |
| 6.14 | CRUD ProductCustomizationConfig | Config detallada | 6.13 |
| 6.15 | Agregar rutas Customization | apiResource para los 9 controllers | 6.8–6.14 |

---

## Fase 7: Testing

| # | Tarea | Descripción | Dependencias |
| --- | --- | --- | --- |
| 7.1 | Tests Auth | Login, registro, 2FA, recovery codes, roles | Fase 2 |
| 7.2 | Tests Catalog | CRUD productos, variantes, atributos | Fase 3 |
| 7.3 | Tests Sales | Órdenes, facturas, historial de estados | Fase 3 |
| 7.4 | Tests Payments | Pagos, transacciones, cuotas | Fase 3 |
| 7.5 | Tests Purchases | Compras, proveedores | Fase 4 |
| 7.6 | Tests Inventory | Stock, movimientos | Fase 4 |
| 7.7 | Tests Customers | Clientes, direcciones | Fase 3 |
| 7.8 | Tests Shipping | Envíos, seguimiento | Fase 4 |
| 7.9 | Tests Returns | Devoluciones, reembolsos | Fase 4 |
| 7.10 | Tests Carts | Carrito, wishlist | Fase 4 |
| 7.11 | Tests Stores | Multi-tienda, dominios | Fase 6 |
| 7.12 | Tests Customization | Personalización de productos | Fase 6 |
| 7.13 | Tests Marketing | Cupones, promociones | Fase 5 |
| 7.14 | Tests Support | Tickets de soporte | Fase 5 |
| 7.15 | Tests RBAC | Permisos y roles | Fase 2 |

---

## Dependencias entre Fases

```
Fase 1 (modelos) ──→ Fase 2 (auth)
                  ──→ Fase 3 (core business)
                  ──→ Fase 4 (operaciones)
                  ──→ Fase 5 (soporte)
                  ──→ Fase 6 (multi-tienda)
Fase 2 + 3 + 4 + 5 + 6 ──→ Fase 7 (testing)
```

---

## Archivos Relacionados

- `backend/DOCUMENTACION.md` — Documentación técnica completa
- `backend/routes/api.php` — Definición de rutas (331 líneas)
- `backend/database/migrations/` — 86 migraciones
- `backend/app/Http/Controllers/Api/` — 81 controllers
- `backend/app/Models/` — 90 modelos
- `backend/app/Services/` — 5 services
