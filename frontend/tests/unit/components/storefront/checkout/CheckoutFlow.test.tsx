// @vitest-environment jsdom
import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';

import CheckoutFlow from '../../../../../src/components/storefront/checkout/CheckoutFlow';
import type { CheckoutFlowLabels } from '../../../../../src/components/storefront/checkout/CheckoutFlow';
import {
  CHECKOUT_DRAFT_KEY,
  writeCheckoutDraft,
} from '../../../../../src/components/storefront/checkout/checkoutDraft';
import {
  EMPTY_CHECKOUT_VALUES,
  type CheckoutFormValues,
} from '../../../../../src/components/storefront/checkout/checkoutSchemas';
import { clearCart } from '../../../../../src/stores/cart';

const labels: CheckoutFlowLabels = {
  stepper: {
    stepOf: 'Paso {current} de {total}',
    stepperLabel: 'Progreso del checkout',
  },
  stepLabels: {
    shipping: 'Envío',
    payment: 'Pago',
    review: 'Revisión',
  },
  shipping: {
    heading: '1 · Datos de envío',
    name: 'Nombre completo',
    email: 'Correo electrónico',
    phone: 'Teléfono',
    address1: 'Dirección línea 1',
    address2: 'Dirección línea 2 (opcional)',
    city: 'Ciudad',
    state: 'Estado / Provincia',
    postal: 'Código postal',
    country: 'País',
  },
  payment: {
    heading: '2 · Método de pago',
    methodLegend: 'Método de pago',
    card: 'Tarjeta de crédito/débito',
    transfer: 'Transferencia bancaria',
    cod: 'Pago contra entrega',
    cardDesc: 'Pagá de forma segura con tu tarjeta.',
    transferDesc: 'Te mostramos los datos para transferir.',
    codDesc: 'Pagá en efectivo al recibir tu pedido.',
    cardDetails: 'Datos de la tarjeta',
    cardName: 'Nombre en la tarjeta',
    cardNumber: 'Número de tarjeta',
    expiry: 'Vencimiento (MM/AA)',
    cvv: 'CVV',
  },
  review: {
    heading: '3 · Revisá tu pedido',
    hint: 'Ajustá cantidades antes de confirmar.',
    edit: 'Editar',
    qty: 'Cant.',
    subtotal: 'Subtotal',
    emptyItems: 'No hay productos en el carrito.',
    shippingStep: 'Envío',
    paymentStep: 'Pago',
  },
  summary: {
    summary: 'Resumen del pedido',
    emptyItems: 'No hay productos en el carrito.',
    subtotal: 'Subtotal',
    shippingCost: 'Envío',
    shippingFree: 'Gratis',
    tax: 'Impuestos',
    total: 'Total',
    freeShippingProgress: 'Te faltan {amount} para envío gratis',
    freeShippingReached: 'Tenés envío gratis',
    trust: {
      shipping: 'Envío gratis sobre $50',
      guarantee: 'Garantía oficial',
      returns: 'Devolución 30 días',
      secure: 'Pago 100% seguro',
    },
  },
  continueToPayment: 'Continuar al pago',
  continueToReview: 'Continuar a la revisión',
  placeOrder: 'Realizar pedido',
  previous: 'Anterior',
  termsHint: 'Al continuar aceptás los términos de compra.',
  validation: {
    required: 'Este campo es obligatorio.',
    email: 'Ingresá un correo válido.',
    postal: 'Ingresá un código postal válido.',
  },
  error: {
    title: 'No pudimos procesar tu pedido.',
    message: 'Revisá los datos marcados e intentá de nuevo.',
  },
};

const validShipping: Partial<CheckoutFormValues> = {
  name: 'Ada Lovelace',
  email: 'ada@example.com',
  phone: '+505 8888 8888',
  address1: 'Calle Central 1',
  city: 'Managua',
  state: 'Managua',
  postal: '2288',
  country: 'NI',
};

function fillShippingForm(): void {
  fireEvent.change(screen.getByLabelText(labels.shipping.name), {
    target: { value: validShipping.name },
  });
  fireEvent.change(screen.getByLabelText(labels.shipping.email), {
    target: { value: validShipping.email },
  });
  fireEvent.change(screen.getByLabelText(labels.shipping.phone), {
    target: { value: validShipping.phone },
  });
  fireEvent.change(screen.getByLabelText(labels.shipping.address1), {
    target: { value: validShipping.address1 },
  });
  fireEvent.change(screen.getByLabelText(labels.shipping.city), {
    target: { value: validShipping.city },
  });
  fireEvent.change(screen.getByLabelText(labels.shipping.state), {
    target: { value: validShipping.state },
  });
  fireEvent.change(screen.getByLabelText(labels.shipping.postal), {
    target: { value: validShipping.postal },
  });
}

function renderFlow(initialStep: 'shipping' | 'payment' | 'review' = 'shipping') {
  return render(
    <CheckoutFlow initialStep={initialStep} checkoutBaseHref="/es/checkout" labels={labels} />,
  );
}

/** El CTA vive en el footer del form y en la barra mobile (mismo nombre). */
function clickCta(name: string): void {
  fireEvent.click(screen.getAllByRole('button', { name })[0]);
}

describe('CheckoutFlow · step-advance gating', (): void => {
  beforeEach((): void => {
    window.sessionStorage.clear();
    window.history.replaceState(null, '', '/es/checkout');
    clearCart();
  });

  it('starts on the shipping step and blocks advance on invalid fields', async (): Promise<void> => {
    renderFlow();

    expect(screen.getByLabelText(labels.shipping.name)).toBeTruthy();
    clickCta(labels.continueToPayment);

    expect((await screen.findAllByText(labels.validation.required)).length).toBeGreaterThan(0);
    expect(screen.queryByText(labels.payment.heading)).toBeNull();
    expect(window.location.search).toBe('');
  });

  it('shows the email message for a malformed email', async (): Promise<void> => {
    renderFlow();
    fireEvent.change(screen.getByLabelText(labels.shipping.email), {
      target: { value: 'no-es-correo' },
    });
    clickCta(labels.continueToPayment);

    expect(await screen.findByText(labels.validation.email)).toBeTruthy();
  });

  it('advances to payment and syncs ?step= after a valid shipping step', async (): Promise<void> => {
    renderFlow();
    fillShippingForm();
    clickCta(labels.continueToPayment);

    expect(await screen.findByText(labels.payment.heading)).toBeTruthy();
    expect(window.location.search).toBe('?step=payment');
  });

  it('returns to a done step from the stepper with values preserved', async (): Promise<void> => {
    renderFlow();
    fillShippingForm();
    clickCta(labels.continueToPayment);
    await screen.findByText(labels.payment.heading);

    fireEvent.click(screen.getByRole('button', { name: /1/ }));
    expect(await screen.findByLabelText(labels.shipping.name)).toHaveProperty(
      'value',
      validShipping.name,
    );
  });
});

describe('CheckoutFlow · payment-method field visibility', (): void => {
  beforeEach((): void => {
    window.sessionStorage.clear();
    window.history.replaceState(null, '', '/es/checkout?step=payment');
    clearCart();
  });

  it('shows card fields only when the method is card', async (): Promise<void> => {
    renderFlow('payment');

    expect(screen.getByLabelText(labels.payment.cardName)).toBeTruthy();

    fireEvent.click(screen.getByLabelText(labels.payment.transfer));
    expect(screen.queryByLabelText(labels.payment.cardName)).toBeNull();

    fireEvent.click(screen.getByLabelText(labels.payment.card));
    expect(await screen.findByLabelText(labels.payment.cardName)).toBeTruthy();
  });

  it('advances past payment without card fields when the method is transfer', async (): Promise<void> => {
    renderFlow('payment');
    fireEvent.click(screen.getByLabelText(labels.payment.transfer));
    clickCta(labels.continueToReview);

    expect(await screen.findByText(labels.review.heading)).toBeTruthy();
    expect(window.location.search).toBe('?step=review');
  });
});

describe('CheckoutFlow · draft persistence', (): void => {
  beforeEach((): void => {
    window.sessionStorage.clear();
    window.history.replaceState(null, '', '/es/checkout');
    clearCart();
  });

  it('saves the draft while typing and restores it on remount', async (): Promise<void> => {
    const first = renderFlow();
    fireEvent.change(screen.getByLabelText(labels.shipping.name), {
      target: { value: 'Ada Lovelace' },
    });

    const stored = JSON.parse(window.sessionStorage.getItem(CHECKOUT_DRAFT_KEY) ?? 'null') as {
      name: string;
    };
    expect(stored.name).toBe('Ada Lovelace');

    first.unmount();
    renderFlow();

    expect(await screen.findByLabelText(labels.shipping.name)).toHaveProperty(
      'value',
      'Ada Lovelace',
    );
  });

  it('restores values written directly into sessionStorage', async (): Promise<void> => {
    writeCheckoutDraft({ ...EMPTY_CHECKOUT_VALUES, city: 'Granada' });
    renderFlow();

    expect(await screen.findByLabelText(labels.shipping.city)).toHaveProperty('value', 'Granada');
  });
});

describe('CheckoutFlow · submit-error banner', (): void => {
  beforeEach((): void => {
    window.sessionStorage.clear();
    window.history.replaceState(null, '', '/es/checkout?step=review');
    clearCart();
  });

  it('shows the designed banner and jumps to the first invalid step on failed submit', async (): Promise<void> => {
    renderFlow('review');

    clickCta(labels.placeOrder);

    expect(await screen.findByText(labels.error.title)).toBeTruthy();
    expect(screen.getByText(labels.error.message)).toBeTruthy();
    expect(screen.getByLabelText(labels.shipping.name)).toBeTruthy();
  });
});
