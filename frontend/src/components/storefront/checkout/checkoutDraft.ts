/**
 * checkoutDraft — borrador del form de checkout en sessionStorage.
 *
 * Persiste los valores entre pasos y reloads (el bug original del
 * flujo GET). Se limpia tras el submit exitoso. Vive en sessionStorage
 * (no localStorage): el borrador es de la sesión de compra.
 */
import {
  EMPTY_CHECKOUT_VALUES,
  PAYMENT_METHODS,
  type CheckoutFormValues,
  type PaymentMethod,
} from './checkoutSchemas';

export const CHECKOUT_DRAFT_KEY = 'checkout.draft';

export function isCheckoutFormValues(value: unknown): value is CheckoutFormValues {
  if (typeof value !== 'object' || value === null) return false;
  const record = value as Record<string, unknown>;
  const textFields = [
    'name',
    'email',
    'phone',
    'address1',
    'address2',
    'city',
    'state',
    'postal',
    'country',
    'cardName',
    'cardNumber',
    'expiry',
    'cvv',
  ] as const;
  for (const field of textFields) {
    if (typeof record[field] !== 'string') return false;
  }
  return (
    typeof record.paymentMethod === 'string' &&
    (PAYMENT_METHODS as readonly string[]).includes(record.paymentMethod)
  );
}

function getStorage(): Storage | null {
  if (typeof window === 'undefined') return null;
  return window.sessionStorage ?? null;
}

export function readCheckoutDraft(): CheckoutFormValues {
  const storage = getStorage();
  if (storage === null) return { ...EMPTY_CHECKOUT_VALUES };
  try {
    const raw = storage.getItem(CHECKOUT_DRAFT_KEY);
    if (raw === null) return { ...EMPTY_CHECKOUT_VALUES };
    const parsed: unknown = JSON.parse(raw);
    if (!isCheckoutFormValues(parsed)) return { ...EMPTY_CHECKOUT_VALUES };
    return { ...parsed, paymentMethod: parsed.paymentMethod as PaymentMethod };
  } catch {
    return { ...EMPTY_CHECKOUT_VALUES };
  }
}

export function writeCheckoutDraft(values: CheckoutFormValues): void {
  const storage = getStorage();
  if (storage === null) return;
  storage.setItem(CHECKOUT_DRAFT_KEY, JSON.stringify(values));
}

export function clearCheckoutDraft(): void {
  const storage = getStorage();
  if (storage === null) return;
  storage.removeItem(CHECKOUT_DRAFT_KEY);
}
