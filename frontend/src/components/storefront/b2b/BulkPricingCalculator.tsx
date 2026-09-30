/**
 * BulkPricingCalculator — Calculadora interactiva de bulk order B2B.
 *
 * Patrón B2B (design-refs §7.3):
 *   "Quick calculator: cantidad + producto = descuento automático"
 *
 * Calcula en cliente el tier aplicable según `quantity`, mostrando:
 *   - Producto seleccionado + precio base unitario.
 *   - Quantity input numérico (>= 1).
 *   - Tier activo con descuento aplicado.
 *   - Subtotal (precio * cantidad con descuento).
 *   - Botón "Add to bulk order" — persiste en localStorage como
 *     stub hasta que el endpoint /api/account/bulk-cart exista.
 *
 * Decisiones:
 *   - React island (no Astro) porque requiere estado + handlers.
 *   - Persistencia en localStorage con clave `b2b.bulk-cart.v1` —
 *     el backend la leerá en checkout cuando exista.
 *   - Validación de cantidad mínima por producto (cada producto
 *     tiene su `minQuantity`).
 *
 * Estados (AGENTS §11): loading · empty · error · success · disabled.
 *  - loading: solo al persistir (botón disabled).
 *  - empty:   producto sin selección (no debería pasar — default = primero).
 *  - error:   cantidad inválida (menor al mínimo o no numérica).
 *  - success: cálculo válido con subtotal visible.
 *  - disabled: botón "Add" disabled hasta que cantidad sea válida.
 */
import { useEffect, useMemo, useState, type ReactElement } from 'react';
import { BULK_PRODUCTS, PRICING_TIERS, resolvePricingTier } from '../../../data/b2b-mock';
import { useTranslations } from '../../../i18n';

interface Props {
  productSelectLabel: string;
  quantityLabel: string;
  discountLabel: string;
  subtotalLabel: string;
  unitPriceLabel: string;
  addButtonLabel: string;
  addingLabel: string;
  successLabel: string;
  errorMinQuantity: (min: number) => string;
  errorInvalidNumber: string;
}

const BULK_CART_STORAGE_KEY = 'b2b.bulk-cart.v1';

export default function BulkPricingCalculator({
  productSelectLabel,
  quantityLabel,
  discountLabel,
  subtotalLabel,
  unitPriceLabel,
  addButtonLabel,
  addingLabel,
  successLabel,
  errorMinQuantity,
  errorInvalidNumber,
}: Props): ReactElement {
  const { t } = useTranslations();
  const [productId, setProductId] = useState<number>(BULK_PRODUCTS[0]?.id ?? 0);
  const [quantityInput, setQuantityInput] = useState<string>(
    String(BULK_PRODUCTS[0]?.minQuantity ?? 1),
  );
  const [persisting, setPersisting] = useState<boolean>(false);
  const [added, setAdded] = useState<boolean>(false);

  const product = useMemo(
    () => BULK_PRODUCTS.find((p) => p.id === productId) ?? BULK_PRODUCTS[0],
    [productId],
  );

  const quantity = useMemo(() => {
    const parsed = Number.parseInt(quantityInput, 10);
    return Number.isNaN(parsed) ? 0 : Math.max(0, parsed);
  }, [quantityInput]);

  const tier = useMemo(() => resolvePricingTier(quantity), [quantity]);
  const isValidQuantity = quantity >= (product?.minQuantity ?? 1) && quantity > 0;
  const unitPriceCents = product?.basePriceCents ?? 0;
  const subtotalCents = Math.round(unitPriceCents * quantity * (1 - tier.discountPercent));

  const errorMessage = useMemo<string | null>(() => {
    if (product === undefined) return null;
    if (quantity === 0) return errorInvalidNumber;
    if (quantity < product.minQuantity) return errorMinQuantity(product.minQuantity);
    return null;
  }, [product, quantity, errorInvalidNumber, errorMinQuantity]);

  const handleAddToBulkOrder = (): void => {
    if (!isValidQuantity || product === undefined) return;
    setPersisting(true);
    try {
      const raw = window.localStorage.getItem(BULK_CART_STORAGE_KEY);
      const cart: { productId: number; quantity: number }[] =
        raw === null ? [] : (JSON.parse(raw) as { productId: number; quantity: number }[]);
      const existing = cart.find((item) => item.productId === product.id);
      if (existing !== undefined) {
        existing.quantity = existing.quantity + quantity;
      } else {
        cart.push({ productId: product.id, quantity });
      }
      window.localStorage.setItem(BULK_CART_STORAGE_KEY, JSON.stringify(cart));
      setAdded(true);
    } finally {
      setPersisting(false);
    }
  };

  useEffect(() => {
    if (!added) return;
    const timeoutId = window.setTimeout(() => setAdded(false), 2500);
    return () => {
      window.clearTimeout(timeoutId);
    };
  }, [added]);

  const formatCents = (cents: number): string =>
    `$${(cents / 100).toFixed(2)} ${product?.currency ?? 'USD'}`;

  if (product === undefined) {
    return (
      <p className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        {t('common.error')}
      </p>
    );
  }

  return (
    <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
      <label className="block">
        <span className="text-xs font-medium text-slate-700">{productSelectLabel}</span>
        <select
          value={productId}
          onChange={(event) => {
            const nextId = Number.parseInt(event.target.value, 10);
            const next = BULK_PRODUCTS.find((p) => p.id === nextId);
            setProductId(nextId);
            setQuantityInput(String(next?.minQuantity ?? 1));
            setAdded(false);
          }}
          className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
          data-testid="bulk-product-select"
        >
          {BULK_PRODUCTS.map((option) => (
            <option key={option.id} value={option.id}>
              {option.name} — {formatCents(option.basePriceCents)}
            </option>
          ))}
        </select>
      </label>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <label className="block">
          <span className="text-xs font-medium text-slate-700">{quantityLabel}</span>
          <input
            type="number"
            min={1}
            value={quantityInput}
            onChange={(event) => {
              setQuantityInput(event.target.value);
              setAdded(false);
            }}
            className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            data-testid="bulk-quantity-input"
          />
        </label>

        <div className="rounded-md bg-slate-50 px-3 py-2 text-sm">
          <p className="text-xs text-slate-500">{unitPriceLabel}</p>
          <p className="font-semibold text-slate-900">{formatCents(unitPriceCents)}</p>
        </div>
      </div>

      <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm">
        <p className="flex items-center justify-between">
          <span className="font-medium text-emerald-900">{tier.label}</span>
          <span className="font-bold text-emerald-900">
            {tier.discountPercent === 0 ? '—' : `-${Math.round(tier.discountPercent * 100)}%`}
          </span>
        </p>
        <p className="mt-1 text-xs text-emerald-700">
          {discountLabel}: {tier.discountPercent === 0 ? '0%' : `${Math.round(tier.discountPercent * 100)}%`}
        </p>
      </div>

      <div className="flex items-center justify-between border-t border-slate-200 pt-3">
        <span className="text-sm font-medium text-slate-700">{subtotalLabel}</span>
        <span className="text-lg font-bold tabular-nums text-slate-900" data-testid="bulk-subtotal">
          {formatCents(subtotalCents)}
        </span>
      </div>

      {errorMessage !== null && (
        <p role="alert" className="text-sm text-rose-600" data-testid="bulk-error">
          {errorMessage}
        </p>
      )}

      <button
        type="button"
        onClick={handleAddToBulkOrder}
        disabled={!isValidQuantity || persisting}
        className="w-full rounded-md bg-[var(--primary,#059669)] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--primary-hover,#047857)] disabled:cursor-not-allowed disabled:bg-slate-300"
        data-testid="bulk-add"
      >
        {persisting ? addingLabel : addButtonLabel}
      </button>

      {added && (
        <p
          role="status"
          className="rounded-md bg-emerald-100 px-3 py-2 text-center text-sm font-medium text-emerald-800"
          data-testid="bulk-success"
        >
          {successLabel}
        </p>
      )}

      <details className="pt-2 text-xs text-slate-500">
        <summary className="cursor-pointer font-medium text-slate-700">
          {`${PRICING_TIERS.length} tiers`}
        </summary>
        <ul className="mt-2 space-y-1">
          {PRICING_TIERS.map((t) => (
            <li key={t.minQuantity}>
              {t.label}:{' '}
              {t.discountPercent === 0 ? 'sin descuento' : `${Math.round(t.discountPercent * 100)}% off`}
            </li>
          ))}
        </ul>
      </details>
    </div>
  );
}
