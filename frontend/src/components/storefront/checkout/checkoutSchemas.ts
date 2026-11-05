/**
 * checkoutSchemas — validación del formulario de checkout (RHF + Zod).
 *
 * Un solo schema cubre los 3 pasos; `STEP_FIELD_NAMES` acota qué campos
 * valida `trigger()` al avanzar de paso. Los mensajes llegan traducidos
 * desde i18n (la página construye el schema por locale).
 */
import { z } from 'zod';
import type { FieldError, FieldErrors, FieldPath, Resolver } from 'react-hook-form';

export type CheckoutStepId = 'shipping' | 'payment' | 'review';

export const PAYMENT_METHODS = ['card', 'transfer', 'cod'] as const;
export type PaymentMethod = (typeof PAYMENT_METHODS)[number];

export interface CheckoutFormValues {
  name: string;
  email: string;
  phone: string;
  address1: string;
  address2: string;
  city: string;
  state: string;
  postal: string;
  country: string;
  paymentMethod: PaymentMethod;
  cardName: string;
  cardNumber: string;
  expiry: string;
  cvv: string;
}

export interface CheckoutValidationMessages {
  required: string;
  email: string;
  postal: string;
}

export const EMPTY_CHECKOUT_VALUES: CheckoutFormValues = {
  name: '',
  email: '',
  phone: '',
  address1: '',
  address2: '',
  city: '',
  state: '',
  postal: '',
  country: 'NI',
  paymentMethod: 'card',
  cardName: '',
  cardNumber: '',
  expiry: '',
  cvv: '',
};

export const SHIPPING_FIELD_NAMES = [
  'name',
  'email',
  'phone',
  'address1',
  'address2',
  'city',
  'state',
  'postal',
  'country',
] as const;

export const PAYMENT_FIELD_NAMES = [
  'paymentMethod',
  'cardName',
  'cardNumber',
  'expiry',
  'cvv',
] as const;

export const STEP_FIELD_NAMES: Record<CheckoutStepId, FieldPath<CheckoutFormValues>[]> = {
  shipping: [...SHIPPING_FIELD_NAMES],
  payment: [...PAYMENT_FIELD_NAMES],
  review: [],
};

const POSTAL_PATTERN = /^[A-Za-z0-9][A-Za-z0-9 -]{2,10}$/;

/** Los campos de tarjeta solo rinden (y validan) con método = card. */
export function shouldShowCardFields(paymentMethod: PaymentMethod): boolean {
  return paymentMethod === 'card';
}

export function createCheckoutSchema(
  messages: CheckoutValidationMessages,
): z.ZodType<CheckoutFormValues> {
  return z
    .object({
      name: z.string().trim().min(1, messages.required),
      email: z
        .string()
        .trim()
        .min(1, messages.required)
        .refine((value) => z.email().safeParse(value).success, messages.email),
      phone: z.string().trim().min(1, messages.required),
      address1: z.string().trim().min(1, messages.required),
      address2: z.string().trim(),
      city: z.string().trim().min(1, messages.required),
      state: z.string().trim().min(1, messages.required),
      postal: z
        .string()
        .trim()
        .min(1, messages.required)
        .refine((value) => POSTAL_PATTERN.test(value), messages.postal),
      country: z.string().trim().min(1, messages.required),
      paymentMethod: z.enum(PAYMENT_METHODS),
      cardName: z.string().trim(),
      cardNumber: z.string().trim(),
      expiry: z.string().trim(),
      cvv: z.string().trim(),
    })
    .superRefine((values, ctx) => {
      if (!shouldShowCardFields(values.paymentMethod)) return;
      const cardFields = ['cardName', 'cardNumber', 'expiry', 'cvv'] as const;
      for (const field of cardFields) {
        if (values[field] === '') {
          ctx.addIssue({
            code: 'custom',
            message: messages.required,
            path: [field],
          });
        }
      }
    });
}

/**
 * Resolver RHF sobre el schema Zod completo. `trigger(stepFieldNames)`
 * valida solo los campos del paso: RHF compara los nombres pedidos
 * contra los errores del schema y avanza úndamente si esos campos
 * no fallan.
 */
export function createCheckoutResolver(
  messages: CheckoutValidationMessages,
): Resolver<CheckoutFormValues> {
  const schema = createCheckoutSchema(messages);
  return async (values) => {
    const result = schema.safeParse(values);
    if (result.success) {
      return { values: result.data, errors: {} };
    }
    const errors: Record<string, FieldError> = {};
    for (const issue of result.error.issues) {
      const key = issue.path[0] === undefined ? '' : String(issue.path[0]);
      if (errors[key] === undefined) {
        errors[key] = { type: 'validate', message: issue.message };
      }
    }
    return { values: {}, errors: errors as FieldErrors<CheckoutFormValues> };
  };
}
