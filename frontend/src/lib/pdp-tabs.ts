/**
 * Lógica pura de navegación por teclado del tab strip del PDP.
 *
 * Contrato WAI-ARIA APG (tabs con activación automática): las flechas
 * mueven el foco Y activan el tab destino; Enter/Space activan vía el
 * click nativo del `<button>`. Este módulo no toca el DOM — el
 * componente `ProductDetailTabs.astro` traduce el índice resultante a
 * foco + `aria-selected` + `hidden` de los paneles.
 */

/** Teclas que navegan entre tabs. */
export type TabNavKey = 'ArrowLeft' | 'ArrowRight' | 'Home' | 'End';

const TAB_NAV_KEYS: readonly string[] = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];

/**
 * ¿La tecla navega entre tabs? El caller usa esto para no
 * interceptar (ni hacer `preventDefault` de) teclas ajenas.
 */
export function isTabNavKey(key: string): key is TabNavKey {
  return TAB_NAV_KEYS.includes(key);
}

/**
 * Índice del tab al que navegar dada la tecla pulsada.
 *
 * - `ArrowRight` / `ArrowLeft`: ±1 con wrap-around en los extremos.
 * - `Home` / `End`: primer / último tab.
 * - Otras teclas: devuelve `current` sin cambios.
 *
 * `current` es un índice válido `0..count-1`; `count` debe ser ≥ 1.
 */
export function nextTabIndex(current: number, count: number, key: string): number {
  switch (key) {
    case 'ArrowRight':
      return (current + 1) % count;
    case 'ArrowLeft':
      return (current - 1 + count) % count;
    case 'Home':
      return 0;
    case 'End':
      return count - 1;
    default:
      return current;
  }
}
