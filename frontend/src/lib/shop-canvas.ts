/**
 * Lienzo compartido del storefront y de la cuenta.
 *
 * Figtree lleva la interfaz. Fraunces lleva los títulos: es la voz
 * de un taller (Panda) y de una tienda editorial (Kitsune) sin
 * cambiar el layout de cada página.
 *
 * Los acentos siguen saliendo del brand. `--primary-soft` y `--line`
 * se derivan del mismo hex para que un badge y un borde hablen el
 * color de la tienda, no un gris genérico.
 */

export const SHOP_FONT_STYLESHEET =
  'https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,400;0,500;0,600;0,700;1,500&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap';

export function shopThemeStyle(accent: string, hover: string): string {
  return [
    `--primary:${accent}`,
    `--primary-hover:${hover}`,
    `--primary-soft:color-mix(in srgb, ${accent} 14%, white)`,
    `--paper:#f3efe8`,
    `--ink:#1c1917`,
    `--line:color-mix(in srgb, ${accent} 22%, #e7e5e4)`,
  ].join(';');
}
