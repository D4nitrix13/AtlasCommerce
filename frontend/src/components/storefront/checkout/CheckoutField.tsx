/**
 * CheckoutField — label + input + error del flujo de checkout.
 *
 * Inputs con relleno papel (paper tint) y focus-visible en acento
 * (patrón HomeHero — nunca `ring` nativo). El error i18n se renderiza
 * debajo del input en rojo semántico 12px.
 */
import type { InputHTMLAttributes, ReactElement } from 'react';
import type { UseFormRegisterReturn } from 'react-hook-form';

export const FIELD_LABEL_CLASSES =
  'text-xs font-medium uppercase tracking-wide text-[var(--ink,#1c1917)]/60';

export const FIELD_INPUT_CLASSES =
  'h-11 w-full rounded-lg border border-[var(--line,#e7e5e4)] bg-[var(--paper,#f3efe8)]/60 px-3 text-sm text-[var(--ink,#1c1917)] transition-colors placeholder:text-[var(--ink,#1c1917)]/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2 motion-reduce:transition-none';

export const FIELD_ERROR_CLASSES = 'text-xs text-red-600';

interface CheckoutFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  id: string;
  label: string;
  registration: UseFormRegisterReturn;
  error?: string;
}

export default function CheckoutField({
  id,
  label,
  registration,
  error,
  ...inputProps
}: CheckoutFieldProps): ReactElement {
  const hasError = error !== undefined && error !== '';
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className={FIELD_LABEL_CLASSES}>
        {label}
      </label>
      <input
        id={id}
        aria-invalid={hasError}
        aria-describedby={hasError ? `${id}-error` : undefined}
        className={FIELD_INPUT_CLASSES}
        {...registration}
        {...inputProps}
      />
      {hasError && (
        <p id={`${id}-error`} className={FIELD_ERROR_CLASSES}>
          {error}
        </p>
      )}
    </div>
  );
}
