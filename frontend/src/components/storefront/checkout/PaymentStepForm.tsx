/**
 * PaymentStepForm — paso 2 (método de pago) del checkout.
 *
 * Radio-cards accesibles: radios reales ocultos visualmente + labels.
 * Los campos de tarjeta se renderizan solo con método = card
 * (`shouldShowCardFields`) con entrada sutil en opacidad/transform.
 */
import type { ReactElement } from 'react';
import type { UseFormReturn } from 'react-hook-form';

import CheckoutField from './CheckoutField';
import {
  shouldShowCardFields,
  type CheckoutFormValues,
  type PaymentMethod,
} from './checkoutSchemas';

export interface PaymentStepFormLabels {
  heading: string;
  methodLegend: string;
  card: string;
  transfer: string;
  cod: string;
  cardDesc: string;
  transferDesc: string;
  codDesc: string;
  cardDetails: string;
  cardName: string;
  cardNumber: string;
  expiry: string;
  cvv: string;
}

interface PaymentStepFormProps {
  form: UseFormReturn<CheckoutFormValues>;
  labels: PaymentStepFormLabels;
}

interface MethodOption {
  value: PaymentMethod;
  title: string;
  description: string;
  iconPath: string;
}

const ICON = {
  card: '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
  transfer:
    '<path d="M19 7 12 3 5 7"/><path d="M5 21h14"/><path d="M5 21V7"/><path d="M19 21V7"/>',
  cod: '<circle cx="12" cy="12" r="8"/><path d="M12 8v8"/><path d="M9 10h6"/><path d="M9 14h6"/>',
};

function MethodIcon({ path }: { path: string }): ReactElement {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className="mt-0.5 h-5 w-5 shrink-0 text-[var(--primary,#475569)]"
      dangerouslySetInnerHTML={{ __html: path }}
    />
  );
}

const RADIO_CARD_CLASSES =
  'flex items-start gap-3 rounded-xl border border-[var(--line,#e7e5e4)] bg-white p-4 transition-colors hover:border-[var(--primary,#475569)]/50 peer-checked:border-[var(--primary,#475569)] peer-checked:bg-[var(--primary-soft,#e6f7f4)] peer-focus-visible:ring-2 peer-focus-visible:ring-[var(--primary,#475569)] motion-reduce:transition-none';

export default function PaymentStepForm({
  form,
  labels,
}: PaymentStepFormProps): ReactElement {
  const { register, formState, watch } = form;
  const { errors } = formState;
  const paymentMethod = watch('paymentMethod');
  const showCardFields = shouldShowCardFields(paymentMethod);

  const options: MethodOption[] = [
    { value: 'card', title: labels.card, description: labels.cardDesc, iconPath: ICON.card },
    {
      value: 'transfer',
      title: labels.transfer,
      description: labels.transferDesc,
      iconPath: ICON.transfer,
    },
    { value: 'cod', title: labels.cod, description: labels.codDesc, iconPath: ICON.cod },
  ];

  return (
    <div className="flex flex-col gap-5">
      <h2 className="text-lg font-semibold text-[var(--ink,#1c1917)]">{labels.heading}</h2>
      <fieldset role="radiogroup" aria-label={labels.methodLegend} className="flex flex-col gap-3">
        <legend className="px-1 text-sm font-medium text-[var(--ink,#1c1917)]/60">
          {labels.methodLegend}
        </legend>
        {options.map((option) => (
          <label key={option.value} className="block cursor-pointer">
            <input
              type="radio"
              value={option.value}
              aria-label={option.title}
              className="peer sr-only"
              {...register('paymentMethod')}
            />
            <span className={RADIO_CARD_CLASSES}>
              <MethodIcon path={option.iconPath} />
              <span className="flex flex-col gap-0.5">
                <span className="text-[15px] font-semibold text-[var(--ink,#1c1917)]">
                  {option.title}
                </span>
                <span className="text-xs text-[var(--ink,#1c1917)]/60">{option.description}</span>
              </span>
            </span>
          </label>
        ))}
        {errors.paymentMethod?.message !== undefined && (
          <p className="text-xs text-red-600">{errors.paymentMethod.message}</p>
        )}
      </fieldset>

      {showCardFields && (
        <div className="checkout-step-in flex flex-col gap-4 border-t border-[var(--line,#e7e5e4)] pt-5">
          <p className="text-sm font-semibold text-[var(--ink,#1c1917)]">{labels.cardDetails}</p>
          <CheckoutField
            id="checkout-card-name"
            label={labels.cardName}
            type="text"
            autoComplete="cc-name"
            registration={register('cardName')}
            error={errors.cardName?.message}
          />
          <CheckoutField
            id="checkout-card-number"
            label={labels.cardNumber}
            type="text"
            inputMode="numeric"
            autoComplete="cc-number"
            registration={register('cardNumber')}
            error={errors.cardNumber?.message}
          />
          <div className="grid grid-cols-2 gap-3">
            <CheckoutField
              id="checkout-card-expiry"
              label={labels.expiry}
              type="text"
              inputMode="numeric"
              placeholder="MM/AA"
              autoComplete="cc-exp"
              registration={register('expiry')}
              error={errors.expiry?.message}
            />
            <CheckoutField
              id="checkout-card-cvv"
              label={labels.cvv}
              type="text"
              inputMode="numeric"
              autoComplete="cc-csc"
              registration={register('cvv')}
              error={errors.cvv?.message}
            />
          </div>
        </div>
      )}
    </div>
  );
}
