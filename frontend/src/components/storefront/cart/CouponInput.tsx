/**
 * CouponInput — Input + botón para aplicar un código de cupón (RF-MKT-060).
 *
 * Componente React island que se monta dentro de la página de carrito.
 * Delega toda la lógica al hook `useCoupon` (validación contra backend,
 * persistencia en localStorage, manejo de errores).
 *
 * Reglas UX:
 *  - Cuando hay cupón aplicado, el input se oculta y aparece un resumen
 *    con el código + monto del descuento + botón "Quitar".
 *  - Los errores se muestran inline debajo del input.
 *  - El botón "Aplicar" se deshabilita durante la llamada al backend.
 */
import { useState, type FormEvent, type ReactElement } from 'react';
import { useTranslations } from '../../../i18n';
import type { TranslationKey } from '../../../i18n/types';
import { useCoupon, type CouponError } from '../../../hooks/useCoupon';

function resolveErrorKey(error: CouponError): TranslationKey {
  switch (error) {
    case 'empty':
      return 'storefront.cart.coupon.error.empty';
    case 'invalid':
      return 'storefront.cart.coupon.error.invalid';
    case 'minAmount':
      return 'storefront.cart.coupon.error.minAmount';
    case 'expired':
      return 'storefront.cart.coupon.error.expired';
    case 'generic':
      return 'storefront.cart.coupon.error.generic';
  }
}

export default function CouponInput(): ReactElement {
  const { t } = useTranslations();
  const { status, coupon, errorMessage, applyCoupon, removeCoupon } = useCoupon();
  const [code, setCode] = useState<string>('');

  const handleSubmit = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
    event.preventDefault();
    await applyCoupon(code);
  };

  const isApplying = status === 'applying';
  const isApplied = coupon !== null && status === 'applied';

  return (
    <section
      aria-labelledby="coupon-label"
      className="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm"
    >
      <h2 id="coupon-label" className="mb-2 text-sm font-medium text-neutral-700">
        {t('storefront.cart.coupon.label')}
      </h2>

      {isApplied ? (
        <div className="flex items-center justify-between gap-4" data-testid="coupon-applied">
          <p className="text-sm text-neutral-900">
            {t('storefront.cart.coupon.applied').replace('{code}', coupon.code)}
          </p>
          <button
            type="button"
            onClick={removeCoupon}
            className="text-sm font-medium text-red-600 hover:text-red-700"
          >
            {t('storefront.cart.coupon.remove')}
          </button>
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="flex flex-col gap-2">
          <div className="flex gap-2">
            <input
              type="text"
              value={code}
              onChange={(event) => setCode(event.target.value)}
              placeholder={t('storefront.cart.coupon.placeholder')}
              disabled={isApplying}
              aria-invalid={status === 'error'}
              className="flex-1 rounded-md border border-neutral-300 px-3 py-2 text-sm disabled:bg-neutral-100"
            />
            <button
              type="submit"
              disabled={isApplying || code.trim() === ''}
              className="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 disabled:bg-neutral-400"
            >
              {isApplying
                ? t('storefront.cart.coupon.applying')
                : t('storefront.cart.coupon.apply')}
            </button>
          </div>

          {status === 'error' && errorMessage !== null && (
            <p role="alert" className="text-sm text-red-600" data-testid="coupon-error">
              {t(resolveErrorKey(errorMessage))}
            </p>
          )}
        </form>
      )}
    </section>
  );
}
