// @vitest-environment jsdom
import { describe, it, expect, beforeEach } from 'vitest';

import {
  CHECKOUT_DRAFT_KEY,
  readCheckoutDraft,
  writeCheckoutDraft,
  clearCheckoutDraft,
} from '../../../../../src/components/storefront/checkout/checkoutDraft';
import { EMPTY_CHECKOUT_VALUES } from '../../../../../src/components/storefront/checkout/checkoutSchemas';

describe('checkoutDraft · sessionStorage persistence', (): void => {
  beforeEach((): void => {
    window.sessionStorage.clear();
  });

  it('round-trips form values through sessionStorage', (): void => {
    const draft = { ...EMPTY_CHECKOUT_VALUES, name: 'Ada', email: 'ada@example.com' };
    writeCheckoutDraft(draft);

    expect(JSON.parse(window.sessionStorage.getItem(CHECKOUT_DRAFT_KEY) ?? 'null')).toEqual(draft);
    expect(readCheckoutDraft()).toEqual(draft);
  });

  it('returns the empty defaults when no draft exists', (): void => {
    expect(readCheckoutDraft()).toEqual(EMPTY_CHECKOUT_VALUES);
  });

  it('returns the empty defaults for a corrupt draft', (): void => {
    window.sessionStorage.setItem(CHECKOUT_DRAFT_KEY, '{not json');
    expect(readCheckoutDraft()).toEqual(EMPTY_CHECKOUT_VALUES);
  });

  it('returns the empty defaults for a draft with the wrong shape', (): void => {
    window.sessionStorage.setItem(CHECKOUT_DRAFT_KEY, JSON.stringify({ name: 42 }));
    expect(readCheckoutDraft()).toEqual(EMPTY_CHECKOUT_VALUES);
  });

  it('clears the draft after a successful submit', (): void => {
    writeCheckoutDraft({ ...EMPTY_CHECKOUT_VALUES, name: 'Ada' });
    clearCheckoutDraft();

    expect(window.sessionStorage.getItem(CHECKOUT_DRAFT_KEY)).toBeNull();
    expect(readCheckoutDraft()).toEqual(EMPTY_CHECKOUT_VALUES);
  });
});
