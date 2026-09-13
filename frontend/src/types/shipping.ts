/**
 * Types del dominio Shipping.
 *
 * Reflejan la shape que devuelve `ShipmentResource` en Laravel:
 *   - `shipping_cost` es un `MoneyPayload` (cents + formatted + currency_code).
 *   - `status` es un enum PHP que llega como string.
 *   - `shipped_at` y `delivered_at` son ISO 8601 (pueden ser null).
 */

import type { MoneyPayload } from '../lib/formatters';

export const SHIPMENT_STATUSES = [
  'pending',
  'shipped',
  'in_transit',
  'delivered',
  'cancelled',
] as const;

export type ShipmentStatus = (typeof SHIPMENT_STATUSES)[number];

export type Shipment = {
  id: number;
  order_id: number;
  shipping_company_id: number | null;
  shipping_zone_id: number | null;
  currency_id: number;
  tracking_code: string | null;
  status: ShipmentStatus;
  shipping_cost: MoneyPayload;
  shipped_at: string | null;
  delivered_at: string | null;
  created_at: string;
  updated_at: string;
};

export type ShipmentWithRelations = Shipment & {
  shipping_company?: {
    id: number;
    name: string;
  } | null;
  shipping_zone?: {
    id: number;
    name: string;
  } | null;
  currency?: {
    id: number;
    code: string;
    symbol: string;
  } | null;
};

export const SHIPMENT_STATUS_LABELS: Record<ShipmentStatus, string> = {
  pending: 'Pendiente',
  shipped: 'Despachado',
  in_transit: 'En tránsito',
  delivered: 'Entregado',
  cancelled: 'Cancelado',
};

export const SHIPMENT_STATUS_COLORS: Record<ShipmentStatus, string> = {
  pending: 'bg-yellow-100 text-yellow-800',
  shipped: 'bg-blue-100 text-blue-800',
  in_transit: 'bg-indigo-100 text-indigo-800',
  delivered: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800',
};
