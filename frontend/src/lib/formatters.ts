/**
 * Formateadores compartidos para la UI.
 *
 * Mantienen paridad con el backend:
 *   - `formatMoney()` consume la shape `{ cents, formatted, currency_code }`
 *     que devuelve `MoneyFormatter::payload()` en Laravel.
 *
 * Centralizar formateo aquí evita reinventar `Intl.NumberFormat` en cada
 * componente y mantiene consistencia visual (decimales, símbolo, locale).
 */

export type MoneyPayload = {
  cents: number;
  formatted: string;
  currency_code: string | null;
};

const DEFAULT_LOCALE = "es-NI";
const DEFAULT_CURRENCY = "USD";

/**
 * Formatea un payload money del backend.
 *
 * Si el payload ya viene con `formatted` desde el backend, lo retorna
 * directamente (single source of truth). Si no, lo construye en cliente
 * usando `Intl.NumberFormat`.
 *
 * @example
 *   formatMoney({ cents: 5000, formatted: "50.00", currency_code: "USD" })
 *   // → "50.00"
 */
export function formatMoney(payload: MoneyPayload | null | undefined): string {
  if (!payload) {
    return "—";
  }

  if (payload.formatted && payload.currency_code) {
    return payload.formatted;
  }

  if (payload.formatted) {
    return payload.formatted;
  }

  return formatCentsAsDecimal(payload.cents, payload.currency_code);
}

/**
 * Formatea un monto en cents como string decimal (ej. `25_00` → `"25.00"`).
 *
 * Usado cuando NO tenemos el payload completo del backend (cálculos en
 * cliente) o cuando queremos ignorar el `formatted` server-side.
 */
export function formatCentsAsDecimal(cents: number, currencyCode: string | null = null): string {
  const code = currencyCode ?? DEFAULT_CURRENCY;
  const decimals = code === "JPY" ? 0 : 2;

  return new Intl.NumberFormat(DEFAULT_LOCALE, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(cents / 10 ** decimals);
}

/**
 * Formatea un timestamp ISO 8601 como fecha local.
 */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) {
    return "—";
  }

  return new Intl.DateTimeFormat(DEFAULT_LOCALE, {
    year: "numeric",
    month: "short",
    day: "2-digit",
  }).format(new Date(iso));
}

/**
 * Formatea un timestamp ISO 8601 como fecha + hora local.
 */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) {
    return "—";
  }

  return new Intl.DateTimeFormat(DEFAULT_LOCALE, {
    year: "numeric",
    month: "short",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(iso));
}