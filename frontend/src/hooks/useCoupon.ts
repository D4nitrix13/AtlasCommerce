/**
 * useCoupon — Hook que gestiona la aplicación de cupones en el carrito.
 *
 * Responsabilidades:
 *  - Validar un código contra `/api/coupons/validate` (backend).
 *  - Persistir el cupón aplicado en `localStorage` con la clave
 *    `applied_coupon_code` para que sobreviva a navegación y refresh.
 *  - Exponer estado (coupon, error, loading) y acciones (apply, remove).
 *
 * Decisiones:
 *  - El backend es la fuente de verdad para validez y monto del
 *    descuento (RF-MKT-060). El frontend sólo muestra lo que el
 *    backend devuelve — nunca calcula descuentos.
 *  - La persistencia en localStorage es para UX (auto-revalidate en
 *    checkout) — el backend RE-evalúa el cupón en cada checkout.
 *  - En modo `client:only="react"` el localStorage está disponible
 *    desde el primer render en el browser.
 */
import { useCallback, useEffect, useState } from 'react';
import { apiFetch, type ApiError } from '../lib/api';
import { API_ENDPOINTS } from '../lib/endpoints';

export interface AppliedCoupon {
  code: string;
  discount_cents: number;
}

export type CouponStatus = 'idle' | 'applying' | 'applied' | 'error';

export type CouponError = 'empty' | 'invalid' | 'minAmount' | 'expired' | 'generic';

export interface UseCouponResult {
  status: CouponStatus;
  coupon: AppliedCoupon | null;
  errorMessage: CouponError | null;
  applyCoupon: (code: string) => Promise<boolean>;
  removeCoupon: () => void;
}

const STORAGE_KEY = 'applied_coupon_code';

export function useCoupon(): UseCouponResult {
  const [status, setStatus] = useState<CouponStatus>('idle');
  const [coupon, setCoupon] = useState<AppliedCoupon | null>(null);
  const [errorMessage, setErrorMessage] = useState<CouponError | null>(null);

  // Hidrata desde localStorage al montar (cliente).
  useEffect(() => {
    if (typeof window === 'undefined') return;
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (stored === null || stored === '') return;
    // No re-validamos aquí: el backend re-evaluará en checkout.
    setCoupon({ code: stored, discount_cents: 0 });
  }, []);

  const applyCoupon = useCallback(async (code: string): Promise<boolean> => {
    const trimmed = code.trim();
    if (trimmed === '') {
      setErrorMessage('empty');
      setStatus('error');
      return false;
    }

    setStatus('applying');
    setErrorMessage(null);

    try {
      const response = await apiFetch<{
        data: {
          code: string;
          discount_type: string;
          value: number;
          discount_cents: number;
        };
      }>(API_ENDPOINTS.marketing.couponsValidate, {
        method: 'POST',
        body: { code: trimmed },
      });

      const applied: AppliedCoupon = {
        code: response.data.code,
        discount_cents: response.data.discount_cents,
      };
      setCoupon(applied);
      setStatus('applied');
      window.localStorage.setItem(STORAGE_KEY, applied.code);
      return true;
    } catch (error) {
      const apiError = error as ApiError;
      // Mapeo básico de errores del backend a claves i18n.
      if (apiError.status === 422) {
        setErrorMessage('minAmount');
      } else if (apiError.status === 404) {
        setErrorMessage('invalid');
      } else if (apiError.status === 410) {
        setErrorMessage('expired');
      } else {
        setErrorMessage('generic');
      }
      setStatus('error');
      return false;
    }
  }, []);

  const removeCoupon = useCallback((): void => {
    setCoupon(null);
    setStatus('idle');
    setErrorMessage(null);
    if (typeof window !== 'undefined') {
      window.localStorage.removeItem(STORAGE_KEY);
    }
  }, []);

  return { status, coupon, errorMessage, applyCoupon, removeCoupon };
}
