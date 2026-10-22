/**
 * tenant-favicon — resuelve el `<link rel="icon">` de un tenant.
 *
 * Los cuatro layouts (`Layout`, `AuthLayout`, `AdminLayout`,
 * `StorefrontLayout`) emiten el mismo link. Que el HTML sea igual en los
 * cuatro no significa que la decisión sea trivial: cada uno la calculaba
 * por su cuenta desde el slug del tenant.
 *
 * ── Por qué NO se construye la URL por convención ──────────────────
 *
 * La ruta `/api/v1/storefront/assets/tenants/{slug}/branding/favicon.webp`
 * es una convención, no un dato. Los layouts la armaban igual para todos
 * los tenants y por lo tanto afirmaban que todo tenant tiene ese archivo.
 * `assets/branding/instrumentos-musicales-la-voz/` sólo tiene un README:
 * el favicon de ese tenant nunca se subió, así que cada carga de página
 * pedía un `.webp` inexistente y se comía un 404. El tipo declarado
 * tampoco correspondía — `type="image/svg+xml"` sobre un `.webp`.
 *
 * ── El contrato nuevo ──────────────────────────────────────────────
 *
 * El favicon es un dato DECLARADO: `StoreBrand.faviconPath`. Si el brand
 * lo declara, se usa y el MIME se deriva de la extensión (declarar
 * `image/svg+xml` sobre un `.webp` es el mismo bug con distinto disfraz).
 * Si no lo declara, se cae al `/favicon.svg` del repo — que existe por
 * definición porque es un asset estático versionado con el código.
 *
 * Un `faviconPath` declarado es una promesa del theme: el theme se
 * versiona con el código, así que la ruta y el archivo llegan juntos.
 * El comportamiento por defecto para un tenant sin branding es el
 * fallback, no un 404.
 *
 * El resolver recibe el brand ya resuelto (no el slug): pedir el brand es
 * trabajo de `getBrandBySlug`, y los layouts que ya lo tienen resuelto no
 * deberían volver a buscarlo.
 */
import type { StoreBrand } from '../config/stores';

/** Lo que los cuatro layouts necesitan para emitir el `<link rel="icon">`. */
export interface TenantFavicon {
  href: string;
  type: string;
}

/** Asset estático del repo. Siempre existe — fallback de todo tenant. */
export const DEFAULT_FAVICON: TenantFavicon = {
  href: '/favicon.svg',
  type: 'image/svg+xml',
};

/**
 * MIME por extensión. Sin esta tabla el layout tendría que hardcodear el
 * tipo, que es justamente el dato que cambia con el asset que el theme
 * declare. `ico` usa `image/x-icon` y no `image/vnd.microsoft.icon`:
 * es el valor que los navegadores aceptan de forma consistente.
 */
const MIME_BY_EXTENSION: Readonly<Record<string, string>> = {
  avif: 'image/avif',
  gif: 'image/gif',
  ico: 'image/x-icon',
  jpeg: 'image/jpeg',
  jpg: 'image/jpeg',
  png: 'image/png',
  svg: 'image/svg+xml',
  webp: 'image/webp',
};

/**
 * Extensión en minúsculas, o `null` si la ruta no tiene una extensión
 * reconocible (path vacío, sin punto, query string, o extensión fuera del
 * set de formatos de favicon).
 *
 * Se corta en `?` / `#` a propósito: un CDN puede servir
 * `favicon.webp?v=3` y sin ese corte la extensión sale `webp?v=3`.
 */
function extractExtension(path: string): string | null {
  const withoutQuery = path.split(/[?#]/)[0] ?? '';
  const lastSegment = withoutQuery.split('/').pop() ?? '';
  const dotIndex = lastSegment.lastIndexOf('.');
  if (dotIndex <= 0 || dotIndex === lastSegment.length - 1) return null;
  return lastSegment.slice(dotIndex + 1).toLowerCase();
}

/**
 * Resuelve el favicon del brand. Función pura: mismo brand → mismo
 * resultado, sin dependencias del DOM ni de `Astro.locals`, para que los
 * cuatro layouts compartan una sola implementación testeable.
 */
export function resolveTenantFavicon(brand: StoreBrand): TenantFavicon {
  const declared = brand.faviconPath;
  if (declared === undefined || declared === '') return DEFAULT_FAVICON;

  const extension = extractExtension(declared);
  if (extension === null) return DEFAULT_FAVICON;

  const mime = MIME_BY_EXTENSION[extension];
  if (mime === undefined) return DEFAULT_FAVICON;

  return { href: declared, type: mime };
}