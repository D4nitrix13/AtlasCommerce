// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';
import es from '../../../src/i18n/locales/es';
import en from '../../../src/i18n/locales/en';

describe('i18n · auth.login.shell.emailPlaceholder (Task 2.6)', () => {
  it('existe en es.ts con valor placeholder razonable', () => {
    expect(es['auth.login.shell.emailPlaceholder']).toBe('tu@correo.com');
  });

  it('existe en en.ts con valor placeholder razonable', () => {
    expect(en['auth.login.shell.emailPlaceholder']).toBe('you@email.com');
  });

  it('es y en NO son idénticos (chequeo trivial de traducción)', () => {
    expect(es['auth.login.shell.emailPlaceholder']).not.toBe(
      en['auth.login.shell.emailPlaceholder']
    );
  });
});
