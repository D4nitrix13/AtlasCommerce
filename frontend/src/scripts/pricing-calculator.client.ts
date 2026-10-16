/**
 * Pricing calculator controller (cliente).
 *
 * Sincroniza slider ↔ number input, lee el producto seleccionado
 * (basePriceCents + currency desde data-attributes), resuelve el
 * tier aplicable y reescribe los outputs (unit / total / savings).
 *
 * Sin dependencias externas — usa `Intl.NumberFormat` del runtime
 * para mantener la moneda del producto.
 *
 * Importado vía `<script>` desde PricingCalculator.astro. La función
 * `initPricingCalculator` se expone en `window` para que el script
 * inline la invoque sin bundling adicional.
 */

export interface TierDiscount {
  /** Cantidad mínima inclusiva donde aplica este descuento. */
  minQuantity: number;
  /** Porcentaje de descuento entero. */
  discountPercent: number;
}

function formatCents(cents: number, currency: string): string {
  try {
    const locale = typeof navigator !== 'undefined' ? navigator.language : 'es-NI';
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency,
    }).format(cents / 100);
  } catch {
    return `${(cents / 100).toFixed(2)} ${currency}`;
  }
}

function resolveDiscount(quantity: number, tiers: readonly TierDiscount[]): number {
  const matched = [...tiers].reverse().find((tier) => quantity >= tier.minQuantity);
  return matched?.discountPercent ?? 0;
}

function tierRangeLabel(quantity: number, tiers: readonly TierDiscount[]): string {
  const matched = [...tiers].reverse().find((tier) => quantity >= tier.minQuantity);
  if (matched === undefined) return '10+';
  if (matched.minQuantity === 500) return `${matched.minQuantity}+`;
  const next = tiers.find((t) => t.minQuantity > matched.minQuantity);
  if (next === undefined) return `${matched.minQuantity}+`;
  return `${matched.minQuantity}\u2013${next.minQuantity - 1}`;
}

function parseTiers(raw: string | undefined): readonly TierDiscount[] {
  if (raw === undefined) return [];
  try {
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    return parsed
      .filter(
        (tier): tier is TierDiscount =>
          typeof tier === 'object' &&
          tier !== null &&
          typeof (tier as TierDiscount).minQuantity === 'number' &&
          typeof (tier as TierDiscount).discountPercent === 'number'
      )
      .sort((a, b) => a.minQuantity - b.minQuantity);
  } catch {
    return [];
  }
}

function initPricingCalculator(form: HTMLFormElement): void {
  const slider = form.querySelector<HTMLInputElement>('[data-pricing-slider]');
  const numberInput = form.querySelector<HTMLInputElement>('[data-pricing-input]');
  const productSelect = form.querySelector<HTMLSelectElement>('[data-pricing-product]');
  const tierBadge = form.querySelector<HTMLElement>('[data-pricing-tier]');
  const unitNode = form.querySelector<HTMLElement>('[data-pricing-unit]');
  const totalNode = form.querySelector<HTMLElement>('[data-pricing-total]');
  const savingsNode = form.querySelector<HTMLElement>('[data-pricing-savings]');
  const liveNode = form.querySelector<HTMLElement>('[data-pricing-output]');
  if (
    slider === null ||
    numberInput === null ||
    productSelect === null ||
    tierBadge === null ||
    unitNode === null ||
    totalNode === null ||
    savingsNode === null ||
    liveNode === null
  ) {
    return;
  }

  const tierLabelTemplate = tierBadge.textContent ?? '';
  const quantityLabelText =
    numberInput.parentElement?.previousElementSibling?.textContent?.trim() ?? 'Cantidad';
  const unitLabelText = unitNode.previousElementSibling?.textContent?.trim() ?? 'Precio unitario';
  const totalLabelText =
    totalNode.parentElement?.previousElementSibling?.textContent?.trim() ?? 'Total';

  const tiers = parseTiers(form.dataset.tiers);

  const update = (): void => {
    const quantity = Number.parseInt(slider.value, 10);
    const safeQuantity = Number.isNaN(quantity)
      ? Number.parseInt(numberInput.value, 10) || 10
      : quantity;

    const selectedOption = productSelect.selectedOptions[0];
    const basePriceCents = Number.parseInt(selectedOption?.dataset.basePrice ?? '0', 10);
    const currency = selectedOption?.dataset.currency ?? 'USD';

    const discount = resolveDiscount(safeQuantity, tiers);
    const totalCents = Math.round(basePriceCents * safeQuantity * (1 - discount / 100));
    const savingsCents = Math.round(basePriceCents * safeQuantity * (discount / 100));
    const rangeLabel = tierRangeLabel(safeQuantity, tiers);
    const discountLabel = discount === 0 ? '0%' : `-${discount}%`;

    unitNode.textContent = formatCents(basePriceCents, currency);
    totalNode.textContent = formatCents(totalCents, currency);
    savingsNode.textContent = formatCents(savingsCents, currency);
    tierBadge.textContent = tierLabelTemplate
      .replace('{range}', rangeLabel)
      .replace('{discount}', discountLabel);

    slider.setAttribute('aria-valuenow', String(safeQuantity));
    liveNode.textContent = `${quantityLabelText}: ${safeQuantity}. ${unitLabelText}: ${formatCents(
      basePriceCents,
      currency
    )}. ${totalLabelText}: ${formatCents(totalCents, currency)}.`;
  };

  const syncFromSlider = (): void => {
    numberInput.value = slider.value;
    update();
  };

  const syncFromInput = (): void => {
    const parsed = Number.parseInt(numberInput.value, 10);
    const safe = Number.isNaN(parsed) ? 10 : Math.max(10, Math.min(1000, parsed));
    slider.value = String(safe);
    numberInput.value = String(safe);
    update();
  };

  slider.addEventListener('input', syncFromSlider);
  numberInput.addEventListener('input', syncFromInput);
  productSelect.addEventListener('change', update);

  update();
}

function bootstrapPricingCalculator(): void {
  document
    .querySelectorAll<HTMLFormElement>('form[data-pricing-calculator]')
    .forEach(initPricingCalculator);
}

if (typeof window !== 'undefined') {
  window.addEventListener('DOMContentLoaded', bootstrapPricingCalculator);
  window.addEventListener('astro:page-load', bootstrapPricingCalculator);
}
