/**
 * Descuentos de volumen compartidos por la calculadora y la cotización.
 * Los cortes son los mismos cinco tiers de la página de pedido por volumen.
 */

const BULK_DISCOUNTS: readonly { minQuantity: number; discountPercent: number }[] = [
  { minQuantity: 500, discountPercent: 35 },
  { minQuantity: 100, discountPercent: 20 },
  { minQuantity: 50, discountPercent: 12 },
  { minQuantity: 25, discountPercent: 5 },
  { minQuantity: 10, discountPercent: 0 },
];

export function bulkDiscountPercent(quantity: number): number {
  const matched = BULK_DISCOUNTS.find((tier) => quantity >= tier.minQuantity);
  return matched?.discountPercent ?? 0;
}

export function bulkTotalCents(unitCents: number, quantity: number): number {
  const discount = bulkDiscountPercent(quantity);
  return Math.round(unitCents * quantity * (1 - discount / 100));
}
