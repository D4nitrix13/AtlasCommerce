/**
 * Mock data para matriz roles↔permisos (RF-AUTH-008).
 *
 * `adminRoles[]` — 6 roles con:
 *  - id, name, slug, description, status
 *  - is_protected (true en `admin` para no exponer acciones destructivas)
 *  - permissions[] — slugs efectivos del rol
 *  - users_count — para mostrar "X usuarios" en la card
 *
 * `allPermissions[]` — 24 permisos del sistema agrupados por módulo
 * (catalog, inventory, sales, marketing, support, stores, reports,
 * system). El módulo se usa para renderizar la matriz en columnas.
 *
 * Cuando el backend exponga `/api/v1/admin/roles` y
 * `/api/v1/admin/permissions`, los componentes hacen `apiFetch` con
 * fallback a este mock (mismo approach que `CustomersAdmin.tsx`).
 *
 * **AGENTS §11** (UI states): este archivo provee data no-vacía para
 * que el componente renderice success; el componente igual maneja
 * empty/error/loading.
 */

export type RoleStatus = 'active' | 'inactive';

export type PermissionModule =
  'catalog' | 'inventory' | 'sales' | 'marketing' | 'support' | 'stores' | 'reports' | 'system';

export interface AdminRole {
  readonly id: number;
  readonly name: string;
  readonly slug: string;
  readonly description: string;
  readonly status: RoleStatus;
  readonly is_protected: boolean;
  readonly permissions: readonly string[];
  readonly users_count: number;
  readonly created_at: string;
}

export interface Permission {
  readonly id: number;
  readonly name: string;
  readonly slug: string;
  readonly module: PermissionModule;
  readonly description: string;
}

export const adminRoles: readonly AdminRole[] = [
  {
    id: 1,
    name: 'Administrador',
    slug: 'admin',
    description: 'Acceso total al panel. Bypasea todas las verificaciones de permiso.',
    status: 'active',
    is_protected: true,
    permissions: [],
    users_count: 1,
    created_at: '2024-01-15T10:00:00Z',
  },
  {
    id: 2,
    name: 'Gerente de tienda',
    slug: 'store_manager',
    description: 'Gestiona órdenes, productos e inventario de sus tiendas asignadas.',
    status: 'active',
    is_protected: false,
    permissions: [
      'catalog.products.view',
      'catalog.products.edit',
      'inventory.stock.view',
      'inventory.stock.adjust',
      'sales.orders.view',
      'sales.orders.edit',
      'sales.invoices.view',
      'reports.view',
      'stores.view',
    ],
    users_count: 2,
    created_at: '2024-03-12T10:00:00Z',
  },
  {
    id: 3,
    name: 'Operador de ventas',
    slug: 'sales_operator',
    description: 'Procesa órdenes, actualiza estados y emite facturas. Sin acceso a inventario.',
    status: 'active',
    is_protected: false,
    permissions: [
      'sales.orders.view',
      'sales.orders.edit',
      'sales.orders.cancel',
      'sales.invoices.view',
      'sales.invoices.edit',
      'customers.view',
    ],
    users_count: 3,
    created_at: '2024-05-04T09:30:00Z',
  },
  {
    id: 4,
    name: 'Operador de inventario',
    slug: 'inventory_operator',
    description: 'Ajusta stock, registra movimientos y gestiona depósitos.',
    status: 'active',
    is_protected: false,
    permissions: [
      'catalog.products.view',
      'inventory.stock.view',
      'inventory.stock.adjust',
      'inventory.stock.transfer',
      'inventory.movements.view',
      'inventory.warehouses.view',
    ],
    users_count: 2,
    created_at: '2024-07-22T14:00:00Z',
  },
  {
    id: 5,
    name: 'Soporte al cliente',
    slug: 'support_agent',
    description: 'Resuelve tickets, modera reseñas y consulta datos de clientes.',
    status: 'active',
    is_protected: false,
    permissions: [
      'customers.view',
      'customers.view_orders',
      'support.tickets.view',
      'support.tickets.reply',
      'support.reviews.view',
      'support.reviews.moderate',
      'sales.orders.view',
    ],
    users_count: 2,
    created_at: '2024-09-15T08:45:00Z',
  },
  {
    id: 6,
    name: 'Marketing',
    slug: 'marketing_editor',
    description: 'Crea y edita cupones, promociones y campañas. Sin acceso a ventas.',
    status: 'inactive',
    is_protected: false,
    permissions: [
      'marketing.coupons.view',
      'marketing.coupons.edit',
      'marketing.promotions.view',
      'marketing.promotions.edit',
      'catalog.products.view',
      'reports.view',
    ],
    users_count: 2,
    created_at: '2025-01-08T12:00:00Z',
  },
] as const;

export const allPermissions: readonly Permission[] = [
  // Catalog
  {
    id: 1,
    name: 'Ver productos',
    slug: 'catalog.products.view',
    module: 'catalog',
    description: 'Listar y ver detalle de productos.',
  },
  {
    id: 2,
    name: 'Editar productos',
    slug: 'catalog.products.edit',
    module: 'catalog',
    description: 'Crear, editar y archivar productos.',
  },
  {
    id: 3,
    name: 'Gestionar categorías',
    slug: 'catalog.categories.manage',
    module: 'catalog',
    description: 'Crear y reorganizar el árbol de categorías.',
  },
  {
    id: 4,
    name: 'Gestionar marcas',
    slug: 'catalog.brands.manage',
    module: 'catalog',
    description: 'CRUD de marcas y logos.',
  },

  // Inventory
  {
    id: 5,
    name: 'Ver stock',
    slug: 'inventory.stock.view',
    module: 'inventory',
    description: 'Consultar stock por producto y depósito.',
  },
  {
    id: 6,
    name: 'Ajustar stock',
    slug: 'inventory.stock.adjust',
    module: 'inventory',
    description: 'Registrar ajustes manuales con motivo.',
  },
  {
    id: 7,
    name: 'Transferir stock',
    slug: 'inventory.stock.transfer',
    module: 'inventory',
    description: 'Mover stock entre depósitos.',
  },
  {
    id: 8,
    name: 'Ver movimientos',
    slug: 'inventory.movements.view',
    module: 'inventory',
    description: 'Auditar entradas, salidas y transferencias.',
  },
  {
    id: 9,
    name: 'Ver depósitos',
    slug: 'inventory.warehouses.view',
    module: 'inventory',
    description: 'Listar depósitos y métricas.',
  },

  // Sales
  {
    id: 10,
    name: 'Ver órdenes',
    slug: 'sales.orders.view',
    module: 'sales',
    description: 'Listar y ver detalle de órdenes.',
  },
  {
    id: 11,
    name: 'Editar órdenes',
    slug: 'sales.orders.edit',
    module: 'sales',
    description: 'Cambiar estado y datos de envío.',
  },
  {
    id: 12,
    name: 'Cancelar órdenes',
    slug: 'sales.orders.cancel',
    module: 'sales',
    description: 'Cancelar órdenes emitidas.',
  },
  {
    id: 13,
    name: 'Ver facturas',
    slug: 'sales.invoices.view',
    module: 'sales',
    description: 'Listar facturas y ver detalle.',
  },
  {
    id: 14,
    name: 'Editar facturas',
    slug: 'sales.invoices.edit',
    module: 'sales',
    description: 'Generar, enviar y marcar como pagadas.',
  },

  // Marketing
  {
    id: 15,
    name: 'Ver cupones',
    slug: 'marketing.coupons.view',
    module: 'marketing',
    description: 'Listar cupones del tenant.',
  },
  {
    id: 16,
    name: 'Editar cupones',
    slug: 'marketing.coupons.edit',
    module: 'marketing',
    description: 'Crear, editar y desactivar cupones.',
  },
  {
    id: 17,
    name: 'Ver promociones',
    slug: 'marketing.promotions.view',
    module: 'marketing',
    description: 'Listar promociones automáticas.',
  },
  {
    id: 18,
    name: 'Editar promociones',
    slug: 'marketing.promotions.edit',
    module: 'marketing',
    description: 'Crear y editar reglas de promoción.',
  },

  // Support
  {
    id: 19,
    name: 'Ver tickets',
    slug: 'support.tickets.view',
    module: 'support',
    description: 'Bandeja de tickets.',
  },
  {
    id: 20,
    name: 'Responder tickets',
    slug: 'support.tickets.reply',
    module: 'support',
    description: 'Publicar respuestas y cambiar estado.',
  },
  {
    id: 21,
    name: 'Moderar reseñas',
    slug: 'support.reviews.moderate',
    module: 'support',
    description: 'Aprobar, rechazar y responder reseñas.',
  },

  // Stores / Reports / System
  {
    id: 22,
    name: 'Ver tiendas',
    slug: 'stores.view',
    module: 'stores',
    description: 'Listar tiendas del tenant.',
  },
  {
    id: 23,
    name: 'Ver reportes',
    slug: 'reports.view',
    module: 'reports',
    description: 'Dashboards y exports de métricas.',
  },
  {
    id: 24,
    name: 'Gestionar sistema',
    slug: 'system.manage',
    module: 'system',
    description: 'Configuración global y auditoría.',
  },
] as const;

/**
 * Orden de módulos en la matriz de permisos.
 *
 * Define el orden visual de las columnas de la matriz. Coincide con
 * el flujo típico de un e-commerce: catálogo → inventario → ventas →
 * marketing → soporte → infraestructura.
 */
export const PERMISSION_MODULE_ORDER: readonly PermissionModule[] = [
  'catalog',
  'inventory',
  'sales',
  'marketing',
  'support',
  'stores',
  'reports',
  'system',
] as const;
