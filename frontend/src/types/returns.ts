/**
 * Types del dominio Returns (return requests + refunds).
 *
 * Reflejan la shape de `ReturnRequestResource` y `RefundResource`.
 */

import type { MoneyPayload } from "../lib/formatters";

export const RETURN_REQUEST_STATUSES = [
  "pending",
  "approved",
  "rejected",
  "completed",
  "cancelled",
] as const;

export type ReturnRequestStatus = (typeof RETURN_REQUEST_STATUSES)[number];

export const REFUND_STATUSES = ["pending", "completed", "failed"] as const;

export type RefundStatus = (typeof REFUND_STATUSES)[number];

export type ReturnRequestItem = {
  id: number;
  return_request_id: number;
  product_id: number;
  product_variant_id: number | null;
  quantity: number;
  amount: MoneyPayload;
  product?: {
    id: number;
    name: string;
  } | null;
};

export type ReturnRequest = {
  id: number;
  store_id: number;
  order_id: number;
  customer_id: number;
  reason: string;
  status: ReturnRequestStatus;
  requested_at: string;
  resolved_at: string | null;
  created_at: string;
  updated_at: string;
};

export type ReturnRequestWithRelations = ReturnRequest & {
  items?: ReturnRequestItem[];
  order?: {
    id: number;
    order_number: string;
  } | null;
  customer?: {
    id: number;
    first_name?: string;
    last_name?: string;
    email?: string;
  } | null;
};

export type Refund = {
  id: number;
  return_request_id: number;
  payment_method_id: number | null;
  currency_id: number;
  status: RefundStatus;
  amount: MoneyPayload;
  exchange_rate_snapshot: string;
  refunded_at: string | null;
  created_at: string;
  updated_at: string;
};

export type RefundWithRelations = Refund & {
  return_request?: {
    id: number;
    status: ReturnRequestStatus;
  } | null;
};

export const RETURN_REQUEST_STATUS_LABELS: Record<ReturnRequestStatus, string> = {
  pending: "Pendiente",
  approved: "Aprobada",
  rejected: "Rechazada",
  completed: "Completada",
  cancelled: "Cancelada",
};

export const RETURN_REQUEST_STATUS_COLORS: Record<ReturnRequestStatus, string> = {
  pending: "bg-yellow-100 text-yellow-800",
  approved: "bg-blue-100 text-blue-800",
  rejected: "bg-red-100 text-red-800",
  completed: "bg-green-100 text-green-800",
  cancelled: "bg-gray-100 text-gray-800",
};

export const REFUND_STATUS_LABELS: Record<RefundStatus, string> = {
  pending: "Pendiente",
  completed: "Completado",
  failed: "Fallido",
};

export const REFUND_STATUS_COLORS: Record<RefundStatus, string> = {
  pending: "bg-yellow-100 text-yellow-800",
  completed: "bg-green-100 text-green-800",
  failed: "bg-red-100 text-red-800",
};