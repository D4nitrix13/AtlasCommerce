// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';
import es from '../../../src/i18n/locales/es';
import en from '../../../src/i18n/locales/en';

describe('i18n · auth.signup.shell.strengthWeak/strengthMedium (Task 2.8)', () => {
  it('strengthWeak existe en es y en', () => {
    expect(typeof es['auth.signup.shell.strengthWeak']).toBe('string');
    expect(typeof en['auth.signup.shell.strengthWeak']).toBe('string');
    expect(es['auth.signup.shell.strengthWeak']).not.toBe(en['auth.signup.shell.strengthWeak']);
  });

  it('strengthMedium existe en es y en', () => {
    expect(typeof es['auth.signup.shell.strengthMedium']).toBe('string');
    expect(typeof en['auth.signup.shell.strengthMedium']).toBe('string');
    expect(es['auth.signup.shell.strengthMedium']).not.toBe(en['auth.signup.shell.strengthMedium']);
  });

  it('strengthWeak NO es igual a strengthMedium en el mismo locale', () => {
    expect(es['auth.signup.shell.strengthWeak']).not.toBe(es['auth.signup.shell.strengthMedium']);
    expect(en['auth.signup.shell.strengthWeak']).not.toBe(en['auth.signup.shell.strengthMedium']);
  });

  it('strengthGood sigue existiendo (no lo borramos)', () => {
    expect(typeof es['auth.signup.shell.strengthGood']).toBe('string');
    expect(typeof en['auth.signup.shell.strengthGood']).toBe('string');
  });
});
