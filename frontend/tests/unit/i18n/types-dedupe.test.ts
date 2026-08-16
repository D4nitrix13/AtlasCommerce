// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';
import es from '../../../src/i18n/locales/es';
import en from '../../../src/i18n/locales/en';
// Vite raw import — evita dependencia de @types/node.
// eslint-disable-next-line import/no-unresolved
import typesSource from '../../../src/i18n/types.ts?raw';

describe('i18n/types · dedupe (Task 2.5)', () => {
  it('types.ts no contiene keys declaradas más de una vez en el union', () => {
    const matches = typesSource.match(/'auth\.[a-zA-Z0-9_.]+'/g) ?? [];
    const counts = new Map<string, number>();
    for (const match of matches) {
      const key = match.replace(/'/g, '');
      counts.set(key, (counts.get(key) ?? 0) + 1);
    }
    const duplicates: string[] = [];
    for (const [key, count] of counts) {
      if (count > 1) {
        duplicates.push(`${key} (${count}x)`);
      }
    }
    expect(duplicates, `duplicates found: ${duplicates.join(', ')}`).toEqual([]);
  });

  it('es.ts y en.ts cubren TODAS las keys declaradas (safety net)', () => {
    const matches = typesSource.match(/'auth\.[a-zA-Z0-9_.]+'/g) ?? [];
    const declared = Array.from(new Set(matches.map((m: string) => m.replace(/'/g, ''))));

    const esKeys = Object.keys(es);
    const enKeys = Object.keys(en);

    for (const key of declared) {
      expect(esKeys, `es.ts missing key: ${key}`).toContain(key);
      expect(enKeys, `en.ts missing key: ${key}`).toContain(key);
    }
  });
});
