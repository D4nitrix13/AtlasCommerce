import { describe, it, expect } from 'vitest';

import {
  createCheckoutSchema,
  shouldShowCardFields,
  STEP_FIELD_NAMES,
  EMPTY_CHECKOUT_VALUES,
  type CheckoutFormValues,
} from '../../../../../src/components/storefront/checkout/checkoutSchemas';

const messages = {
  required: 'Este campo es obligatorio.',
  email: 'Ingresá un correo válido.',
  postal: 'Ingresá un código postal válido.',
};

const schema = createCheckoutSchema(messages);

function validValues(overrides: Partial<CheckoutFormValues> = {}): CheckoutFormValues {
  return {
    ...EMPTY_CHECKOUT_VALUES,
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    phone: '+505 8888 8888',
    address1: 'Calle Central 1',
    city: 'Managua',
    state: 'Managua',
    postal: '2288',
    country: 'NI',
    paymentMethod: 'card',
    cardName: 'Ada Lovelace',
    cardNumber: '4111 1111 1111 1111',
    expiry: '12/30',
    cvv: '123',
    ...overrides,
  };
}

function issueFor(values: CheckoutFormValues, field: string): string | undefined {
  const result = schema.safeParse(values);
  if (result.success) return undefined;
  return result.error.issues.find((issue) => String(issue.path[0]) === field)?.message;
}

describe('createCheckoutSchema · shipping step', (): void => {
  it('accepts a valid shipping payload', (): void => {
    const result = schema.safeParse(validValues());
    expect(result.success).toBe(true);
  });

  it('rejects empty required shipping fields with the required message', (): void => {
    for (const field of ['name', 'phone', 'address1', 'city', 'state', 'country'] as const) {
      expect(issueFor(validValues({ [field]: '' }), field)).toBe(messages.required);
    }
  });

  it('rejects an empty email with the required message', (): void => {
    expect(issueFor(validValues({ email: '' }), 'email')).toBe(messages.required);
  });

  it('rejects a malformed email with the email message', (): void => {
    expect(issueFor(validValues({ email: 'no-es-un-correo' }), 'email')).toBe(messages.email);
  });

  it('rejects an empty postal code with the required message', (): void => {
    expect(issueFor(validValues({ postal: '' }), 'postal')).toBe(messages.required);
  });

  it('rejects a malformed postal code with the postal message', (): void => {
    expect(issueFor(validValues({ postal: '!!' }), 'postal')).toBe(messages.postal);
  });
});

describe('createCheckoutSchema · payment step', (): void => {
  it('requires the four card fields when the method is card', (): void => {
    for (const field of ['cardName', 'cardNumber', 'expiry', 'cvv'] as const) {
      expect(issueFor(validValues({ [field]: '' }), field)).toBe(messages.required);
    }
  });

  it('accepts empty card fields when the method is transfer', (): void => {
    const result = schema.safeParse(
      validValues({
        paymentMethod: 'transfer',
        cardName: '',
        cardNumber: '',
        expiry: '',
        cvv: '',
      }),
    );
    expect(result.success).toBe(true);
  });

  it('accepts empty card fields when the method is cod', (): void => {
    const result = schema.safeParse(
      validValues({
        paymentMethod: 'cod',
        cardName: '',
        cardNumber: '',
        expiry: '',
        cvv: '',
      }),
    );
    expect(result.success).toBe(true);
  });

  it('rejects an unknown payment method', (): void => {
    const invalidMethod = 'paypal' as CheckoutFormValues['paymentMethod'];
    const result = schema.safeParse(validValues({ paymentMethod: invalidMethod }));
    expect(result.success).toBe(false);
    expect(issueFor(validValues({ paymentMethod: invalidMethod }), 'paymentMethod')).toBeTruthy();
  });
});

describe('shouldShowCardFields', (): void => {
  it('is true only for the card method', (): void => {
    expect(shouldShowCardFields('card')).toBe(true);
    expect(shouldShowCardFields('transfer')).toBe(false);
    expect(shouldShowCardFields('cod')).toBe(false);
  });
});

describe('STEP_FIELD_NAMES', (): void => {
  it('scopes the shipping and payment step fields', (): void => {
    expect(STEP_FIELD_NAMES.shipping).toContain('name');
    expect(STEP_FIELD_NAMES.shipping).toContain('postal');
    expect(STEP_FIELD_NAMES.shipping).not.toContain('cardName');
    expect(STEP_FIELD_NAMES.payment).toContain('paymentMethod');
    expect(STEP_FIELD_NAMES.payment).toContain('cvv');
    expect(STEP_FIELD_NAMES.review).toHaveLength(0);
  });
});
