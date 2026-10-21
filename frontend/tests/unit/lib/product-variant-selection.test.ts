/**
 * Tests del modelo de variantes del PDP (acabado + mano).
 *
 * Casos cubiertos:
 *   - Default de acabado: gana la primera variante CON stock; si
 *     ninguna tiene, la primera de la lista.
 *   - `?finish=` gana siempre sobre el default.
 *   - `?finish=` con slug desconocido (URL stale) cae al default en
 *     vez de dejar la galería vacía.
 *   - Ocultación del picker con <2 acabados (188/346 productos).
 *   - Normalización de `handedness` (null → right-handed).
 *   - Opciones de mano deduplicadas + umbral del selector.
 *   - Filtro por mano del listado de acabados.
 *
 * Todos los helpers reciben argumentos POSICIONALES: TypeScript no
 * tiene named arguments (a diferencia de PHP), así que la legibilidad
 * va en el nombre de la función y en el orden documentado de sus
 * parámetros.
 */
import { describe, expect, it } from 'vitest';
import {
  HANDEDNESS_LEFT,
  HANDEDNESS_RIGHT,
  buildFinishOptions,
  collectHandednessOptions,
  hasFinishPicker,
  hasHandednessPicker,
  normalizeHandedness,
  resolveSelectedFinishId,
  type ProductApiVariant,
} from '../../../src/lib/product-variant-selection';

const HAND_LABELS = {
  [HANDEDNESS_LEFT]: 'Izquierda',
  [HANDEDNESS_RIGHT]: 'Derecha',
} as const;

function variant(overrides: Partial<ProductApiVariant> & Pick<ProductApiVariant, 'finishSlug'>): ProductApiVariant {
  return {
    id: 1,
    name: 'Test',
    finishLabel: 'Test',
    finishHex: null,
    condition: 'nuevo',
    stock: 0,
    ...overrides,
  };
}

describe('resolveSelectedFinishId', () => {
  it('elige la primera variante con stock sobre la primera de la lista', () => {
    const variants = [
      variant({ id: 1, finishSlug: 'ebony', stock: 0 }),
      variant({ id: 2, finishSlug: 'cherry', stock: 3 }),
      variant({ id: 3, finishSlug: 'sunburst', stock: 9 }),
    ];
    const options = buildFinishOptions(variants, null);

    expect(resolveSelectedFinishId(options, variants, null)).toBe('cherry');
  });

  it('cae a la primera de la lista cuando ninguna tiene stock', () => {
    const variants = [
      variant({ id: 1, finishSlug: 'ebony', stock: 0 }),
      variant({ id: 2, finishSlug: 'cherry', stock: 0 }),
    ];
    const options = buildFinishOptions(variants, null);

    expect(resolveSelectedFinishId(options, variants, null)).toBe('ebony');
  });

  it('deja ganar el query param ?finish= sobre el default con stock', () => {
    const variants = [
      variant({ id: 1, finishSlug: 'ebony', stock: 0 }),
      variant({ id: 2, finishSlug: 'cherry', stock: 5 }),
    ];
    const options = buildFinishOptions(variants, null);

    expect(resolveSelectedFinishId(options, variants, 'ebony')).toBe('ebony');
  });

  it('ignora un ?finish= que no existe y vuelve al default', () => {
    const variants = [
      variant({ id: 1, finishSlug: 'ebony', stock: 0 }),
      variant({ id: 2, finishSlug: 'cherry', stock: 5 }),
    ];
    const options = buildFinishOptions(variants, null);

    expect(resolveSelectedFinishId(options, variants, 'nope')).toBe('cherry');
  });

  it('devuelve undefined sin variantes — la PDP no renderiza selector', () => {
    expect(resolveSelectedFinishId([], [], 'ebony')).toBeUndefined();
  });
});

describe('hasFinishPicker', () => {
  it('oculta el selector con un solo acabado', () => {
    const options = buildFinishOptions([variant({ finishSlug: 'ebony' })], null);

    expect(hasFinishPicker(options)).toBe(false);
  });

  it('muestra el selector con dos o más acabados', () => {
    const options = buildFinishOptions(
      [variant({ id: 1, finishSlug: 'ebony' }), variant({ id: 2, finishSlug: 'cherry' })],
      null,
    );

    expect(hasFinishPicker(options)).toBe(true);
  });
});

describe('normalizeHandedness', () => {
  it('mapea null a la mano derecha', () => {
    expect(normalizeHandedness(variant({ finishSlug: 'ebony', handedness: null })))
      .toBe(HANDEDNESS_RIGHT);
  });

  it('mapea undefined (backend viejo) a la mano derecha', () => {
    expect(normalizeHandedness(variant({ finishSlug: 'ebony' }))).toBe(HANDEDNESS_RIGHT);
  });

  it('respeta el valor de dominio tal cual viene', () => {
    expect(normalizeHandedness(variant({ finishSlug: 'ebony', handedness: HANDEDNESS_LEFT })))
      .toBe(HANDEDNESS_LEFT);
  });

  it('no adivina un valor desconocido: lo pasa tal cual', () => {
    expect(normalizeHandedness(variant({ finishSlug: 'ebony', handedness: 'zurdo' })))
      .toBe('zurdo');
  });
});

describe('collectHandednessOptions', () => {
  it('devuelve una sola opción cuando todas las variantes son diestras', () => {
    const options = collectHandednessOptions(
      [variant({ id: 1, finishSlug: 'ebony' }), variant({ id: 2, finishSlug: 'cherry' })],
      HAND_LABELS,
    );

    expect(options).toEqual([{ id: HANDEDNESS_RIGHT, label: 'Derecha' }]);
    expect(hasHandednessPicker(options)).toBe(false);
  });

  it('deduplica y ofrece selector cuando el producto tiene ambas manos', () => {
    const options = collectHandednessOptions(
      [
        variant({ id: 1, finishSlug: 'ebony' }),
        variant({ id: 2, finishSlug: 'ebony-lh', handedness: HANDEDNESS_LEFT }),
        variant({ id: 3, finishSlug: 'cherry', handedness: HANDEDNESS_LEFT }),
      ],
      HAND_LABELS,
    );

    expect(options).toEqual([
      { id: HANDEDNESS_RIGHT, label: 'Derecha' },
      { id: HANDEDNESS_LEFT, label: 'Izquierda' },
    ]);
    expect(hasHandednessPicker(options)).toBe(true);
  });
});

describe('buildFinishOptions', () => {
  it('filtra los acabados por la mano izquierda', () => {
    const variants = [
      variant({ id: 1, finishSlug: 'ebony' }),
      variant({ id: 2, finishSlug: 'ebony-lh', handedness: HANDEDNESS_LEFT }),
    ];

    expect(buildFinishOptions(variants, HANDEDNESS_LEFT)).toEqual([
      { id: 'ebony-lh', label: 'Test' },
    ]);
  });

  it('filtra los acabados por la mano derecha', () => {
    const variants = [
      variant({ id: 1, finishSlug: 'ebony' }),
      variant({ id: 2, finishSlug: 'ebony-lh', handedness: HANDEDNESS_LEFT }),
    ];

    expect(buildFinishOptions(variants, HANDEDNESS_RIGHT)).toEqual([
      { id: 'ebony', label: 'Test' },
    ]);
  });

  it('no filtra cuando no hay mano seleccionada', () => {
    const variants = [
      variant({ id: 1, finishSlug: 'ebony' }),
      variant({ id: 2, finishSlug: 'ebony-lh', handedness: HANDEDNESS_LEFT }),
    ];

    expect(buildFinishOptions(variants, null)).toHaveLength(2);
  });

  it('colapsa variantes que comparten finishSlug sin duplicar la opción', () => {
    const variants = [
      variant({ id: 1, finishSlug: 'ebony', stock: 0 }),
      variant({ id: 2, finishSlug: 'ebony', stock: 4 }),
    ];

    expect(buildFinishOptions(variants, null)).toHaveLength(1);
  });
});