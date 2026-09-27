/**
 * Mock data para el módulo de Inventory admin.
 *
 * Tres dominios:
 *  - stockEntries[]    : stock por producto+variante (30 items)
 *  - movements[]       : entradas/salidas/ajustes/transferencias (20 items)
 *  - warehouses[]      : depósitos físicos (5 items)
 *
 * Mismo patrón que `admin-dashboard.ts`: tipos exportados + arrays
 * congelados con `as const`. Cuando el backend exponga los endpoints
 * `/api/v1/admin/inventory/*`, los componentes hacen `Astro.fetch()`
 * con fallback a este mock (mismo approach que `RecentOrdersTable`).
 *
 * Status colors siguen la convención de `Badge.astro`:
 *   in_stock → emerald | low_stock → amber | out_of_stock → rose
 *
 * Movement types siguen convención WMS ligero (entrada/salida/ajuste/transferencia).
 *
 * **AGENTS §11** (UI states): este archivo provee data no-vacía para
 * que el componente pueda renderizar success; los componentes igual
 * manejan empty/error/loading en SSR.
 */

export type StockStatus = 'in_stock' | 'low_stock' | 'out_of_stock';
export type MovementType = 'entry' | 'exit' | 'adjustment' | 'transfer';

export interface StockEntry {
  readonly id: string;
  readonly productId: string;
  readonly productName: string;
  readonly variantLabel: string;
  readonly sku: string;
  readonly warehouseId: string;
  readonly warehouseName: string;
  readonly currentStock: number;
  readonly lowStockThreshold: number;
  readonly status: StockStatus;
}

export interface InventoryMovement {
  readonly id: string;
  readonly type: MovementType;
  readonly productName: string;
  readonly sku: string;
  readonly quantity: number;
  readonly warehouseFrom: string | null;
  readonly warehouseTo: string | null;
  readonly reason: string;
  readonly performedBy: string;
  readonly createdAt: string;
}

export interface Warehouse {
  readonly id: string;
  readonly name: string;
  readonly address: string;
  readonly city: string;
  readonly country: string;
  readonly managerName: string;
  readonly capacity: number;
  readonly itemsCount: number;
  readonly isActive: boolean;
}

export const stockEntries: readonly StockEntry[] = [
  { id: 'stk-001', productId: 'p-001', productName: 'Camiseta Kitsune Edición Limitada', variantLabel: 'Negro · M', sku: 'KIT-CS-001-M-NE', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 3, lowStockThreshold: 10, status: 'low_stock' },
  { id: 'stk-002', productId: 'p-001', productName: 'Camiseta Kitsune Edición Limitada', variantLabel: 'Negro · L', sku: 'KIT-CS-001-L-NE', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 18, lowStockThreshold: 10, status: 'in_stock' },
  { id: 'stk-003', productId: 'p-001', productName: 'Camiseta Kitsune Edición Limitada', variantLabel: 'Blanco · M', sku: 'KIT-CS-001-M-BL', warehouseId: 'wh-002', warehouseName: 'Sucursal León', currentStock: 0, lowStockThreshold: 10, status: 'out_of_stock' },
  { id: 'stk-004', productId: 'p-002', productName: 'Sudadera Negra Oversize', variantLabel: 'Única', sku: 'KIT-SD-003', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 5, lowStockThreshold: 8, status: 'low_stock' },
  { id: 'stk-005', productId: 'p-003', productName: 'Gorra Bordada Kitsune', variantLabel: 'Negro', sku: 'KIT-GO-002-NE', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 28, lowStockThreshold: 10, status: 'in_stock' },
  { id: 'stk-006', productId: 'p-003', productName: 'Gorra Bordada Kitsune', variantLabel: 'Beige', sku: 'KIT-GO-002-BE', warehouseId: 'wh-003', warehouseName: 'Sucursal Granada', currentStock: 8, lowStockThreshold: 10, status: 'low_stock' },
  { id: 'stk-007', productId: 'p-004', productName: 'Taza Personalizada Logo', variantLabel: 'Cerámica 11oz', sku: 'KIT-TZ-001-11', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 124, lowStockThreshold: 20, status: 'in_stock' },
  { id: 'stk-008', productId: 'p-004', productName: 'Taza Personalizada Logo', variantLabel: 'Cerámica 15oz', sku: 'KIT-TZ-001-15', warehouseId: 'wh-002', warehouseName: 'Sucursal León', currentStock: 0, lowStockThreshold: 15, status: 'out_of_stock' },
  { id: 'stk-009', productId: 'p-005', productName: 'Pack 3 Playeras Básicas', variantLabel: 'Surtido · M', sku: 'KIT-PK-003-M', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 42, lowStockThreshold: 12, status: 'in_stock' },
  { id: 'stk-010', productId: 'p-005', productName: 'Pack 3 Playeras Básicas', variantLabel: 'Surtido · L', sku: 'KIT-PK-003-L', warehouseId: 'wh-004', warehouseName: 'Centro de Distribución Estelí', currentStock: 7, lowStockThreshold: 12, status: 'low_stock' },
  { id: 'stk-011', productId: 'p-006', productName: 'Hoodie Bordado Premium', variantLabel: 'Negro · XL', sku: 'KIT-HO-001-XL-NE', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 1, lowStockThreshold: 5, status: 'low_stock' },
  { id: 'stk-012', productId: 'p-006', productName: 'Hoodie Bordado Premium', variantLabel: 'Gris · L', sku: 'KIT-HO-001-L-GR', warehouseId: 'wh-003', warehouseName: 'Sucursal Granada', currentStock: 16, lowStockThreshold: 5, status: 'in_stock' },
  { id: 'stk-013', productId: 'p-007', productName: 'Cuaderno Personalizado A5', variantLabel: 'Tapa dura', sku: 'KIT-CU-005', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 89, lowStockThreshold: 25, status: 'in_stock' },
  { id: 'stk-014', productId: 'p-008', productName: 'Bolsa Tote Estampada', variantLabel: 'Algodón natural', sku: 'KIT-BT-002', warehouseId: 'wh-002', warehouseName: 'Sucursal León', currentStock: 34, lowStockThreshold: 15, status: 'in_stock' },
  { id: 'stk-015', productId: 'p-009', productName: 'Botella Térmica 500ml', variantLabel: 'Acero · Negro', sku: 'KIT-BO-001-NE', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 0, lowStockThreshold: 10, status: 'out_of_stock' },
  { id: 'stk-016', productId: 'p-009', productName: 'Botella Térmica 500ml', variantLabel: 'Acero · Plata', sku: 'KIT-BO-001-PL', warehouseId: 'wh-004', warehouseName: 'Centro de Distribución Estelí', currentStock: 22, lowStockThreshold: 10, status: 'in_stock' },
  { id: 'stk-017', productId: 'p-010', productName: 'Stickers Pack x10', variantLabel: 'Mix vinilo', sku: 'KIT-ST-010', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 156, lowStockThreshold: 30, status: 'in_stock' },
  { id: 'stk-018', productId: 'p-011', productName: 'Mochila Urbana 20L', variantLabel: 'Negro', sku: 'KIT-MO-001-NE', warehouseId: 'wh-005', warehouseName: 'Bodega Temporales', currentStock: 4, lowStockThreshold: 6, status: 'low_stock' },
  { id: 'stk-019', productId: 'p-012', productName: 'Lanyard Sublimado', variantLabel: 'Polyester', sku: 'KIT-LA-001', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 73, lowStockThreshold: 20, status: 'in_stock' },
  { id: 'stk-020', productId: 'p-013', productName: 'Mousepad XL Personalizado', variantLabel: '90x40cm', sku: 'KIT-MP-002', warehouseId: 'wh-003', warehouseName: 'Sucursal Granada', currentStock: 12, lowStockThreshold: 10, status: 'in_stock' },
  { id: 'stk-021', productId: 'p-014', productName: 'Camisa Polo Bordada', variantLabel: 'Azul · L', sku: 'KIT-PL-004-L-AZ', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 6, lowStockThreshold: 8, status: 'low_stock' },
  { id: 'stk-022', productId: 'p-014', productName: 'Camisa Polo Bordada', variantLabel: 'Blanco · M', sku: 'KIT-PL-004-M-BL', warehouseId: 'wh-002', warehouseName: 'Sucursal León', currentStock: 24, lowStockThreshold: 8, status: 'in_stock' },
  { id: 'stk-023', productId: 'p-015', productName: 'Delantal Cocina Personalizado', variantLabel: 'Beige', sku: 'KIT-DE-001', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 19, lowStockThreshold: 10, status: 'in_stock' },
  { id: 'stk-024', productId: 'p-016', productName: 'Gorra Trucker Kitsune', variantLabel: 'Verde', sku: 'KIT-GO-003-VE', warehouseId: 'wh-004', warehouseName: 'Centro de Distribución Estelí', currentStock: 0, lowStockThreshold: 10, status: 'out_of_stock' },
  { id: 'stk-025', productId: 'p-017', productName: 'Calendario 2026 Personalizado', variantLabel: 'A4 espiral', sku: 'KIT-CA-001', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 48, lowStockThreshold: 15, status: 'in_stock' },
  { id: 'stk-026', productId: 'p-018', productName: 'Llavero Acrílico Sublimado', variantLabel: '5cm cuadrado', sku: 'KIT-LL-002', warehouseId: 'wh-005', warehouseName: 'Bodega Temporales', currentStock: 203, lowStockThreshold: 50, status: 'in_stock' },
  { id: 'stk-027', productId: 'p-019', productName: 'Camiseta Dry-Fit Sport', variantLabel: 'Verde · M', sku: 'KIT-DF-001-M-VE', warehouseId: 'wh-003', warehouseName: 'Sucursal Granada', currentStock: 11, lowStockThreshold: 8, status: 'in_stock' },
  { id: 'stk-028', productId: 'p-020', productName: 'Parche Bordado 7cm', variantLabel: 'Mix diseños', sku: 'KIT-PA-001', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 87, lowStockThreshold: 25, status: 'in_stock' },
  { id: 'stk-029', productId: 'p-021', productName: 'Termo Cervecero 350ml', variantLabel: 'Acero · Negro', sku: 'KIT-TE-001-NE', warehouseId: 'wh-002', warehouseName: 'Sucursal León', currentStock: 2, lowStockThreshold: 8, status: 'low_stock' },
  { id: 'stk-030', productId: 'p-022', productName: 'Memoria USB 16GB Madera', variantLabel: 'Personalizada', sku: 'KIT-US-016', warehouseId: 'wh-001', warehouseName: 'Almacén Central Managua', currentStock: 31, lowStockThreshold: 10, status: 'in_stock' },
] as const;

export const inventoryMovements: readonly InventoryMovement[] = [
  { id: 'mov-001', type: 'entry', productName: 'Camiseta Kitsune Edición Limitada', sku: 'KIT-CS-001-L-NE', quantity: 50, warehouseFrom: null, warehouseTo: 'Almacén Central Managua', reason: 'Compra a proveedor', performedBy: 'María García', createdAt: '2026-09-26T08:14:00Z' },
  { id: 'mov-002', type: 'exit', productName: 'Taza Personalizada Logo', sku: 'KIT-TZ-001-11', quantity: -12, warehouseFrom: 'Almacén Central Managua', warehouseTo: null, reason: 'Venta #ORD-1042', performedBy: 'Sistema', createdAt: '2026-09-26T09:02:00Z' },
  { id: 'mov-003', type: 'transfer', productName: 'Gorra Bordada Kitsune', sku: 'KIT-GO-002-NE', quantity: 15, warehouseFrom: 'Almacén Central Managua', warehouseTo: 'Sucursal León', reason: 'Rebalanceo', performedBy: 'Carlos López', createdAt: '2026-09-26T09:45:00Z' },
  { id: 'mov-004', type: 'adjustment', productName: 'Hoodie Bordado Premium', sku: 'KIT-HO-001-XL-NE', quantity: -2, warehouseFrom: 'Almacén Central Managua', warehouseTo: null, reason: 'Merma detectada', performedBy: 'Roberto Sánchez', createdAt: '2026-09-25T16:30:00Z' },
  { id: 'mov-005', type: 'exit', productName: 'Pack 3 Playeras Básicas', sku: 'KIT-PK-003-M', quantity: -8, warehouseFrom: 'Almacén Central Managua', warehouseTo: null, reason: 'Venta #ORD-1041', performedBy: 'Sistema', createdAt: '2026-09-25T15:12:00Z' },
  { id: 'mov-006', type: 'entry', productName: 'Stickers Pack x10', sku: 'KIT-ST-010', quantity: 200, warehouseFrom: null, warehouseTo: 'Almacén Central Managua', reason: 'Compra a proveedor', performedBy: 'María García', createdAt: '2026-09-25T11:00:00Z' },
  { id: 'mov-007', type: 'transfer', productName: 'Botella Térmica 500ml', sku: 'KIT-BO-001-PL', quantity: 30, warehouseFrom: 'Centro de Distribución Estelí', warehouseTo: 'Almacén Central Managua', reason: 'Devolución a central', performedBy: 'Ana Martínez', createdAt: '2026-09-25T10:25:00Z' },
  { id: 'mov-008', type: 'exit', productName: 'Cuaderno Personalizado A5', sku: 'KIT-CU-005', quantity: -25, warehouseFrom: 'Almacén Central Managua', warehouseTo: null, reason: 'Venta #ORD-1038', performedBy: 'Sistema', createdAt: '2026-09-24T17:48:00Z' },
  { id: 'mov-009', type: 'adjustment', productName: 'Camiseta Kitsune Edición Limitada', sku: 'KIT-CS-001-M-NE', quantity: +4, warehouseFrom: 'Almacén Central Managua', warehouseTo: null, reason: 'Conteo físico', performedBy: 'Roberto Sánchez', createdAt: '2026-09-24T14:00:00Z' },
  { id: 'mov-010', type: 'entry', productName: 'Mochila Urbana 20L', sku: 'KIT-MO-001-NE', quantity: 12, warehouseFrom: null, warehouseTo: 'Bodega Temporales', reason: 'Compra a proveedor', performedBy: 'María García', createdAt: '2026-09-24T09:30:00Z' },
  { id: 'mov-011', type: 'exit', productName: 'Lanyard Sublimado', sku: 'KIT-LA-001', quantity: -45, warehouseFrom: 'Almacén Central Managua', warehouseTo: null, reason: 'Venta corporativa B2B', performedBy: 'Sistema', createdAt: '2026-09-23T16:15:00Z' },
  { id: 'mov-012', type: 'transfer', productName: 'Camisa Polo Bordada', sku: 'KIT-PL-004-L-AZ', quantity: 10, warehouseFrom: 'Almacén Central Managua', warehouseTo: 'Sucursal Granada', reason: 'Rebalanceo', performedBy: 'Carlos López', createdAt: '2026-09-23T13:20:00Z' },
  { id: 'mov-013', type: 'adjustment', productName: 'Parche Bordado 7cm', sku: 'KIT-PA-001', quantity: -5, warehouseFrom: 'Almacén Central Managua', warehouseTo: null, reason: 'Pérdida registrada', performedBy: 'Roberto Sánchez', createdAt: '2026-09-23T11:00:00Z' },
  { id: 'mov-014', type: 'entry', productName: 'Mousepad XL Personalizado', sku: 'KIT-MP-002', quantity: 30, warehouseFrom: null, warehouseTo: 'Sucursal Granada', reason: 'Compra a proveedor', performedBy: 'Ana Martínez', createdAt: '2026-09-22T08:00:00Z' },
  { id: 'mov-015', type: 'exit', productName: 'Memoria USB 16GB Madera', sku: 'KIT-US-016', quantity: -6, warehouseFrom: 'Almacén Central Managua', warehouseTo: null, reason: 'Venta #ORD-1033', performedBy: 'Sistema', createdAt: '2026-09-22T15:42:00Z' },
  { id: 'mov-016', type: 'transfer', productName: 'Termo Cervecero 350ml', sku: 'KIT-TE-001-NE', quantity: 8, warehouseFrom: 'Almacén Central Managua', warehouseTo: 'Sucursal León', reason: 'Rebalanceo', performedBy: 'Carlos López', createdAt: '2026-09-22T12:00:00Z' },
  { id: 'mov-017', type: 'exit', productName: 'Bolsa Tote Estampada', sku: 'KIT-BT-002', quantity: -10, warehouseFrom: 'Sucursal León', warehouseTo: null, reason: 'Venta #ORD-1029', performedBy: 'Sistema', createdAt: '2026-09-21T17:30:00Z' },
  { id: 'mov-018', type: 'entry', productName: 'Calendario 2026 Personalizado', sku: 'KIT-CA-001', quantity: 80, warehouseFrom: null, warehouseTo: 'Almacén Central Managua', reason: 'Producción local', performedBy: 'María García', createdAt: '2026-09-21T10:00:00Z' },
  { id: 'mov-019', type: 'adjustment', productName: 'Llavero Acrílico Sublimado', sku: 'KIT-LL-002', quantity: -12, warehouseFrom: 'Bodega Temporales', warehouseTo: null, reason: 'Conteo físico', performedBy: 'Roberto Sánchez', createdAt: '2026-09-20T15:30:00Z' },
  { id: 'mov-020', type: 'exit', productName: 'Camiseta Dry-Fit Sport', sku: 'KIT-DF-001-M-VE', quantity: -4, warehouseFrom: 'Sucursal Granada', warehouseTo: null, reason: 'Venta #ORD-1024', performedBy: 'Sistema', createdAt: '2026-09-20T11:20:00Z' },
] as const;

export const warehouses: readonly Warehouse[] = [
  {
    id: 'wh-001',
    name: 'Almacén Central Managua',
    address: 'Km 7.5 Carretera a Masaya, Plaza Natura Local 12',
    city: 'Managua',
    country: 'NI',
    managerName: 'María García',
    capacity: 12000,
    itemsCount: 1247,
    isActive: true,
  },
  {
    id: 'wh-002',
    name: 'Sucursal León',
    address: 'Av. Rubén Darío, esquina opuesta a la UNAN',
    city: 'León',
    country: 'NI',
    managerName: 'Carlos López',
    capacity: 3500,
    itemsCount: 412,
    isActive: true,
  },
  {
    id: 'wh-003',
    name: 'Sucursal Granada',
    address: 'Calle La Calzada, contiguo a Café Guardabarranco',
    city: 'Granada',
    country: 'NI',
    managerName: 'Ana Martínez',
    capacity: 2800,
    itemsCount: 318,
    isActive: true,
  },
  {
    id: 'wh-004',
    name: 'Centro de Distribución Estelí',
    address: 'Salida norte hacia Condega, complejo logístico',
    city: 'Estelí',
    country: 'NI',
    managerName: 'Roberto Sánchez',
    capacity: 5400,
    itemsCount: 681,
    isActive: true,
  },
  {
    id: 'wh-005',
    name: 'Bodega Temporales',
    address: 'Km 4 Carretera Norte, zona industrial',
    city: 'Managua',
    country: 'NI',
    managerName: 'Laura Díaz',
    capacity: 8000,
    itemsCount: 207,
    isActive: false,
  },
] as const;

/**
 * Helper: deriva status visual a partir del stock vs threshold.
 *
 * Centraliza la regla para que el `StockTable.astro` y el `AdjustStockModal.astro`
 * no dupliquen la lógica. Si el backend ya devuelve `status` en cada entry,
 * se prefiere el valor de la API; este helper es fallback cuando llegan
 * entries crudos sin clasificar.
 *
 *   - currentStock <= 0                              → 'out_of_stock'
 *   - currentStock > 0 && currentStock <= threshold → 'low_stock'
 *   - currentStock > threshold                       → 'in_stock'
 */
export function deriveStockStatus(currentStock: number, threshold: number): StockStatus {
  if (currentStock <= 0) return 'out_of_stock';
  if (currentStock <= threshold) return 'low_stock';
  return 'in_stock';
}
