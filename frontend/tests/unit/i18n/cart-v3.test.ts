// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';
import es from '../../../src/i18n/locales/es';
import en from '../../../src/i18n/locales/en';

/**
 * Cobertura de las keys nuevas de la vista de carrito v3 (rediseño).
 *
 * Convención: `storefront.cart.v3.*`. Si alguien borra o renombra una,
 * falla acá antes de que la página renderice literal `undefined`.
 */
const REQUIRED_KEYS = [
  'storefront.cart.v3.freeShippingProgress',
  'storefront.cart.v3.freeShippingReached',
  'storefront.cart.v3.steps.bag',
  'storefront.cart.v3.steps.checkout',
  'storefront.cart.v3.steps.done',
  'storefront.cart.v3.trust.shipping',
  'storefront.cart.v3.trust.guarantee',
  'storefront.cart.v3.trust.returns',
  'storefront.cart.v3.trust.secure',
] as const;

describe('i18n · storefront.cart.v3 (rediseño carrito)', () => {
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

  it('freeShippingProgress conserva el placeholder {amount} en ambos locales', () => {
    const key = 'storefront.cart.v3.freeShippingProgress';
    expect(es[key]).toContain('{amount}');
    expect(en[key]).toContain('{amount}');
  });
});
