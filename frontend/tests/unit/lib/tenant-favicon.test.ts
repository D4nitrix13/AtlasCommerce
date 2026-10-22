/**
 * Tests unitarios para `tenant-favicon` — el resolver que los cuatro
 * layouts (`Layout`, `AuthLayout`, `AdminLayout`, `StorefrontLayout`)
 * usan para emitir su `<link rel="icon">`.
 *
 * Cobertura:
 *   - `faviconPath` declarado → se usa la ruta del brand.
 *   - MIME derivado de la extensión (el bug original declaraba
 *     `image/svg+xml` sobre un asset `.webp`).
 *   - `faviconPath` ausente o vacío → fallback al asset estático del repo.
 *   - Extensión desconocida o ruta sin extensión → fallback (no inventar
 *     un MIME para un formato que no es de favicon).
 *   - Tenant `null` → `getBrandBySlug` cae al DEFAULT_BRAND → fallback.
 *     Es el caso real de dev local sin `/etc/hosts`, y también el del
 *     tenant `instrumentos-musicales-la-voz`, cuyo branding todavía no
 *     se subió.
 */
import { describe, expect, it } from 'vitest';
import { DEFAULT_FAVICON, resolveTenantFavicon } from '../../../src/lib/tenant-favicon';
import { DEFAULT_BRAND, getBrandBySlug, type StoreBrand } from '../../../src/config/stores';

/** Brand con `faviconPath` declarado; el resto de campos es irrelevante. */
function brandWithFavicon(faviconPath: string | undefined): StoreBrand {
  return { ...DEFAULT_BRAND, faviconPath };
}

describe('resolveTenantFavicon — faviconPath declarado', () => {
  it('usa la ruta declarada por el brand', () => {
    const favicon = resolveTenantFavicon(
      brandWithFavicon('/api/v1/storefront/assets/tenants/panda-estampados/branding/favicon.svg'),
    );

    expect(favicon).toEqual({
      href: '/api/v1/storefront/assets/tenants/panda-estampados/branding/favicon.svg',
      type: 'image/svg+xml',
    });
  });

  it('deriva image/webp para un .webp — el bug era declarar image/svg+xml', () => {
    const favicon = resolveTenantFavicon(brandWithFavicon('/branding/favicon.webp'));

    expect(favicon.type).toBe('image/webp');
  });

  it.each([
    ['/branding/favicon.svg', 'image/svg+xml'],
    ['/branding/favicon.png', 'image/png'],
    ['/branding/favicon.jpg', 'image/jpeg'],
    ['/branding/favicon.jpeg', 'image/jpeg'],
    ['/branding/favicon.webp', 'image/webp'],
    ['/branding/favicon.avif', 'image/avif'],
    ['/branding/favicon.gif', 'image/gif'],
    ['/branding/favicon.ico', 'image/x-icon'],
  ])('deriva el MIME correcto para %s', (path, expectedType) => {
    expect(resolveTenantFavicon(brandWithFavicon(path)).type).toBe(expectedType);
  });

  it('normaliza la extensión a minúsculas y descarta query strings', () => {
    expect(resolveTenantFavicon(brandWithFavicon('/branding/favicon.WEBP')).type).toBe('image/webp');
    expect(resolveTenantFavicon(brandWithFavicon('/branding/favicon.webp?v=3')).type).toBe('image/webp');
  });
});

describe('resolveTenantFavicon — fallback al asset del repo', () => {
  it('cae al favicon estático cuando el brand no declara faviconPath', () => {
    const favicon = resolveTenantFavicon({ ...DEFAULT_BRAND });

    expect(favicon).toEqual(DEFAULT_FAVICON);
    expect(favicon.href).toBe('/favicon.svg');
    expect(favicon.type).toBe('image/svg+xml');
  });

  it('cae al favicon estático cuando faviconPath es string vacío', () => {
    expect(resolveTenantFavicon(brandWithFavicon(''))).toEqual(DEFAULT_FAVICON);
  });

  it('cae al favicon estático cuando la extensión no es de favicon', () => {
    expect(resolveTenantFavicon(brandWithFavicon('/branding/favicon.txt'))).toEqual(DEFAULT_FAVICON);
  });

  it('cae al favicon estático cuando la ruta no tiene extensión', () => {
    expect(resolveTenantFavicon(brandWithFavicon('/branding/favicon'))).toEqual(DEFAULT_FAVICON);
    expect(resolveTenantFavicon(brandWithFavicon('/branding/'))).toEqual(DEFAULT_FAVICON);
  });

  it('no propaga una ruta con punto inicial como extensión válida', () => {
    // `/.hidden` no es un nombre de archivo de favicon: sin nombre antes
    // del punto no hay extensión que derivar.
    expect(resolveTenantFavicon(brandWithFavicon('/branding/.webp'))).toEqual(DEFAULT_FAVICON);
  });
});

describe('resolveTenantFavicon — tenants sin branding subido', () => {
  it('tenant null (dev local sin /etc/hosts) cae al favicon del repo', () => {
    expect(resolveTenantFavicon(getBrandBySlug(null))).toEqual(DEFAULT_FAVICON);
  });

  it('ningún theme del repo declara faviconPath todavía', () => {
    // mientras esto sea cierto, ningún layout puede volver a pedir un
    // asset de branding por convención: el 404 desapareció de raíz.
    for (const slug of ['panda-estampados', 'kitsune', 'mm-boutique', 'morales-store', 'instrumentos-musicales-la-voz']) {
      expect(getBrandBySlug(slug).faviconPath).toBeUndefined();
    }
  });
});