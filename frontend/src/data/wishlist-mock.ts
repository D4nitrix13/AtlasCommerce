/**
 * Mock data — wishlist "My Collection" del cliente (F2.3).
 *
 * Este archivo existe SOLO porque `GET /api/storefront/wishlist` aún no
 * está implementado. Cuando el endpoint exista, este archivo se reemplaza
 * por fetch + hidratación. La shape de `WishlistItemMock` es lo único
 * estable que cruza la frontera.
 *
 * Decisiones de diseño (design-refs §6.2):
 *  - La wishlist NO es una lista plana — cada item tiene contexto
 *    personal (`addedDaysAgo`, `personalNote`) que la diferencia de una
 *    "lista de productos guardados" cualquiera. Patrón Mejuri/Mr Porter.
 *  - `notifyWhenBack`/`inStock`: presente sólo cuando el producto no
 *    está disponible. El botón "Notify" aparece condicionalmente.
 *  - 7 items cubren estados visuales (con/sin nota, en stock/agotado,
 *    hace cuánto se agregó).
 */

const PLACEHOLDER_IMAGE =
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" role="img" aria-label="Wishlist item placeholder">
      <defs>
        <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#f1f5f9"/>
          <stop offset="100%" stop-color="#e2e8f0"/>
        </linearGradient>
      </defs>
      <rect width="400" height="400" fill="url(#g)"/>
      <circle cx="200" cy="160" r="60" fill="#cbd5e1"/>
      <path d="M120 320 Q200 220 280 320 Z" fill="#cbd5e1"/>
      <text x="200" y="370" text-anchor="middle" font-family="system-ui, sans-serif" font-size="16" fill="#64748b">Wishlist item</text>
    </svg>`
  );

export interface WishlistItemMock {
  id: number;
  productSlug: string;
  productName: string;
  imageUrl: string;
  priceCents: number;
  currency: string;
  /** Días desde que se agregó a la wishlist (entero, escala 1-90). */
  addedDaysAgo: number;
  /** Nota personal del cliente. Opcional. */
  personalNote?: string;
  /** Stock actual. `0` = agotado. */
  stock: number;
  /** Si `true` y `stock === 0`, ofrecer "Notify when back". */
  notifyWhenBack?: boolean;
}

const CURRENCY = 'USD';

/**
 * Colección curada del cliente. Mezcla de:
 *  - 4 items en stock (sin restricción de notify)
 *  - 2 items agotados que pueden recibir notify
 *  - 1 item con nota personal extendida (recordatorio del motivo)
 *
 * Catálogo disímil para validar el grid responsive: las imágenes
 * cargan con la misma data URI placeholder mientras la imagen real
 * no exista en mock.
 */
export const WISHLIST_MOCK: readonly WishlistItemMock[] = [
  {
    id: 1,
    productSlug: 'frieren-figure-1-7',
    productName: 'Frieren — 1/7 Scale Figure',
    imageUrl: PLACEHOLDER_IMAGE,
    priceCents: 18500,
    currency: CURRENCY,
    addedDaysAgo: 14,
    personalNote: 'Para mi cumpleaños del próximo mes.',
    stock: 3,
  },
  {
    id: 2,
    productSlug: 'jujutsu-kaisen-gojo-art',
    productName: 'Gojo Satoru — Limited Art Print',
    imageUrl: PLACEHOLDER_IMAGE,
    priceCents: 5900,
    currency: CURRENCY,
    addedDaysAgo: 28,
    personalNote: 'Cuadrar para la colección del cuarto.',
    stock: 0,
    notifyWhenBack: true,
  },
  {
    id: 3,
    productSlug: 'totoro-plush-xl',
    productName: 'Totoro Plush (XL)',
    imageUrl: PLACEHOLDER_IMAGE,
    priceCents: 8400,
    currency: CURRENCY,
    addedDaysAgo: 3,
    stock: 6,
  },
  {
    id: 4,
    productSlug: 'saber-alter-meteor',
    productName: 'Saber Alter — Meteor Variant',
    imageUrl: PLACEHOLDER_IMAGE,
    priceCents: 22500,
    currency: CURRENCY,
    addedDaysAgo: 47,
    personalNote: undefined,
    stock: 1,
  },
  {
    id: 5,
    productSlug: 'chainsaw-man-power',
    productName: 'Power — Reze Variant Figure',
    imageUrl: PLACEHOLDER_IMAGE,
    priceCents: 14900,
    currency: CURRENCY,
    addedDaysAgo: 62,
    personalNote: 'Pedir junto con la de Denji si llega.',
    stock: 0,
    notifyWhenBack: true,
  },
  {
    id: 6,
    productSlug: 'spy-x-family-anya-strap',
    productName: 'Anya — Phone Strap',
    imageUrl: PLACEHOLDER_IMAGE,
    priceCents: 1900,
    currency: CURRENCY,
    addedDaysAgo: 9,
    stock: 12,
  },
  {
    id: 7,
    productSlug: 'kiki-delivery-service-pins',
    productName: 'Kiki — Enamel Pins Set',
    imageUrl: PLACEHOLDER_IMAGE,
    priceCents: 3400,
    currency: CURRENCY,
    addedDaysAgo: 21,
    stock: 4,
  },
];

/** Devuelve todos los items (mock). */
export function getAllWishlistItems(): readonly WishlistItemMock[] {
  return WISHLIST_MOCK;
}
