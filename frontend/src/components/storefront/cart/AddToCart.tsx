/**
 * AddToCart — isla de compra rápida (PDP y card de catálogo).
 *
 * `mode="full"` (PDP): selector de acabado, cantidad y botón de compra.
 * `mode="compact"` (card): un solo botón `+` que agrega el primer
 * acabado con cantidad 1.
 *
 * Labels por props con fallback español, mismo patrón que
 * `ProductCardCatalog.astro` (`finishCountLabel`): el componente no
 * importa el diccionario i18n.
 *
 * Los swatches exponen `data-panda-finish` (foto de portada del acabado)
 * para que el script del PDP siga cambiando la imagen de portada al
 * elegir un color — atributo que antes llevaban los radios que esta
 * isla reemplaza.
 */
import type { ChangeEvent, ReactElement } from 'react';
import { useEffect, useRef, useState } from 'react';
import { addToCart } from '../../../stores/cart';

export interface AddToCartFinish {
  slug: string;
  label: string;
  hex: string;
  /** Foto de portada de este acabado (preview en el PDP). */
  image?: string;
}

export interface AddToCartLabels {
  add: string;
  added: string;
  qty: string;
  variant?: string;
}

interface AddToCartProps {
  productSlug: string;
  name: string;
  imageUrl: string;
  priceCents: number;
  currency: string;
  finishes: readonly AddToCartFinish[];
  mode: 'full' | 'compact';
  labels?: AddToCartLabels;
}

const MIN_QUANTITY = 1;
const MAX_QUANTITY = 99;
const ADDED_FEEDBACK_MS = 1500;

const DEFAULT_LABELS: AddToCartLabels = {
  add: 'Agregar al carrito',
  added: 'Agregado',
  qty: 'Cantidad',
  variant: 'Acabado',
};

function clampQuantity(value: number): number {
  return Math.min(MAX_QUANTITY, Math.max(MIN_QUANTITY, value));
}

export default function AddToCart({
  productSlug,
  name,
  imageUrl,
  priceCents,
  currency,
  finishes,
  mode,
  labels,
}: AddToCartProps): ReactElement {
  const resolved: AddToCartLabels = { ...DEFAULT_LABELS, ...labels };
  const [selectedSlug, setSelectedSlug] = useState<string | null>(
    finishes[0]?.slug ?? null,
  );
  // String y no number: permite que el campo quede vacío mientras se
  // tipea sin que el clamp "1" se coma los dígitos siguientes.
  const [quantity, setQuantity] = useState<string>('1');
  const [added, setAdded] = useState(false);
  const feedbackTimer = useRef<number | undefined>(undefined);

  useEffect(() => {
    return () => {
      window.clearTimeout(feedbackTimer.current);
    };
  }, []);

  const isCompact = mode === 'compact';
  const isAddDisabled = !isCompact && finishes.length === 0;

  function handleQuantityChange(event: ChangeEvent<HTMLInputElement>): void {
    const raw = event.target.value;
    if (raw === '') {
      setQuantity('');
      return;
    }
    const parsed = Number.parseInt(raw, 10);
    if (Number.isNaN(parsed)) {
      return;
    }
    setQuantity(String(clampQuantity(parsed)));
  }

  function handleAdd(): void {
    const finish =
      finishes.find((entry) => entry.slug === selectedSlug) ?? finishes[0];
    const effectiveQuantity = isCompact
      ? 1
      : clampQuantity(Number.parseInt(quantity, 10) || MIN_QUANTITY);
    addToCart(
      {
        productSlug,
        name,
        imageUrl,
        priceCents,
        currency,
        variantLabel: finish?.label,
      },
      effectiveQuantity,
    );
    setAdded(true);
    window.clearTimeout(feedbackTimer.current);
    feedbackTimer.current = window.setTimeout(() => {
      setAdded(false);
    }, ADDED_FEEDBACK_MS);
  }

  if (isCompact) {
    return (
      <span className="inline-flex items-center gap-2">
        <button
          type="button"
          aria-label={added ? resolved.added : resolved.add}
          onClick={handleAdd}
          className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 transition hover:border-[var(--primary,#475569)] hover:text-[var(--primary,#475569)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2"
        >
          <span aria-hidden="true">{added ? '✓' : '+'}</span>
        </button>
        <span role="status" aria-live="polite" className="sr-only">
          {added ? resolved.added : ''}
        </span>
      </span>
    );
  }

  return (
    <div className="flex flex-col gap-3">
      {finishes.length > 0 && (
        <div className="flex flex-col gap-2">
          <span className="text-sm font-semibold text-slate-900">
            {resolved.variant}
          </span>
          <div
            className="flex flex-wrap gap-2"
            role="group"
            aria-label={resolved.variant}
          >
            {finishes.map((finish) => {
              const isSelected = finish.slug === selectedSlug;
              return (
                <button
                  key={finish.slug}
                  type="button"
                  aria-label={finish.label}
                  aria-pressed={isSelected}
                  data-panda-finish={finish.image}
                  onClick={() => {
                    setSelectedSlug(finish.slug);
                  }}
                  className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm transition hover:border-slate-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2"
                  style={
                    isSelected
                      ? {
                          borderColor: 'var(--primary, #475569)',
                          boxShadow: '0 0 0 1px var(--primary, #475569)',
                        }
                      : undefined
                  }
                >
                  <span
                    aria-hidden="true"
                    className="h-4 w-4 rounded-full border border-black/10"
                    style={{ backgroundColor: finish.hex }}
                  />
                  {finish.label}
                </button>
              );
            })}
          </div>
        </div>
      )}

      <label className="flex max-w-[8rem] flex-col gap-2 text-sm font-semibold text-slate-900">
        {resolved.qty}
        <input
          type="number"
          name="quantity"
          min={MIN_QUANTITY}
          max={MAX_QUANTITY}
          value={quantity}
          onChange={handleQuantityChange}
          className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-base font-normal"
        />
      </label>

      <button
        type="button"
        disabled={isAddDisabled}
        onClick={handleAdd}
        className="inline-flex h-12 items-center justify-center rounded-full bg-[var(--primary,#475569)] px-6 text-sm font-semibold text-white transition hover:bg-[var(--primary-hover,#334155)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
      >
        {added ? resolved.added : resolved.add}
      </button>

      <span role="status" aria-live="polite" className="sr-only">
        {added ? resolved.added : ''}
      </span>
    </div>
  );
}
