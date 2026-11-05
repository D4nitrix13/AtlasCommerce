/**
 * Tests estructurales de los controles de compra del PDP (lote 2, T3.2).
 *
 * Los componentes Astro no se renderizan en jsdom (requieren el
 * compilador Astro) → tests estructurales sobre el source
 * (estilo `catalog-grid-css.test.ts` / `pdp-tabs-placement.test.ts`).
 *
 * Cobertura:
 *   - `ProductSizeGrid`: pills compactas inline (`flex flex-wrap`,
 *     `rounded-full`, `h-9`) en lugar de la grilla de botones
 *     grandes (`grid-cols-*`). Disabled = muted + `line-through` +
 *     `aria-disabled`.
 *   - Semántica de selección preservada: `role="radio"`,
 *     `aria-checked`, `data-size-option` e hidden input.
 *   - `ProductPurchaseBar`: sin `<select>` nativo — stepper
 *     − / + con botones reales (`aria-label`), valor `tabular-nums`
 *     y el mismo wiring de form (`name="quantity"`).
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const SIZE_GRID_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/ProductSizeGrid.astro',
);
const PURCHASE_BAR_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/ProductPurchaseBar.tsx',
);

describe('ProductSizeGrid — pills compactas (T3.2)', () => {
  const source = readFileSync(SIZE_GRID_PATH, 'utf-8');

  it('renderiza una fila inline de pills con wrap, no una grilla de columnas', () => {
    // El usuario pidió representar la información de la talla de otra
    // manera ("aprovechar cada espacio"): pills compactas CH M G XG
    // lado a lado que envuelven solas, no la grilla 2/3 columnas de
    // botones grandes que dominaba la columna.
    expect(source).toMatch(/flex flex-wrap/);
    expect(source).not.toMatch(/grid-cols-/);
  });

  it('las pills son compactas y de borde redondeado total', () => {
    // ~36px de alto (`h-9`) con `rounded-full` — quiet controls que
    // no compiten con el CTA de compra.
    expect(source).toMatch(/\bh-9\b/);
    expect(source).toMatch(/rounded-full/);
    expect(source).not.toMatch(/\bh-14\b/);
  });

  it('el estado disabled es muted + line-through + aria-disabled', () => {
    // Talla agotada: se lee como tachada, sin romper la fila.
    expect(source).toMatch(/aria-disabled/);
    expect(source).toMatch(/line-through/);
  });

  it('preserva la semántica de selección actual', () => {
    // Cambio visual no más: los botones siguen siendo `role="radio"`
    // con `aria-checked`, marcan `data-size-option` y el valor viaja
    // por hidden input para el form y el deep-link `?size=`.
    expect(source).toMatch(/role="radio"/);
    expect(source).toMatch(/aria-checked/);
    expect(source).toMatch(/data-size-option/);
    expect(source).toMatch(/type="hidden"/);
    expect(source).toMatch(/<legend\b/);
  });
});

describe('ProductPurchaseBar — stepper de cantidad (T3.2)', () => {
  const source = readFileSync(PURCHASE_BAR_PATH, 'utf-8');

  it('reemplaza el <select> nativo por un stepper − / +', () => {
    // "el componente cantidad se ve horrible": el dropdown nativo
    // sale y entra un stepper con botones reales.
    expect(source).not.toMatch(/<select/);
    expect(source).not.toMatch(/<\/select>/);
  });

  it('los botones − / + son reales y accesibles', () => {
    // Baymard + patrón de `ProductQuantitySelector.astro`: botones
    // con `aria-label` explícito (i18n via labels) y glifos ocultos
    // a lectores de pantalla.
    expect(source).toMatch(/aria-label=\{resolved\.decrement\}/);
    expect(source).toMatch(/aria-label=\{resolved\.increment\}/);
    expect(source).toMatch(/aria-hidden="true">−</);
    expect(source).toMatch(/aria-hidden="true">\+</);
  });

  it('el valor usa tabular-nums y expone el mismo wiring de form', () => {
    // El readout no debe saltar de ancho al cambiar de 1 a 2 dígitos,
    // y add-to-cart / buy-now siguen leyendo `name="quantity"`.
    expect(source).toMatch(/tabular-nums/);
    expect(source).toMatch(/type="hidden"/);
    expect(source).toMatch(/name="quantity"/);
    expect(source).toMatch(/role="group"/);
  });
});
