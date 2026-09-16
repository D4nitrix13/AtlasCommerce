// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const { mockApiFetch } = vi.hoisted(() => ({
  mockApiFetch: vi.fn(),
}));

vi.mock('../../../../../src/lib/api', () => ({
  apiFetch: mockApiFetch,
  ApiError: class ApiError extends Error {
    status: number;
    constructor(status: number) {
      super(`HTTP ${status}`);
      this.status = status;
    }
  },
}));

vi.mock('../../../../../src/lib/endpoints', () => ({
  API_ENDPOINTS: {
    marketing: {
      couponsValidate: '/coupons/validate',
    },
  },
}));

vi.mock('../../../../../src/i18n', async (importOriginal) => {
  const actual = (await importOriginal()) as Record<string, unknown>;
  return {
    ...actual,
    useTranslations: () => ({
      t: (key: string) => key,
      locale: 'es',
    }),
  };
});

import CouponInput from '../../../../../src/components/storefront/cart/CouponInput';

describe('CouponInput', (): void => {
  beforeEach((): void => {
    mockApiFetch.mockReset();
    window.localStorage.clear();
  });

  it('renders the input and apply button when no coupon is applied', (): void => {
    render(<CouponInput />);
    expect(screen.getByPlaceholderText('storefront.cart.coupon.placeholder')).toBeTruthy();
    expect(screen.getByText('storefront.cart.coupon.apply')).toBeTruthy();
  });

  it('submits the code to the validate endpoint and shows the applied state', async (): Promise<void> => {
    mockApiFetch.mockResolvedValue({
      data: {
        code: 'PROMO10',
        discount_type: 'percentage',
        value: 10,
        discount_cents: 1000,
      },
    });

    const user = userEvent.setup();
    render(<CouponInput />);

    await user.type(
      screen.getByPlaceholderText('storefront.cart.coupon.placeholder'),
      'promo10',
    );
    await user.click(screen.getByText('storefront.cart.coupon.apply'));

    await waitFor((): void => {
      expect(screen.getByTestId('coupon-applied')).toBeTruthy();
    });

    expect(mockApiFetch).toHaveBeenCalledWith(
      '/coupons/validate',
      expect.objectContaining({
        method: 'POST',
        body: { code: 'promo10' },
      }),
    );
    expect(window.localStorage.getItem('applied_coupon_code')).toBe('PROMO10');
  });

  it('shows an error message when the coupon is invalid (404)', async (): Promise<void> => {
    mockApiFetch.mockRejectedValue(
      Object.assign(new Error('HTTP 404'), { status: 404 }),
    );

    const user = userEvent.setup();
    render(<CouponInput />);

    await user.type(
      screen.getByPlaceholderText('storefront.cart.coupon.placeholder'),
      'INVALID',
    );
    await user.click(screen.getByText('storefront.cart.coupon.apply'));

    await waitFor((): void => {
      expect(screen.getByTestId('coupon-error')).toBeTruthy();
    });
    expect(screen.getByText('storefront.cart.coupon.error.invalid')).toBeTruthy();
  });

  it('removes the coupon when the remove button is clicked', async (): Promise<void> => {
    mockApiFetch.mockResolvedValue({
      data: {
        code: 'PROMO10',
        discount_type: 'percentage',
        value: 10,
        discount_cents: 1000,
      },
    });

    const user = userEvent.setup();
    render(<CouponInput />);

    await user.type(
      screen.getByPlaceholderText('storefront.cart.coupon.placeholder'),
      'PROMO10',
    );
    await user.click(screen.getByText('storefront.cart.coupon.apply'));

    await waitFor((): void => {
      expect(screen.getByTestId('coupon-applied')).toBeTruthy();
    });

    await user.click(screen.getByText('storefront.cart.coupon.remove'));

    await waitFor((): void => {
      expect(screen.getByPlaceholderText('storefront.cart.coupon.placeholder')).toBeTruthy();
    });
    expect(window.localStorage.getItem('applied_coupon_code')).toBeNull();
  });

  it('shows a generic error on network failure', async (): Promise<void> => {
    mockApiFetch.mockRejectedValue(
      Object.assign(new Error('network'), { status: 0 }),
    );

    const user = userEvent.setup();
    render(<CouponInput />);

    await user.type(
      screen.getByPlaceholderText('storefront.cart.coupon.placeholder'),
      'PROMO10',
    );
    await user.click(screen.getByText('storefront.cart.coupon.apply'));

    await waitFor((): void => {
      expect(screen.getByText('storefront.cart.coupon.error.generic')).toBeTruthy();
    });
  });
});
