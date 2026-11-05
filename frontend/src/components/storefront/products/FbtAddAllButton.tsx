/**
 * FbtAddAllButton — CTA "Agregar todo al carrito" del bundle FBT.
 *
 * Isla React chica (`client:visible`) que envuelve SOLO el botón del
 * footer de `<ProductFrequentlyBoughtTogether>`: el resto del bundle
 * es HTML estático de Astro. Al clickear agrega cada línea del bundle
 * al cart store (qty 1, sin `variantLabel`) y navega al carrito.
 *
 * Sin JS el `<a>` sigue siendo un link normal al carrito (fallback).
 */
import type { MouseEvent, ReactElement } from 'react';
import { addToCart, type CartLine } from '../../../stores/cart';

export interface FbtAddAllButtonProps {
  /** Líneas del bundle (producto + accesorios), sin cantidad. */
  lines: readonly Omit<CartLine, 'quantity'>[];
  /** Carrito locale-prefixed (`/{locale}/cart`). */
  cartHref: string;
  label: string;
}

export default function FbtAddAllButton({
  lines,
  cartHref,
  label,
}: FbtAddAllButtonProps): ReactElement {
  function handleClick(event: MouseEvent<HTMLAnchorElement>): void {
    event.preventDefault();
    for (const line of lines) {
      addToCart(line, 1);
    }
    window.location.assign(cartHref);
  }

  return (
    <a
      href={cartHref}
      onClick={handleClick}
      className="inline-flex h-12 items-center justify-center rounded-full bg-[var(--primary,#475569)] px-6 text-sm font-bold text-white shadow-sm transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary,#475569)] focus-visible:ring-offset-2"
    >
      {label}
    </a>
  );
}
