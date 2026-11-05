// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';
import es from '../../../src/i18n/locales/es';
import en from '../../../src/i18n/locales/en';

/**
 * Cobertura de las keys nuevas del rediseño de checkout (C2–C7).
 *
 * Convención: `storefront.checkout.v3.*` aditivo + reuso de
 * `storefront.cart.v3.trust.*`. Si alguien borra o renombra una,
 * falla acá antes de que la página renderice literal `undefined`.
 */
const REQUIRED_KEYS = [
  'storefront.checkout.v3.continueToPayment',
  'storefront.checkout.v3.continueToReview',
  'storefront.checkout.v3.stepOf',
  'storefront.checkout.v3.stepperLabel',
  'storefront.checkout.v3.validation.required',
  'storefront.checkout.v3.validation.email',
  'storefront.checkout.v3.validation.postal',
  'storefront.checkout.v3.payment.cardDesc',
  'storefront.checkout.v3.payment.transferDesc',
  'storefront.checkout.v3.payment.codDesc',
  'storefront.checkout.v3.error.title',
  'storefront.checkout.v3.error.message',
] as const;

const REUSED_KEYS = [
  'storefront.checkout.v3.edit',
  'storefront.checkout.v3.placeOrder',
  'storefront.checkout.v3.previous',
  'storefront.cart.v3.trust.shipping',
  'storefront.cart.v3.trust.guarantee',
  'storefront.cart.v3.trust.returns',
  'storefront.cart.v3.trust.secure',
  'storefront.cart.v3.freeShippingProgress',
  'storefront.cart.v3.freeShippingReached',
] as const;

describe('i18n · storefront.checkout.v3 (rediseño checkout)', () => {
  it('es.ts expone todas las keys nuevas con valor no vacío', () => {
    for (const key of REQUIRED_KEYS) {
      expect(es[key], `es.ts missing key: ${key}`).toBeTruthy();
    }
  });

  it('en.ts expone todas las keys nuevas con valor no vacío', () => {
    for (const key of REQUIRED_KEYS) {
      expect(en[key], `en.ts missing key: ${key}`).toBeTruthy();
    }
  });

  it('las keys reusadas del carrito siguen disponibles', () => {
    for (const key of REUSED_KEYS) {
      expect(es[key], `es.ts missing reused key: ${key}`).toBeTruthy();
      expect(en[key], `en.ts missing reused key: ${key}`).toBeTruthy();
    }
  });

  it('stepOf conserva los placeholders {current} y {total}', () => {
    const key = 'storefront.checkout.v3.stepOf';
    for (const dict of [es, en]) {
      expect(dict[key]).toContain('{current}');
      expect(dict[key]).toContain('{total}');
    }
  });

  it('freeShippingProgress conserva el placeholder {amount}', () => {
    const key = 'storefront.cart.v3.freeShippingProgress';
    expect(es[key]).toContain('{amount}');
    expect(en[key]).toContain('{amount}');
  });
});
