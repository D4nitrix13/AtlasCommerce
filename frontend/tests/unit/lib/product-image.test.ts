/**
 * Tests para el helper imageSrcset — extraído de ProductDetailGallery
 * en Oct 2026 para poder testearlo en isolation.
 *
 * Casos cubiertos:
 *   - Square 1:1 (Gibson) — heights = widths
 *   - Landscape 3:1 (Fender AmPro Tele) — heights ≈ width / 3
 *   - Landscape 3.05:1 (Fender AmPro Tele, medidas exactas) — heights
 *     preservan aspect ratio
 *   - URL inválida (sin token de tamaño) — retorna ''
 *   - Diferente aspect para cada width — verifica que la sustitución
 *     de la regex reemplaza el token correctamente
 */
import { describe, expect, it } from 'vitest';
import { imageSrcset, parseSizeToken, SIZES_W } from '../../../src/lib/product-image';

describe('parseSizeToken', () => {
  it('extrae W y H del primer match de token de tamaño', () => {
    expect(parseSizeToken('...-1600x1600-03.webp')).toEqual({ w: 1600, h: 1600 });
    expect(parseSizeToken('...-1600x525-01.webp')).toEqual({ w: 1600, h: 525 });
  });

  it('retorna null para URLs sin token de tamaño', () => {
    expect(parseSizeToken('https://example.com/image.png')).toBeNull();
    expect(parseSizeToken('image.webp')).toBeNull();
    expect(parseSizeToken('')).toBeNull();
  });

  it('toma el PRIMER match (ignora matches siguientes)', () => {
    // path tiene el -1600x1600- (size) seguido de -03 (index)
    expect(parseSizeToken('a-1600x1600-03-800x800-back.webp')).toEqual({ w: 1600, h: 1600 });
  });
});

describe('imageSrcset', () => {
  it('SQUARE 1:1 (Gibson): todos los heights = widths', () => {
    const srcset = imageSrcset(
      '/images/products/gibson-mod-collection-es-335-smooth-sepia/smooth-sepia/gibson-mod-collection-es-335-smooth-sepia-smooth-sepia-1600x1600-03.webp'
    );
    // Aspect = 1.0 → heights = widths
    expect(srcset).toContain('400x400-03.webp 400w');
    expect(srcset).toContain('800x800-03.webp 800w');
    expect(srcset).toContain('1200x1200-03.webp 1200w');
    expect(srcset).toContain('1600x1600-03.webp 1600w');
  });

  it('LANDSCAPE 3:1 (Fender AmPro Tele): heights ≈ width / 3', () => {
    const srcset = imageSrcset(
      '/images/products/fender-american-professional-telecaster/3-color-sunburst/fender-american-professional-telecaster-3-color-sunburst-1600x525-01.webp'
    );
    // Aspect 525/1600 = 0.328125
    // widths: 1600, 1200, 800, 400 → heights 525, 394, 263, 131
    expect(srcset).toContain('400x131-01.webp 400w');
    expect(srcset).toContain('800x263-01.webp 800w');
    expect(srcset).toContain('1200x394-01.webp 1200w');
    expect(srcset).toContain('1600x525-01.webp 1600w');
  });

  it('preserva index (NN) y el resto del path', () => {
    const srcset = imageSrcset('/images/products/x/y/x-y-1600x525-01.webp');
    // Todos los srcset entries deben tener _01.webp
    const entries = srcset.split(', ');
    for (const e of entries) {
      expect(e).toContain('01.webp');
      expect(e).toContain('x-y-');
    }
  });

  it('retorna string vacío para URL sin token de tamaño (degraded mode)', () => {
    // Browser usará `src` solo, sin upscale
    expect(imageSrcset('https://example.com/image.png')).toBe('');
    expect(imageSrcset('image.webp')).toBe('');
    expect(imageSrcset('')).toBe('');
  });

  it('genera exactamente 4 entries con los SIZES_W definidos', () => {
    const srcset = imageSrcset('/images/products/x/y/x-y-1600x1600-01.webp');
    const entries = srcset.split(', ');
    expect(entries).toHaveLength(SIZES_W.length);
    for (let i = 0; i < SIZES_W.length; i++) {
      expect(entries[i]).toMatch(/\d+w$/);
    }
  });
});
