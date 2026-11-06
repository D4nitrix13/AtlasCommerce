import { bulkTotalCents, bulkDiscountPercent } from '../../../lib/bulk-estimate';

/**
 * Cotización en vivo: el resumen sigue al formulario y el envío
 * abre el ticket. El total usa los mismos cortes de volumen que la
 * calculadora. Cuando exista POST /api/storefront/rfq, este handler
 * pasa a ser un fetch.
 */
const form = document.getElementById('rfq-form') as HTMLFormElement | null;
const summaryProducto = document.querySelector('[data-testid="rfq-summary-producto"]');
const summaryCantidad = document.querySelector('[data-testid="rfq-summary-cantidad"]');
const summaryTecnica = document.querySelector('[data-testid="rfq-summary-tecnica"]');
const summaryTotal = document.querySelector('[data-testid="rfq-summary-total"]');
const summaryUnitPrice = document.querySelector('[data-testid="rfq-summary-unit-price"]');
const summaryDiscountRow = document.querySelector('[data-testid="rfq-summary-discount-row"]');
const summaryDiscountPct = document.querySelector('[data-testid="rfq-summary-discount-pct"]');

function readControl(source: HTMLFormElement, name: string): HTMLElement | null {
  const element = source.elements.namedItem(name);
  if (
    element instanceof HTMLInputElement ||
    element instanceof HTMLTextAreaElement ||
    element instanceof HTMLSelectElement
  ) {
    return element;
  }
  return null;
}

function selectedLabel(control: HTMLElement | null): string {
  if (control instanceof HTMLSelectElement) {
    return control.selectedOptions[0]?.textContent?.trim() ?? '';
  }
  if (control instanceof HTMLInputElement) return control.value.trim();
  return '';
}

function money(cents: number, currency: string): string {
  try {
    return new Intl.NumberFormat('es-NI', { style: 'currency', currency }).format(cents / 100);
  } catch {
    return `${(cents / 100).toFixed(2)} ${currency}`;
  }
}

function setDiscount(units: number): void {
  const discount = bulkDiscountPercent(units);
  if (!(summaryDiscountRow instanceof HTMLElement)) return;
  summaryDiscountRow.classList.toggle('hidden', discount === 0);
  if (discount > 0 && summaryDiscountPct instanceof HTMLElement) {
    summaryDiscountPct.textContent = String(discount);
  }
}

function updateEstimate(product: HTMLSelectElement, quantity: string): void {
  const option = product.selectedOptions[0];
  const price = Number.parseInt(option?.dataset.price ?? '', 10);
  const units = Number.parseInt(quantity, 10);
  const currency = option?.dataset.currency ?? 'USD';
  const isInvalid = Number.isNaN(price) || Number.isNaN(units) || units < 1;

  if (summaryTotal instanceof HTMLElement) {
    summaryTotal.textContent = isInvalid ? '—' : money(bulkTotalCents(price, units), currency);
  }
  if (summaryUnitPrice instanceof HTMLElement) {
    summaryUnitPrice.textContent = isInvalid ? '—' : money(price, currency);
  }
  if (isInvalid) {
    if (summaryDiscountRow instanceof HTMLElement) summaryDiscountRow.classList.add('hidden');
    return;
  }
  setDiscount(units);
}

function writeSummary(source: HTMLFormElement): void {
  const product = readControl(source, 'tipoProducto');
  const quantityControl = readControl(source, 'cantidad');
  const technique = readControl(source, 'tecnica');
  const productName = selectedLabel(product);
  const quantity = quantityControl instanceof HTMLInputElement ? quantityControl.value.trim() : '';
  const techniqueName = selectedLabel(technique);

  if (summaryProducto instanceof HTMLElement) {
    summaryProducto.textContent = productName === '' ? '—' : productName;
  }
  if (summaryCantidad instanceof HTMLElement) {
    summaryCantidad.textContent = quantity === '' ? '—' : `${quantity} und.`;
  }
  if (summaryTecnica instanceof HTMLElement) {
    summaryTecnica.textContent = techniqueName === '' ? '—' : techniqueName;
  }
  if (product instanceof HTMLSelectElement) updateEstimate(product, quantity);
}

if (form !== null) {
  form.addEventListener('input', () => writeSummary(form));
  form.addEventListener('submit', (event: Event) => {
    event.preventDefault();
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    const ticketNumber = `RFQ-${Date.now().toString(36).toUpperCase()}`;
    const product = readControl(form, 'tipoProducto');
    const quantityControl = readControl(form, 'cantidad');
    const params = new URLSearchParams({ ticket: ticketNumber });
    if (product instanceof HTMLSelectElement && product.value !== '') {
      params.set('product', product.value);
      params.set('producto', selectedLabel(product));
    }
    if (quantityControl instanceof HTMLInputElement && quantityControl.value !== '') {
      params.set('cantidad', quantityControl.value);
    }
    window.location.href = `${window.location.pathname.replace(/\/$/, '')}/confirmation?${params.toString()}`;
  });
}
