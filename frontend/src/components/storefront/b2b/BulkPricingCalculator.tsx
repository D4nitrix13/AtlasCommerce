/**
 * BulkPricingCalculator — Calculadora interactiva de bulk order B2B.
 *
 * Patrón B2B (design-refs §7.3):
 *   "Quick calculator: cantidad + producto = descuento automático"
 *
 * Componente orquestador: delega render a sub-componentes y
 * persistencia al hook `useBulkCart`. Mantiene tamaño bajo
 * (AGENTS §1: función principal <40 líneas, complejidad <10).
 *
 * Decisiones:
 *   - React island (no Astro) porque requiere estado + handlers.
 *   - Persistencia en localStorage con clave `b2b.bulk-cart.v1` —
 *     el backend la leerá en checkout cuando exista.
 *   - Validación de cantidad mínima por producto.
 *
 * Estados (AGENTS §11): loading · empty · error · success · disabled.
 */
import { useEffect, useMemo, useState, type ReactElement } from 'react';
import { BULK_PRODUCTS, PRICING_TIERS, resolvePricingTier, type BulkProduct } from '../../../data/b2b-mock';
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

interface BulkCartItem {
  productId: number;
  quantity: number;
}

function readBulkCart(): BulkCartItem[] {
  if (typeof window === 'undefined') return [];
  const raw = window.localStorage.getItem(BULK_CART_STORAGE_KEY);
  if (raw === null) return [];
  try {
    const parsed: unknown = JSON.parse(raw);
    return Array.isArray(parsed) ? (parsed as BulkCartItem[]) : [];
  } catch {
    return [];
  }
}

function writeBulkCart(cart: BulkCartItem[]): void {
  if (typeof window === 'undefined') return;
  window.localStorage.setItem(BULK_CART_STORAGE_KEY, JSON.stringify(cart));
}

function useBulkCart(product: BulkProduct | undefined, quantity: number) {
  const [persisting, setPersisting] = useState<boolean>(false);
  const [added, setAdded] = useState<boolean>(false);
  const isValidQuantity =
    product !== undefined && quantity >= product.minQuantity && quantity > 0;

  const addToBulkOrder = (): boolean => {
    if (product === undefined || !isValidQuantity) return false;
    setPersisting(true);
    try {
      const cart = readBulkCart();
      const existing = cart.find((item) => item.productId === product.id);
      if (existing !== undefined) {
        existing.quantity += quantity;
      } else {
        cart.push({ productId: product.id, quantity });
      }
      writeBulkCart(cart);
      setAdded(true);
      return true;
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

  return { persisting, added, isValidQuantity, addToBulkOrder };
}

interface ProductSelectorProps {
  productId: number;
  products: readonly BulkProduct[];
  label: string;
  formatPrice: (cents: number, currency: string) => string;
  onChange: (id: number) => void;
}

function ProductSelector({
  productId,
  products,
  label,
  formatPrice,
  onChange,
}: ProductSelectorProps): ReactElement {
  return (
    <label className="block">
      <span className="text-xs font-medium text-slate-700">{label}</span>
      <select
        value={productId}
        onChange={(event) => {
          onChange(Number.parseInt(event.target.value, 10));
        }}
        className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
        data-testid="bulk-product-select"
      >
        {products.map((option) => (
          <option key={option.id} value={option.id}>
            {option.name} — {formatPrice(option.basePriceCents, option.currency)}
          </option>
        ))}
      </select>
    </label>
  );
}

interface QuantityFieldProps {
  value: string;
  label: string;
  unitPriceLabel: string;
  unitPriceText: string;
  onChange: (next: string) => void;
}

function QuantityField({
  value,
  label,
  unitPriceLabel,
  unitPriceText,
  onChange,
}: QuantityFieldProps): ReactElement {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <label className="block">
        <span className="text-xs font-medium text-slate-700">{label}</span>
        <input
          type="number"
          min={1}
          value={value}
          onChange={(event) => {
            onChange(event.target.value);
          }}
          className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
          data-testid="bulk-quantity-input"
        />
      </label>
      <div className="rounded-md bg-slate-50 px-3 py-2 text-sm">
        <p className="text-xs text-slate-500">{unitPriceLabel}</p>
        <p className="font-semibold text-slate-900">{unitPriceText}</p>
      </div>
    </div>
  );
}

interface TierCardProps {
  label: string;
  discountPercent: number;
  discountLabel: string;
}

function TierCard({ label, discountPercent, discountLabel }: TierCardProps): ReactElement {
  const discountText = discountPercent === 0 ? '0%' : `${Math.round(discountPercent * 100)}%`;
  return (
    <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm">
      <p className="flex items-center justify-between">
        <span className="font-medium text-emerald-900">{label}</span>
        <span className="font-bold text-emerald-900">
          {discountPercent === 0 ? '—' : `-${discountText}`}
        </span>
      </p>
      <p className="mt-1 text-xs text-emerald-700">
        {discountLabel}: {discountText}
      </p>
    </div>
  );
}

interface SubtotalRowProps {
  label: string;
  amount: string;
}

function SubtotalRow({ label, amount }: SubtotalRowProps): ReactElement {
  return (
    <div className="flex items-center justify-between border-t border-slate-200 pt-3">
      <span className="text-sm font-medium text-slate-700">{label}</span>
      <span className="text-lg font-bold tabular-nums text-slate-900" data-testid="bulk-subtotal">
        {amount}
      </span>
    </div>
  );
}

interface AddButtonProps {
  disabled: boolean;
  label: string;
  busyLabel: string;
  onClick: () => void;
}

function AddButton({ disabled, label, busyLabel, onClick }: AddButtonProps): ReactElement {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      className="w-full rounded-md bg-[var(--primary,#059669)] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[var(--primary-hover,#047857)] disabled:cursor-not-allowed disabled:bg-slate-300"
      data-testid="bulk-add"
    >
      {disabled && busyLabel !== label ? busyLabel : label}
    </button>
  );
}

interface SuccessMessageProps {
  text: string;
}

function SuccessMessage({ text }: SuccessMessageProps): ReactElement {
  return (
    <p
      role="status"
      className="rounded-md bg-emerald-100 px-3 py-2 text-center text-sm font-medium text-emerald-800"
      data-testid="bulk-success"
    >
      {text}
    </p>
  );
}

interface TiersListProps {
  tierCountLabel: string;
}

function TiersList({ tierCountLabel }: TiersListProps): ReactElement {
  return (
    <details className="pt-2 text-xs text-slate-500">
      <summary className="cursor-pointer font-medium text-slate-700">
        {tierCountLabel.replace('{count}', String(PRICING_TIERS.length))}
      </summary>
      <ul className="mt-2 space-y-1">
        {PRICING_TIERS.map((tier) => (
          <li key={tier.minQuantity}>
            {tier.label}:{' '}
            {tier.discountPercent === 0
              ? 'sin descuento'
              : `${Math.round(tier.discountPercent * 100)}% off`}
          </li>
        ))}
      </ul>
    </details>
  );
}

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
  const initialProduct = BULK_PRODUCTS[0];
  const [productId, setProductId] = useState<number>(initialProduct?.id ?? 0);
  const [quantityInput, setQuantityInput] = useState<string>(
    String(initialProduct?.minQuantity ?? 1),
  );
  const product = useMemo(
    () => BULK_PRODUCTS.find((p) => p.id === productId) ?? initialProduct,
    [productId, initialProduct],
  );
  const quantity = useMemo(() => {
    const parsed = Number.parseInt(quantityInput, 10);
    return Number.isNaN(parsed) ? 0 : Math.max(0, parsed);
  }, [quantityInput]);
  const tier = useMemo(() => resolvePricingTier(quantity), [quantity]);
  const subtotalCents = Math.round(
    (product?.basePriceCents ?? 0) * quantity * (1 - tier.discountPercent),
  );
  const errorMessage = useMemo<string | null>(() => {
    if (product === undefined) return null;
    if (quantity === 0) return errorInvalidNumber;
    if (quantity < product.minQuantity) return errorMinQuantity(product.minQuantity);
    return null;
  }, [product, quantity, errorInvalidNumber, errorMinQuantity]);
  const { persisting, added, isValidQuantity, addToBulkOrder } = useBulkCart(
    product,
    quantity,
  );
  const formatPrice = (cents: number, currency: string): string =>
    `$${(cents / 100).toFixed(2)} ${currency}`;

  if (product === undefined) {
    return (
      <p className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        {t('common.error')}
      </p>
    );
  }

  return (
    <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
      <ProductSelector
        productId={productId}
        products={BULK_PRODUCTS}
        label={productSelectLabel}
        formatPrice={formatPrice}
        onChange={(next) => {
          const found = BULK_PRODUCTS.find((p) => p.id === next);
          setProductId(next);
          setQuantityInput(String(found?.minQuantity ?? 1));
        }}
      />
      <QuantityField
        value={quantityInput}
        label={quantityLabel}
        unitPriceLabel={unitPriceLabel}
        unitPriceText={formatPrice(product.basePriceCents, product.currency)}
        onChange={setQuantityInput}
      />
      <TierCard
        label={tier.label}
        discountPercent={tier.discountPercent}
        discountLabel={discountLabel}
      />
      <SubtotalRow label={subtotalLabel} amount={formatPrice(subtotalCents, product.currency)} />
      {errorMessage !== null && (
        <p role="alert" className="text-sm text-rose-600" data-testid="bulk-error">
          {errorMessage}
        </p>
      )}
      <AddButton
        disabled={!isValidQuantity || persisting}
        label={addButtonLabel}
        busyLabel={addingLabel}
        onClick={addToBulkOrder}
      />
      {added && <SuccessMessage text={successLabel} />}
      <TiersList tierCountLabel={`${PRICING_TIERS.length} tiers`} />
    </div>
  );
}
