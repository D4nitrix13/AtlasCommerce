/**
 * product-image.ts — helpers para construir URLs srcset responsive
 * desde imágenes de producto WebP servidas estáticamente.
 *
 * Extraído de ProductDetailGallery.astro (Oct 2026) para poder
 * testearse en isolation. La galería PDP importa estas funciones y
 * genera el atributo `srcset` con la lógica testeada acá.
 *
 * CONVENCIÓN DE NAMING (Fase 6 del image architecture plan):
 *   storage_key:  tenants/{slug}/{collection}/{entity}/{hash}.{ext}
 *   proxy_url:    /api/v1/storefront/assets/{storage_key}
 *   helper nuevo: frontend/src/lib/asset-url.ts (assetUrl + mockAssetStorageKey)
 *
 * La galería recibe la URL completa del proxy desde la API response.
 * El storage key NO contiene tokens de tamaño porque las imágenes
 * se sirven tal cual desde el FTP disk via el AssetController backend
 * (ver backend/app/Http/Controllers/Api/Storefront/AssetController.php).
 *
 * DEPRECADO: el `SIZE_TOKEN_RE` y `parseSizeToken()` quedaron de la
 * convención legacy `/images/products/{slug}/{variant}/{slug}-{variant}-{WxH}-{NN}.webp`
 * (helper `fenderImageUrl()` removido en Fase 6). Si los callers
 * activos no usan `imageSrcset()` con URLs nuevas, el helper
 * devolverá srcset vacío (degraded pero funcional). Migrar callers
 * al helper `assetUrl()` + `<img>` sin srcset antes de eliminar.
 *
 * Tamaños generados por el recon pipeline legacy (cwebp -resize W 0):
 *   1600, 1200, 800, 400 (width)
 *   Height = round(W * source_aspect)
 *
 * Estrategia del srcset (legacy, ver DEPRECADO arriba):
 *   - Parsea el token de tamaño (WxH) del URL base via regex
 *   - Deriva `aspect = h / w` del source
 *   - Para cada width objetivo, calcula height proporcional
 *   - Genera 4 candidatos con srcset syntax estándar
 *   - Si el URL no matchea el patrón, devuelve srcset vacío
 *     (degraded pero funcional: el browser usa `src` solo)
 */

export const SIZES_W = [400, 800, 1200, 1600] as const;

/**
 * Regex que captura el primer token `-{W}x{H}-` del URL.
 *
 * Ejemplos que matchea:
 *   "...-1600x1600-03.webp"      → W=1600, H=1600
 *   "...-1600x525-01.webp"        → W=1600, H=525
 *   "...-1200x394-01.webp"        → W=1200, H=394
 *
 * Patrones que NO matchea:
 *   "https://example.com/something.png" (sin token)
 *   "image.webp" (sin tamaño en el nombre)
 */
const SIZE_TOKEN_RE = /-(\d{3,4})x(\d{2,4})-/;

/**
 * Parsea el token de tamaño (WxH) del primer match del regex.
 *
 * @returns objeto `{w, h}` con las dimensiones parseadas, o `null`
 *          si el URL no contiene un token de tamaño reconocible.
 */
export function parseSizeToken(url: string): { w: number; h: number } | null {
  const m = url.match(SIZE_TOKEN_RE);
  if (m === null) return null;
  const w = Number.parseInt(m[1] ?? '0', 10);
  const h = Number.parseInt(m[2] ?? '0', 10);
  if (!Number.isFinite(w) || !Number.isFinite(h) || w <= 0 || h <= 0) return null;
  return { w, h };
}

/**
 * Genera el atributo `srcset` con los 4 tamaños WebP disponibles.
 *
 * Calcula el height proporcional al aspect del source para cada
 * width objetivo (preserva la relación de aspecto al cambiar tamaño).
 *
 * @param url URL base de la imagen (debe incluir el token `-{W}x{H}-`).
 * @returns string con formato "url1 400w, url2 800w, url3 1200w, url4 1600w"
 *          o string vacío `""` si el URL no matchea el patrón esperado.
 */
export function imageSrcset(url: string): string {
  const base = parseSizeToken(url);
  if (base === null) return '';
  // aspect = h / w (e.g., 525/1600 = 0.3281 para Fender AmPro Tele)
  const aspect = base.h / base.w;
  return SIZES_W.map((w) => {
    const h = Math.round(w * aspect);
    return `${url.replace(SIZE_TOKEN_RE, `-${w}x${h}-`)} ${w}w`;
  }).join(', ');
}
