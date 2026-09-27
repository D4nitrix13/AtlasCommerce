/**
 * Formateadores compartidos para la UI.
 *
 * Mantienen paridad con el backend:
 *   - `formatMoney()` consume la shape `{ cents, formatted, currency_code }`
 *     que devuelve `MoneyFormatter::payload()` en Laravel.
 *   - `formatCurrency()` es el helper que prefiere la UI pública: recibe
 *     un monto en cents y un currency code opcional, y devuelve la
 *     versión localizada (ej. "$25.00", "¥2500", "C$15.50"). Es la
 *     versión "lista para mostrar" — la que consumen ProductCard,
 *     CartSummary, etc.
 *
 * Centralizar formateo aquí evita reinventar `Intl.NumberFormat` en cada
 * componente y mantiene consistencia visual (decimales, símbolo, locale).
 */

export type MoneyPayload = {
  cents: number;
  formatted: string;
  currency_code: string | null;
};

const DEFAULT_LOCALE = 'es-NI';
const DEFAULT_CURRENCY = 'USD';

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
    return '—';
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
  const decimals = code === 'JPY' ? 0 : 2;

  return new Intl.NumberFormat(DEFAULT_LOCALE, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(cents / 10 ** decimals);
}

/**
 * Formatea un monto en cents como moneda localizada con símbolo.
 *
 * Es el helper principal para ProductCard, CartSummary y cualquier
 * superficie pública que muestre precios al cliente. Usa
 * `Intl.NumberFormat` con `style: 'currency'` para respetar convenciones
 * por país (símbolo antes/después, separadores, decimales).
 *
 * Usa el locale del runtime (`navigator.language` en cliente, fallback
 * `es-NI` en SSR) para que el símbolo y los separadores se vean nativos
 * al usuario.
 *
 * Si el `currencyCode` es desconocido por el runtime, cae a
 * `formatCentsAsDecimal` para evitar que la UI muestre literal
 * `[currency]` o un símbolo roto.
 *
 * @example
 *   formatCurrency(2500)                       // "$25.00"
 *   formatCurrency(2500, 'USD')                // "$25.00"
 *   formatCurrency(250000, 'JPY')              // "¥2,500"
 *   formatCurrency(1500, 'NIO')                // "C$15.00"
 */
export function formatCurrency(cents: number, currencyCode: string | null = null): string {
  const code = currencyCode ?? DEFAULT_CURRENCY;

  try {
    return new Intl.NumberFormat(resolveRuntimeLocale(), {
      style: 'currency',
      currency: code,
    }).format(cents / (code === 'JPY' ? 1 : 100));
  } catch {
    // Currency code desconocido por ICU: cae al decimal sin símbolo.
    return formatCentsAsDecimal(cents, code);
  }
}

/**
 * Resuelve el locale del runtime (cliente) o cae al default para SSR.
 *
 * En SSR siempre devuelve `DEFAULT_LOCALE` para mantener consistencia
 * entre server-render y client-hydrate (sin mismatch). En cliente usa
 * `navigator.language` para que el formateo se sienta nativo.
 */
function resolveRuntimeLocale(): string {
  if (typeof navigator === 'undefined') {
    return DEFAULT_LOCALE;
  }
  return navigator.language ?? DEFAULT_LOCALE;
}

/**
 * Formatea un timestamp ISO 8601 como fecha local.
 */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) {
    return '—';
  }

  return new Intl.DateTimeFormat(DEFAULT_LOCALE, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  }).format(new Date(iso));
}

/**
 * Formatea un timestamp ISO 8601 como fecha + hora local.
 */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) {
    return '—';
  }

  return new Intl.DateTimeFormat(DEFAULT_LOCALE, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(iso));
}
