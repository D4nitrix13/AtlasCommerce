/**
 * BulkPricingCalculator — Calculadora interactiva de bulk order B2B.
 *
 * Patrón B2B (design-refs §7.3):
 *   "Quick calculator: cantidad + producto = descuento automático"
 *
 * Componente orquestador: estado y cálculo en `useBulkPricingState`,
 * render puro en `BulkPricingCalculatorView`. Esto mantiene main
 * <30 líneas (AGENTS §1).
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
import {
  BULK_PRODUCTS,
  PRICING_TIERS,
  resolvePricingTier,
  type BulkOrderProduct,
} from '../../../data/b2b-mock';
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

function useBulkCart(product: BulkOrderProduct | undefined, quantity: number) {
  const [persisting, setPersisting] = useState<boolean>(false);
  const [added, setAdded] = useState<boolean>(false);
  const isValidQuantity = product !== undefined && quantity >= product.minQuantity && quantity > 0;

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
  products: readonly BulkOrderProduct[];
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

interface BulkPricingCalculatorViewProps {
  productId: number;
  quantityInput: string;
  onProductChange: (nextId: number) => void;
  onQuantityChange: (next: string) => void;
  labels: {
    productSelect: string;
    quantity: string;
    discount: string;
    subtotal: string;
    unitPrice: string;
    add: string;
    adding: string;
    success: string;
  };
  tierLabel: string;
  tierDiscountPercent: number;
  subtotalText: string;
  unitPriceText: string;
  errorMessage: string | null;
  persisting: boolean;
  isValidQuantity: boolean;
  added: boolean;
  onAddToBulkOrder: () => void;
  formatPrice: (cents: number, currency: string) => string;
}

interface PricingSummaryProps {
  tierLabel: string;
  tierDiscountPercent: number;
  subtotalLabel: string;
  subtotalText: string;
  errorMessage: string | null;
}

function PricingSummary({
  tierLabel,
  tierDiscountPercent,
  subtotalLabel,
  subtotalText,
  errorMessage,
}: PricingSummaryProps): ReactElement {
  return (
    <>
      <TierCard
        label={tierLabel}
        discountPercent={tierDiscountPercent}
        discountLabel={subtotalLabel}
      />
      <SubtotalRow label={subtotalLabel} amount={subtotalText} />
      {errorMessage !== null && (
        <p role="alert" className="text-sm text-rose-600" data-testid="bulk-error">
          {errorMessage}
        </p>
      )}
    </>
  );
}

interface BulkActionsProps {
  disabled: boolean;
  addLabel: string;
  addingLabel: string;
  added: boolean;
  successLabel: string;
  onAddToBulkOrder: () => void;
}

function BulkActions({
  disabled,
  addLabel,
  addingLabel,
  added,
  successLabel,
  onAddToBulkOrder,
}: BulkActionsProps): ReactElement {
  return (
    <>
      <AddButton
        disabled={disabled}
        label={addLabel}
        busyLabel={addingLabel}
        onClick={onAddToBulkOrder}
      />
      {added && <SuccessMessage text={successLabel} />}
    </>
  );
}

function BulkPricingCalculatorView(props: BulkPricingCalculatorViewProps): ReactElement {
  return (
    <div className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
      <ProductSelector
        productId={props.productId}
        products={BULK_PRODUCTS}
        label={props.labels.productSelect}
        formatPrice={props.formatPrice}
        onChange={props.onProductChange}
      />
      <QuantityField
        value={props.quantityInput}
        label={props.labels.quantity}
        unitPriceLabel={props.labels.unitPrice}
        unitPriceText={props.unitPriceText}
        onChange={props.onQuantityChange}
      />
      <PricingSummary
        tierLabel={props.tierLabel}
        tierDiscountPercent={props.tierDiscountPercent}
        subtotalLabel={props.labels.discount}
        subtotalText={props.subtotalText}
        errorMessage={props.errorMessage}
      />
      <BulkActions
        disabled={!props.isValidQuantity || props.persisting}
        addLabel={props.labels.add}
        addingLabel={props.labels.adding}
        added={props.added}
        successLabel={props.labels.success}
        onAddToBulkOrder={props.onAddToBulkOrder}
      />
      <TiersList tierCountLabel={`${PRICING_TIERS.length} tiers`} />
    </div>
  );
}

interface UseBulkPricingStateResult {
  product: BulkOrderProduct | undefined;
  productId: number;
  quantityInput: string;
  onProductChange: (nextId: number) => void;
  onQuantityChange: (next: string) => void;
  tierLabel: string;
  tierDiscountPercent: number;
  subtotalText: string;
  unitPriceText: string;
  errorMessage: string | null;
  persisting: boolean;
  added: boolean;
  isValidQuantity: boolean;
  addToBulkOrder: () => boolean;
  formatPrice: (cents: number, currency: string) => string;
}

function useProductSelection(): {
  productId: number;
  quantityInput: string;
  product: BulkOrderProduct | undefined;
  onProductChange: (nextId: number) => void;
  onQuantityChange: (next: string) => void;
} {
  const initial = BULK_PRODUCTS[0];
  const [productId, setProductId] = useState<number>(initial?.id ?? 0);
  const [quantityInput, setQuantityInput] = useState<string>(String(initial?.minQuantity ?? 1));
  const product = useMemo(
    () => BULK_PRODUCTS.find((p) => p.id === productId) ?? initial,
    [productId, initial]
  );
  return {
    productId,
    quantityInput,
    product,
    onProductChange: (next) => {
      const found = BULK_PRODUCTS.find((p) => p.id === next);
      setProductId(next);
      setQuantityInput(String(found?.minQuantity ?? 1));
    },
    onQuantityChange: setQuantityInput,
  };
}

function usePricing(
  product: BulkOrderProduct | undefined,
  quantity: number
): {
  tierLabel: string;
  tierDiscountPercent: number;
  subtotalCents: number;
  unitPriceText: string;
  subtotalText: string;
  formatPrice: (cents: number, currency: string) => string;
} {
  const tier = useMemo(() => resolvePricingTier(quantity), [quantity]);
  const subtotalCents = Math.round(
    (product?.basePriceCents ?? 0) * quantity * (1 - tier.discountPercent)
  );
  const formatPrice = (cents: number, currency: string): string =>
    `$${(cents / 100).toFixed(2)} ${currency}`;
  return {
    tierLabel: tier.label,
    tierDiscountPercent: tier.discountPercent,
    subtotalCents,
    unitPriceText:
      product === undefined ? '' : formatPrice(product.basePriceCents, product.currency),
    subtotalText: product === undefined ? '' : formatPrice(subtotalCents, product.currency),
    formatPrice,
  };
}

function useErrorMessage(
  product: BulkOrderProduct | undefined,
  quantity: number,
  errorInvalidNumber: string,
  errorMinQuantity: (min: number) => string
): string | null {
  return useMemo<string | null>(() => {
    if (product === undefined) return null;
    if (quantity === 0) return errorInvalidNumber;
    if (quantity < product.minQuantity) return errorMinQuantity(product.minQuantity);
    return null;
  }, [product, quantity, errorInvalidNumber, errorMinQuantity]);
}

function useBulkPricingState({
  errorInvalidNumber,
  errorMinQuantity,
}: Props): UseBulkPricingStateResult {
  const { product, productId, quantityInput, onProductChange, onQuantityChange } =
    useProductSelection();
  const quantity = useMemo(() => {
    const parsed = Number.parseInt(quantityInput, 10);
    return Number.isNaN(parsed) ? 0 : Math.max(0, parsed);
  }, [quantityInput]);
  const pricing = usePricing(product, quantity);
  const errorMessage = useErrorMessage(product, quantity, errorInvalidNumber, errorMinQuantity);
  const { persisting, added, isValidQuantity, addToBulkOrder } = useBulkCart(product, quantity);
  return {
    product,
    productId,
    quantityInput,
    onProductChange,
    onQuantityChange,
    tierLabel: pricing.tierLabel,
    tierDiscountPercent: pricing.tierDiscountPercent,
    subtotalText: pricing.subtotalText,
    unitPriceText: pricing.unitPriceText,
    errorMessage,
    persisting,
    added,
    isValidQuantity,
    addToBulkOrder,
    formatPrice: pricing.formatPrice,
  };
}

export default function BulkPricingCalculator(props: Props): ReactElement {
  const { t } = useTranslations();
  const state = useBulkPricingState(props);
  if (state.product === undefined) {
    return (
      <p className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        {t('common.error')}
      </p>
    );
  }
  return (
    <BulkPricingCalculatorView
      productId={state.productId}
      quantityInput={state.quantityInput}
      onProductChange={state.onProductChange}
      onQuantityChange={state.onQuantityChange}
      labels={{
        productSelect: props.productSelectLabel,
        quantity: props.quantityLabel,
        discount: props.discountLabel,
        subtotal: props.subtotalLabel,
        unitPrice: props.unitPriceLabel,
        add: props.addButtonLabel,
        adding: props.addingLabel,
        success: props.successLabel,
      }}
      tierLabel={state.tierLabel}
      tierDiscountPercent={state.tierDiscountPercent}
      subtotalText={state.subtotalText}
      unitPriceText={state.unitPriceText}
      errorMessage={state.errorMessage}
      persisting={state.persisting}
      isValidQuantity={state.isValidQuantity}
      added={state.added}
      onAddToBulkOrder={state.addToBulkOrder}
      formatPrice={state.formatPrice}
    />
  );
}
