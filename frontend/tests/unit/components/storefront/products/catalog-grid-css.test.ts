/**
 * Tests estructurales para los fixes de CSS del catálogo (Phase 7).
 *
 * Valida que los componentes críticos del catálogo tienen las
 * utilities de layout necesarias para igualar alturas en el grid.
 * Tests estructurales (string match en class) en lugar de snapshots
 * porque los componentes Astro requieren el renderizador Astro
 * (out of scope de esta tarea).
 *
 * Cobertura:
 *   - ProductCard.astro: debe tener `h-full` para estirar al row.
 *   - ProductGrid.astro: debe tener `grid-auto-rows: 1fr` (en
 *     scoped <style>) para forzar rows iguales.
 */
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const PRODUCT_CARD_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/ProductCard.astro',
);
const PRODUCT_GRID_PATH = resolve(
  __dirname,
  '../../../../../src/components/storefront/products/ProductGrid.astro',
);

describe('ProductCard layout fix (Phase 7 catalog grid CSS)', () => {
  const source = readFileSync(PRODUCT_CARD_PATH, 'utf-8');

  it('el <article> raíz tiene la utility h-full', () => {
    // El card vive en un grid con grid-auto-rows: 1fr. Para que el
    // card estire al row completo, su <article> raíz debe tener
    // `h-full`. Sin esto, la primera card se ve "más arriba" que
    // las demás cuando hay badges exclusivos o condition toggles
    // que cambian la altura del contenido.
    expect(source).toMatch(/<article[^>]*\bh-full\b/);
  });

  it('mantiene flex-col para que el cuerpo del card estire su contenido', () => {
    // Necesario junto con h-full: el wrapper externo flex-col
    // garantiza que el inner div con flex-1 empuje el footer
    // (precio + CTA) al fondo del card.
    expect(source).toMatch(/class="[^"]*flex[^"]*flex-col/);
  });
});

describe('ProductGrid layout fix (Phase 7 catalog grid CSS)', () => {
  const source = readFileSync(PRODUCT_GRID_PATH, 'utf-8');

  it('el grid de productos tiene un scoped <style> con grid-auto-rows: 1fr', () => {
    // Tailwind no expone `grid-auto-rows: 1fr` como utility built-in
    // en esta versión. Usamos un scoped <style> block en lugar de
    // inline style (AGENTS §9 prohíbe inline styles para cosas
    // estáticas) o la sintaxis `grid-rows-[1fr]` (no soportada).
    expect(source).toMatch(/<style[^>]*>/);
    expect(source).toMatch(/grid-auto-rows:\s*1fr/);
  });

  it('el <ul> del grid usa la class products-grid', () => {
    // El selector .products-grid es lo que el scoped <style> apunta.
    expect(source).toMatch(/<ul[^>]*class="[^"]*\bproducts-grid\b/);
  });
});