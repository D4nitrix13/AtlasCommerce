/**
 * ShippingStepForm — paso 1 (contacto + dirección) del checkout.
 *
 * Labels 12px/500 sobre inputs con relleno papel; errores por campo
 * en rojo semántico 12px (mensajes i18n vía schema Zod).
 */
import type { ReactElement } from 'react';
import type { UseFormReturn } from 'react-hook-form';

import CheckoutField, {
  FIELD_INPUT_CLASSES,
  FIELD_LABEL_CLASSES,
} from './CheckoutField';
import type { CheckoutFormValues } from './checkoutSchemas';

export interface ShippingStepFormLabels {
  heading: string;
  name: string;
  email: string;
  phone: string;
  address1: string;
  address2: string;
  city: string;
  state: string;
  postal: string;
  country: string;
}

interface ShippingStepFormProps {
  form: UseFormReturn<CheckoutFormValues>;
  labels: ShippingStepFormLabels;
}

const COUNTRY_OPTIONS = [
  { value: 'NI', label: 'Nicaragua' },
  { value: 'MX', label: 'México' },
  { value: 'ES', label: 'España' },
  { value: 'AR', label: 'Argentina' },
  { value: 'US', label: 'Estados Unidos' },
] as const;

export default function ShippingStepForm({
  form,
  labels,
}: ShippingStepFormProps): ReactElement {
  const { register, formState } = form;
  const { errors } = formState;

  return (
    <div className="flex flex-col gap-4">
      <h2 className="text-lg font-semibold text-[var(--ink,#1c1917)]">{labels.heading}</h2>
      <CheckoutField
        id="checkout-name"
        label={labels.name}
        type="text"
        autoComplete="name"
        registration={register('name')}
        error={errors.name?.message}
      />
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <CheckoutField
          id="checkout-email"
          label={labels.email}
          type="email"
          autoComplete="email"
          registration={register('email')}
          error={errors.email?.message}
        />
        <CheckoutField
          id="checkout-phone"
          label={labels.phone}
          type="tel"
          autoComplete="tel"
          registration={register('phone')}
          error={errors.phone?.message}
        />
        <div className="sm:col-span-2">
          <CheckoutField
            id="checkout-address1"
            label={labels.address1}
            type="text"
            autoComplete="address-line1"
            registration={register('address1')}
            error={errors.address1?.message}
          />
        </div>
        <div className="sm:col-span-2">
          <CheckoutField
            id="checkout-address2"
            label={labels.address2}
            type="text"
            autoComplete="address-line2"
            registration={register('address2')}
            error={errors.address2?.message}
          />
        </div>
        <CheckoutField
          id="checkout-city"
          label={labels.city}
          type="text"
          autoComplete="address-level2"
          registration={register('city')}
          error={errors.city?.message}
        />
        <CheckoutField
          id="checkout-state"
          label={labels.state}
          type="text"
          autoComplete="address-level1"
          registration={register('state')}
          error={errors.state?.message}
        />
        <CheckoutField
          id="checkout-postal"
          label={labels.postal}
          type="text"
          autoComplete="postal-code"
          registration={register('postal')}
          error={errors.postal?.message}
        />
        <div className="flex flex-col gap-1.5">
          <label htmlFor="checkout-country" className={FIELD_LABEL_CLASSES}>
            {labels.country}
          </label>
          <select
            id="checkout-country"
            autoComplete="country"
            aria-invalid={errors.country !== undefined}
            className={FIELD_INPUT_CLASSES}
            {...register('country')}
          >
            {COUNTRY_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
          {errors.country?.message !== undefined && (
            <p className="text-xs text-red-600">{errors.country.message}</p>
          )}
        </div>
      </div>
    </div>
  );
}
